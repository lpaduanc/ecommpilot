<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillCustomersFromOrdersCommand extends Command
{
    protected $signature = 'customers:backfill-from-orders
                            {--store= : Specific store ID (optional, runs all if not provided)}';

    protected $description = 'Create synced_customers records from synced_orders for stores that have orders but no customers';

    public function handle(): int
    {
        $storeId = $this->option('store');

        $this->info('Extracting customers from orders...');

        $storeFilter = $storeId ? 'AND so.store_id = ?' : '';
        $bindings = $storeId ? [(int) $storeId] : [];

        // Get unique customers from orders that don't exist in synced_customers yet
        $customers = DB::select("
            SELECT
                so.store_id,
                so.customer_email AS email,
                MAX(so.customer_name) AS name,
                COUNT(*) AS total_orders,
                SUM(so.total) AS total_spent,
                MIN(so.external_created_at) AS first_order_at,
                MAX(so.external_created_at) AS last_order_at
            FROM synced_orders so
            WHERE so.customer_email IS NOT NULL
              AND so.customer_email != ''
              AND so.deleted_at IS NULL
              {$storeFilter}
              AND NOT EXISTS (
                  SELECT 1 FROM synced_customers sc
                  WHERE sc.email = so.customer_email
                    AND sc.store_id = so.store_id
                    AND sc.deleted_at IS NULL
              )
            GROUP BY so.store_id, so.customer_email
        ", $bindings);

        $count = count($customers);
        $this->info("Found {$count} customers to insert.");

        if ($count === 0) {
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $chunks = array_chunk($customers, 500);

        foreach ($chunks as $chunk) {
            $rows = [];
            $now = now();

            foreach ($chunk as $customer) {
                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'store_id' => $customer->store_id,
                    'external_id' => 'order-derived-' . md5($customer->email . $customer->store_id),
                    'name' => $customer->name ?? 'Desconhecido',
                    'email' => $customer->email,
                    'phone' => null,
                    'total_orders' => (int) $customer->total_orders,
                    'total_spent' => (float) $customer->total_spent,
                    'first_order_at' => $customer->first_order_at,
                    'last_order_at' => $customer->last_order_at,
                    'external_created_at' => $customer->first_order_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('synced_customers')->insert($rows);
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$count} customer(s) created.");

        return Command::SUCCESS;
    }
}
