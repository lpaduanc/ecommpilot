<?php

namespace App\Jobs\Sync;

use App\Models\Store;
use App\Services\Integration\NuvemshopService;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Pedidos por página
     */
    private const PER_PAGE = 200;

    /**
     * Máximo de erros consecutivos antes de falhar
     */
    private const MAX_CONSECUTIVE_ERRORS = 5;

    /**
     * TTL do checkpoint em segundos (2 horas)
     */
    private const CHECKPOINT_TTL = 7200;

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

    /**
     * ID único para prevenir duplicação de jobs
     */
    public function uniqueId(): string
    {
        return "sync_orders_{$this->store->id}";
    }

    /**
     * Por quanto tempo o job deve permanecer único (30 minutos)
     */
    public function uniqueFor(): int
    {
        return 1800;
    }

    public function handle(NuvemshopService $nuvemshopService): void
    {
        // Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            Log::channel('sync')->info('>>> [ORDERS] Sync cancelado - batch cancelado', [
                'store_id' => $this->store->id,
                'batch_id' => $this->batch()?->id,
            ]);

            return;
        }

        // Verificar se atingiu o limite de pedidos do plano
        $planLimitService = app(\App\Services\PlanLimitService::class);
        $user = $this->store->user;

        if ($user && $planLimitService->hasExceededOrdersLimit($user)) {
            Log::channel('sync')->info('>>> [ORDERS] Sync de PEDIDOS ignorado - limite do plano atingido', [
                'store_id' => $this->store->id,
                'store_name' => $this->store->name,
                'user_id' => $user->id,
                'orders_limit' => $planLimitService->getOrdersLimit($user),
                'current_orders' => $planLimitService->getMonthlyOrdersCount($user),
            ]);

            return;
        }

        // Recupera checkpoint se existir
        $checkpointKey = "sync_orders_checkpoint:{$this->store->id}";
        $currentPage = Cache::get($checkpointKey, 1);

        Log::channel('sync')->info('>>> [ORDERS] Iniciando sync de PEDIDOS com paginação interna', [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'batch_id' => $this->batch()?->id,
            'incremental' => $this->updatedSince !== null,
            'starting_page' => $currentPage,
        ]);

        $consecutiveErrors = 0;
        $totalSynced = 0;
        $updatedSinceIso = $this->updatedSince?->toIso8601String();

        while (true) {
            // Reconecta ao banco para evitar conexões stale em jobs longos
            DB::reconnect();

            // Verifica se batch foi cancelado
            if ($this->batch()?->cancelled()) {
                Log::channel('sync')->info('>>> [ORDERS] Sync interrompido - batch cancelado', [
                    'store_id' => $this->store->id,
                    'current_page' => $currentPage,
                    'total_synced' => $totalSynced,
                ]);
                break;
            }

            // Verifica limite do plano novamente (pode ter mudado durante execução)
            if ($user && $planLimitService->hasExceededOrdersLimit($user)) {
                Log::channel('sync')->info('>>> [ORDERS] Sync interrompido - limite do plano atingido', [
                    'store_id' => $this->store->id,
                    'current_page' => $currentPage,
                    'total_synced' => $totalSynced,
                ]);
                break;
            }

            try {
                Log::channel('sync')->debug('[ORDERS] Buscando página de pedidos', [
                    'store_id' => $this->store->id,
                    'page' => $currentPage,
                    'per_page' => self::PER_PAGE,
                ]);

                // Busca página de pedidos
                $orders = $nuvemshopService->fetchOrdersPage(
                    $this->store,
                    $currentPage,
                    self::PER_PAGE,
                    $updatedSinceIso
                );

                $ordersCount = count($orders);

                Log::channel('sync')->debug('[ORDERS] Página recebida', [
                    'store_id' => $this->store->id,
                    'page' => $currentPage,
                    'orders_count' => $ordersCount,
                ]);

                // Salva pedidos no banco
                if ($ordersCount > 0) {
                    $nuvemshopService->saveOrders($this->store, $orders);
                    $totalSynced += $ordersCount;

                    Log::channel('sync')->info('[ORDERS] Página sincronizada com sucesso', [
                        'store_id' => $this->store->id,
                        'page' => $currentPage,
                        'orders_count' => $ordersCount,
                        'total_synced' => $totalSynced,
                    ]);
                }

                // Atualiza checkpoint no cache
                Cache::put($checkpointKey, $currentPage + 1, self::CHECKPOINT_TTL);

                // Reset contador de erros em sucesso
                $consecutiveErrors = 0;

                // Se retornou menos que PER_PAGE, acabaram os pedidos
                if ($ordersCount < self::PER_PAGE) {
                    Log::channel('sync')->info('[ORDERS] Última página alcançada', [
                        'store_id' => $this->store->id,
                        'final_page' => $currentPage,
                        'total_synced' => $totalSynced,
                    ]);
                    // Limpa checkpoint
                    Cache::forget($checkpointKey);
                    break;
                }

                // Incrementa página para próxima iteração
                $currentPage++;

                // Força garbage collection a cada 10 páginas
                if ($currentPage % 10 === 0) {
                    gc_collect_cycles();
                }

            } catch (\Exception $e) {
                $consecutiveErrors++;

                Log::channel('sync')->error('[ORDERS] Erro ao sincronizar página', [
                    'store_id' => $this->store->id,
                    'page' => $currentPage,
                    'error' => $e->getMessage(),
                    'consecutive_errors' => $consecutiveErrors,
                    'total_synced' => $totalSynced,
                ]);

                // Se atingiu limite de erros consecutivos, lança exceção
                if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                    Log::channel('sync')->error('[ORDERS] Limite de erros consecutivos atingido', [
                        'store_id' => $this->store->id,
                        'page' => $currentPage,
                        'max_errors' => self::MAX_CONSECUTIVE_ERRORS,
                    ]);
                    throw $e;
                }

                // Backoff exponencial antes de retry
                $sleepSeconds = min(30 * $consecutiveErrors, 300); // Max 5 minutos
                Log::channel('sync')->warning('[ORDERS] Aguardando antes de retry', [
                    'store_id' => $this->store->id,
                    'sleep_seconds' => $sleepSeconds,
                ]);
                sleep($sleepSeconds);
            }
        }

        Log::channel('sync')->info('<<< [ORDERS] Sync de PEDIDOS finalizado', [
            'store_id' => $this->store->id,
            'total_synced' => $totalSynced,
            'final_page' => $currentPage,
        ]);
    }

    /**
     * Determina o tempo de espera antes de retentar
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }
}
