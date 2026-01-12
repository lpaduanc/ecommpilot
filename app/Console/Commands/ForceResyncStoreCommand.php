<?php

namespace App\Console\Commands;

use App\Jobs\SyncStoreDataJob;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ForceResyncStoreCommand extends Command
{
    protected $signature = 'store:force-resync {store_id? : The ID of the store to resync} {--all : Resync all stores}';

    protected $description = 'Force a complete resync of store data, clearing checkpoints and last_sync_at';

    public function handle(): int
    {
        if ($this->option('all')) {
            $stores = Store::all();
            $this->info("Force resyncing all {$stores->count()} stores...");

            foreach ($stores as $store) {
                $this->resyncStore($store);
            }

            return Command::SUCCESS;
        }

        $storeId = $this->argument('store_id');

        if (! $storeId) {
            // Show available stores
            $stores = Store::all(['id', 'name', 'domain', 'sync_status', 'last_sync_at']);

            if ($stores->isEmpty()) {
                $this->error('No stores found.');

                return Command::FAILURE;
            }

            $this->table(
                ['ID', 'Name', 'Domain', 'Sync Status', 'Last Sync'],
                $stores->map(fn ($s) => [
                    $s->id,
                    $s->name,
                    $s->domain,
                    $s->sync_status->value,
                    $s->last_sync_at?->format('Y-m-d H:i:s') ?? 'Never',
                ])
            );

            $storeId = $this->ask('Enter store ID to resync');
        }

        $store = Store::find($storeId);

        if (! $store) {
            $this->error("Store with ID {$storeId} not found.");

            return Command::FAILURE;
        }

        $this->resyncStore($store);

        return Command::SUCCESS;
    }

    private function resyncStore(Store $store): void
    {
        $this->info("Force resyncing store: {$store->name} (ID: {$store->id})");

        // Clear the checkpoint cache
        $checkpointKey = "sync_checkpoint:{$store->id}";
        Cache::forget($checkpointKey);
        $this->line('  - Cleared checkpoint cache');

        // Reset last_sync_at to force full sync
        $store->update(['last_sync_at' => null]);
        $this->line('  - Reset last_sync_at to null (will trigger full sync)');

        // Dispatch the sync job
        SyncStoreDataJob::dispatch($store);
        $this->line('  - Dispatched SyncStoreDataJob');

        $this->info('  Done! Sync job has been queued.');
    }
}
