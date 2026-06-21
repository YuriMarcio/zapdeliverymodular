<?php

namespace App\Http\Controllers;

use App\Domains\WhatsApp\Actions\HandleIncomingMessage;
use App\Jobs\SyncWhatsAppHistoryJob;
use App\Models\Tenant;
use App\Models\WhatsappInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request, HandleIncomingMessage $handleIncomingMessage)
    {
        $payload = $request->all();
        $event   = data_get($payload, 'event');

        if ($event === 'connection.update') {
            $this->handleConnectionUpdate($payload);
            return response()->json(['success' => true]);
        }

        if ($event === 'qrcode.updated') {
            $this->handleQrcodeUpdated($payload);
            return response()->json(['success' => true]);
        }

        $handleIncomingMessage->execute($payload);

        return response()->json(['success' => true]);
    }

    private function handleConnectionUpdate(array $payload): void
    {
        $instance = data_get($payload, 'instance');
        $state    = data_get($payload, 'data.state') ?? data_get($payload, 'data.instance.state');

        Log::info("connection.update | instância={$instance} estado={$state}");

        if (!$instance) return;

        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $wasConnected = (bool) $store->whatsapp_connected;

        if ($state === 'open') {
            $store->update(['whatsapp_connected' => true]);

            // Dispara sync completo na primeira vez que conecta
            if (!$wasConnected) {
                Log::info("WhatsApp conectou — disparando sync de histórico | tenant={$store->id}");

                WhatsappInstance::updateOrCreate(
                    ['tenant_id' => $store->id, 'instance_name' => $instance],
                    ['status' => 'sincronizando', 'connected_at' => now(), 'last_seen_at' => now()]
                );

                SyncWhatsAppHistoryJob::dispatch($store->id, 100);
            }
        } elseif ($state === 'close') {
            // "connecting" é um estado transitório normal durante o pareamento via QR
            // e não deve sobrescrever o status 'awaiting_scan'.
            $store->update(['whatsapp_connected' => false]);

            WhatsappInstance::updateOrCreate(
                ['tenant_id' => $store->id, 'instance_name' => $instance],
                ['status' => 'disconnected', 'last_seen_at' => now()]
            );
        }
    }

    private function handleQrcodeUpdated(array $payload): void
    {
        $instance = data_get($payload, 'instance');
        $qrCode   = data_get($payload, 'data.qrcode.base64');

        if (!$instance || !$qrCode) return;

        // Evolution API às vezes já retorna o base64 prefixado como data URI
        // (data:image/png;base64,...). Normaliza para sempre salvar o base64 "puro".
        if (str_starts_with($qrCode, 'data:image')) {
            $qrCode = substr($qrCode, strpos($qrCode, ',') + 1);
        }

        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        WhatsappInstance::updateOrCreate(
            ['tenant_id' => $store->id, 'instance_name' => $instance],
            ['status' => 'awaiting_scan', 'qrcode' => $qrCode, 'last_seen_at' => now()]
        );
    }
}
