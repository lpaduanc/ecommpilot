<?php

use App\Http\Controllers\Api\AdminAnalysesController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminEmailConfigurationController;
use App\Http\Controllers\Api\AdminIntegrationsController;
use App\Http\Controllers\Api\AdminPlanController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreConfigController;
use App\Http\Controllers\Api\StoreSettingsController;
use App\Http\Controllers\Api\TrackingSettingsController;
use App\Http\Controllers\Api\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Rate limiting para prevenir brute force (5 tentativas por minuto)
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'updatePassword']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    Route::prefix('dashboard')->middleware('can:dashboard.view')->group(function () {
        Route::get('bulk', [DashboardController::class, 'bulk']); // Bulk endpoint for all dashboard data
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('charts/revenue', [DashboardController::class, 'revenueChart']);
        Route::get('charts/orders-status', [DashboardController::class, 'ordersStatusChart']);
        Route::get('charts/top-products', [DashboardController::class, 'topProducts']);
        Route::get('charts/payment-methods', [DashboardController::class, 'paymentMethodsChart']);
        Route::get('charts/categories', [DashboardController::class, 'categoriesChart']);
        Route::get('low-stock', [DashboardController::class, 'lowStock']);
    });

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->middleware('can:products.view')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::get('/{id}/performance', [ProductController::class, 'performance']);
    });

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->middleware('can:orders.view')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/filters', [OrderController::class, 'filters']);
        Route::get('/export', [OrderController::class, 'export']);
        Route::get('/stats', [OrderController::class, 'stats']);
        Route::get('/{id}', [OrderController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Locations (Brazil States and Cities)
    |--------------------------------------------------------------------------
    */
    Route::prefix('locations')->group(function () {
        Route::get('states', [LocationController::class, 'states']);
        Route::get('cities/{uf}', [LocationController::class, 'cities']);
    });

    /*
    |--------------------------------------------------------------------------
    | Discounts / Coupons
    |--------------------------------------------------------------------------
    */
    Route::prefix('discounts')->middleware('can:marketing.access')->group(function () {
        Route::get('/bulk', [DiscountController::class, 'bulk']); // Single request for all data
        Route::get('/', [DiscountController::class, 'index']);
        Route::get('/stats', [DiscountController::class, 'stats']);
        Route::get('/filters', [DiscountController::class, 'filters']);
    });

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    Route::prefix('integrations')->group(function () {
        // Visualização de lojas - qualquer usuário autenticado
        Route::get('stores', [IntegrationController::class, 'stores']);
        Route::get('my-stores', [IntegrationController::class, 'myStores']);
        Route::get('sync-status', [IntegrationController::class, 'syncStatus']);
        Route::post('select-store/{storeId}', [IntegrationController::class, 'selectStore']);

        // Gerenciamento de integrações - requer permissão
        Route::middleware('can:integrations.manage')->group(function () {
            Route::get('nuvemshop/connect', [IntegrationController::class, 'connectNuvemshop']);
            Route::post('nuvemshop/authorize', [IntegrationController::class, 'authorizeNuvemshop']);
            Route::post('stores/{storeId}/sync', [IntegrationController::class, 'sync']);
            Route::delete('stores/{storeId}', [IntegrationController::class, 'disconnect']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Analysis (AI)
    |--------------------------------------------------------------------------
    */
    Route::prefix('analysis')->group(function () {
        // Visualização de análises - requer permissão
        Route::middleware('can:analysis.view')->group(function () {
            Route::get('current', [AnalysisController::class, 'current']);
            Route::get('history', [AnalysisController::class, 'history']);
            Route::get('{id}', [AnalysisController::class, 'show']);
            Route::post('{id}/resend-email', [AnalysisController::class, 'resendEmail']);
        });

        // Solicitar nova análise - requer permissão específica
        Route::post('request', [AnalysisController::class, 'request'])->middleware('can:analysis.request');

        // Marcar sugestão como feita (legacy) - requer permissão de visualização
        Route::post('{analysisId}/suggestions/{suggestionId}/done', [AnalysisController::class, 'markSuggestionDone'])
            ->middleware('can:analysis.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Suggestions (Persistent)
    |--------------------------------------------------------------------------
    */
    Route::prefix('suggestions')->middleware('can:analysis.view')->group(function () {
        Route::get('/', [AnalysisController::class, 'suggestions']);
        Route::get('/stats', [AnalysisController::class, 'suggestionStats']);
        Route::get('/{id}', [AnalysisController::class, 'showSuggestion']);
        Route::patch('/{id}', [AnalysisController::class, 'updateSuggestion']);
        Route::post('/{id}/feedback', [AnalysisController::class, 'submitFeedback']); // V4: Feedback loop
    });

    /*
    |--------------------------------------------------------------------------
    | Chat (AI)
    |--------------------------------------------------------------------------
    */
    Route::prefix('chat')->middleware('can:chat.use')->group(function () {
        Route::get('conversation', [ChatController::class, 'conversation']);
        // Rate limit: 20 mensagens por minuto por usuário
        Route::post('message', [ChatController::class, 'sendMessage'])->middleware('throttle:20,1');
        Route::delete('conversation', [ChatController::class, 'clearConversation']);
    });

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('users')->middleware('can:users.view')->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::get('/permissions', [UserManagementController::class, 'permissions']);
        Route::post('/', [UserManagementController::class, 'store'])->middleware('can:users.create');
        Route::get('/{id}', [UserManagementController::class, 'show']);
        Route::put('/{id}', [UserManagementController::class, 'update'])->middleware('can:users.edit');
        Route::delete('/{id}', [UserManagementController::class, 'destroy'])->middleware('can:users.delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('can:admin.access')->group(function () {
        Route::get('stats', [AdminController::class, 'dashboardStats']);
        Route::get('clients', [AdminController::class, 'clients']);
        Route::post('clients', [AdminController::class, 'createClient']);
        Route::get('clients/permissions', [AdminController::class, 'clientPermissions']);
        Route::get('clients/{id}', [AdminController::class, 'clientDetail']);
        Route::put('clients/{id}', [AdminController::class, 'updateClient']);
        Route::delete('clients/{id}', [AdminController::class, 'deleteClient']);
        Route::post('clients/{id}/toggle-status', [AdminController::class, 'toggleClientStatus']);
        Route::post('clients/{id}/add-credits', [AdminController::class, 'addCredits']);
        Route::post('clients/{id}/remove-credits', [AdminController::class, 'removeCredits']);
        Route::post('clients/{id}/reset-password', [AdminController::class, 'resetPassword']);
        Route::post('clients/{id}/impersonate', [AdminController::class, 'impersonate']);

        // Admin Settings
        Route::prefix('settings')->group(function () {
            Route::get('ai', [AdminSettingsController::class, 'getAISettings']);
            Route::put('ai', [AdminSettingsController::class, 'updateAISettings']);
            Route::post('ai/test', [AdminSettingsController::class, 'testAIProvider']);

            // Analysis Format Settings
            Route::get('analysis-format', [AdminSettingsController::class, 'getAnalysisFormatSettings']);
            Route::put('analysis-format', [AdminSettingsController::class, 'updateAnalysisFormatSettings']);

            // Email Configurations
            Route::prefix('email')->group(function () {
                Route::get('/', [AdminEmailConfigurationController::class, 'index']);
                Route::post('/', [AdminEmailConfigurationController::class, 'store']);
                Route::get('/{id}', [AdminEmailConfigurationController::class, 'show']);
                Route::put('/{id}', [AdminEmailConfigurationController::class, 'update']);
                Route::delete('/{id}', [AdminEmailConfigurationController::class, 'destroy']);
                Route::post('/{id}/test', [AdminEmailConfigurationController::class, 'test']);
            });

            // Store/Nuvemshop Settings
            Route::prefix('store')->group(function () {
                Route::get('/', [StoreSettingsController::class, 'getStoreSettings']);
                Route::put('/', [StoreSettingsController::class, 'updateStoreSettings']);
                Route::get('connection', [StoreSettingsController::class, 'getConnectionStatus']);
                Route::post('exchange-token', [StoreSettingsController::class, 'exchangeToken']);
                Route::post('test-connection', [StoreSettingsController::class, 'testConnection']);
                Route::post('disconnect', [StoreSettingsController::class, 'disconnect']);
            });
        });

        // Brazil Locations Sync
        Route::prefix('locations')->group(function () {
            Route::post('sync', [LocationController::class, 'sync']);
            Route::get('sync-status', [LocationController::class, 'syncStatus']);
        });

        // Plans Management
        Route::prefix('plans')->group(function () {
            Route::get('/', [AdminPlanController::class, 'index']);
            Route::post('/', [AdminPlanController::class, 'store']);
            Route::get('/{id}', [AdminPlanController::class, 'show']);
            Route::put('/{id}', [AdminPlanController::class, 'update']);
            Route::delete('/{id}', [AdminPlanController::class, 'destroy']);
            Route::post('/{id}/assign', [AdminPlanController::class, 'assignToClient']);
        });

        // Client Plan Management
        Route::get('clients/{id}/usage', [AdminPlanController::class, 'clientUsage']);
        Route::get('clients/{id}/subscription', [AdminPlanController::class, 'clientSubscription']);
        Route::delete('clients/{id}/subscription', [AdminPlanController::class, 'removeFromClient']);
        Route::get('clients-with-plans', [AdminPlanController::class, 'clientsWithPlans']);

        // Integrations (External Data Services)
        Route::prefix('integrations')->group(function () {
            Route::get('external-data', [AdminIntegrationsController::class, 'getExternalData']);
            Route::put('external-data', [AdminIntegrationsController::class, 'updateExternalData']);
            Route::post('external-data/test', [AdminIntegrationsController::class, 'testExternalData']);
            Route::post('external-data/test-decodo', [AdminIntegrationsController::class, 'testDecodo']);
        });

        // Analyses Management
        Route::prefix('analyses')->group(function () {
            Route::get('/', [AdminAnalysesController::class, 'index']);
            Route::get('/stats', [AdminAnalysesController::class, 'stats']);
            Route::get('/{analysis}', [AdminAnalysesController::class, 'show']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Store Configuration
    |--------------------------------------------------------------------------
    */
    Route::get('niches', [StoreConfigController::class, 'getNiches']);
    Route::prefix('stores/{store}')->group(function () {
        Route::get('config', [StoreConfigController::class, 'show']);
        Route::put('config', [StoreConfigController::class, 'update'])->middleware('can:integrations.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | User Settings
    |--------------------------------------------------------------------------
    */
    Route::prefix('settings')->group(function () {
        // Visualização de configurações - requer permissão
        Route::middleware('can:settings.view')->group(function () {
            Route::get('notifications', [AuthController::class, 'getNotificationSettings']);
        });

        // Edição de configurações - requer permissão
        Route::middleware('can:settings.edit')->group(function () {
            Route::put('notifications', [AuthController::class, 'updateNotificationSettings']);
        });

        // Store/Nuvemshop Settings - requer permissão de integrações
        Route::prefix('store')->group(function () {
            Route::get('/', [StoreSettingsController::class, 'getUserStoreSettings']);
            Route::get('connection', [StoreSettingsController::class, 'getConnectionStatus']);

            // Ações que modificam configurações da loja
            Route::middleware('can:integrations.manage')->group(function () {
                Route::put('/', [StoreSettingsController::class, 'updateUserStoreSettings']);
                Route::post('exchange-token', [StoreSettingsController::class, 'exchangeToken']);
                Route::post('test-connection', [StoreSettingsController::class, 'testConnection']);
                Route::post('disconnect', [StoreSettingsController::class, 'disconnect']);
            });
        });

        // Tracking Settings (por loja)
        Route::prefix('tracking')->group(function () {
            Route::get('/', [TrackingSettingsController::class, 'show']);  // Config para frontend
            Route::get('/edit', [TrackingSettingsController::class, 'edit']); // Config completa para edição
            Route::put('/', [TrackingSettingsController::class, 'update'])->middleware('can:settings.edit');
            Route::patch('/{provider}', [TrackingSettingsController::class, 'updateProvider'])->middleware('can:settings.edit');
        });
    });

});

/*
|--------------------------------------------------------------------------
| Webhook Routes (No Auth)
|--------------------------------------------------------------------------
*/
Route::prefix('integrations')->group(function () {
    Route::get('nuvemshop/callback', [IntegrationController::class, 'callbackNuvemshop']);
});
