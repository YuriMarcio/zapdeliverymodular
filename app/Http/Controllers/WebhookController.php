<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Domains\WhatsApp\Actions\HandleIncomingMessage;

class WebhookController extends Controller
{
    public function handle(
        Request $request,
        HandleIncomingMessage $handleIncomingMessage
    ) {
        $handleIncomingMessage->execute($request->all());

        return response()->json([
            'success' => true
        ]);
    }
}