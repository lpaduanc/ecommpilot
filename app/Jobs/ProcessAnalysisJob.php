<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Mail\AnalysisCompletedMail;
use App\Models\ActivityLog;
use App\Models\Analysis;
use App\Models\SystemSetting;
use App\Services\AI\Agents\LiteStoreAnalysisService;
use App\Services\AI\Agents\StoreAnalysisService;
use App\Services\AnalysisService;
use App\Services\EmailConfigurationService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class ProcessAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Canal de log para analise
     */
    private string $logChannel = 'analysis';

    /**
     * Number of times to attempt the job.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between retries (exponential: 60, 120, 240).
     */
    public array $backoff = [60, 120, 240];

    /**
     * Maximum execution time in seconds (10 minutes for AI analysis).
     */
    public int $timeout = 600;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private Analysis $analysis
    ) {
        // Use dedicated queue for analysis jobs with higher timeout
        $this->onQueue('analysis');
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Prevent duplicate analysis jobs for the same analysis
        return [
            (new WithoutOverlapping($this->analysis->id))
                ->releaseAfter(600) // Release lock after 10 minutes
                ->expireAfter(900), // Lock expires after 15 minutes
        ];
    }

    /**
     * Determine if the job should be unique.
     */
    public function uniqueId(): string
    {
        return 'analysis-'.$this->analysis->id;
    }

    public function handle(
        AnalysisService $legacyService,
        StoreAnalysisService $agentService,
        LiteStoreAnalysisService $liteAgentService,
        NotificationService $notificationService
    ): void {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     PROCESS ANALYSIS - INICIO DA ANALISE AI                     ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Configuracao da analise', [
            'analysis_id' => $this->analysis->id,
            'store_id' => $this->analysis->store_id,
            'user_id' => $this->analysis->user_id,
            'attempt' => $this->attempts(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Refresh the analysis to get latest state
        $this->analysis->refresh();

        // Skip if already completed or failed (avoid duplicate processing)
        if ($this->analysis->status === AnalysisStatus::Completed) {
            Log::channel($this->logChannel)->info('--- Analise ja concluida, ignorando', [
                'analysis_id' => $this->analysis->id,
                'status' => 'completed',
            ]);

            return;
        }

        if ($this->analysis->status === AnalysisStatus::Failed) {
            Log::channel($this->logChannel)->info('--- Analise ja falhou, ignorando', [
                'analysis_id' => $this->analysis->id,
                'status' => 'failed',
            ]);

            return;
        }

        try {
            // Mark as processing
            $this->analysis->markAsProcessing();

            // Notificar início da análise
            $notificationService->notifyAnalysisStarted($this->analysis);

            Log::channel($this->logChannel)->info('>>> Status atualizado para PROCESSING', [
                'analysis_id' => $this->analysis->id,
            ]);

            // Check if we should use the new agent pipeline
            $useAgentPipeline = SystemSetting::get('ai.use_agent_pipeline', true);

            if ($useAgentPipeline) {
                $this->processWithAgentPipeline($agentService, $liteAgentService);
            } else {
                Log::channel($this->logChannel)->info('>>> Usando LEGACY pipeline', [
                    'analysis_id' => $this->analysis->id,
                ]);
                // Use legacy analysis service
                $legacyService->processAnalysis($this->analysis);
            }

            $totalTime = round((microtime(true) - $startTime), 2);

            Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
            Log::channel($this->logChannel)->info('║     PROCESS ANALYSIS - ANALISE CONCLUIDA COM SUCESSO            ║');
            Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
            Log::channel($this->logChannel)->info('Estatisticas finais da analise', [
                'analysis_id' => $this->analysis->id,
                'total_time_seconds' => $totalTime,
                'status' => 'success',
                'timestamp_end' => now()->toIso8601String(),
            ]);

            // Notificar conclusão da análise
            $notificationService->notifyAnalysisCompleted($this->analysis);

            // Send completion email to user
            $this->sendCompletionEmail($notificationService);
        } catch (\Throwable $e) {
            $totalTime = round((microtime(true) - $startTime), 2);

            Log::channel($this->logChannel)->error('!!! ERRO NA ANALISE AI', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'attempt' => $this->attempts(),
                'total_time_seconds' => $totalTime,
            ]);

            // Mark as failed immediately (don't wait for failed() method)
            $this->handleFailure($e, $notificationService);

            throw $e;
        }
    }

    /**
     * Process analysis using the agent pipeline.
     */
    private function processWithAgentPipeline(
        StoreAnalysisService $agentService,
        LiteStoreAnalysisService $liteAgentService
    ): void {
        $store = $this->analysis->store;

        if (! $store) {
            throw new \RuntimeException('Store not found for analysis');
        }

        // Determine which pipeline to use based on provider
        $useLitePipeline = $this->shouldUseLitePipeline();

        if ($useLitePipeline) {
            Log::channel($this->logChannel)->info('>>> Usando LITE pipeline (Anthropic)', [
                'analysis_id' => $this->analysis->id,
                'store_id' => $store->id,
                'store_name' => $store->name,
                'provider' => 'anthropic',
            ]);
            $result = $liteAgentService->execute($store, $this->analysis);
        } else {
            Log::channel($this->logChannel)->info('>>> Usando FULL pipeline (Gemini)', [
                'analysis_id' => $this->analysis->id,
                'store_id' => $store->id,
                'store_name' => $store->name,
                'provider' => 'gemini',
            ]);
            $result = $agentService->execute($store, $this->analysis);
        }

        Log::channel($this->logChannel)->info('<<< Agent pipeline concluido', [
            'analysis_id' => $this->analysis->id,
            'suggestions_count' => $result['suggestions_count'] ?? 0,
            'niche' => $result['niche'] ?? 'unknown',
            'pipeline' => $result['pipeline'] ?? 'full',
        ]);
    }

    /**
     * Determine if the lite pipeline should be used.
     * Uses lite pipeline when the default provider is Anthropic (30k token/min limit).
     */
    private function shouldUseLitePipeline(): bool
    {
        $defaultProvider = SystemSetting::get('ai.provider', config('services.ai.default', 'gemini'));

        return $defaultProvider === 'anthropic';
    }

    /**
     * Handle failure - mark analysis as failed and refund credits.
     * The user will be notified to try again later.
     */
    private function handleFailure(\Throwable $exception, NotificationService $notificationService): void
    {
        try {
            // Refresh to get latest state
            $this->analysis->refresh();

            // Only handle if not already completed
            if ($this->analysis->status === AnalysisStatus::Completed) {
                return;
            }

            $userMessage = $this->getUserFriendlyErrorMessage($exception);

            Log::channel($this->logChannel)->warning('>>> Marcando analise como falha', [
                'analysis_id' => $this->analysis->id,
                'attempts' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $exception->getMessage(),
            ]);

            // Mark as failed with clear message for user
            $this->analysis->markAsFailed($userMessage);

            // Notify user about the failure
            $notificationService->notifyAnalysisFailed($this->analysis, $userMessage);

            // Refund credits since analysis failed
            if ($this->analysis->user) {
                $this->analysis->user->addCredits($this->analysis->credits_used ?? 1);
                Log::channel($this->logChannel)->info('>>> Creditos reembolsados ao usuario', [
                    'analysis_id' => $this->analysis->id,
                    'user_id' => $this->analysis->user_id,
                    'credits_refunded' => $this->analysis->credits_used ?? 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel($this->logChannel)->error('!!! Erro ao tratar falha da analise', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get a user-friendly error message based on the exception.
     */
    private function getUserFriendlyErrorMessage(\Throwable $exception): string
    {
        // Log the technical error for debugging
        Log::channel($this->logChannel)->error('Detalhes tecnicos do erro', [
            'analysis_id' => $this->analysis->id,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        // Return a generic, user-friendly message
        return 'Ocorreu um erro ao processar sua análise. Por favor, tente novamente em alguns minutos.';
    }

    /**
     * Handle a job failure (called by Laravel after all retries exhausted).
     * Mark as failed and notify user to try again later.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel($this->logChannel)->error('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->error('║     PROCESS ANALYSIS - FALHA PERMANENTE                         ║');
        Log::channel($this->logChannel)->error('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->error('Detalhes da falha permanente', [
            'analysis_id' => $this->analysis->id,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Refresh analysis to get latest state
        $this->analysis->refresh();

        // If analysis is already completed, don't do anything
        if ($this->analysis->status === AnalysisStatus::Completed) {
            return;
        }

        // Mark as failed, notify user, and refund credits
        $notificationService = app(NotificationService::class);
        $this->handleFailure($exception, $notificationService);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Give up after 30 minutes total
        return now()->addMinutes(30);
    }

    /**
     * Send completion email to the user.
     */
    private function sendCompletionEmail(NotificationService $notificationService): void
    {
        try {
            // Refresh to get latest data with relationships
            $this->analysis->refresh();
            $this->analysis->load(['user', 'store', 'persistentSuggestions']);

            $user = $this->analysis->user;

            if (! $user || empty($user->email)) {
                $this->analysis->update([
                    'email_error' => 'Usuário sem e-mail cadastrado.',
                ]);

                Log::channel($this->logChannel)->warning('>>> Email de conclusao nao enviado - usuario sem email', [
                    'analysis_id' => $this->analysis->id,
                    'user_id' => $this->analysis->user_id,
                ]);

                return;
            }

            // Log tentativa de envio
            Log::channel('mail')->info('Tentando enviar email de conclusao de analise', [
                'analysis_id' => $this->analysis->id,
                'user_email' => $user->email,
                'store_name' => $this->analysis->store->name ?? 'N/A',
            ]);

            // Prepare email data using AnalysisCompletedMail for data extraction
            $mailData = new AnalysisCompletedMail($this->analysis);

            // Render the email template to HTML
            $htmlContent = View::make('emails.analysis-completed', [
                'userName' => $mailData->userName,
                'storeName' => $mailData->storeName,
                'periodStart' => $mailData->periodStart,
                'periodEnd' => $mailData->periodEnd,
                'healthScore' => $mailData->healthScore,
                'healthStatus' => $mailData->healthStatus,
                'mainInsight' => $mailData->mainInsight,
                'suggestions' => $mailData->suggestions,
            ])->render();

            // Send email directly via EmailConfigurationService (bypasses Laravel Mail caching)
            $emailService = app(EmailConfigurationService::class);
            $result = $emailService->sendHtmlEmail(
                'ai-analysis',
                $user->email,
                $user->name ?? '',
                "Análise de IA Concluída - {$mailData->storeName}",
                $htmlContent
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }

            // Atualizar campos de email com sucesso
            $this->analysis->update([
                'email_sent_at' => now(),
                'email_error' => null,
            ]);

            // Notificar envio de email bem-sucedido
            $notificationService->notifyEmailSent($user, 'analysis_completed', $user->email);

            Log::channel('mail')->info('Email de conclusao de analise enviado com sucesso', [
                'analysis_id' => $this->analysis->id,
                'user_email' => $user->email,
                'store_name' => $this->analysis->store->name ?? 'N/A',
            ]);

            Log::channel($this->logChannel)->info('>>> Email de conclusao enviado com sucesso', [
                'analysis_id' => $this->analysis->id,
                'user_email' => $user->email,
                'store_name' => $this->analysis->store->name ?? 'N/A',
            ]);

            ActivityLog::log('email.analysis_sent', $this->analysis, [
                'user_email' => $user->email,
                'store_name' => $this->analysis->store->name ?? 'N/A',
            ]);
        } catch (\Throwable $e) {
            // Salvar erro no banco
            $this->analysis->update([
                'email_error' => $e->getMessage(),
            ]);

            // Notificar falha no envio de email
            if ($user) {
                $notificationService->notifyEmailFailed($user, 'analysis_completed', $user->email ?? '', $e->getMessage());
            }

            // Log error but don't fail the job - email is secondary
            Log::channel('mail')->error('Falha ao enviar email de conclusao de analise', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Log::channel($this->logChannel)->warning('>>> Falha ao enviar email de conclusao', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
            ]);

            ActivityLog::log('email.analysis_failed', $this->analysis, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
