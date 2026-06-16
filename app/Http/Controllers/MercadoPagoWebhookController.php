<?php

namespace App\Http\Controllers;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\Response
    {
        $topic  = $request->input('type') ?? $request->query('topic');
        $dataId = $request->input('data.id') ?? $request->query('id');

        Log::info('MercadoPago webhook recebido', ['topic' => $topic, 'id' => $dataId]);

        // Apenas processar notificações de pagamento
        if ($topic !== 'payment' || !$dataId) {
            return response('OK', 200);
        }

        // Buscar o pedido pelo mp_payment_id armazenado em notes
        // Formato: "mp_payment_id:12345"
        $order = Order::where('notes', 'like', "mp_payment_id:{$dataId}%")->first();

        if (!$order) {
            Log::warning('MercadoPago webhook: pedido não encontrado', ['mp_payment_id' => $dataId]);
            return response('OK', 200);
        }

        // Evitar processar duas vezes
        if ($order->payment_status === 'paid') {
            return response('OK', 200);
        }

        // Confirmar status do pagamento diretamente na API do MP
        $store   = Tenant::find($order->tenant_id);
        $gateway = $store?->gateways()->where('active', true)->first();

        if (!$gateway) {
            Log::warning('MercadoPago webhook: gateway não configurado', ['tenant' => $order->tenant_id]);
            return response('OK', 200);
        }

        $mpResponse = Http::withHeaders(['Authorization' => 'Bearer ' . $gateway->access_token])
            ->get("https://api.mercadopago.com/v1/payments/{$dataId}");

        if (!$mpResponse->ok()) {
            Log::error('MercadoPago webhook: falha ao consultar pagamento', ['id' => $dataId]);
            return response('OK', 200);
        }

        $paymentData = $mpResponse->json();
        $status      = $paymentData['status'] ?? null;

        if ($status !== 'approved') {
            Log::info('MercadoPago webhook: pagamento não aprovado', ['status' => $status, 'order' => $order->number]);
            return response('OK', 200);
        }

        // Atualizar pedido
        $order->update([
            'payment_status' => 'paid',
            'status'         => 'confirmed',
        ]);

        Log::info('MercadoPago webhook: pedido pago', ['order' => $order->number]);

        // Notificar cliente via WhatsApp
        $this->notifyCustomer($order, $store);

        return response('OK', 200);
    }

    private function notifyCustomer(Order $order, Tenant $store): void
    {
        $customer = $order->customer;
        if (!$customer || !$store->whatsapp_instance) {
            return;
        }

        try {
            $msg  = "✅ *Pagamento confirmado!*\n\n";
            $msg .= "📦 Pedido: *{$order->number}*\n";
            $msg .= "💰 Total pago: R$ " . number_format($order->total, 2, ',', '.') . "\n\n";
            $msg .= "🍽️ Seu pedido já está sendo preparado!\n";
            $msg .= "⏱ Tempo estimado: 45–55 min\n\n";
            $msg .= "Qualquer dúvida, é só chamar aqui. 😊";

            app(EvolutionService::class)->sendText(
                $store->whatsapp_instance,
                $customer->phone,
                $msg
            );
        } catch (\Throwable $e) {
            Log::error('MercadoPago webhook: erro ao notificar cliente', [
                'order'   => $order->number,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
