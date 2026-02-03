<?php

namespace App\Console\Commands;

use App\Enums\AnalysisStatus;
use App\Enums\SyncStatus;
use App\Jobs\ProcessAnalysisJob;
use App\Models\Analysis;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutoAnalysisCommand extends Command
{
    /**
     * Window in minutes to distribute analysis jobs (2 hours = 120 minutes).
     * Jobs are spread across this window to avoid AI API overload.
     */
    private const DISTRIBUTION_WINDOW_MINUTES = 120;

    /**
     * Number of days to include in the analysis period.
     */
    private const ANALYSIS_PERIOD_DAYS = 15;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyses:auto
        {--store=* : Specific store IDs to analyze}
        {--no-delay : Dispatch all jobs immediately without distribution}
        {--dry-run : Show eligible stores without dispatching analyses}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispara análises automáticas para lojas elegíveis após sincronização';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando análises automáticas...');

        $specificStoreIds = $this->option('store');
        $isDryRun = $this->option('dry-run');
        $useDelay = ! $this->option('no-delay');

        // Get eligible stores
        $stores = $this->getEligibleStores($specificStoreIds);

        if ($stores->isEmpty()) {
            $this->warn('Nenhuma loja elegível para análise automática.');

            Log::channel('analysis')->info('Comando analyses:auto executado - nenhuma loja elegível', [
                'specific_store_ids' => $specificStoreIds ?: 'all',
            ]);

            return Command::SUCCESS;
        }

        $this->info("Encontradas {$stores->count()} loja(s) elegíveis.");

        if ($isDryRun) {
            return $this->displayDryRunTable($stores);
        }

        if ($useDelay) {
            $this->info('Distribuindo jobs em janela de '.self::DISTRIBUTION_WINDOW_MINUTES.' minutos.');
        }

        $dispatchedCount = 0;
        $skippedCount = 0;
        $skippedReasons = [];

        foreach ($stores as $store) {
            // Check if analysis already exists for today
            if ($this->hasAnalysisToday($store)) {
                $this->warn("Loja '{$store->name}' (ID: {$store->id}) já possui análise hoje. Pulando...");
                $skippedCount++;
                $skippedReasons[$store->id] = 'already_analyzed_today';

                continue;
            }

            // Create analysis record
            $analysis = $this->createAnalysis($store);

            // Calculate delay and dispatch
            if ($useDelay) {
                $delayMinutes = $this->calculateDelay($store->id);
                ProcessAnalysisJob::dispatch($analysis)->delay(now()->addMinutes($delayMinutes));
                $this->line("✓ Loja '{$store->name}' (ID: {$store->id}) agendada com delay de {$delayMinutes} min.");
            } else {
                ProcessAnalysisJob::dispatch($analysis);
                $this->line("✓ Loja '{$store->name}' (ID: {$store->id}) enfileirada para análise.");
            }

            $dispatchedCount++;
        }

        // Log results
        Log::channel('analysis')->info('Comando analyses:auto executado', [
            'total_eligible' => $stores->count(),
            'dispatched' => $dispatchedCount,
            'skipped' => $skippedCount,
            'skipped_reasons' => $skippedReasons,
            'distribution_enabled' => $useDelay,
            'window_minutes' => self::DISTRIBUTION_WINDOW_MINUTES,
        ]);

        $this->newLine();
        $this->info('Análises automáticas iniciadas!');
        $this->info("Análises enfileiradas: {$dispatchedCount}");

        if ($useDelay && $dispatchedCount > 0) {
            $this->info('Jobs distribuídos entre 0 e '.self::DISTRIBUTION_WINDOW_MINUTES.' minutos.');
        }

        if ($skippedCount > 0) {
            $this->warn("Lojas puladas (já analisadas hoje): {$skippedCount}");
        }

        return Command::SUCCESS;
    }

    /**
     * Get stores eligible for automatic analysis.
     *
     * @param  array|null  $specificIds  Specific store IDs to filter
     */
    private function getEligibleStores(?array $specificIds): Collection
    {
        $query = Store::query()
            ->with('user.subscriptions.plan')
            ->where('auto_analysis_enabled', true)
            ->where('sync_status', SyncStatus::Completed)
            ->where('token_requires_reconnection', false);

        if (! empty($specificIds)) {
            $query->whereIn('id', $specificIds);
        }

        return $query->get()->filter(function ($store) {
            return $store->isEligibleForAutoAnalysis();
        });
    }

    /**
     * Check if store already has an analysis created today.
     */
    private function hasAnalysisToday(Store $store): bool
    {
        return Analysis::where('store_id', $store->id)
            ->whereDate('created_at', today())
            ->whereNotIn('status', [AnalysisStatus::Failed])
            ->exists();
    }

    /**
     * Create a new analysis record for the store.
     */
    private function createAnalysis(Store $store): Analysis
    {
        return Analysis::create([
            'user_id' => $store->user_id,
            'store_id' => $store->id,
            'status' => AnalysisStatus::Pending,
            'period_start' => now()->subDays(self::ANALYSIS_PERIOD_DAYS),
            'period_end' => now(),
        ]);
    }

    /**
     * Calculate delay in minutes for a store based on its ID.
     * Uses modulo to distribute stores evenly across the time window.
     */
    private function calculateDelay(int $storeId): int
    {
        return $storeId % self::DISTRIBUTION_WINDOW_MINUTES;
    }

    /**
     * Display a table of eligible stores without dispatching.
     */
    private function displayDryRunTable(Collection $stores): int
    {
        $this->newLine();
        $this->info('=== DRY RUN - Lojas elegíveis para análise automática ===');
        $this->newLine();

        $tableData = $stores->map(function ($store) {
            $hasAnalysisToday = $this->hasAnalysisToday($store);
            $delayMinutes = $this->calculateDelay($store->id);

            return [
                'ID' => $store->id,
                'Nome' => substr($store->name, 0, 30),
                'Usuário' => $store->user?->name ?? 'N/A',
                'Plano' => $store->user?->currentPlan()?->name ?? 'N/A',
                'Último Sync' => $store->last_sync_at?->format('d/m H:i') ?? 'Nunca',
                'Análise Hoje' => $hasAnalysisToday ? 'Sim (skip)' : 'Não',
                'Delay (min)' => $delayMinutes,
            ];
        });

        $this->table(
            ['ID', 'Nome', 'Usuário', 'Plano', 'Último Sync', 'Análise Hoje', 'Delay (min)'],
            $tableData->toArray()
        );

        $wouldDispatch = $stores->filter(fn ($store) => ! $this->hasAnalysisToday($store))->count();

        $this->newLine();
        $this->info("Total elegíveis: {$stores->count()}");
        $this->info("Seriam enfileiradas: {$wouldDispatch}");
        $this->info("Seriam puladas: ".($stores->count() - $wouldDispatch));

        Log::channel('analysis')->info('Comando analyses:auto dry-run executado', [
            'total_eligible' => $stores->count(),
            'would_dispatch' => $wouldDispatch,
        ]);

        return Command::SUCCESS;
    }
}
