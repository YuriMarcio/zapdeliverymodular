<?php

use App\Http\Controllers\Api\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\InstanceController as AdminInstanceController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\QuickMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

/*
|--------------------------------------------------------------------------
| Dashboard API
| CSRF desativado para api/* em bootstrap/app.php
|--------------------------------------------------------------------------
*/
Route::prefix('api')->group(function () {

    // ── Auth (público) ────────────────────────────────────────────────────
    Route::post('auth/login',   [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // ── Protegidas por JWT ────────────────────────────────────────────────
    Route::middleware('auth:api')->group(function () {

        Route::post('auth/logout',          [AuthController::class, 'logout']);
        Route::get('auth/me',               [AuthController::class, 'me']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // Pedidos
        Route::get('orders',        [OrderController::class, 'index']);
        Route::patch('orders/{id}', [OrderController::class, 'update']);

        // Conversas
        Route::get('conversations',                          [ConversationController::class, 'index']);
        Route::patch('conversations/{id}',                   [ConversationController::class, 'update']);
        Route::patch('conversations/{id}/mark-read',         [ConversationController::class, 'markRead']);
        Route::get('conversations/{id}/messages',            [ConversationController::class, 'messages']);
        Route::post('conversations/{id}/messages',           [ConversationController::class, 'sendMessage']);

        // Mensagens rápidas
        Route::get('quick-messages',         [QuickMessageController::class, 'index']);
        Route::post('quick-messages',        [QuickMessageController::class, 'store']);
        Route::put('quick-messages/{id}',    [QuickMessageController::class, 'update']);
        Route::delete('quick-messages/{id}', [QuickMessageController::class, 'destroy']);

        // Produtos
        Route::get('products',         [ProductController::class, 'index']);
        Route::post('products',        [ProductController::class, 'store']);
        Route::get('products/{id}',    [ProductController::class, 'show']);
        Route::put('products/{id}',    [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);

        // Categorias
        Route::get('categories',          [CategoryController::class, 'index']);
        Route::post('categories',         [CategoryController::class, 'store']);
        Route::delete('categories/{id}',  [CategoryController::class, 'destroy']);

        // Promoções
        Route::get('promotions',                   [PromotionController::class, 'index']);
        Route::post('promotions',                  [PromotionController::class, 'store']);
        Route::put('promotions/{id}',              [PromotionController::class, 'update']);
        Route::patch('promotions/{id}/toggle',     [PromotionController::class, 'toggle']);
        Route::delete('promotions/{id}',           [PromotionController::class, 'destroy']);

        // Financeiro — exclusivo do plano Consultoria
        Route::middleware('plan.feature:financeiro')->group(function () {
            Route::get('finance/entries',    [FinanceController::class, 'entries']);
            Route::get('finance/summary',    [FinanceController::class, 'summary']);
            Route::post('finance/withdraw',  [FinanceController::class, 'withdraw']);
        });

        // Automações (stub) — exclusivo do plano Consultoria
        Route::middleware('plan.feature:automacao')->group(function () {
            Route::get('automations',                [AutomationController::class, 'index']);
            Route::put('automations/{id}',           [AutomationController::class, 'update']);
            Route::patch('automations/{id}/toggle',  [AutomationController::class, 'toggle']);
            Route::post('automations/preview',       [AutomationController::class, 'preview']);
        });

        // Marketing (stub)
        Route::get('campaign-plans',             [CampaignController::class, 'plans']);
        Route::get('campaigns',                  [CampaignController::class, 'index']);
        Route::post('campaigns',                 [CampaignController::class, 'store']);
        Route::get('campaigns/{id}/analytics',   [CampaignController::class, 'analytics']);

        // Analytics / KPIs
        Route::get('analytics/summary',       [AnalyticsController::class, 'summary']);
        Route::get('analytics/sales',         [AnalyticsController::class, 'sales']);
        Route::get('analytics/top-products',  [AnalyticsController::class, 'topProducts']);

        // Canal WhatsApp
        Route::get('channels/whatsapp',                [ChannelController::class, 'whatsapp']);
        Route::post('channels/whatsapp/connect',       [ChannelController::class, 'connect']);
        Route::post('channels/whatsapp/disconnect',    [ChannelController::class, 'disconnect']);
        Route::post('channels/whatsapp/sync',          [ChannelController::class, 'sync']);
        Route::post('channels/whatsapp/sync-history',  [ChannelController::class, 'syncHistory']);
    });

    // ── Portal admin (super_admin) ───────────────────────────────────────
    Route::prefix('admin')->middleware(['auth:api', 'role:super_admin'])->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        Route::get('clients',                [AdminClientController::class, 'index']);
        Route::post('clients',               [AdminClientController::class, 'store']);
        Route::get('clients/{id}',           [AdminClientController::class, 'show']);
        Route::patch('clients/{id}/status',  [AdminClientController::class, 'updateStatus']);
        Route::delete('clients/{id}',        [AdminClientController::class, 'destroy']);

        Route::get('plans',           [AdminPlanController::class, 'index']);
        Route::post('plans',          [AdminPlanController::class, 'store']);
        Route::patch('plans/{id}',    [AdminPlanController::class, 'update']);
        Route::delete('plans/{id}',   [AdminPlanController::class, 'destroy']);

        Route::get('settings',  [AdminSettingController::class, 'index']);
        Route::put('settings',  [AdminSettingController::class, 'update']);

        Route::get('instances',                  [AdminInstanceController::class, 'index']);
        Route::post('instances/{id}/reconnect',  [AdminInstanceController::class, 'reconnect']);
        Route::post('instances/{id}/disconnect', [AdminInstanceController::class, 'disconnect']);
    });
});
