<?php

namespace App\Jobs\Sync;

use App\Models\Store;
use App\Services\Integration\NuvemshopService;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas antes de falhar
     */
    public int $tries = 3;

    /**
     * Timeout do job em segundos (5 minutos)
     */
    public int $timeout = 300;

    public function __construct(
        public Store $store,
        public ?Carbon $updatedSince = null
    ) {
        $this->onQueue('sync');
    }

    public function handle(NuvemshopService $nuvemshopService): void
    {
        // Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $startTime = microtime(true);

        Log::channel('sync')->info('>>> [PARALLEL] Iniciando sync de PRODUTOS', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'batch_id' => $this->batch()?->id,
            'incremental' => $this->updatedSince !== null,
        ]);

        try {
            $nuvemshopService->syncProducts($this->store, $this->updatedSince);

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            Log::channel('sync')->info('<<< [PARALLEL] Sync de PRODUTOS concluido', [
                'store_id' => $this->store->id,
                'time_ms' => $elapsed,
                'products_count' => $this->store->products()->count(),
            ]);
        } catch (\Exception $e) {
            Log::channel('sync')->error('!!! [PARALLEL] Erro no sync de PRODUTOS', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Determina o tempo de espera antes de retentar
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }
}
