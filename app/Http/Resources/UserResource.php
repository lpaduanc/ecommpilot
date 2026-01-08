<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role?->value,
            'is_active' => $this->is_active,
            'ai_credits' => $this->ai_credits,
            'must_change_password' => $this->must_change_password,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'store' => $this->whenLoaded('activeStore', fn () => new StoreResource($this->activeStore)),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
