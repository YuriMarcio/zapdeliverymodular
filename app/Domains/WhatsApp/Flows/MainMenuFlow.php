<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PromotionCampaign;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class MainMenuFlow
{
    public function __construct(
        protected EvolutionService $evolution
    ) {}

    public function handle(string $instance, string $number): void
    {
        Log::info("Enviando menu principal para {$number}");

        $client = Customer::where('phone', $number)->first();
        $store  = Tenant::where('whatsapp_instance', $instance)->first();

        if (!$store) {
            Log::error("Loja não encontrada para a instância {$instance}");
            return;
        }

        $storeName   = $store->name ?? 'nossa loja';
        $greeting    = $client ? "🍔 Bem-vindo de volta!" : "🍔 Bem-vindo ao {$storeName}!";
        $description = "Estou aqui para matar sua fome em poucos segundos 🚀";

        /*
        |--------------------------------------------------------------------------
        | Busca campanha ativa
        |--------------------------------------------------------------------------
        */
        $campaign = PromotionCampaign::where('tenant_id', $store->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->first();

        /*
        |--------------------------------------------------------------------------
        | COM campanha → imagem do banner + botões
        |--------------------------------------------------------------------------
        */
        if ($campaign) {
            Log::info("Campanha ativa encontrada: {$campaign->id}");

            $this->evolution->sendButtonsWithImage(
                $instance,
                $number,
                $greeting,
                $campaign->description ?? $description,
                $campaign->banner_url,
                [
                    [
                        'type'        => 'reply',
                        'displayText' => '🔥 Ver promoções',
                        'id'          => 'VIEW_PROMOTION_CAMPAIGN_' . $campaign->id,
                    ],
                    [
                        'type'        => 'reply',
                        'displayText' => '📖 Ver cardápio',
                        'id'          => 'VER_CATEGORY_',
                    ],
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SEM campanha → carrossel de categorias
        |--------------------------------------------------------------------------
        */
        $categories = Category::where('tenant_id', $store->id)->get();

        if ($categories->isEmpty()) {
            $this->evolution->sendText($instance, $number, "{$greeting}\n{$description}\n\n😕 Nenhuma categoria disponível no momento.");
            return;
        }

        $cards = $categories->map(fn($category) => [
            'title'    => $category->name,
            'body'     => $category->description ?? "Explore nossos pratos de {$category->name}",
            'imageUrl' => $category->image_url,
            'buttons'  => [
                [
                    'type'        => 'reply',
                    'displayText' => '📖 Ver ' . $category->name,
                    'id'          => 'VER_CATEGORY_' . $category->id,
                ]
            ],
        ])->values()->toArray();

        $this->evolution->sendCarousel(
            $instance,
            $number,
            "{$greeting}\n{$description}",
            $cards
        );

        Log::info("Menu principal enviado com sucesso para {$number}");
    }
}