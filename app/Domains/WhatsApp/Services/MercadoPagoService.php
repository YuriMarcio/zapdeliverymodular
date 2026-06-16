<?php

namespace App\Domains\WhatsApp\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    /**
     * Cria um pagamento PIX e retorna dados para exibir ao cliente no WhatsApp.
     *
     * @return array{payment_id: int, qr_code: string, qr_code_base64: string, ticket_url: string}|null
     */
    public function createPixPayment(Order $order, Customer $customer, string $accessToken): ?array
    {
        try {
            MercadoPagoConfig::setAccessToken($accessToken);
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

            $client = new PaymentClient();

            $payload = [
                'transaction_amount' => (float) $order->total,
                'description'        => "Pedido {$order->number}",
                'payment_method_id'  => 'pix',
                'payer'              => [
                    'email' => $customer->email ?? 'cliente@zapfree.app',
                    'first_name' => $customer->name ?? 'Cliente',
                ],
                'metadata' => [
                    'order_id'   => $order->id,
                    'order_number' => $order->number,
                    'tenant_id'  => $order->tenant_id,
                ],
                'notification_url' => route('api.mercadopago.webhook'),
            ];

            $payment = $client->create($payload);

            if (!$payment || $payment->status === 'rejected') {
                Log::error('MercadoPago: pagamento rejeitado', ['order' => $order->number, 'status' => $payment?->status]);
                return null;
            }

            $transactionData = $payment->point_of_interaction->transaction_data ?? null;

            return [
                'payment_id'      => $payment->id,
                'qr_code'         => $transactionData?->qr_code ?? '',
                'qr_code_base64'  => $transactionData?->qr_code_base64 ?? '',
                'ticket_url'      => $transactionData?->ticket_url ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('MercadoPago: erro ao criar PIX', [
                'order'   => $order->number,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cria uma preference e retorna o link de pagamento (fallback web).
     */
    public function createPreference(Order $order, Customer $customer, string $accessToken): ?string
    {
        try {
            MercadoPagoConfig::setAccessToken($accessToken);

            $client = new PreferenceClient();

            $items = $order->items->map(fn ($item) => [
                'title'       => $item->name,
                'quantity'    => $item->quantity,
                'unit_price'  => (float) $item->unit_price,
                'currency_id' => 'BRL',
            ])->toArray();

            $preference = $client->create([
                'items'       => $items,
                'payer'       => ['email' => $customer->email ?? 'cliente@zapfree.app'],
                'external_reference' => $order->number,
                'notification_url'   => route('api.mercadopago.webhook'),
            ]);

            return $preference->init_point ?? null;
        } catch (\Throwable $e) {
            Log::error('MercadoPago: erro ao criar preference', [
                'order'   => $order->number,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
