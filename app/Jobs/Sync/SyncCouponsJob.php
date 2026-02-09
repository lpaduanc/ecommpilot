<?php

namespace App\Jobs\Sync;

use App\Exceptions\TokenExpiredException;
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

class SyncCouponsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas antes de falhar
     */
    public int $tries = 3;

    /**
     * Timeout do job - 0 = sem limite
     */
    public int $timeout = 0;

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

        // Refresh store model to get latest state
        $this->store->refresh();

        // Check if store requires reconnection (token expired/invalid)
        if ($this->store->token_requires_reconnection) {
            Log::channel('sync')->warning('>>> [COUPONS] Sync ignorado - loja requer reconexão OAuth', [
                'store_id' => $this->store->id,
                'store_name' => $this->store->name,
                'sync_status' => $this->store->sync_status->value,
            ]);

            return;
        }

        $startTime = microtime(true);

        Log::channel('sync')->info('>>> [PARALLEL] Iniciando sync de CUPONS', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'batch_id' => $this->batch()?->id,
            'incremental' => $this->updatedSince !== null,
        ]);

        try {
            $nuvemshopService->syncCoupons($this->store, $this->updatedSince);

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            Log::channel('sync')->info('<<< [PARALLEL] Sync de CUPONS concluido', [
                'store_id' => $this->store->id,
                'time_ms' => $elapsed,
                'coupons_count' => $this->store->coupons()->count(),
            ]);
        } catch (TokenExpiredException $e) {
            // Token expirado - cancela batch imediatamente
            Log::channel('sync')->warning('[COUPONS] Token expirado - cancelando batch', [
                'store_id' => $this->store->id,
            ]);

            if ($this->batch()) {
                $this->batch()->cancel();
            }

            throw $e;
        } catch (\Exception $e) {
            Log::channel('sync')->error('!!! [PARALLEL] Erro no sync de CUPONS', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
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
