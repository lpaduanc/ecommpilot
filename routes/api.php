<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    
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
    Route::prefix('dashboard')->group(function () {
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
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('products/{id}/performance', [ProductController::class, 'performance']);

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    Route::prefix('integrations')->group(function () {
        Route::get('stores', [IntegrationController::class, 'stores']);
        Route::get('my-stores', [IntegrationController::class, 'myStores']);
        Route::post('select-store/{storeId}', [IntegrationController::class, 'selectStore']);
        Route::get('nuvemshop/connect', [IntegrationController::class, 'connectNuvemshop']);
        Route::post('stores/{storeId}/sync', [IntegrationController::class, 'sync']);
        Route::delete('stores/{storeId}', [IntegrationController::class, 'disconnect']);
    });

    /*
    |--------------------------------------------------------------------------
    | Analysis (AI)
    |--------------------------------------------------------------------------
    */
    Route::prefix('analysis')->group(function () {
        Route::get('current', [AnalysisController::class, 'current']);
        Route::post('request', [AnalysisController::class, 'request']);
        Route::get('history', [AnalysisController::class, 'history']);
        Route::get('{id}', [AnalysisController::class, 'show']);
        Route::post('{analysisId}/suggestions/{suggestionId}/done', [AnalysisController::class, 'markSuggestionDone']);
    });

    /*
    |--------------------------------------------------------------------------
    | Chat (AI)
    |--------------------------------------------------------------------------
    */
    Route::prefix('chat')->group(function () {
        Route::get('conversation', [ChatController::class, 'conversation']);
        Route::post('message', [ChatController::class, 'sendMessage']);
        Route::delete('conversation', [ChatController::class, 'clearConversation']);
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
        });
    });

    /*
    |--------------------------------------------------------------------------
    | User Settings
    |--------------------------------------------------------------------------
    */
    Route::prefix('settings')->group(function () {
        Route::get('notifications', [AuthController::class, 'getNotificationSettings']);
        Route::put('notifications', [AuthController::class, 'updateNotificationSettings']);
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

