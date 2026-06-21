<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WhatsappInstance;
use Illuminate\Http\JsonResponse;

class InstanceController extends Controller
{
    use ApiResponse;

    public function __construct(protected EvolutionService $evolution) {}

    /**
     * Lê da tabela whatsapp_instances (já mantida em sincronia pelo webhook
     * connection.update e pelas rotas de connect/disconnect/sync do lojista).
     * Evita fan-out de N chamadas HTTP ao Evolution a cada carregamento da tela.
     */
    public function index(): JsonResponse
    {
        $instances = WhatsappInstance::with('tenant')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn ($instance) => [
                'id'         => $instance->id,
                'clientId'   => $instance->tenant_id,
                'clientName' => $instance->tenant?->name,
                'phone'      => $instance->tenant?->phone,
                'device'     => $instance->instance_name,
                'status'     => $instance->status,
                'lastSync'   => $instance->last_seen_at?->toIso8601String(),
                'latencyMs'  => null,
            ]);

        return $this->success($instances);
    }

    public function reconnect(string $id): JsonResponse
    {
        $instance = WhatsappInstance::find($id);
        if (!$instance) {
            return $this->error('NOT_FOUND', 'Instância não encontrada.', [], 404);
        }

        $result = $this->evolution->getQrCode($instance->instance_name);

        $qrCode = data_get($result, 'base64')
               ?? data_get($result, 'qrcode.base64')
               ?? data_get($result, 'code');

        if (!$qrCode) {
            $state = data_get($result, 'instance.state');
            if ($state === 'open') {
                $instance->update(['status' => 'connected', 'last_seen_at' => now()]);
                return $this->success(['status' => 'already_connected']);
            }

            return $this->error('QR_UNAVAILABLE', 'QR Code não disponível. Tente novamente.', [], 503);
        }

        $instance->update(['status' => 'awaiting_scan', 'qrcode' => $qrCode, 'last_seen_at' => now()]);

        return $this->success([
            'status'  => 'awaiting_scan',
            'qr_code' => $qrCode,
        ]);
    }

    public function disconnect(string $id): JsonResponse
    {
        $instance = WhatsappInstance::find($id);
        if (!$instance) {
            return $this->error('NOT_FOUND', 'Instância não encontrada.', [], 404);
        }

        try {
            \Illuminate\Support\Facades\Http::withHeaders(['apikey' => env('EVOLUTION_API_KEY')])
                ->delete(rtrim(env('EVOLUTION_API_URL'), '/') . '/instance/logout/' . $instance->instance_name);
        } catch (\Throwable) {
            // segue para marcar como desconectado mesmo se a chamada falhar
        }

        $instance->update(['status' => 'disconnected']);
        Tenant::where('id', $instance->tenant_id)->update(['whatsapp_connected' => false]);

        return $this->success(null, 'Instância desconectada.');
    }
}
