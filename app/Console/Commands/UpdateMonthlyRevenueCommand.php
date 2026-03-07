<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMonthlyRevenueCommand extends Command
{
    protected $signature = 'stores:update-revenue
        {--force : Executar mesmo que hoje não seja o último dia útil do mês}
        {--store=* : IDs específicos de lojas para atualizar}';

    protected $description = 'Atualiza o faturamento mensal médio de todas as lojas (média dos últimos 3 meses, excl. Nov/Dez)';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->isLastBusinessDay()) {
            $this->info('Hoje não é o último dia útil do mês. Pulando...');

            return Command::SUCCESS;
        }

        $query = Store::query()
            ->where('sync_status', '!=', SyncStatus::Disconnected);

        $specificIds = $this->option('store');
        if (! empty($specificIds)) {
            $query->whereIn('id', $specificIds);
        }

        $stores = $query->get();
        $updated = 0;

        foreach ($stores as $store) {
            if ($store->requiresReconnection()) {
                $this->warn("Loja '{$store->name}' requer reconexão. Pulando...");

                continue;
            }

            $store->updateMonthlyRevenue();
            $updated++;

            $formatted = $store->monthly_revenue !== null
                ? 'R$ '.number_format($store->monthly_revenue, 2, ',', '.')
                : 'Sem dados';

            $this->line("Loja '{$store->name}': {$formatted}");
        }

        $this->info("Faturamento atualizado para {$updated} loja(s).");

        Log::info('Comando stores:update-revenue executado', [
            'updated' => $updated,
            'total' => $stores->count(),
        ]);

        return Command::SUCCESS;
    }

    private function isLastBusinessDay(): bool
    {
        $today = now()->timezone('America/Sao_Paulo');
        $lastDay = $today->copy()->endOfMonth()->startOfDay();

        while ($lastDay->isWeekend()) {
            $lastDay->subDay();
        }

        return $today->isSameDay($lastDay);
    }
}
