<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'external_id' => $this->external_id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => (float) $this->value,
            'valid' => $this->valid,
            'used' => $this->used,
            'max_uses' => $this->max_uses,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'min_price' => $this->min_price ? (float) $this->min_price : null,
            'categories' => $this->categories ?? [],
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'has_reached_max_uses' => $this->hasReachedMaxUses(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Include analytics if available
            'analytics' => $this->when(isset($this->analytics), $this->analytics),
        ];
    }
}
