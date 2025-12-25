<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Integration\NuvemshopService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStoreDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private Store $store
    ) {}

    public function handle(NuvemshopService $nuvemshopService): void
    {
        $this->store->markAsSyncing();

        try {
            Log::info("Starting sync for store: {$this->store->name}");

            // Sync products
            $nuvemshopService->syncProducts($this->store);
            Log::info("Products synced for store: {$this->store->name}");

            // Sync orders
            $nuvemshopService->syncOrders($this->store);
            Log::info("Orders synced for store: {$this->store->name}");

            // Sync customers
            $nuvemshopService->syncCustomers($this->store);
            Log::info("Customers synced for store: {$this->store->name}");

            $this->store->markAsSynced();

            Log::info("Sync completed for store: {$this->store->name}");
        } catch (\Exception $e) {
            Log::error("Sync failed for store {$this->store->name}: {$e->getMessage()}");
            $this->store->markAsFailed();
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->store->markAsFailed();
        Log::error("SyncStoreDataJob failed for store {$this->store->id}: {$exception->getMessage()}");
    }
}

