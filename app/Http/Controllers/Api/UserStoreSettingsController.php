<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

/**
 * User Store Settings Controller
 *
 * Provides read-only access to store connection settings for authenticated users.
 *
 * Note: This controller currently returns GLOBAL settings from SystemSetting.
 * In the future, this should be refactored to support per-user/per-store connections.
 *
 * Admin-only settings (client_id, client_secret) are managed via StoreSettingsController
 * under /api/admin/settings/store routes.
 */
class UserStoreSettingsController extends Controller
{
    /**
     * Get store settings for the authenticated user.
     * Returns connection status and configuration (read-only).
     *
     * This is a temporary implementation that returns global settings.
     * In the future, this should return user-specific store connections.
     */
    public function getStoreSettings(): JsonResponse
    {
        $accessToken = SystemSetting::where('key', 'nuvemshop.access_token')->first();
        $userId = SystemSetting::get('nuvemshop.user_id');
        $scope = SystemSetting::get('nuvemshop.scope');
        $tokenType = SystemSetting::get('nuvemshop.token_type');
        $grantType = SystemSetting::get('nuvemshop.grant_type', 'authorization_code');

        $isConnected = !empty(SystemSetting::get('nuvemshop.access_token'));

        // For security, we don't expose client_id and client_secret to regular users
        return response()->json([
            'grant_type' => $grantType,
            'is_connected' => $isConnected,
            'access_token' => $accessToken?->getDisplayValue(),
            'user_id' => $userId,
            'scope' => $scope,
            'token_type' => $tokenType,
            'has_access_token' => $isConnected,
            // Frontend compatibility fields
            'clientId' => null, // Hidden from regular users
            'clientSecret' => null, // Hidden from regular users
            'isConnected' => $isConnected,
        ]);
    }

    /**
     * Get connection status.
     * Provides detailed information about the current Nuvemshop connection.
     */
    public function getConnectionStatus(): JsonResponse
    {
        $accessToken = SystemSetting::where('key', 'nuvemshop.access_token')->first();
        $userId = SystemSetting::get('nuvemshop.user_id');
        $scope = SystemSetting::get('nuvemshop.scope');
        $tokenType = SystemSetting::get('nuvemshop.token_type');

        $isConnected = !empty(SystemSetting::get('nuvemshop.access_token'));

        return response()->json([
            'is_connected' => $isConnected,
            'access_token' => $accessToken?->getDisplayValue(),
            'user_id' => $userId,
            'scope' => $scope,
            'token_type' => $tokenType,
            'has_access_token' => $isConnected,
        ]);
    }

    /**
     * Update store settings (NOT ALLOWED for regular users).
     * Regular users cannot modify global store settings.
     * This should redirect to admin routes or return 403.
     */
    public function updateStoreSettings(): JsonResponse
    {
        return response()->json([
            'message' => 'Acesso negado. Configurações da loja são gerenciadas apenas por administradores.',
            'hint' => 'Use as rotas /api/admin/settings/store se você possui permissões de administrador.',
        ], 403);
    }
}
