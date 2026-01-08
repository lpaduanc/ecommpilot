<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando',
            self::Paid => 'Pago',
            self::Refunded => 'Reembolsado',
            self::Failed => 'Falhou',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
