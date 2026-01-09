<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'shipping_status' => $this->shipping_status,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'shipping' => (float) $this->shipping,
            'total' => (float) $this->total,
            'payment_method' => $this->payment_method,
            'coupon' => $this->coupon,
            'items' => $this->items ?? [],
            'shipping_address' => $this->shipping_address,
            'external_created_at' => $this->external_created_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
