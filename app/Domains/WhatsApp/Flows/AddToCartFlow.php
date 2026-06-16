<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Jobs\SendCartConfirmationJob;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            
            // Adiciona ou soma o produto passando a quantidade (usa preço promocional se houver)
            $price = $product->promotion_price ?? $product->price;
            $this->state->addToCart($cart, $store->id, $product->id, $price, $quantity);

            return $cart;
        });

        // Debounce: armazena timestamp e despacha job com delay de 4 segundos.
        // Se o cliente clicar em vários produtos rapidamente, apenas o último job envia a mensagem.
        $timestamp = now()->timestamp;
        Cache::put("cart_confirm_{$store->id}_{$number}", $timestamp, 30);

        SendCartConfirmationJob::dispatch($instance, $number, $store->id, $timestamp)
            ->delay(now()->addSeconds(4));
    }
}