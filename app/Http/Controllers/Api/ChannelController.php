<?php

namespace App\Http\Controllers\Api;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SyncWhatsAppHistoryJob;
use App\Models\Tenant;
use App\Models\WhatsappInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelController extends Controller
{
    use ApiResponse;

    public function __construct(protected EvolutionService $evolution) {}

    public function whatsapp(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $store    = Tenant::find($tenantId);

        $instance = WhatsappInstance::where('tenant_id', $tenantId)->latest()->first();

        $latencyMs = null;

        if ($store?->whatsapp_instance) {
            $start = microtime(true);
            try {
                // Usa o endpoint de status da instância no Evolution
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'apikey' => config('services.evolution.key', env('EVOLUTION_API_KEY')),
                ])->get(rtrim(env('EVOLUTION_API_URL'), '/') . '/instance/fetchInstances');

                $latencyMs = (int) ((microtime(true) - $start) * 1000);
            } catch (\Throwable) {
                $latencyMs = null;
            }
        }

        return $this->success([
            'phone'      => $store?->phone,
            'device'     => $instance?->instance_name,
            'status'     => $instance?->status ?? ($store?->whatsapp_connected ? 'connected' : 'disconnected'),
            'qrCode'     => $this->normalizeQrCode($instance?->qrcode),
            'lastSync'   => $instance?->last_seen_at?->toIso8601String(),
            'latencyMs'  => $latencyMs,
        ]);
    }

    public function connect(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $store    = Tenant::find($tenantId);

        if (!$store) {
            return $this->error('NOT_FOUND', 'Loja não encontrada.', [], 404);
        }

        // Usa o tenant_id como nome da instância se não tiver
        $instanceName = $store->whatsapp_instance ?? 'tenant_' . $tenantId;

        // Cria a instância no Evolution se ainda não existir
        if (!$store->whatsapp_instance) {
            $this->evolution->createInstance($instanceName);
            $this->evolution->setWebhook($instanceName);
            $store->update(['whatsapp_instance' => $instanceName]);
        }

        // Busca o QR code
        $result = $this->evolution->getQrCode($instanceName);

        $qrCode = $this->normalizeQrCode(
            data_get($result, 'base64')
                ?? data_get($result, 'qrcode.base64')
                ?? data_get($result, 'code')
        );

        if (!$qrCode) {
            // Instância já está conectada
            $state = data_get($result, 'instance.state') ?? 'open';
            if ($state === 'open') {
                $this->upsertInstanceStatus($tenantId, $instanceName, 'connected');
                return $this->success(['status' => 'already_connected']);
            }

            return $this->error('QR_UNAVAILABLE', 'QR Code não disponível. Tente novamente.', [], 503);
        }

        $this->upsertInstanceStatus($tenantId, $instanceName, 'awaiting_scan', $qrCode);

        return $this->success([
            'status'    => 'awaiting_scan',
            'qr_code'   => $qrCode,  // base64 — exibir como <img src="data:image/png;base64,{qr_code}">
            'instance'  => $instanceName,
        ]);
    }

    // Evolution API às vezes já retorna o base64 prefixado como data URI
    // (data:image/png;base64,...). Normaliza para sempre devolver o base64
    // "puro", já que o frontend monta o data URI sozinho.
    private function normalizeQrCode(?string $qrCode): ?string
    {
        if ($qrCode && str_starts_with($qrCode, 'data:image')) {
            return substr($qrCode, strpos($qrCode, ',') + 1);
        }

        return $qrCode;
    }

    private function upsertInstanceStatus(string $tenantId, string $instanceName, string $status, ?string $qrcode = null): void
    {
        WhatsappInstance::updateOrCreate(
            ['tenant_id' => $tenantId, 'instance_name' => $instanceName],
            array_filter([
                'status'       => $status,
                'qrcode'       => $qrcode,
                'connected_at' => $status === 'connected' ? now() : null,
                'last_seen_at' => now(),
            ], fn ($v) => $v !== null)
        );
    }

    public function disconnect(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $store    = Tenant::find($tenantId);

        if (!$store?->whatsapp_instance) {
            return $this->error('NOT_CONFIGURED', 'Instância não configurada.', [], 422);
        }

        try {
            \Illuminate\Support\Facades\Http::withHeaders(['apikey' => env('EVOLUTION_API_KEY')])
                ->delete(rtrim(env('EVOLUTION_API_URL'), '/') . '/instance/logout/' . $store->whatsapp_instance);

            $store->update(['whatsapp_connected' => false]);
            $this->upsertInstanceStatus($tenantId, $store->whatsapp_instance, 'disconnected');
        } catch (\Throwable $e) {
            Log::warning('Falha ao desconectar WhatsApp: ' . $e->getMessage());
        }

        return $this->success(null, 'WhatsApp desconectado.');
    }

    public function syncHistory(Request $request): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $store    = Tenant::find($tenantId);

        if (!$store?->whatsapp_instance) {
            return $this->error('NOT_CONFIGURED', 'Instância WhatsApp não configurada.', [], 422);
        }

        $limit = (int) ($request->input('messages_per_chat', 50));
        $limit = max(10, min($limit, 200));

        SyncWhatsAppHistoryJob::dispatch($tenantId, $limit);

        return $this->success([
            'message'          => 'Sincronização iniciada em background.',
            'messages_per_chat' => $limit,
        ]);
    }

    public function sync(): JsonResponse
    {
        $tenantId = auth('api')->user()->tenant_id;
        $store    = Tenant::find($tenantId);

        if (!$store?->whatsapp_instance) {
            return $this->error('NOT_CONFIGURED', 'Instância WhatsApp não configurada.', [], 422);
        }

        $start = microtime(true);
        $synced = false;

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'apikey' => env('EVOLUTION_API_KEY'),
            ])->get(rtrim(env('EVOLUTION_API_URL'), '/') . '/instance/connectionState/' . $store->whatsapp_instance);

            $synced    = $response->ok();
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            if ($synced) {
                $connected = data_get($response->json(), 'instance.state') === 'open';
                $store->update(['whatsapp_connected' => $connected]);

                $this->upsertInstanceStatus($tenantId, $store->whatsapp_instance, $connected ? 'connected' : 'disconnected');
            }
        } catch (\Throwable $e) {
            Log::warning('ChannelController: falha ao sincronizar', ['error' => $e->getMessage()]);
            $latencyMs = (int) ((microtime(true) - $start) * 1000);
        }

        return $this->success([
            'synced'    => $synced,
            'latencyMs' => $latencyMs,
        ]);
    }
}
