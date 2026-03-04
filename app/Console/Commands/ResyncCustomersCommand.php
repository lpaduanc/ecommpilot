<?php

namespace App\Console\Commands;

use App\Jobs\Sync\SyncCustomersJob;
use App\Models\Store;
use App\Models\SyncedCustomer;
use App\Services\CustomerRfmService;
use Illuminate\Console\Command;

class ResyncCustomersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:resync
                            {--store= : ID da loja específica para ressincronizar}
                            {--all : Ressincronizar clientes de todas as lojas}
                            {--dry-run : Mostrar o que seria feito sem executar}
                            {--force : Pular confirmação interativa}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpa e ressincroniza completamente os clientes de uma ou todas as lojas (ignora filtro incremental de 24h)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeId = $this->option('store');
        $all = $this->option('all');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Exige que ao menos uma das opções seja passada
        if (! $storeId && ! $all) {
            $this->error('Informe --store={id} para uma loja específica ou --all para todas as lojas.');
            $this->newLine();
            $this->line('Exemplos:');
            $this->line('  php artisan customers:resync --store=5');
            $this->line('  php artisan customers:resync --all');
            $this->line('  php artisan customers:resync --store=5 --dry-run');
            $this->line('  php artisan customers:resync --all --force');

            return Command::FAILURE;
        }

        // Carrega as lojas alvo
        if ($all) {
            $stores = Store::orderBy('id')->get();

            if ($stores->isEmpty()) {
                $this->error('Nenhuma loja encontrada no banco de dados.');

                return Command::FAILURE;
            }
        } else {
            $store = Store::find($storeId);

            if (! $store) {
                $this->error("Loja com ID {$storeId} não encontrada.");

                return Command::FAILURE;
            }

            $stores = collect([$store]);
        }

        // Exibe resumo do que será feito
        $this->displaySummary($stores, $dryRun);

        // Pede confirmação se não estiver em dry-run nem com --force
        if (! $dryRun && ! $force) {
            if (! $this->confirm('Confirma a execução? Esta ação irá apagar e ressincronizar os dados de clientes.', false)) {
                $this->line('Operação cancelada.');

                return Command::SUCCESS;
            }
        }

        $this->newLine();

        $exitCode = Command::SUCCESS;

        foreach ($stores as $store) {
            $result = $this->processStore($store, $dryRun);

            if (! $result) {
                $exitCode = Command::FAILURE;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->comment('Modo dry-run: nenhuma alteração foi realizada. Execute sem --dry-run para aplicar.');
        } else {
            $count = $stores->count();
            $this->info("Concluido. Jobs de ressincronizacao disparados para {$count} loja(s).");
            $this->line('Acompanhe o progresso com: php artisan queue:work --queue=sync');
        }

        return $exitCode;
    }

    /**
     * Exibe uma tabela resumindo o impacto da operação antes de executar.
     */
    private function displaySummary(\Illuminate\Support\Collection $stores, bool $dryRun): void
    {
        $mode = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$mode}Lojas que serao processadas:");
        $this->newLine();

        $rows = $stores->map(function (Store $store) {
            $customerCount = SyncedCustomer::withTrashed()
                ->where('store_id', $store->id)
                ->count();

            $softDeletedCount = SyncedCustomer::onlyTrashed()
                ->where('store_id', $store->id)
                ->count();

            return [
                $store->id,
                $store->name,
                $store->domain ?? '-',
                $store->sync_status->value,
                $store->last_sync_at?->format('Y-m-d H:i') ?? 'Nunca',
                $customerCount,
                $softDeletedCount,
            ];
        });

        $this->table(
            ['ID', 'Loja', 'Dominio', 'Status Sync', 'Ultima Sync', 'Total Clientes (incl. deletados)', 'Soft-Deleted'],
            $rows
        );

        $this->newLine();
        $this->warn('ATENCAO: Todos os registros de synced_customers serao apagados permanentemente (hard delete)');
        $this->warn('         e um novo SyncCustomersJob sera disparado na fila "sync" com updatedSince = null.');
        $this->newLine();
    }

    /**
     * Processa a ressincronização de uma loja específica.
     *
     * Passos:
     *  1. Verifica se a loja requer reconexão OAuth (não pode sincronizar)
     *  2. Hard-delete de todos os synced_customers da loja
     *  3. Invalida o cache RFM
     *  4. Despacha SyncCustomersJob com updatedSince = null (sync completa)
     */
    private function processStore(Store $store, bool $dryRun): bool
    {
        $prefix = "[Loja #{$store->id} - {$store->name}]";

        $this->line("{$prefix} Processando...");

        // Verificação de segurança: lojas com token inválido não conseguem sincronizar
        if ($store->token_requires_reconnection) {
            $this->warn("{$prefix} PULADA - loja requer reconexao OAuth (token invalido). Reconecte a loja antes de ressincronizar.");

            return false;
        }

        // Contagem atual para log
        $totalWithTrashed = SyncedCustomer::withTrashed()
            ->where('store_id', $store->id)
            ->count();

        $this->line("{$prefix} Registros encontrados (incluindo soft-deleted): {$totalWithTrashed}");

        if ($dryRun) {
            $this->comment("{$prefix} [DRY-RUN] Hard-delete de {$totalWithTrashed} registros seria executado.");
            $this->comment("{$prefix} [DRY-RUN] SyncCustomersJob seria disparado com updatedSince = null.");
            $this->newLine();

            return true;
        }

        // Hard-delete de todos os registros de clientes da loja (incluindo soft-deleted)
        $deleted = SyncedCustomer::withTrashed()
            ->where('store_id', $store->id)
            ->forceDelete();

        $this->line("{$prefix} {$deleted} registros apagados permanentemente (hard delete).");

        // Invalida cache RFM para que a próxima requisição recalcule com dados frescos
        try {
            app(CustomerRfmService::class)->invalidateCache($store);
            $this->line("{$prefix} Cache RFM invalidado.");
        } catch (\Exception $e) {
            // Cache pode estar indisponível - não é erro crítico
            $this->warn("{$prefix} Nao foi possivel invalidar cache RFM: {$e->getMessage()}");
        }

        // Despacha o job de sync com updatedSince = null para forçar sync completa
        SyncCustomersJob::dispatch($store, null);

        $this->info("{$prefix} SyncCustomersJob disparado (sync completa sem filtro de data).");
        $this->newLine();

        return true;
    }
}
