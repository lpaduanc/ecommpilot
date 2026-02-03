<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'platform' => $this->platform?->value,
            'name' => $this->name,
            'domain' => $this->domain,
            'website_url' => $this->website_url,
            'email' => $this->email,
            'user_id' => $this->external_store_id,
            'sync_status' => $this->sync_status?->value,
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
