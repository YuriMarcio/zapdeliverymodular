<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class CartEditFlow
{
    public function __construct(
        protected EvolutionService $evolution,
        protected ConversationStateService $state,
    ) {}

    public function handle(string $instance, string $number): void
    {
        Log::info("CartEditFlow | {$number}");

        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
        if (!$customer) return;

        $cart = $this->state->getCart($store->id, $customer->id);
        if (!$cart || $cart->items->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Seu carrinho está vazio. Digite *oi* para ver o cardápio.");
            return;
        }

        $cards = $cart->items->map(fn ($item) => [
            'title'    => "{$item->quantity}x {$item->product->name}",
            'body'     => "R$ " . number_format($item->unit_price * $item->quantity, 2, ',', '.'),
            'footer'   => 'Unitário: R$ ' . number_format($item->unit_price, 2, ',', '.'),
            'imageUrl' => $item->product->image_url ?? 'https://via.placeholder.com/300',
            'buttons'  => [
                [
                    'type'        => 'reply',
                    'displayText' => '🗑️ Remover',
                    'id'          => 'REMOVE_ITEM_' . $item->id,
                ],
                [
                    'type'        => 'reply',
                    'displayText' => '✏️ Editar quantidade',
                    'id'          => 'EDIT_ITEM_' . $item->id,
                ],
            ],
        ])->values()->toArray();

        $this->evolution->sendCarousel(
            $instance,
            $number,
            "✏️ *Editar carrinho*\nEscolha um item para modificar:",
            $cards
        );
    }
}
