<?php

namespace App\Jobs;

use App\Jobs\Sync\SyncCouponsJob;
use App\Jobs\Sync\SyncCustomersJob;
use App\Jobs\Sync\SyncOrdersJob;
use App\Jobs\Sync\SyncProductsJob;
use App\Models\Store;
use App\Services\DashboardService;
use App\Services\Integration\NuvemshopService;
use App\Services\NotificationService;
use App\Services\ProductAnalyticsService;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncStoreDataJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Canal de log para sincronizacao
     */
    private string $logChannel = 'sync';

    /**
     * Número de tentativas antes de falhar
     */
    public int $tries = 3;

    /**
     * Tempo de espera entre tentativas (em segundos)
     */
    public int $backoff = 60;

    /**
     * Timeout do job em segundos (10 minutos)
     */
    public int $timeout = 600;

    /**
     * Máximo de exceções antes de falhar permanentemente
     */
    public int $maxExceptions = 2;

    /**
     * A loja a ser sincronizada
     */
    public Store $store;

    /**
     * Se deve usar modo paralelo (Job Batching) ao invés de sequencial
     */
    public bool $parallelMode;

    /**
     * Chave do cache para checkpoints de idempotência
     */
    private string $checkpointKey;

    public function __construct(Store $store, bool $parallelMode = true)
    {
        $this->store = $store;
        $this->parallelMode = $parallelMode;
        $this->checkpointKey = "sync_checkpoint:{$store->id}";

        // Define a fila específica para sincronização
        $this->onQueue('sync');
    }

    /**
     * ID único para prevenir jobs duplicados
     */
    public function uniqueId(): string
    {
        return 'sync_store_'.$this->store->id;
    }

    /**
     * Tempo que o lock de unicidade deve ser mantido (5 minutos)
     */
    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(
        NuvemshopService $nuvemshopService,
        DashboardService $dashboardService,
        ProductAnalyticsService $productAnalyticsService,
        NotificationService $notificationService
    ): void {
        $startTime = microtime(true);
        $this->store->markAsSyncing();

        // Determine if this is an incremental sync (not the first sync)
        // First sync: last_sync_at is null - fetch all data
        // Subsequent syncs: fetch only data updated in the last 24 hours
        $isFirstSync = $this->store->last_sync_at === null;
        $updatedSince = $isFirstSync ? null : Carbon::now()->subHours(24);

        try {
            // Notificar início da sincronização
            $notificationService->notifySyncStarted($this->store, 'all');

            Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
            Log::channel($this->logChannel)->info('║     SYNC STORE DATA - INICIO DA SINCRONIZACAO                   ║');
            Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
            Log::channel($this->logChannel)->info('Configuracao da sincronizacao', [
                'store_id' => $this->store->id,
                'store_name' => $this->store->name,
                'parallel_mode' => $this->parallelMode,
                'is_first_sync' => $isFirstSync,
                'updated_since' => $updatedSince?->toIso8601String(),
                'attempt' => $this->attempts(),
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($this->parallelMode) {
                // MODO PARALELO: Usa Job Batching para executar jobs simultaneamente
                $this->executeParallelSync($updatedSince, $dashboardService, $productAnalyticsService, $notificationService, $startTime);
            } else {
                // MODO SEQUENCIAL: Executa jobs um após o outro (compatibilidade)
                $this->executeSequentialSync($nuvemshopService, $updatedSince, $dashboardService, $productAnalyticsService, $notificationService, $startTime);
            }
        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime), 2);

            Log::channel($this->logChannel)->error('!!! ERRO NA SINCRONIZACAO', [
                'store_id' => $this->store->id,
                'store_name' => $this->store->name,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'parallel_mode' => $this->parallelMode,
                'attempt' => $this->attempts(),
                'total_time_seconds' => $totalTime,
            ]);

            // Não marca como falho se ainda há tentativas
            if ($this->attempts() >= $this->tries) {
                $this->store->markAsFailed();
                $this->clearCheckpoint();

                // Notificar falha permanente
                $notificationService->notifySyncFailed($this->store, 'all', $e->getMessage());

                Log::channel($this->logChannel)->error('!!! SINCRONIZACAO FALHOU PERMANENTEMENTE', [
                    'store_id' => $this->store->id,
                    'store_name' => $this->store->name,
                    'max_attempts_reached' => true,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Executa sincronização em modo paralelo usando Job Batching.
     * Todos os jobs (produtos, pedidos, clientes, cupons) são executados simultaneamente.
     */
    private function executeParallelSync(
        ?Carbon $updatedSince,
        DashboardService $dashboardService,
        ProductAnalyticsService $productAnalyticsService,
        NotificationService $notificationService,
        float $startTime
    ): void {
        $store = $this->store;
        $logChannel = $this->logChannel;

        Log::channel($logChannel)->info('>>> [PARALLEL MODE] Iniciando batch de sincronizacao', [
            'store_id' => $store->id,
        ]);

        $batch = Bus::batch([
            new SyncProductsJob($store, $updatedSince),
            new SyncOrdersJob($store, $updatedSince),
            new SyncCustomersJob($store, $updatedSince),
            new SyncCouponsJob($store, $updatedSince),
        ])
            ->name("sync-store-{$store->id}")
            ->allowFailures()
            ->then(function (Batch $batch) use ($store, $dashboardService, $productAnalyticsService, $notificationService, $startTime, $logChannel) {
                // Todos os jobs completaram com sucesso
                $store->markAsSynced();

                // Clear all caches after successful sync
                $dashboardService->clearCache($store);
                $productAnalyticsService->invalidateABCCache($store);

                $totalTime = round((microtime(true) - $startTime), 2);

                // Coletar estatísticas para notificação
                $stats = [
                    'products_count' => $store->products()->count(),
                    'orders_count' => $store->orders()->count(),
                    'customers_count' => $store->customers()->count(),
                    'coupons_count' => $store->coupons()->count(),
                ];

                // Notificar conclusão da sincronização
                $notificationService->notifySyncCompleted($store, 'all', $stats);

                Log::channel($logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
                Log::channel($logChannel)->info('║     SYNC STORE DATA - BATCH CONCLUIDO COM SUCESSO               ║');
                Log::channel($logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
                Log::channel($logChannel)->info('Estatisticas finais da sincronizacao paralela', [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'total_time_seconds' => $totalTime,
                    'batch_id' => $batch->id,
                    'total_jobs' => $batch->totalJobs,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs,
                    'status' => 'success',
                ]);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($store, $logChannel) {
                // Algum job falhou
                Log::channel($logChannel)->error('!!! [PARALLEL MODE] Batch falhou parcialmente', [
                    'store_id' => $store->id,
                    'batch_id' => $batch->id,
                    'failed_jobs' => $batch->failedJobs,
                    'error' => $e->getMessage(),
                ]);
            })
            ->finally(function (Batch $batch) use ($store, $logChannel) {
                // Executado sempre, independente de sucesso ou falha
                Log::channel($logChannel)->info('<<< [PARALLEL MODE] Batch finalizado', [
                    'store_id' => $store->id,
                    'batch_id' => $batch->id,
                    'cancelled' => $batch->cancelled(),
                    'finished' => $batch->finished(),
                ]);
            })
            ->onQueue('sync')
            ->dispatch();

        Log::channel($logChannel)->info('>>> [PARALLEL MODE] Batch disparado', [
            'store_id' => $store->id,
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
        ]);
    }

    /**
     * Executa sincronização em modo sequencial (compatibilidade com sistema antigo).
     * Jobs são executados um após o outro com sistema de checkpoints.
     */
    private function executeSequentialSync(
        NuvemshopService $nuvemshopService,
        ?Carbon $updatedSince,
        DashboardService $dashboardService,
        ProductAnalyticsService $productAnalyticsService,
        NotificationService $notificationService,
        float $startTime
    ): void {
        $checkpoint = $this->getCheckpoint();

        Log::channel($this->logChannel)->info('>>> [SEQUENTIAL MODE] Usando modo sequencial com checkpoints', [
            'store_id' => $this->store->id,
            'checkpoint' => $checkpoint,
        ]);

        // Sync products (se ainda não foi feito)
        if (! in_array('products', $checkpoint)) {
            $stepStart = microtime(true);
            Log::channel($this->logChannel)->info('>>> Iniciando sync de PRODUTOS', [
                'store' => $this->store->name,
            ]);

            $nuvemshopService->syncProducts($this->store, $updatedSince);
            $this->saveCheckpoint('products');

            $stepTime = round((microtime(true) - $stepStart) * 1000, 2);
            Log::channel($this->logChannel)->info('<<< Sync de PRODUTOS concluido', [
                'store' => $this->store->name,
                'time_ms' => $stepTime,
            ]);
        } else {
            Log::channel($this->logChannel)->info('--- Sync de PRODUTOS ignorado (checkpoint)', [
                'store' => $this->store->name,
            ]);
        }

        // Sync orders (se ainda não foi feito)
        if (! in_array('orders', $checkpoint)) {
            $stepStart = microtime(true);
            Log::channel($this->logChannel)->info('>>> Iniciando sync de PEDIDOS', [
                'store' => $this->store->name,
            ]);

            $nuvemshopService->syncOrders($this->store, $updatedSince);
            $this->saveCheckpoint('orders');

            $stepTime = round((microtime(true) - $stepStart) * 1000, 2);
            Log::channel($this->logChannel)->info('<<< Sync de PEDIDOS concluido', [
                'store' => $this->store->name,
                'time_ms' => $stepTime,
            ]);
        } else {
            Log::channel($this->logChannel)->info('--- Sync de PEDIDOS ignorado (checkpoint)', [
                'store' => $this->store->name,
            ]);
        }

        // Sync customers (se ainda não foi feito)
        if (! in_array('customers', $checkpoint)) {
            $stepStart = microtime(true);
            Log::channel($this->logChannel)->info('>>> Iniciando sync de CLIENTES', [
                'store' => $this->store->name,
            ]);

            $nuvemshopService->syncCustomers($this->store, $updatedSince);
            $this->saveCheckpoint('customers');

            $stepTime = round((microtime(true) - $stepStart) * 1000, 2);
            Log::channel($this->logChannel)->info('<<< Sync de CLIENTES concluido', [
                'store' => $this->store->name,
                'time_ms' => $stepTime,
            ]);
        } else {
            Log::channel($this->logChannel)->info('--- Sync de CLIENTES ignorado (checkpoint)', [
                'store' => $this->store->name,
            ]);
        }

        // Sync coupons (se ainda não foi feito)
        if (! in_array('coupons', $checkpoint)) {
            $stepStart = microtime(true);
            Log::channel($this->logChannel)->info('>>> Iniciando sync de CUPONS', [
                'store' => $this->store->name,
            ]);

            $nuvemshopService->syncCoupons($this->store, $updatedSince);
            $this->saveCheckpoint('coupons');

            $stepTime = round((microtime(true) - $stepStart) * 1000, 2);
            Log::channel($this->logChannel)->info('<<< Sync de CUPONS concluido', [
                'store' => $this->store->name,
                'time_ms' => $stepTime,
            ]);
        } else {
            Log::channel($this->logChannel)->info('--- Sync de CUPONS ignorado (checkpoint)', [
                'store' => $this->store->name,
            ]);
        }

        $this->store->markAsSynced();
        $this->clearCheckpoint();

        // Clear all caches after successful sync
        $dashboardService->clearCache($this->store);
        $productAnalyticsService->invalidateABCCache($this->store);

        $totalTime = round((microtime(true) - $startTime), 2);

        // Coletar estatísticas para notificação
        $stats = [
            'products_count' => $this->store->products()->count(),
            'orders_count' => $this->store->orders()->count(),
            'customers_count' => $this->store->customers()->count(),
            'coupons_count' => $this->store->coupons()->count(),
        ];

        // Notificar conclusão da sincronização
        $notificationService->notifySyncCompleted($this->store, 'all', $stats);

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     SYNC STORE DATA - SINCRONIZACAO CONCLUIDA                   ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Estatisticas finais da sincronizacao', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'total_time_seconds' => $totalTime,
            'status' => 'success',
            'caches_cleared' => true,
            'timestamp_end' => now()->toIso8601String(),
        ]);
    }

    /**
     * Obtém o checkpoint atual do cache
     */
    private function getCheckpoint(): array
    {
        return Cache::get($this->checkpointKey, []);
    }

    /**
     * Salva um checkpoint no cache
     */
    private function saveCheckpoint(string $step): void
    {
        $checkpoint = $this->getCheckpoint();
        $checkpoint[] = $step;
        Cache::put($this->checkpointKey, $checkpoint, now()->addHours(2));
    }

    /**
     * Limpa o checkpoint do cache
     */
    private function clearCheckpoint(): void
    {
        Cache::forget($this->checkpointKey);
    }

    /**
     * Determina o tempo de espera antes de retentar
     */
    public function backoff(): array
    {
        return [60, 120, 300]; // 1min, 2min, 5min
    }

    public function failed(\Throwable $exception): void
    {
        $this->store->markAsFailed();
        $this->clearCheckpoint();

        Log::channel($this->logChannel)->error('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->error('║     SYNC STORE DATA - FALHA PERMANENTE                          ║');
        Log::channel($this->logChannel)->error('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->error('Detalhes da falha permanente', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
