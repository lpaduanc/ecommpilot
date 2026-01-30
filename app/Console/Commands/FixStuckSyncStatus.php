<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixStuckSyncStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:fix-stuck
                            {store_id? : ID da loja específica (opcional)}
                            {--all : Corrigir todas as lojas travadas}
                            {--dry-run : Apenas mostrar o que seria corrigido}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige status de sincronização travado em "syncing" ou "pending"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $storeId = $this->argument('store_id');
        $fixAll = $this->option('all');
        $dryRun = $this->option('dry-run');

        if (!$storeId && !$fixAll) {
            $this->error('Você deve especificar um store_id ou usar a opção --all');
            return 1;
        }

        // Lojas com status travado há mais de 15 minutos
        $stuckThreshold = now()->subMinutes(15);

        $query = Store::whereIn('sync_status', [SyncStatus::Syncing, SyncStatus::Pending])
            ->where('updated_at', '<', $stuckThreshold);

        if ($storeId) {
            $query->where('id', $storeId);
        }

        $stuckStores = $query->get();

        if ($stuckStores->isEmpty()) {
            $this->info('Nenhuma loja com status travado encontrada.');
            return 0;
        }

        $this->info("Encontradas {$stuckStores->count()} loja(s) com status travado:");
        $this->newLine();

        foreach ($stuckStores as $store) {
            $minutesStuck = now()->diffInMinutes($store->updated_at);

            $this->line("ID: {$store->id} | {$store->name}");
            $this->line("  Status atual: {$store->sync_status->value}");
            $this->line("  Travado há: {$minutesStuck} minutos");
            $this->line("  Última atualização: {$store->updated_at->format('Y-m-d H:i:s')}");
            $this->line("  Última sync: " . ($store->last_sync_at ? $store->last_sync_at->format('Y-m-d H:i:s') : 'Nunca'));

            // Verificar se há jobs pendentes na fila
            $pendingJobs = DB::table('jobs')
                ->where('queue', 'sync')
                ->where('payload', 'like', '%"store_id":' . $store->id . '%')
                ->count();

            $this->line("  Jobs pendentes: {$pendingJobs}");

            if (!$dryRun) {
                // Verificar se a sincronização foi realmente concluída
                // Checar timestamp dos dados mais recentes
                $latestProduct = $store->products()->latest('updated_at')->first();
                $latestOrder = $store->orders()->latest('updated_at')->first();

                $latestProductTime = $latestProduct?->updated_at;
                $latestOrderTime = $latestOrder?->updated_at;

                // Determinar o timestamp mais recente entre produtos e pedidos
                $latestDataTime = null;
                if ($latestProductTime && $latestOrderTime) {
                    $latestDataTime = $latestProductTime->gt($latestOrderTime) ? $latestProductTime : $latestOrderTime;
                } elseif ($latestProductTime) {
                    $latestDataTime = $latestProductTime;
                } elseif ($latestOrderTime) {
                    $latestDataTime = $latestOrderTime;
                }

                // Se dados foram atualizados nos últimos 30 minutos, considerar sync bem-sucedida
                if ($latestDataTime && $latestDataTime->diffInMinutes(now()) < 30) {
                    $store->update([
                        'sync_status' => SyncStatus::Completed,
                        'last_sync_at' => $latestDataTime,
                    ]);
                    $this->info("  ✓ Status corrigido para: completed (dados sincronizados em {$latestDataTime->format('H:i:s')})");
                } else {
                    // Sem dados recentes - marcar como failed
                    $store->update([
                        'sync_status' => SyncStatus::Failed,
                    ]);
                    $this->warn("  ✓ Status corrigido para: failed (sem dados recentes)");
                }
            } else {
                $this->comment("  [DRY RUN] Status seria corrigido");
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->comment('Modo dry-run: nenhuma alteração foi feita. Execute sem --dry-run para aplicar as correções.');
        } else {
            $this->info("✓ {$stuckStores->count()} loja(s) corrigida(s) com sucesso!");
        }

        return 0;
    }
}
