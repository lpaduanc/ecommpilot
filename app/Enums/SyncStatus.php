<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Completed = 'completed';
    case Failed = 'failed';
    case TokenExpired = 'token_expired';
    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Syncing => 'Sincronizando',
            self::Completed => 'Sincronizado',
            self::Failed => 'Falhou',
            self::TokenExpired => 'Token Expirado - Reconectar',
            self::Disconnected => 'Desconectada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
