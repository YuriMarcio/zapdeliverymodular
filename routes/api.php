<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::post('/evolution-webhook', [WebhookController::class, 'handle']);
