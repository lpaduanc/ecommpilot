<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception lançada quando o token OAuth está expirado/inválido
 * e requer reconexão manual do usuário.
 */
class TokenExpiredException extends RuntimeException
{
    /**
     * ID da loja que requer reconexão
     */
    public int $storeId;

    /**
     * Nome da loja
     */
    public string $storeName;

    public function __construct(int $storeId, string $storeName)
    {
        $this->storeId = $storeId;
        $this->storeName = $storeName;

        parent::__construct("Token OAuth expirado para loja '{$storeName}' (ID: {$storeId}). Reconexão necessária.");
    }

    /**
     * Converte para array para logging
     */
    public function context(): array
    {
        return [
            'store_id' => $this->storeId,
            'store_name' => $this->storeName,
            'requires_reconnection' => true,
        ];
    }
}
