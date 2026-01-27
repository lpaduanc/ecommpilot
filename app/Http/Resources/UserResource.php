<?php

namespace App\Http\Resources;

use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $planLimitService = app(PlanLimitService::class);

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role?->value,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'store' => $this->whenLoaded('activeStore', fn () => new StoreResource($this->activeStore)),
            'plan_limits' => $planLimitService->getUserLimits($this->resource),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
