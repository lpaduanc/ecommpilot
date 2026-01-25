<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\PaymentStatus;
use App\Models\Store;
use App\Models\SyncedOrder;
use Carbon\Carbon;

echo "=== Testing Chat Data Query ===\n\n";

$store = Store::first();
if (!$store) {
    echo "No store found!\n";
    exit(1);
}

echo "Store: {$store->name} (ID: {$store->id})\n";
echo "----------------------------------------\n\n";

$days = 15;
$endDate = Carbon::now();
$startDate = Carbon::now()->subDays($days);

echo "Period: {$startDate->format('d/m/Y')} to {$endDate->format('d/m/Y')}\n";
echo "Period days: {$days}\n\n";

// Test original query (all orders in period)
$allOrders = SyncedOrder::where('store_id', $store->id)
    ->whereBetween('external_created_at', [$startDate, $endDate])
    ->get();

echo "All orders in period: {$allOrders->count()}\n";

// Test payment status distribution
$byStatus = $allOrders->groupBy(fn($o) => $o->payment_status?->value ?? 'null')
    ->map->count()
    ->toArray();

echo "Orders by payment status:\n";
foreach ($byStatus as $status => $count) {
    echo "  - {$status}: {$count}\n";
}
echo "\n";

// Test NEW query (paid orders only)
$paidOrders = SyncedOrder::where('store_id', $store->id)
    ->whereBetween('external_created_at', [$startDate, $endDate])
    ->where('payment_status', PaymentStatus::Paid)
    ->get();

echo "Paid orders (NEW QUERY): {$paidOrders->count()}\n";
echo "Total revenue: R$ " . number_format($paidOrders->sum('total'), 2, ',', '.') . "\n";
echo "Average ticket: R$ " . number_format($paidOrders->avg('total') ?? 0, 2, ',', '.') . "\n\n";

// Sample paid orders
if ($paidOrders->count() > 0) {
    echo "Sample paid orders (first 3):\n";
    foreach ($paidOrders->take(3) as $order) {
        echo sprintf(
            "  - Order #%s: R$ %s (Status: %s, Payment: %s, Date: %s)\n",
            $order->order_number,
            number_format($order->total, 2, ',', '.'),
            $order->status?->value ?? 'null',
            $order->payment_status?->value ?? 'null',
            $order->external_created_at->format('d/m/Y H:i')
        );
    }
}

echo "\n=== Test Complete ===\n";
