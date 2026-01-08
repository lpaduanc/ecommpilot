<?php

namespace App\DTOs;

/**
 * DTO para informações básicas da loja
 */
readonly class StoreInfoDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $domain,
        public ?string $email,
        public string $platform,
        public string $syncStatus,
        public ?\DateTimeInterface $lastSyncAt,
    ) {}

    public static function fromModel(\App\Models\Store $store): self
    {
        return new self(
            id: $store->id,
            name: $store->name,
            domain: $store->domain,
            email: $store->email,
            platform: $store->platform->value,
            syncStatus: $store->sync_status->value,
            lastSyncAt: $store->last_sync_at,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'email' => $this->email,
            'platform' => $this->platform,
            'sync_status' => $this->syncStatus,
            'last_sync_at' => $this->lastSyncAt?->format('Y-m-d H:i:s'),
        ];
    }
}
