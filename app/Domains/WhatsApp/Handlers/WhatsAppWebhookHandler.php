<?php

namespace App\Domains\WhatsApp\Handlers;

use App\Domains\WhatsApp\Flows\AddToCartFlow;
use App\Domains\WhatsApp\Flows\CategoryProductsFlow;
use App\Domains\WhatsApp\Flows\CheckoutFlow;
use App\Domains\WhatsApp\Flows\MainMenuFlow;
use App\Domains\WhatsApp\Flows\PromotionCampaignFlow;
use App\Domains\WhatsApp\Services\ConversationStateService;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookHandler
{
    public function __construct(
        protected EvolutionService $evolution,
        protected ConversationStateService $state,
    ) {}

    public function handle(array $payload): void
    {
        $event    = $payload['event']    ?? null;
        $instance = $payload['instance'] ?? null;
        $data     = $payload['data']     ?? [];

        if ($event !== 'messages.upsert' || !$instance) return;

        $key       = $data['key']        ?? [];
        $fromMe    = $key['fromMe']      ?? false;
        $remoteJid = $key['remoteJid']   ?? null;

        if ($fromMe || !$remoteJid) return;

        $number  = str_replace('@s.whatsapp.net', '', $remoteJid);
        $message = $data['message'] ?? [];

        Log::info("Webhook | instância={$instance} número={$number}");

        $buttonId = $this->extractButtonId($message);
        $text     = $this->extractText($message);

        Log::info("Webhook | buttonId={$buttonId} texto={$text}");

        if ($buttonId) {
            $this->routeButton($instance, $number, $buttonId);
            return;
        }

        $this->routeText($instance, $number, $text);
    }

    /*
    |--------------------------------------------------------------------------
    | Extração do buttonId — todos os formatos Evolution/Baileys
    |--------------------------------------------------------------------------
    */

    private function extractButtonId(array $message): ?string
    {
        // Formato atual: templateButtonReplyMessage
        if (!empty($message['templateButtonReplyMessage']['selectedId'])) {
            return $message['templateButtonReplyMessage']['selectedId'];
        }

        // Formato legado: buttonsResponseMessage
        if (!empty($message['buttonsResponseMessage']['selectedButtonId'])) {
            return $message['buttonsResponseMessage']['selectedButtonId'];
        }

        // Formato nativeFlow (JSON com "id")
        $params = $message['interactiveResponseMessage']['nativeFlowResponseMessage']['paramsJson'] ?? null;
        if ($params) {
            $decoded = json_decode($params, true);
            if (!empty($decoded['id'])) return $decoded['id'];
        }

        // Formato lista
        if (!empty($message['listResponseMessage']['singleSelectReply']['selectedRowId'])) {
            return $message['listResponseMessage']['singleSelectReply']['selectedRowId'];
        }

        return null;
    }

    private function extractText(array $message): string
    {
        return strtolower(trim(
            $message['conversation']
            ?? $message['extendedTextMessage']['text']
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

            // ── Promoção ──────────────────────────────────────────────────────
            str_starts_with($buttonId, 'VIEW_PROMOTION_CAMPAIGN_')
                => app(PromotionCampaignFlow::class)->handle(
                    $instance, $number,
                    (int) str_replace('VIEW_PROMOTION_CAMPAIGN_', '', $buttonId)
                ),

            // ── Categoria ─────────────────────────────────────────────────────
            str_starts_with($buttonId, 'VER_CATEGORY_')
                => app(CategoryProductsFlow::class)->handle(
                    $instance, $number,
                    (int) str_replace('VER_CATEGORY_', '', $buttonId)
                ),

            // ── Adicionar produto ao carrinho ─────────────────────────────────
            str_starts_with($buttonId, 'ADD_PRODUCT_')
                => app(AddToCartFlow::class)->handle(
                    $instance, $number,
                    (int) str_replace('ADD_PRODUCT_', '', $buttonId)
                ),

            // ── Checkout ──────────────────────────────────────────────────────
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
        // Verifica se há conversa ativa em alguma etapa de checkout
        $store = Tenant::where('whatsapp_instance', $instance)->first();
        if ($store) {
            $customer = Customer::where('tenant_id', $store->id)->where('phone', $number)->first();
            if ($customer) {
                $conversation = $this->state->getConversation($store->id, $customer->id);
                if ($conversation && $conversation->step) {
                    // Está no meio de um fluxo de checkout — delega
                    app(CheckoutFlow::class)->processText($instance, $number, $text);
                    return;
                }
            }
        }

        // Sem fluxo ativo — triggers do menu
        $triggers = ['oi', 'olá', 'ola', 'opa', 'menu', 'início', 'inicio', 'começar', 'comecar', '1', ''];
        if (in_array($text, $triggers)) {
            app(MainMenuFlow::class)->handle($instance, $number);
            return;
        }

        Log::info("Texto não mapeado: '{$text}' de {$number}");
    }
}