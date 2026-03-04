<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => preg_replace('/^\d{8,}\s+/', '', $this->name ?? ''),
            'email' => $this->email,
            'phone' => $this->phone,
            'total_orders' => $this->total_orders,
            'total_spent' => (float) $this->total_spent,
            'average_order_value' => $this->averageOrderValue(),
            'first_order_at' => $this->first_order_at?->toISOString(),
            'last_order_at' => $this->last_order_at?->toISOString(),
            'days_without_purchase' => $this->days_without_purchase,
            'external_created_at' => $this->external_created_at?->toISOString(),
            'rfm_segment' => $this->resource->rfm_segment ?? null,
            'rfm_scores' => $this->resource->rfm_scores ?? null,
            // Indicates whether order metrics were computed from synced_orders (true)
            // or are the original values from the Nuvemshop API sync (false/null).
            'orders_from_real_data' => (bool) ($this->resource->has_real_orders ?? false),
        ];
    }
}
