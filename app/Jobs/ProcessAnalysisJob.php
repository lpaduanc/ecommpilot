<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Mail\AnalysisCompletedMail;
use App\Models\ActivityLog;
use App\Models\Analysis;
use App\Models\SystemSetting;
use App\Models\User;
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
use Illuminate\Support\Collection;
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
     * Safe log to mail channel - silently ignores permission errors.
     */
    private function safeMailLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('mail')->{$level}($message, $context);
        } catch (\Throwable) {
            // Fallback to default log channel if mail channel fails
            try {
                Log::{$level}('[mail] '.$message, $context);
            } catch (\Throwable) {
                // Silently ignore
            }
        }
    }

    /**
     * Number of times to attempt the job.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between retries (exponential: 60, 120, 240).
     */
    public array $backoff = [60, 120, 240];

    /**
     * Timeout do job - 0 = sem limite
     */
    public int $timeout = 0;

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
            'analysis_type' => $this->analysis->analysis_type?->value ?? 'general',
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
                'max_tries' => $this->tries,
                'total_time_seconds' => $totalTime,
            ]);

            // Mark as failed immediately (don't wait for failed() method)
            $this->handleFailure($e, $notificationService);

            // Check if this is a non-retryable error (connection issues, config errors)
            if ($this->isNonRetryableError($e)) {
                Log::channel($this->logChannel)->warning('>>> Erro nao-retry - deletando job da fila', [
                    'analysis_id' => $this->analysis->id,
                    'error_type' => get_class($e),
                ]);
                // Delete from queue - don't retry
                $this->delete();

                return;
            }

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
        $defaultProvider = SystemSetting::get('ai.provider') ?? 'gemini';

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
        } catch (\Throwable $e) {
            Log::channel($this->logChannel)->error('!!! Erro ao tratar falha da analise', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if the error is non-retryable (should fail immediately).
     */
    private function isNonRetryableError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $className = get_class($exception);

        // Database connection errors - no point retrying if DB is unreachable
        if (str_contains($message, 'could not translate host name')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'No connection could be made')
            || str_contains($message, 'SQLSTATE[08006]')
            || str_contains($message, 'SQLSTATE[HY000]')) {
            return true;
        }

        // API key/configuration errors - won't fix themselves
        if (str_contains($message, 'invalid x-api-key')
            || str_contains($message, 'invalid api key')
            || str_contains($message, 'API key is not configured')
            || str_contains($message, 'is not properly configured')) {
            return true;
        }

        // Store/model not found - data issue
        if (str_contains($message, 'Store not found')
            || str_contains($className, 'ModelNotFoundException')) {
            return true;
        }

        return false;
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
        $errorId = 'err_'.uniqid();

        Log::channel($this->logChannel)->error('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->error('║     PROCESS ANALYSIS - FALHA PERMANENTE                         ║');
        Log::channel($this->logChannel)->error('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->error('Detalhes da falha permanente', [
            'error_id' => $errorId,
            'analysis_id' => $this->analysis->id,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
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
     * Determine the list of recipients for the completion email.
     *
     * - Manual analysis: only the user who requested it (requested_by_user_id).
     * - Automatic analysis: owner + employees that have access to the store.
     */
    private function getEmailRecipients(): Collection
    {
        $analysis = $this->analysis;

        // Manual analysis: send only to who requested it
        if ($analysis->requested_by_user_id) {
            $requester = $analysis->requestedBy;

            return $requester ? collect([$requester]) : collect();
        }

        // Automatic analysis: owner + employees with access to this store
        $owner = $analysis->user;
        $recipients = collect([$owner]);

        // Employees explicitly assigned to this store via pivot store_user
        $employeesWithStore = User::where('parent_user_id', $owner->id)
            ->whereHas('assignedStores', fn ($q) => $q->where('store_id', $analysis->store_id))
            ->get();

        // Employees with no store assignments (legacy: access to all stores)
        $employeesWithNoAssignments = User::where('parent_user_id', $owner->id)
            ->whereDoesntHave('assignedStores')
            ->get();

        return $recipients
            ->merge($employeesWithStore)
            ->merge($employeesWithNoAssignments)
            ->unique('id');
    }

    /**
     * Send completion email to the user.
     */
    private function sendCompletionEmail(NotificationService $notificationService): void
    {
        try {
            // Refresh to get latest data with relationships
            $this->analysis->refresh();
            $this->analysis->load(['user', 'requestedBy', 'store', 'persistentSuggestions']);

            $recipients = $this->getEmailRecipients()->filter(fn ($r) => ! empty($r->email));

            if ($recipients->isEmpty()) {
                $this->analysis->update([
                    'email_error' => 'Nenhum destinatário com e-mail cadastrado.',
                ]);

                Log::channel($this->logChannel)->warning('>>> Email de conclusao nao enviado - nenhum destinatario com email', [
                    'analysis_id' => $this->analysis->id,
                    'user_id' => $this->analysis->user_id,
                    'requested_by_user_id' => $this->analysis->requested_by_user_id,
                ]);

                return;
            }

            $storeName = $this->analysis->store->name ?? 'N/A';

            // Build shared mail data once (extracts store/analysis fields)
            $mailData = new AnalysisCompletedMail($this->analysis);

            $emailService = app(EmailConfigurationService::class);

            $atLeastOneSent = false;
            $errors = [];

            foreach ($recipients as $recipient) {
                try {
                    $this->safeMailLog('info', 'Tentando enviar email de conclusao de analise', [
                        'analysis_id' => $this->analysis->id,
                        'recipient_email' => $recipient->email,
                        'recipient_id' => $recipient->id,
                        'store_name' => $storeName,
                    ]);

                    // Render template with the specific recipient's name
                    $htmlContent = View::make('emails.analysis-completed', [
                        'userName' => $recipient->name ?? $mailData->userName,
                        'storeName' => $mailData->storeName,
                        'periodStart' => $mailData->periodStart,
                        'periodEnd' => $mailData->periodEnd,
                        'healthScore' => $mailData->healthScore,
                        'healthStatus' => $mailData->healthStatus,
                        'mainInsight' => $mailData->mainInsight,
                        'suggestions' => $mailData->suggestions,
                        'analysisType' => $mailData->analysisType,
                        'analysisTypeLabel' => $mailData->analysisTypeLabel,
                        'premiumSummary' => $mailData->premiumSummary,
                        'alerts' => $mailData->alerts,
                    ])->render();

                    $result = $emailService->sendHtmlEmail(
                        'ai-analysis',
                        $recipient->email,
                        $recipient->name ?? '',
                        "Análise de IA Concluída - {$mailData->storeName}",
                        $htmlContent
                    );

                    if (! $result['success']) {
                        throw new \RuntimeException($result['message']);
                    }

                    $atLeastOneSent = true;

                    $notificationService->notifyEmailSent($recipient, 'analysis_completed', $recipient->email);

                    $this->safeMailLog('info', 'Email de conclusao de analise enviado com sucesso', [
                        'analysis_id' => $this->analysis->id,
                        'recipient_email' => $recipient->email,
                        'store_name' => $storeName,
                    ]);

                    Log::channel($this->logChannel)->info('>>> Email de conclusao enviado com sucesso', [
                        'analysis_id' => $this->analysis->id,
                        'recipient_email' => $recipient->email,
                        'store_name' => $storeName,
                    ]);

                    ActivityLog::log('email.analysis_sent', $this->analysis, [
                        'user_email' => $recipient->email,
                        'store_name' => $storeName,
                    ]);
                } catch (\Throwable $e) {
                    $errors[] = "[{$recipient->email}] {$e->getMessage()}";

                    $notificationService->notifyEmailFailed($recipient, 'analysis_completed', $recipient->email, $e->getMessage());

                    $this->safeMailLog('error', 'Falha ao enviar email de conclusao de analise para destinatario', [
                        'analysis_id' => $this->analysis->id,
                        'recipient_email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]);

                    Log::channel($this->logChannel)->warning('>>> Falha ao enviar email de conclusao para destinatario', [
                        'analysis_id' => $this->analysis->id,
                        'recipient_email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]);

                    ActivityLog::log('email.analysis_failed', $this->analysis, [
                        'recipient_email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($atLeastOneSent) {
                $this->analysis->update([
                    'email_sent_at' => now(),
                    'email_error' => ! empty($errors) ? 'Falhas parciais: '.implode('; ', $errors) : null,
                ]);
            } else {
                $this->analysis->update([
                    'email_error' => implode('; ', $errors),
                ]);
            }
        } catch (\Throwable $e) {
            // Salvar erro no banco
            $this->analysis->update([
                'email_error' => $e->getMessage(),
            ]);

            // Log error but don't fail the job - email is secondary
            $this->safeMailLog('error', 'Falha ao enviar email de conclusao de analise', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
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
