<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Processing => 'Processando',
            self::Completed => 'Concluída',
            self::Failed => 'Falhou',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

