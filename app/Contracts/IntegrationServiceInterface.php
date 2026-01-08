<?php

namespace App\Contracts;

use App\Models\Store;

interface IntegrationServiceInterface
{
    /**
     * Obtém a URL de autorização para a plataforma
     */
    public function getAuthorizationUrl(int $userId): string;

    /**
     * Processa o callback de autorização
     */
    public function handleCallback(string $code, int $userId): Store;

    /**
     * Sincroniza produtos da loja
     */
    public function syncProducts(Store $store): void;

    /**
     * Sincroniza pedidos da loja
     */
    public function syncOrders(Store $store): void;

    /**
     * Sincroniza clientes da loja
     */
    public function syncCustomers(Store $store): void;

    /**
     * Verifica se a integração está configurada
     */
    public function isConfigured(): bool;

    /**
     * Obtém o nome da plataforma
     */
    public function getPlatformName(): string;
}
