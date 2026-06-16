<?php

use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/evolution-webhook', [WebhookController::class, 'handle']);

Route::post('/mercadopago-webhook', [MercadoPagoWebhookController::class, 'handle'])
    ->name('api.mercadopago.webhook');
