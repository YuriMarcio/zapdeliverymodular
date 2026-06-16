<?php

namespace App\Domains\WhatsApp\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Tenant;

/**
 * Gerencia o estado da conversa (etapa atual + contexto temporário).
 * Tudo fica na tabela conversations: campo step + context (json).
 */
class ConversationStateService
{
    /*
    |--------------------------------------------------------------------------
    | Etapas possíveis
    |--------------------------------------------------------------------------
    */
    public const STEP_IDLE              = null;
    public const STEP_WAITING_EMAIL     = 'waiting_email';
    public const STEP_WAITING_ADDRESS   = 'waiting_address';
    public const STEP_WAITING_REFERENCE = 'waiting_reference';
    public const STEP_CONFIRMING_DATA   = 'confirming_data';
    public const STEP_ORDER_SUMMARY     = 'order_summary';
    public const STEP_WAITING_ITEM_NOTE = 'waiting_item_note';

    /*
    |--------------------------------------------------------------------------
    | Conversa ativa
    |--------------------------------------------------------------------------
    */

    public function getOrCreateConversation(string $tenantId, int $customerId): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'tenant_id'   => $tenantId,
                'customer_id' => $customerId,
                'status'      => 'open',
            ],
            [
                'step'    => null,
                'context' => [],
            ]
        );
    }

    public function getConversation(string $tenantId, int $customerId): ?Conversation
    {
        return Conversation::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', 'open')
            ->latest()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Step
    |--------------------------------------------------------------------------
    */

    public function setStep(Conversation $conversation, ?string $step): void
    {
        $conversation->update(['step' => $step]);
    }

    public function getStep(Conversation $conversation): ?string
    {
        return $conversation->step;
    }

    /*
    |--------------------------------------------------------------------------
    | Context (dados temporários: email coletado, endereço digitado, etc)
    |--------------------------------------------------------------------------
    */

    public function setContext(Conversation $conversation, array $data): void
    {
        $current = $conversation->context ?? [];
        $conversation->update(['context' => array_merge($current, $data)]);
        $conversation->refresh();
    }

    public function getContext(Conversation $conversation): array
    {
        return $conversation->context ?? [];
    }

    public function clearContext(Conversation $conversation): void
    {
        $conversation->update(['step' => null, 'context' => []]);
    }

    /*
    |--------------------------------------------------------------------------
    | Carrinho ativo
    |--------------------------------------------------------------------------
    */

    public function getOrCreateCart(string $tenantId, int $customerId): Cart
    {
        return Cart::firstOrCreate(
            [
                'tenant_id'   => $tenantId,
                'customer_id' => $customerId,
                'status'      => 'open',
            ]
        );
    }

    public function getCart(string $tenantId, int $customerId): ?Cart
    {
        return Cart::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('status', 'open')
            ->with('items.product')
            ->first();
    }

    public function addToCart($cart, $tenantId, $productId, $price, $quantity = 1): void
    {
        // lockForUpdate() segura as requisições ultra-rápidas numa fila para evitar duplicidade ou soma errada
        $cartItem = $cart->items()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($cartItem) {
            // Se já existe no carrinho, soma a quantidade de forma atômica
            $cartItem->increment('quantity', $quantity);
        } else {
            // Se é o primeiro clique, cria a linha.
            // CORREÇÃO: 'unit_price' em vez de 'price'
            $cart->items()->create([
                'tenant_id'  => $tenantId,
                'product_id' => $productId,
                'unit_price' => $price,
                'quantity'   => $quantity
            ]);
        }
    }
    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['status' => 'closed']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de exibição do carrinho
    |--------------------------------------------------------------------------
    */

    public function getCartTotal(Cart $cart): float
    {
        return $cart->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    public function formatCartSummary(Cart $cart): string
    {
        $cart->load('items.product');
        $lines = $cart->items->map(
            fn ($item) =>
            "• {$item->quantity}x {$item->product->name} — R$ " .
            number_format($item->unit_price * $item->quantity, 2, ',', '.')
        )->join("\n");

        $total = number_format($this->getCartTotal($cart), 2, ',', '.');

        return "🛒 *Itens adicionados:*\n{$lines}\n\n💰 *Total parcial: R$ {$total}*";
    }
}
