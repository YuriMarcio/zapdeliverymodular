<?php

namespace App\Domains\WhatsApp\Actions;

use App\Domains\WhatsApp\Flows\AddToCartFlow;
use App\Domains\WhatsApp\Flows\CartEditFlow;
use App\Domains\WhatsApp\Flows\CartSummaryFlow;
use App\Domains\WhatsApp\Flows\CategoryProductsFlow;
use App\Domains\WhatsApp\Flows\CheckoutFlow;
use App\Domains\WhatsApp\Flows\ChooseQuantityFlow;
use App\Domains\WhatsApp\Flows\MainMenuFlow;
use App\Domains\WhatsApp\Flows\PromotionCampaignFlow;
use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class HandleIncomingMessage
{
    public function __construct(
        protected ConversationStateService $state,
        protected EvolutionService $evolution,
    ) {
    }

    public function execute(array $payload): void
    {
        // Ignora eventos que não são mensagens
        if (data_get($payload, 'event') !== 'messages.upsert') {
            return;
        }

        // Ignora mensagens enviadas pelo próprio bot
        if (data_get($payload, 'data.key.fromMe')) {
            return;
        }

        $instance  = data_get($payload, 'instance');
        $remoteJid = data_get($payload, 'data.key.remoteJid');

        if (!$instance || !$remoteJid) {
            return;
        }

        $number  = str_replace('@s.whatsapp.net', '', $remoteJid);
        $message = data_get($payload, 'data.message', []);

        $buttonId = $this->extractButtonId($message);
        $text     = $this->extractText($message);

        Log::info("HandleIncomingMessage | instância={$instance} número={$number} buttonId={$buttonId} texto={$text}");

        if ($buttonId) {
            $this->routeButton($instance, $number, $buttonId);
            return;
        }

        $this->routeText($instance, $number, $text);
    }

    /*
    |--------------------------------------------------------------------------
    | Extração do buttonId — cobre todos os formatos Evolution/Baileys
    |--------------------------------------------------------------------------
    */

    private function extractButtonId(array $message): ?string
    {
        // Formato atual (templateButtonReplyMessage)
        $id = data_get($message, 'templateButtonReplyMessage.selectedId');
        if ($id) {
            return $id;
        }

        // Formato legado (buttonsResponseMessage)
        $id = data_get($message, 'buttonsResponseMessage.selectedButtonId');
        if ($id) {
            return $id;
        }

        // Formato nativeFlow (JSON com "id")
        $params = data_get($message, 'interactiveResponseMessage.nativeFlowResponseMessage.paramsJson');
        if ($params) {
            $decoded = json_decode($params, true);
            if (!empty($decoded['id'])) {
                return $decoded['id'];
            }
        }

        // Formato lista
        $id = data_get($message, 'listResponseMessage.singleSelectReply.selectedRowId');
        if ($id) {
            return $id;
        }

        return null;
    }

    private function extractText(array $message): string
    {
        return strtolower(trim(
            data_get($message, 'conversation')
            ?? data_get($message, 'extendedTextMessage.text')
            ?? ''
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Roteador de botões
    |--------------------------------------------------------------------------
    */

    private function routeButton(string $instance, string $number, string $buttonId): void
    {
        Log::info("Botão: {$buttonId}");

        match (true) {

            // ── Menu ──────────────────────────────────────────────────────────
            $buttonId === 'VIEW_MENU'
                => app(MainMenuFlow::class)->handle($instance, $number),

            // ── Promoção → carrossel de produtos ──────────────────────────────
            str_starts_with($buttonId, 'VIEW_PROMOTION_CAMPAIGN_')
                => app(PromotionCampaignFlow::class)->handle(
                    $instance,
                    $number,
                    (int) str_replace('VIEW_PROMOTION_CAMPAIGN_', '', $buttonId)
                ),

            // ── Categoria → carrossel de produtos ─────────────────────────────
            str_starts_with($buttonId, 'VER_CATEGORY_') || str_starts_with($buttonId, 'SELECT_CATEGORY_')
            => app(CategoryProductsFlow::class)->handle(
                $instance,
                $number,
                (int) preg_replace('/[^0-9]/', '', $buttonId) // Extrai apenas o número, independente do prefixo
            ),

            // ── Adicionar produto ao carrinho ─────────────────────────────────
            str_starts_with($buttonId, 'ADD_PRODUCT_')
                => app(AddToCartFlow::class)->handle(
                    $instance,
                    $number,
                    (int) str_replace('ADD_PRODUCT_', '', $buttonId)
                ),

            // ── Seletor de quantidade (novo produto) ──────────────────────────
            str_starts_with($buttonId, 'CHOOSE_QTY_')
                => app(ChooseQuantityFlow::class)->handle(
                    $instance,
                    $number,
                    (int) str_replace('CHOOSE_QTY_', '', $buttonId)
                ),

            // ── Adicionar N unidades (vindo do ChooseQuantityFlow) ────────────
            str_starts_with($buttonId, 'ADD_PROD_') && str_contains($buttonId, '_QTY_')
                => (static function () use ($instance, $number, $buttonId) {
                    preg_match('/ADD_PROD_(\d+)_QTY_(\d+)/', $buttonId, $m);
                    app(AddToCartFlow::class)->handle($instance, $number, (int) $m[1], (int) $m[2]);
                })(),

            // ── Ver carrinho ──────────────────────────────────────────────────
            $buttonId === 'VIEW_CART'
                => app(CartSummaryFlow::class)->handle($instance, $number),

            // ── Editar carrinho (carrossel de itens) ──────────────────────────
            $buttonId === 'EDIT_CART'
                => app(CartEditFlow::class)->handle($instance, $number),

            // ── Remover item do carrinho ──────────────────────────────────────
            str_starts_with($buttonId, 'REMOVE_ITEM_')
                => (function () use ($instance, $number, $buttonId) {
                    $cartItemId = (int) str_replace('REMOVE_ITEM_', '', $buttonId);
                    CartItem::find($cartItemId)?->delete();
                    app(CartSummaryFlow::class)->handle($instance, $number);
                })(),

            // ── Editar quantidade de item existente ───────────────────────────
            str_starts_with($buttonId, 'EDIT_ITEM_')
                => (function () use ($instance, $number, $buttonId) {
                    $cartItemId = (int) str_replace('EDIT_ITEM_', '', $buttonId);
                    $item = CartItem::with('product')->find($cartItemId);
                    if (!$item) return;
                    $buttons = [];
                    foreach ([1, 2, 3, 4, 5] as $qty) {
                        $buttons[] = [
                            'type'        => 'reply',
                            'displayText' => "{$qty} unidade" . ($qty > 1 ? 's' : ''),
                            'id'          => "SET_ITEM_QTY_{$cartItemId}_{$qty}",
                        ];
                    }
                    app(EvolutionService::class)->sendButtons(
                        $instance,
                        $number,
                        "✏️ Quantas unidades de *{$item->product->name}*?",
                        "Selecione a nova quantidade:",
                        '',
                        $buttons
                    );
                })(),

            // ── Setar nova quantidade de item existente ───────────────────────
            str_starts_with($buttonId, 'SET_ITEM_QTY_')
                => (function () use ($instance, $number, $buttonId) {
                    preg_match('/SET_ITEM_QTY_(\d+)_(\d+)/', $buttonId, $m);
                    $item = CartItem::find((int) $m[1]);
                    if ($item) {
                        $item->update(['quantity' => (int) $m[2]]);
                    }
                    app(CartSummaryFlow::class)->handle($instance, $number);
                })(),

            // ── Cancelar pedido e voltar ao início ────────────────────────────
            $buttonId === 'CANCEL_ORDER'
                => (function () use ($instance, $number) {
                    $store = Tenant::where('whatsapp_instance', $instance)->first();
                    if ($store) {
                        $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
                        if ($customer) {
                            $conversation = $this->state->getConversation($store->id, $customer->id);
                            if ($conversation) $this->state->clearContext($conversation);
                            $cart = $this->state->getCart($store->id, $customer->id);
                            if ($cart) $this->state->clearCart($cart);
                        }
                    }
                    app(MainMenuFlow::class)->handle($instance, $number);
                })(),

            // ── Checkout direto → Mercado Pago ────────────────────────────────
            $buttonId === 'CONFIRM_CHECKOUT'
                => app(CheckoutFlow::class)->directCheckout($instance, $number),

            // ── Checkout completo (fluxo legado com endereço) ─────────────────
            $buttonId === 'CHECKOUT'
                => app(CheckoutFlow::class)->start($instance, $number),

            $buttonId === 'SKIP_REFERENCE'
                => app(CheckoutFlow::class)->skipReference($instance, $number),

            $buttonId === 'CONFIRM_DATA'
                => app(CheckoutFlow::class)->showOrderSummary($instance, $number),

            $buttonId === 'CHANGE_ADDRESS'
                => app(CheckoutFlow::class)->changeAddress($instance, $number),

            $buttonId === 'PAY_NOW'
                => app(CheckoutFlow::class)->processPayment($instance, $number),

            $buttonId === 'EDIT_ORDER'
                => app(CheckoutFlow::class)->editOrder($instance, $number),

            // ── Não mapeado ───────────────────────────────────────────────────
            default => Log::warning("Botão não mapeado: {$buttonId}"),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Roteador de texto
    |--------------------------------------------------------------------------
    */

    private function routeText(string $instance, string $number, string $text): void
    {
        // Verifica se há fluxo de checkout ativo para este número
        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if ($store) {
            $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
            if ($customer) {
                $conversation = $this->state->getConversation($store->id, $customer->id);
                if ($conversation && $conversation->step) {
                    app(CheckoutFlow::class)->processText($instance, $number, $text);
                    return;
                }
            }
        }

        // Triggers de menu simples (sem limpar carrinho)
        $triggers = ['oi', 'olá', 'ola', 'opa', 'menu', '1', ''];
        if (in_array($text, $triggers)) {
            app(MainMenuFlow::class)->handle($instance, $number);
            return;
        }

        // Limpar sessão e voltar ao início (limpar + clear + inicio e variantes)
        if (in_array($text, ['limpar', 'clear', 'cancelar', 'reiniciar', 'inicio', 'início', 'começar', 'comecar'])) {
            if ($store ?? false) {
                $customer = $customer ?? Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
                if ($customer) {
                    $conversation = $this->state->getConversation($store->id, $customer->id);
                    if ($conversation) {
                        $this->state->clearContext($conversation);
                    }
                    $cart = $this->state->getCart($store->id, $customer->id);
                    if ($cart) {
                        $this->state->clearCart($cart);
                    }
                }
            }
            app(MainMenuFlow::class)->handle($instance, $number);
            return;
        }

        Log::info("Texto não mapeado: '{$text}' de {$number}");
    }
}
