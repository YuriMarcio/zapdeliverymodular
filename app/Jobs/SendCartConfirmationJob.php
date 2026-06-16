<?php

namespace App\Jobs;

use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SendCartConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $instance,
        public readonly string $number,
        public readonly string $tenantId,
        public readonly int    $dispatchedAt,
    ) {}

    public function handle(): void
    {
        $cacheKey   = "cart_confirm_{$this->tenantId}_{$this->number}";
        $lastUpdate = Cache::get($cacheKey);

        // Se outro produto foi adicionado depois, o job mais recente cuida do envio
        if ((int) $lastUpdate !== $this->dispatchedAt) {
            return;
        }

        $store    = Tenant::find($this->tenantId);
        $customer = Customer::where('tenant_id', $this->tenantId)
                            ->where('phone', $this->number)
                            ->first();

        if (!$store || !$customer) {
            return;
        }

        $state = app(ConversationStateService::class);
        $cart  = $state->getCart($this->tenantId, $customer->id);

        if (!$cart || $cart->items->isEmpty()) {
            return;
        }

        $total      = $state->getCartTotal($cart);
        $totalItems = $cart->items->sum('quantity');
        $noun       = $totalItems === 1 ? 'item' : 'itens';

        app(EvolutionService::class)->sendButtons(
            $this->instance,
            $this->number,
            '🛒 Carrinho atualizado!',
            "Você tem *{$totalItems} {$noun}* no carrinho.\nTotal: *R$ " . number_format($total, 2, ',', '.') . '*',
            '',
            [
                [
                    'type'        => 'reply',
                    'displayText' => '🛒 Ver carrinho',
                    'id'          => 'VIEW_CART',
                ],
                [
                    'type'        => 'reply',
                    'displayText' => '✏️ Adicionar observação',
                    'id'          => 'EDIT_CART',
                ],
            ]
        );
    }
}
