<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserManagementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'is_active' => $this->is_active,
            'is_employee' => $this->parent_user_id !== null,
            'created_at' => $this->created_at?->toISOString(),
            'permissions' => $this->permissions->pluck('name')->toArray(),
            'is_first_user' => $this->parent_user_id === null,
            'assigned_stores' => $this->whenLoaded('assignedStores', function () {
                return $this->assignedStores->map(fn ($store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                ]);
            }, []),
            'store_ids' => $this->whenLoaded('assignedStores', function () {
                return $this->assignedStores->pluck('id')->toArray();
            }, []),
        ];
    }
}
