<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Trial = 'trial';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Expirado',
            self::Trial => 'Período de Teste',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'danger',
            self::Expired => 'warning',
            self::Trial => 'info',
        };
    }
}
