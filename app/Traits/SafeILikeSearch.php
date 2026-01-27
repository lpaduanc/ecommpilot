<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait para realizar buscas ILIKE de forma segura no PostgreSQL.
 *
 * Sanitiza inputs antes de usar em queries ILIKE para prevenir:
 * - SQL Injection
 * - ReDoS (Regular Expression Denial of Service)
 * - Pattern injection
 */
trait SafeILikeSearch
{
    /**
     * Realiza busca ILIKE segura em uma coluna.
     *
     * @param  Builder  $query
     * @param  string  $column  Coluna para buscar
     * @param  string  $search  Termo de busca
     * @param  string  $position  'contains', 'starts', 'ends'
     * @return Builder
     */
    public function scopeSafeILike(Builder $query, string $column, string $search, string $position = 'contains'): Builder
    {
        if (empty($search)) {
            return $query;
        }

        // Sanitizar input
        $sanitized = $this->sanitizeILikeInput($search);

        // Construir pattern baseado na posição
        $pattern = match ($position) {
            'starts' => $sanitized.'%',
            'ends' => '%'.$sanitized,
            'exact' => $sanitized,
            default => '%'.$sanitized.'%', // contains
        };

        // Usar prepared statement (safe)
        return $query->where($column, 'ILIKE', $pattern);
    }

    /**
     * Sanitiza input para uso seguro em ILIKE.
     *
     * @param  string  $value
     * @return string
     */
    protected function sanitizeILikeInput(string $value): string
    {
        // Limitar tamanho (prevenir DoS)
        $value = mb_substr($value, 0, 255);

        // Remover caracteres de controle
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

        // Escapar wildcards existentes do ILIKE (%, _)
        $value = str_replace(['%', '_'], ['\\%', '\\_'], $value);

        // Remover múltiplos espaços
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    /**
     * Busca em múltiplas colunas com ILIKE seguro.
     *
     * @param  Builder  $query
     * @param  array  $columns  Colunas para buscar
     * @param  string  $search  Termo de busca
     * @return Builder
     */
    public function scopeMultiColumnSafeILike(Builder $query, array $columns, string $search): Builder
    {
        if (empty($search) || empty($columns)) {
            return $query;
        }

        $sanitized = $this->sanitizeILikeInput($search);
        $pattern = '%'.$sanitized.'%';

        return $query->where(function ($q) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'ILIKE', $pattern);
            }
        });
    }
}
