<?php

namespace App\Enums;

enum NotificationType: string
{
    case Sync = 'sync';
    case Analysis = 'analysis';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Sync => 'Sincronização',
            self::Analysis => 'Análise',
            self::Email => 'E-mail',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sync => 'refresh',
            self::Analysis => 'chart-bar',
            self::Email => 'envelope',
        };
    }
}
