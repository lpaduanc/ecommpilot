<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Store;
use App\Models\SyncedCustomer;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    private array $paymentMethods = [
        'pix' => 40,
        'credit_card' => 35,
        'boleto' => 15,
        'debit_card' => 10,
    ];

    private array $shippingStatuses = [
        'pending',
        'processing',
        'shipped',
        'delivered',
    ];

    public function run(): void
    {
        $stores = Store::with(['products', 'customers'])->get();

        foreach ($stores as $store) {
            $this->createOrdersForStore($store);
        }
    }

    private function createOrdersForStore(Store $store): void
    {
        $products = $store->products->where('is_active', true);
        $customers = $store->customers;

        if ($products->isEmpty() || $customers->isEmpty()) {
            return;
        }

        // Create orders for the last 90 days with varying quantities
        for ($daysAgo = 0; $daysAgo < 90; $daysAgo++) {
            // More orders on recent days, weekends
            $ordersForDay = $this->getOrderCountForDay($daysAgo);

            for ($i = 0; $i < $ordersForDay; $i++) {
                $this->createOrder($store, $products, $customers, $daysAgo);
            }
        }
    }

    private function getOrderCountForDay(int $daysAgo): int
    {
        $date = now()->subDays($daysAgo);
        $dayOfWeek = $date->dayOfWeek;
        
        // Base orders (more recent = more orders)
        $baseOrders = max(1, 8 - ($daysAgo / 15));
        
        // Weekend boost
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $baseOrders *= 1.3;
        }
        
        // Random variation
        return rand((int) ($baseOrders * 0.5), (int) ($baseOrders * 1.5));
    }

    private function createOrder(Store $store, $products, $customers, int $daysAgo): void
    {
        $customer = $customers->random();
        $orderDate = now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));
        
        // Select 1-4 products for the order
        $numProducts = rand(1, 4);
        $orderProducts = $products->random(min($numProducts, $products->count()));
        
        $items = [];
        $subtotal = 0;

        foreach ($orderProducts as $product) {
            $quantity = rand(1, 3);
            $price = $product->price;
            $itemTotal = $price * $quantity;
            $subtotal += $itemTotal;

            $items[] = [
                'product_id' => $product->external_id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => $itemTotal,
            ];
        }

        // Calculate discount (10% of orders get a discount)
        $discount = 0;
        if (rand(1, 10) === 1) {
            $discount = round($subtotal * (rand(5, 20) / 100), 2);
        }

        // Shipping cost based on subtotal
        $shipping = $subtotal >= 299 ? 0 : rand(15, 45);

        $total = $subtotal - $discount + $shipping;

        // Determine order status based on date
        $orderStatus = $this->determineOrderStatus($daysAgo);
        $paymentStatus = $this->determinePaymentStatus($orderStatus);
        $shippingStatus = $this->determineShippingStatus($orderStatus, $daysAgo);

        SyncedOrder::create([
            'store_id' => $store->id,
            'external_id' => 'ORD' . $store->id . '-' . uniqid() . '-' . rand(10000, 99999),
            'order_number' => '#' . rand(10000, 99999),
            'status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'shipping_status' => $shippingStatus,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $total,
            'payment_method' => $this->getRandomPaymentMethod(),
            'items' => $items,
            'shipping_address' => $this->generateShippingAddress($customer->name),
            'external_created_at' => $orderDate,
        ]);
    }

    private function determineOrderStatus(int $daysAgo): OrderStatus
    {
        // Orders older than 14 days are mostly delivered
        if ($daysAgo > 14) {
            $rand = rand(1, 100);
            if ($rand <= 85) return OrderStatus::Delivered;
            if ($rand <= 95) return OrderStatus::Cancelled;
            return OrderStatus::Shipped;
        }

        // Orders 7-14 days old
        if ($daysAgo > 7) {
            $rand = rand(1, 100);
            if ($rand <= 50) return OrderStatus::Delivered;
            if ($rand <= 80) return OrderStatus::Shipped;
            if ($rand <= 90) return OrderStatus::Paid;
            if ($rand <= 95) return OrderStatus::Cancelled;
            return OrderStatus::Pending;
        }

        // Orders less than 7 days old
        $rand = rand(1, 100);
        if ($rand <= 20) return OrderStatus::Delivered;
        if ($rand <= 50) return OrderStatus::Shipped;
        if ($rand <= 75) return OrderStatus::Paid;
        if ($rand <= 90) return OrderStatus::Pending;
        return OrderStatus::Cancelled;
    }

    private function determinePaymentStatus(OrderStatus $orderStatus): PaymentStatus
    {
        return match ($orderStatus) {
            OrderStatus::Pending => rand(1, 100) <= 60 ? PaymentStatus::Pending : PaymentStatus::Paid,
            OrderStatus::Cancelled => rand(1, 100) <= 70 ? PaymentStatus::Refunded : PaymentStatus::Failed,
            default => PaymentStatus::Paid,
        };
    }

    private function determineShippingStatus(OrderStatus $orderStatus, int $daysAgo): string
    {
        return match ($orderStatus) {
            OrderStatus::Pending => 'pending',
            OrderStatus::Paid => $daysAgo > 3 ? 'processing' : 'pending',
            OrderStatus::Shipped => 'shipped',
            OrderStatus::Delivered => 'delivered',
            OrderStatus::Cancelled => 'cancelled',
        };
    }

    private function getRandomPaymentMethod(): string
    {
        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($this->paymentMethods as $method => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $method;
            }
        }

        return 'pix';
    }

    private function generateShippingAddress(string $customerName): array
    {
        $streets = [
            'Rua das Flores', 'Avenida Brasil', 'Rua São Paulo', 'Avenida Paulista',
            'Rua Amazonas', 'Rua Rio de Janeiro', 'Avenida Atlântica', 'Rua Copacabana',
            'Alameda Santos', 'Rua Augusta', 'Avenida Ipiranga', 'Rua Oscar Freire',
        ];

        $cities = [
            ['city' => 'São Paulo', 'state' => 'SP', 'zip' => '01310-' . rand(100, 999)],
            ['city' => 'Rio de Janeiro', 'state' => 'RJ', 'zip' => '20040-' . rand(100, 999)],
            ['city' => 'Belo Horizonte', 'state' => 'MG', 'zip' => '30130-' . rand(100, 999)],
            ['city' => 'Curitiba', 'state' => 'PR', 'zip' => '80010-' . rand(100, 999)],
            ['city' => 'Porto Alegre', 'state' => 'RS', 'zip' => '90010-' . rand(100, 999)],
            ['city' => 'Brasília', 'state' => 'DF', 'zip' => '70040-' . rand(100, 999)],
            ['city' => 'Salvador', 'state' => 'BA', 'zip' => '40010-' . rand(100, 999)],
            ['city' => 'Recife', 'state' => 'PE', 'zip' => '50010-' . rand(100, 999)],
        ];

        $location = $cities[array_rand($cities)];

        return [
            'name' => $customerName,
            'street' => $streets[array_rand($streets)],
            'number' => rand(1, 2000),
            'complement' => rand(1, 100) <= 30 ? 'Apto ' . rand(1, 500) : null,
            'neighborhood' => 'Centro',
            'city' => $location['city'],
            'state' => $location['state'],
            'zip_code' => $location['zip'],
            'country' => 'Brasil',
        ];
    }
}

