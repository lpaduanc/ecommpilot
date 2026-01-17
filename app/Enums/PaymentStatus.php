<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Voided = 'voided';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Paid => 'Pago',
            self::Refunded => 'Reembolsado',
            self::Voided => 'Recusado',
            self::Failed => 'Falhou',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Check if this status represents a cancelled/voided payment.
     */
    public function isCancelled(): bool
    {
        return in_array($this, [self::Cancelled, self::Voided]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
