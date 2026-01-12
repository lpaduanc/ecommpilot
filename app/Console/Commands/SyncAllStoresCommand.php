<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Jobs\SyncStoreDataJob;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAllStoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:sync {--store=* : Specific store IDs to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza dados de todas as lojas conectadas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronização de lojas...');

        // Se foi especificado --store, sincroniza apenas essas lojas
        $specificStoreIds = $this->option('store');

        if (! empty($specificStoreIds)) {
            return $this->syncSpecificStores($specificStoreIds);
        }

        // Busca todas as lojas que NÃO precisam de reconexão
        $stores = Store::query()
            ->where('token_requires_reconnection', false)
            ->where('sync_status', '!=', SyncStatus::TokenExpired->value)
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('Nenhuma loja encontrada para sincronização.');

            return Command::SUCCESS;
        }

        $this->info("Encontradas {$stores->count()} loja(s) para sincronizar.");

        $queuedCount = 0;
        $skippedCount = 0;

        foreach ($stores as $store) {
            // Verifica se a loja requer reconexão (método do modelo)
            if ($store->requiresReconnection()) {
                $this->warn("Loja '{$store->name}' (ID: {$store->id}) requer reconexão. Pulando...");
                $skippedCount++;

                continue;
            }

            // Dispatch do job de sincronização
            SyncStoreDataJob::dispatch($store);
            $queuedCount++;

            $this->line("✓ Loja '{$store->name}' (ID: {$store->id}) enfileirada para sincronização.");
        }

        Log::info('Comando stores:sync executado', [
            'total_stores' => $stores->count(),
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
