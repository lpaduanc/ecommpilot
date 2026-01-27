<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para sanitizar inputs de busca contra SQL injection e ReDoS.
 *
 * Remove caracteres potencialmente perigosos em buscas ILIKE:
 * - Wildcards excessivos (%, _)
 * - Caracteres de controle
 * - Limita tamanho do input
 */
class SanitizeSearchInput
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $searchFields = ['search', 'q', 'query', 'filter'];

        foreach ($searchFields as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $value = $request->input($field);

                // Sanitize search input
                $sanitized = $this->sanitizeSearch($value);

                // Update request with sanitized value
                $request->merge([$field => $sanitized]);
            }
        }

        return $next($request);
    }

    /**
     * Sanitiza string de busca para prevenir SQL injection e ReDoS.
     */
    private function sanitizeSearch(string $value): string
    {
        // Limitar tamanho (prevenir DoS)
        $value = mb_substr($value, 0, 255);

        // Remover caracteres de controle
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

        // Escapar caracteres especiais do PostgreSQL ILIKE
        // Não remover wildcards completamente, mas limitar
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        // Remover múltiplos espaços
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
