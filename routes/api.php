<?php

use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks externos (sem autenticação de dashboard)
|--------------------------------------------------------------------------
*/

// Evolution API (WhatsApp)
Route::post('/evolution-webhook', [WebhookController::class, 'handle']);
Route::post('/webhooks/whatsapp', [WebhookController::class, 'handle']);

// MercadoPago
Route::post('/mercadopago-webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->name('api.mercadopago.webhook');
Route::post('/webhooks/mercado-pago', [MercadoPagoWebhookController::class, 'handle']);

// Integrações futuras (stub — retornam 200 OK)
Route::post('/webhooks/ifood',    fn () => response('OK', 200));
Route::post('/webhooks/rappi',    fn () => response('OK', 200));
Route::post('/webhooks/ubereats', fn () => response('OK', 200));
Route::post('/webhooks/meta',     fn () => response('OK', 200));
