<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AddToCartFlow
{
    public function __construct(
        protected EvolutionService $evolution,
        protected ConversationStateService $state,
    ) {}

    // Parâmetro $quantity adicionado com padrão 1
    public function handle(string $instance, string $number, int $productId, int $quantity = 1): void
    {
        Log::info("AddToCartFlow | número={$number} produto={$productId} qtd={$quantity}");

        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if (!$store) return;

        $product = Product::find($productId);
        if (!$product) {
            $this->evolution->sendText($instance, $number, "😕 Produto não encontrado.");
            return;
        }

        // Transação: Blinda o banco contra os cliques simultâneos (Race Condition)
        $cart = DB::transaction(function () use ($store, $number, $product, $quantity) {
            // Garante customer
            $customer = Customer::firstOrCreate(
                ['tenant_id' => $store->id, 'phone' => $number],
                ['name' => 'Cliente']
            );

            // Garante o carrinho
            $cart = $this->state->getOrCreateCart($store->id, $customer->id);
            
            // Adiciona ou soma o produto passando a quantidade
            $this->state->addToCart($cart, $store->id, $product->id, $product->price, $quantity);

            return $cart;
        });

        $cart->load('items.product');

        $totalItems = $cart->items->sum('quantity');
        $noun       = $totalItems === 1 ? 'item' : 'itens';

        $this->evolution->sendButtons(
            $instance,
            $number,
            "✅ *{$quantity}x {$product->name}* adicionado!",
            "Você tem *{$totalItems} {$noun}* no carrinho.",
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => '🛒 Ver carrinho',
                    'id'          => 'VIEW_CART',
                ]
            ]
        );
    }
}