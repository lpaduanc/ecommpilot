<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCustomerOrderDatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:backfill-order-dates
                            {--store= : Specific store ID to backfill (optional, runs all if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill first_order_at and last_order_at on synced_customers from synced_orders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeId = $this->option('store');

        $this->info('Starting backfill of customer order dates...');

        if ($storeId) {
            $this->info("Backfilling store ID: {$storeId}");
            $affected = $this->backfillForStore((int) $storeId);
        } else {
            $this->info('Backfilling all stores...');
            $affected = $this->backfillAllStores();
        }

        $this->info("Done. {$affected} customer record(s) updated.");

        return Command::SUCCESS;
    }

    /**
     * Backfill order dates for a specific store.
     */
    private function backfillForStore(int $storeId): int
    {
        return DB::affectingStatement('
            UPDATE synced_customers sc SET
                first_order_at = sub.first_order,
                last_order_at  = sub.last_order
            FROM (
                SELECT customer_email, store_id,
                       MIN(external_created_at) AS first_order,
                       MAX(external_created_at) AS last_order
                FROM synced_orders
                WHERE customer_email IS NOT NULL
                  AND store_id = ?
                GROUP BY customer_email, store_id
            ) sub
            WHERE sc.email     = sub.customer_email
              AND sc.store_id  = sub.store_id
              AND sc.store_id  = ?
              AND sc.deleted_at IS NULL
        ', [$storeId, $storeId]);
    }

    /**
     * Backfill order dates for all stores.
     */
    private function backfillAllStores(): int
    {
        return DB::affectingStatement('
            UPDATE synced_customers sc SET
                first_order_at = sub.first_order,
                last_order_at  = sub.last_order
            FROM (
                SELECT customer_email, store_id,
                       MIN(external_created_at) AS first_order,
                       MAX(external_created_at) AS last_order
                FROM synced_orders
                WHERE customer_email IS NOT NULL
                GROUP BY customer_email, store_id
            ) sub
            WHERE sc.email    = sub.customer_email
              AND sc.store_id = sub.store_id
              AND sc.deleted_at IS NULL
        ', []);
    }
}
