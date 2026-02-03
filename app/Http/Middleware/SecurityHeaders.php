<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip CSP for Horizon routes (Horizon needs unsafe-eval for Vue.js)
        if ($request->is('horizon*')) {
            return $response;
        }

        $isLocal = app()->isLocal() || app()->environment('development', 'testing');
        $unsafeEval = env('CSP_UNSAFE_EVAL', $isLocal) ? " 'unsafe-eval'" : '';

        // CSP adaptativo ao ambiente
        $connectSrc = "'self' https://api.nuvemshop.com.br https://api.tiendanube.com";
        $scriptSrc = "'self' 'unsafe-inline'{$unsafeEval}";
        $styleSrc = "'self' 'unsafe-inline' https://fonts.googleapis.com";

        if ($isLocal) {
            // Permitir Vite dev server em desenvolvimento
            $viteOrigins = 'http://localhost:5173 http://127.0.0.1:5173';
            $scriptSrc .= " {$viteOrigins}";
            $styleSrc .= " {$viteOrigins}";
            $connectSrc .= ' ws://localhost:* ws://127.0.0.1:* http://localhost:* http://127.0.0.1:*';
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src {$connectSrc}",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        // Adicionar headers de segurança
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
