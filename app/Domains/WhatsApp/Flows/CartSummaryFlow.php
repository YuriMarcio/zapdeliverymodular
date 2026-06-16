<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class CartSummaryFlow
{
    public function __construct(
        protected EvolutionService $evolution,
        protected ConversationStateService $state,
    ) {}

    public function handle(string $instance, string $number): void
    {
        Log::info("CartSummaryFlow | {$number}");

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

        $summary = $this->state->formatCartSummary($cart);

        $this->evolution->sendButtons(
            $instance,
            $number,
            '🛒 Seu Carrinho',
            $summary,
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => '✏️ Editar carrinho',
                    'id'          => 'EDIT_CART',
                ],
                [
                    'type'        => 'reply',
                    'displayText' => '✅ Finalizar pedido',
                    'id'          => 'CONFIRM_CHECKOUT',
                ],
            ]
        );
    }
}
