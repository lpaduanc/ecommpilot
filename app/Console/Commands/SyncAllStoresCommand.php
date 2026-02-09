<?php

namespace App\Console\Commands;

use App\Jobs\SyncStoreDataJob;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAllStoresCommand extends Command
{
    /**
     * Window in minutes to distribute sync jobs (4 hours = 240 minutes).
     * Jobs are spread across this window to avoid peak load.
     */
    private const DISTRIBUTION_WINDOW_MINUTES = 240;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:sync
        {--store=* : Specific store IDs to sync}
        {--no-delay : Dispatch all jobs immediately without distribution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza dados de todas as lojas conectadas com distribuição temporal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronização de lojas...');

        // Se foi especificado --store, sincroniza apenas essas lojas (sem delay)
        $specificStoreIds = $this->option('store');

        if (! empty($specificStoreIds)) {
            return $this->syncSpecificStores($specificStoreIds);
        }

        $useDelay = ! $this->option('no-delay');

        // Busca todas as lojas
        $stores = Store::query()->get();

        if ($stores->isEmpty()) {
            $this->warn('Nenhuma loja encontrada para sincronização.');

            return Command::SUCCESS;
        }

        $this->info("Encontradas {$stores->count()} loja(s) para sincronizar.");

        if ($useDelay) {
            $this->info('Distribuindo jobs em janela de '.self::DISTRIBUTION_WINDOW_MINUTES.' minutos.');
        }

        $queuedCount = 0;
        $skippedCount = 0;

        foreach ($stores as $store) {
            // Verifica se a loja requer reconexão (método do modelo)
            if ($store->requiresReconnection()) {
                $this->warn("Loja '{$store->name}' (ID: {$store->id}) requer reconexão. Pulando...");
                $skippedCount++;

                continue;
            }

            // Calcular delay baseado no ID da loja para distribuir uniformemente
            if ($useDelay) {
                $delayMinutes = $this->calculateDelay($store->id);
                SyncStoreDataJob::dispatch($store)->delay(now()->addMinutes($delayMinutes));
                $this->line("✓ Loja '{$store->name}' (ID: {$store->id}) agendada com delay de {$delayMinutes} min.");
            } else {
                SyncStoreDataJob::dispatch($store);
                $this->line("✓ Loja '{$store->name}' (ID: {$store->id}) enfileirada para sincronização.");
            }

            $queuedCount++;
        }

        Log::info('Comando stores:sync executado', [
            'total_stores' => $stores->count(),
            'queued' => $queuedCount,
            'skipped' => $skippedCount,
            'distribution_enabled' => $useDelay,
            'window_minutes' => self::DISTRIBUTION_WINDOW_MINUTES,
        ]);

        $this->newLine();
        $this->info('Sincronização iniciada com sucesso!');
        $this->info("Lojas enfileiradas: {$queuedCount}");

        if ($useDelay && $queuedCount > 0) {
            $this->info('Jobs distribuídos entre 0 e '.self::DISTRIBUTION_WINDOW_MINUTES.' minutos.');
        }

        if ($skippedCount > 0) {
            $this->warn("Lojas puladas (requerem reconexão): {$skippedCount}");
        }

        return Command::SUCCESS;
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
     * Sincroniza lojas específicas pelos IDs fornecidos
     */
    private function syncSpecificStores(array $storeIds): int
    {
        $stores = Store::whereIn('id', $storeIds)->get();

        if ($stores->isEmpty()) {
            $this->error('Nenhuma loja encontrada com os IDs fornecidos.');

            return Command::FAILURE;
        }

        $this->info("Encontradas {$stores->count()} loja(s) com os IDs especificados.");

        $queuedCount = 0;
        $skippedCount = 0;

        foreach ($stores as $store) {
            if ($store->requiresReconnection()) {
                $this->warn("Loja '{$store->name}' (ID: {$store->id}) requer reconexão. Pulando...");
                $skippedCount++;

                continue;
            }

            SyncStoreDataJob::dispatch($store);
            $queuedCount++;

            $this->line("✓ Loja '{$store->name}' (ID: {$store->id}) enfileirada para sincronização.");
        }

        Log::info('Comando stores:sync executado (lojas específicas)', [
            'store_ids' => $storeIds,
            'found' => $stores->count(),
            'queued' => $queuedCount,
            'skipped' => $skippedCount,
        ]);

        $this->newLine();
        $this->info('Sincronização iniciada com sucesso!');
        $this->info("Lojas enfileiradas: {$queuedCount}");

        if ($skippedCount > 0) {
            $this->warn("Lojas puladas (requerem reconexão): {$skippedCount}");
        }

        return Command::SUCCESS;
    }
}
