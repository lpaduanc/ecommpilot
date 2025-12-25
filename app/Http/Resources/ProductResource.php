<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price ? (float) $this->compare_at_price : null,
            'stock_quantity' => $this->stock_quantity,
            'sku' => $this->sku,
            'images' => $this->images ?? [],
            'categories' => $this->categories ?? [],
            'variants' => $this->variants ?? [],
            'is_active' => $this->is_active,
            'has_low_stock' => $this->hasLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
            'has_discount' => $this->hasDiscount(),
            'discount_percentage' => $this->discountPercentage(),
            'external_created_at' => $this->external_created_at?->toISOString(),
            'external_updated_at' => $this->external_updated_at?->toISOString(),
        ];
    }
}

