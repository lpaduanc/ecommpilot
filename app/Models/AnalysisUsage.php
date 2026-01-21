<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisUsage extends Model
{
    protected $table = 'analysis_usage';

    protected $fillable = [
        'user_id',
        'store_id',
        'date',
        'count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Incrementa o contador de uso.
     */
    public function incrementCount(): void
    {
        $this->increment('count');
    }

    /**
     * Obtém ou cria um registro de uso para hoje.
     */
    public static function getOrCreateForToday(int $userId, int $storeId): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $userId,
                'store_id' => $storeId,
                'date' => today(),
            ],
            ['count' => 0]
        );
    }

    /**
     * Retorna o uso de hoje para um usuário/loja.
     */
    public static function getTodayUsage(int $userId, int $storeId): int
    {
        return static::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('date', today())
            ->value('count') ?? 0;
    }
}
