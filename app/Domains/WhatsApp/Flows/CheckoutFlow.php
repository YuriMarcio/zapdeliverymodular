<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Domains\WhatsApp\Services\MercadoPagoService;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Models\TenantGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fluxo de checkout — gerencia todas as etapas:
 *
 * [CHECKOUT]
 *   → tem cadastro?
 *       NÃO → pede email → pede endereço → pede referência → confirma dados
 *       SIM → mostra endereço salvo → confirmar ou alterar
 *   → resumo do pedido → pagar agora / editar
 */
class CheckoutFlow
{
    public function __construct(
        protected EvolutionService $evolution,
        protected ConversationStateService $state,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Entrada: botão CHECKOUT clicado
    |--------------------------------------------------------------------------
    */

    public function start(string $instance, string $number): void
    {
        Log::info("CheckoutFlow::start | {$number}");

        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) {
            $this->evolution->sendText($instance, $number, "😕 Carrinho não encontrado. Digite *oi* para recomeçar.");
            return;
        }

        $cart = $this->state->getCart($store->id, $customer->id);
        if (!$cart || $cart->items->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Seu carrinho está vazio. Digite *oi* para ver o cardápio.");
            return;
        }

        $conversation = $this->state->getOrCreateConversation($store->id, $customer->id);

        // Cliente tem email? (cadastro completo)
        if ($customer->email) {
            $this->askConfirmAddress($instance, $number, $customer, $conversation);
        } else {
            // Sem cadastro — coleta email primeiro
            $this->state->setStep($conversation, ConversationStateService::STEP_WAITING_EMAIL);
            $this->evolution->sendText(
                $instance,
                $number,
                "📧 Informe seu e-mail para verificarmos se já tem cadastro e receber o comprovante:"
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Processamento de texto digitado (chamado pelo WebhookHandler)
    |--------------------------------------------------------------------------
    */

    public function processText(string $instance, string $number, string $text): void
    {
        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $conversation = $this->state->getConversation($store->id, $customer->id);
        if (!$conversation) return;

        $step = $this->state->getStep($conversation);

        match ($step) {
            ConversationStateService::STEP_WAITING_EMAIL     => $this->handleEmail($instance, $number, $text, $customer, $conversation),
            ConversationStateService::STEP_WAITING_ADDRESS   => $this->handleAddress($instance, $number, $text, $customer, $conversation),
            ConversationStateService::STEP_WAITING_REFERENCE => $this->handleReference($instance, $number, $text, $customer, $conversation),
            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Etapa 1 — Email
    |--------------------------------------------------------------------------
    */

    private function handleEmail(string $instance, string $number, string $email, Customer $customer, $conversation): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->evolution->sendText($instance, $number, "❌ E-mail inválido. Por favor, informe um e-mail válido:");
            return;
        }

        // Salva email no customer
        $customer->update(['email' => $email]);

        $this->state->setContext($conversation, ['email' => $email]);
        $this->state->setStep($conversation, ConversationStateService::STEP_WAITING_ADDRESS);

        $this->evolution->sendText(
            $instance,
            $number,
            "✅ Cadastro iniciado com sucesso! Vamos seguir com os dados de entrega.\n\n" .
            "📍 Informe o endereço completo de entrega:\n_Ex: Rua das Flores, 123 - Centro, Cidade - UF_"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Etapa 2 — Endereço
    |--------------------------------------------------------------------------
    */

    private function handleAddress(string $instance, string $number, string $address, Customer $customer, $conversation): void
    {
        if (strlen($address) < 10) {
            $this->evolution->sendText($instance, $number, "❌ Endereço muito curto. Por favor, informe o endereço completo:");
            return;
        }

        $this->state->setContext($conversation, ['address_text' => $address]);
        $this->state->setStep($conversation, ConversationStateService::STEP_WAITING_REFERENCE);

        $this->evolution->sendButtons(
            $instance,
            $number,
            "📍 Tem alguma referência para ajudar na entrega?",
            "_Ex: Próximo ao mercado, portão azul_",
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => 'Pular',
                    'id'          => 'SKIP_REFERENCE',
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Etapa 3 — Referência
    |--------------------------------------------------------------------------
    */

    private function handleReference(string $instance, string $number, string $reference, Customer $customer, $conversation): void
    {
        $this->state->setContext($conversation, ['reference' => $reference]);
        $this->confirmData($instance, $number, $customer, $conversation);
    }

    /*
    |--------------------------------------------------------------------------
    | Botão: Pular referência
    |--------------------------------------------------------------------------
    */

    public function skipReference(string $instance, string $number): void
    {
        $store    = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $conversation = $this->state->getConversation($store->id, $customer->id);
        if (!$conversation) return;

        $this->state->setContext($conversation, ['reference' => '']);
        $this->confirmData($instance, $number, $customer, $conversation);
    }

    /*
    |--------------------------------------------------------------------------
    | Confirma dados coletados
    |--------------------------------------------------------------------------
    */

    private function confirmData(string $instance, string $number, Customer $customer, $conversation): void
    {
        $ctx  = $this->state->getContext($conversation);
        $name = $customer->name ?? 'Cliente';
        $email = $customer->email ?? $ctx['email'] ?? '';
        $address = $ctx['address_text'] ?? '';
        $reference = $ctx['reference'] ?? '';

        $msg  = "✅ *Confirme seus dados de entrega:*\n\n";
        $msg .= "👤 *Nome:* {$name}\n";
        $msg .= "📧 *E-mail:* {$email}\n";
        $msg .= "📍 *Endereço:* {$address}\n";
        if ($reference) $msg .= "🚀 *Referência:* {$reference}\n";
        $msg .= "\n_Para corrigir algo, basta digitar:_\n";
        $msg .= "`nome: Novo Nome`, `endereço: Rua Nova, 456` ou `referencia: portão azul`";

        $this->state->setStep($conversation, ConversationStateService::STEP_CONFIRMING_DATA);

        $this->evolution->sendButtons(
            $instance,
            $number,
            '✅ Confirme seus dados de entrega:',
            $msg,
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => '✅ Tudo certo',
                    'id'          => 'CONFIRM_DATA',
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Botão: endereço cadastrado — exibe endereços salvos
    |--------------------------------------------------------------------------
    */

    private function askConfirmAddress(string $instance, string $number, Customer $customer, $conversation): void
    {
        $addresses = $customer->addresses()->latest()->take(3)->get();

        if ($addresses->isEmpty()) {
            // Tem email mas não tem endereço
            $this->state->setStep($conversation, ConversationStateService::STEP_WAITING_ADDRESS);
            $this->evolution->sendText(
                $instance,
                $number,
                "📍 Informe o endereço completo de entrega:\n_Ex: Rua das Flores, 123 - Centro, Cidade - UF_"
            );
            return;
        }

        // Usa o último endereço
        $addr = $addresses->first();
        $addressText = "{$addr->street}, {$addr->number} - {$addr->district}, {$addr->city} - {$addr->state}, {$addr->zip_code}";
        $reference   = $addr->reference ?? '';

        $this->state->setContext($conversation, [
            'address_text'     => $addressText,
            'reference'        => $reference,
            'customer_address_id' => $addr->id,
        ]);

        $msg = "📍 *Entrega será em:*\n{$addressText}";
        if ($reference) $msg .= "\n🚀 *Referência:* {$reference}";
        $msg .= "\n\nEstá correto?";

        $this->state->setStep($conversation, ConversationStateService::STEP_CONFIRMING_DATA);

        $this->evolution->sendButtons(
            $instance,
            $number,
            '📍 Entrega será em:',
            $msg,
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => '✅ Confirmar endereço',
                    'id'          => 'CONFIRM_DATA',
                ],
                [
                    'type'        => 'reply',
                    'displayText' => '✏️ Alterar endereço',
                    'id'          => 'CHANGE_ADDRESS',
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Botão: alterar endereço
    |--------------------------------------------------------------------------
    */

    public function changeAddress(string $instance, string $number): void
    {
        $store    = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $conversation = $this->state->getConversation($store->id, $customer->id);
        if (!$conversation) return;

        $this->state->setStep($conversation, ConversationStateService::STEP_WAITING_ADDRESS);
        $this->evolution->sendText(
            $instance,
            $number,
            "📍 Informe o novo endereço completo de entrega:\n_Ex: Rua das Flores, 123 - Centro, Cidade - UF_"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Botão: CONFIRM_DATA → exibe resumo do pedido
    |--------------------------------------------------------------------------
    */

    public function showOrderSummary(string $instance, string $number): void
    {
        $store    = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $cart = $this->state->getCart($store->id, $customer->id);
        if (!$cart || $cart->items->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Carrinho vazio. Digite *oi* para recomeçar.");
            return;
        }

        $conversation = $this->state->getConversation($store->id, $customer->id);
        $ctx          = $this->state->getContext($conversation);

        $subtotal    = $this->state->getCartTotal($cart);
        $deliveryFee = (float) ($store->delivery_fee ?? 0);
        $total       = $subtotal + $deliveryFee;

        $items = $cart->items->map(fn($i) =>
            "• {$i->quantity}x *{$i->product->name}* — R$ " . number_format($i->unit_price * $i->quantity, 2, ',', '.')
        )->join("\n");

        $address   = $ctx['address_text'] ?? '';
        $reference = $ctx['reference']    ?? '';

        $msg  = "📋 *Resumo do seu pedido:*\n\n";
        $msg .= "{$items}\n\n";
        $msg .= "🧾 *Subtotal:* R$ " . number_format($subtotal, 2, ',', '.') . "\n";
        $msg .= "🚚 *Taxa de entrega:* R$ " . number_format($deliveryFee, 2, ',', '.') . "\n";
        $msg .= "💰 *Total:* R$ " . number_format($total, 2, ',', '.') . "\n\n";
        $msg .= "📍 *Entrega em:*\n{$address}";
        if ($reference) $msg .= "\n🚀 {$reference}";
        $msg .= "\n\n⏱ *Tempo estimado:* 45–55 min\n\n";
        $msg .= "_Para alterar algo antes de pagar, basta digitar:_\n";
        $msg .= "`nome: Novo Nome`, `endereço: Rua Nova, 456` ou `referencia: portão azul`";

        $this->state->setStep($conversation, ConversationStateService::STEP_ORDER_SUMMARY);

        $this->evolution->sendButtons(
            $instance,
            $number,
            '📋 Resumo do seu pedido:',
            $msg,
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => '💳 Pagar agora',
                    'id'          => 'PAY_NOW',
                ],
                [
                    'type'        => 'reply',
                    'displayText' => '✏️ Editar pedido',
                    'id'          => 'EDIT_ORDER',
                ]
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Botão: PAY_NOW → gera pedido + link de pagamento
    |--------------------------------------------------------------------------
    */

    public function processPayment(string $instance, string $number): void
    {
        $store    = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $cart = $this->state->getCart($store->id, $customer->id);
        if (!$cart || $cart->items->isEmpty()) return;

        $conversation = $this->state->getConversation($store->id, $customer->id);
        $ctx          = $this->state->getContext($conversation);

        $subtotal    = $this->state->getCartTotal($cart);
        $deliveryFee = (float) ($store->delivery_fee ?? 0);
        $total       = $subtotal + $deliveryFee;
        $orderNumber = 'ZAP-' . strtoupper(Str::random(10));

        // Cria o pedido
        $order = Order::create([
            'tenant_id'      => $store->id,
            'customer_id'    => $customer->id,
            'number'         => $orderNumber,
            'status'         => 'pending',
            'payment_method' => 'pix',
            'payment_status' => 'pending',
            'subtotal'       => $subtotal,
            'delivery_fee'   => $deliveryFee,
            'discount'       => 0,
            'total'          => $total,
            'address'        => [
                'street'    => $ctx['address_text'] ?? '',
                'reference' => $ctx['reference']    ?? '',
            ],
        ]);

        // Cria os itens do pedido
        foreach ($cart->items as $item) {
            OrderItem::create([
                'tenant_id'  => $store->id,
                'order_id'   => $order->id,
                'name'       => $item->product->name,
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->unit_price * $item->quantity,
                'notes'      => $item->notes,
            ]);
        }

        // Fecha o carrinho
        $this->state->clearCart($cart);
        $this->state->clearContext($conversation);

        $this->sendPaymentMessage($instance, $number, $store, $order, $customer, $total, $orderNumber);
    }

    /*
    |--------------------------------------------------------------------------
    | Checkout direto → cria pedido e envia link Mercado Pago sem coletar endereço
    |--------------------------------------------------------------------------
    */

    public function directCheckout(string $instance, string $number): void
    {
        Log::info("CheckoutFlow::directCheckout | {$number}");

        $store    = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $cart = $this->state->getCart($store->id, $customer->id);
        if (!$cart || $cart->items->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Carrinho vazio. Digite *oi* para ver o cardápio.");
            return;
        }

        $conversation = $this->state->getConversation($store->id, $customer->id);
        $ctx          = $conversation ? $this->state->getContext($conversation) : [];

        $subtotal    = $this->state->getCartTotal($cart);
        $deliveryFee = (float) ($store->delivery_fee ?? 0);
        $total       = $subtotal + $deliveryFee;
        $orderNumber = 'ZAP-' . strtoupper(Str::random(10));

        $order = Order::create([
            'tenant_id'      => $store->id,
            'customer_id'    => $customer->id,
            'number'         => $orderNumber,
            'status'         => 'pending',
            'payment_method' => 'pix',
            'payment_status' => 'pending',
            'subtotal'       => $subtotal,
            'delivery_fee'   => $deliveryFee,
            'discount'       => 0,
            'total'          => $total,
            'address'        => [
                'street'    => $ctx['address_text'] ?? '',
                'reference' => $ctx['reference']    ?? '',
            ],
        ]);

        foreach ($cart->items as $item) {
            OrderItem::create([
                'tenant_id'   => $store->id,
                'order_id'    => $order->id,
                'name'        => $item->product->name,
                'product_id'  => $item->product_id,
                'quantity'    => $item->quantity,
                'unit_price'  => $item->unit_price,
                'total_price' => $item->unit_price * $item->quantity,
                'notes'       => $item->notes,
            ]);
        }

        $this->state->clearCart($cart);
        if ($conversation) {
            $this->state->clearContext($conversation);
        }

        $this->sendPaymentMessage($instance, $number, $store, $order, $customer, $total, $orderNumber);
    }

    /*
    |--------------------------------------------------------------------------
    | Botão: EDIT_ORDER → volta ao carrinho
    |--------------------------------------------------------------------------
    */

    public function editOrder(string $instance, string $number): void
    {
        $store    = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $conversation = $this->state->getConversation($store->id, $customer->id);
        if ($conversation) {
            $this->state->setStep($conversation, null);
        }

        app(MainMenuFlow::class)->handle($instance, $number);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolução do link/PIX de pagamento
    |--------------------------------------------------------------------------
    */

    private function resolvePaymentUrl(Tenant $store, Order $order, Customer $customer): string
    {
        return url("/pay/{$order->number}");
    }

    /*
    |--------------------------------------------------------------------------
    | Envia mensagem de pagamento (PIX ou link)
    |--------------------------------------------------------------------------
    */

    private function sendPaymentMessage(
        string   $instance,
        string   $number,
        Tenant   $store,
        Order    $order,
        Customer $customer,
        float    $total,
        string   $orderNumber
    ): void {
        $gateway = TenantGateway::where('tenant_id', $store->id)
                                ->where('active', true)
                                ->first();

        if ($gateway && $gateway->access_token) {
            $mp   = app(MercadoPagoService::class);
            $pix  = $mp->createPixPayment($order, $customer, $gateway->access_token);

            if ($pix) {
                // Salva o ID do pagamento MP no pedido para o webhook
                $order->update(['notes' => 'mp_payment_id:' . $pix['payment_id']]);

                $header = "✅ Pedido *{$orderNumber}* confirmado!\n💰 Total: R$ " . number_format($total, 2, ',', '.');

                // Envia QR code como imagem se disponível
                if ($pix['qr_code_base64']) {
                    $this->evolution->sendImage(
                        $instance,
                        $number,
                        'data:image/png;base64,' . $pix['qr_code_base64'],
                        "📱 Escaneie o QR code para pagar via PIX\n{$header}"
                    );
                }

                // Envia código PIX copia e cola
                if ($pix['qr_code']) {
                    $this->evolution->sendText(
                        $instance,
                        $number,
                        "📋 *PIX Copia e Cola:*\n```\n{$pix['qr_code']}\n```\n\nAssim que o pagamento for confirmado, você receberá uma mensagem aqui. ✅"
                    );
                }

                return;
            }
        }

        // Fallback: link de pagamento web
        $paymentUrl = $this->resolvePaymentUrl($store, $order, $customer);

        $msg  = "Tudo certo com o seu pedido! ✅\n\n";
        $msg .= "📦 *Pedido:* {$orderNumber}\n";
        $msg .= "💰 *Total a pagar:* R$ " . number_format($total, 2, ',', '.') . "\n\n";
        $msg .= "🔒 Aceitamos PIX ou Cartão em ambiente seguro.\n\n";
        $msg .= "Clique no botão abaixo para pagar. Assim que confirmado, te aviso aqui! 👇";

        $this->evolution->sendButtons(
            $instance,
            $number,
            'Tudo certo com o seu pedido! ✅',
            $msg,
            '',
            [
                [
                    'type'        => 'url',
                    'displayText' => '💳 Pagar no Mercado Pago',
                    'url'         => $paymentUrl,
                ]
            ]
        );
    }
}