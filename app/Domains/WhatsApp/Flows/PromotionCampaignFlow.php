<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Category;
use App\Models\PromotionCampaign;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Fluxo: usuário clicou em uma categoria → exibe produtos como carrossel
 */
class PromotionCampaignFlow
{
    public function __construct(
        protected EvolutionService $evolution,
    ) {
    }

    public function handle(string $instance, string $number): void
    {
        Log::info("PromotionCampaignFlow | Listando produtos para {$number}");

        $promotionCampaign = PromotionCampaign::where('is_active', true)->first();
        $campaignProducts = $promotionCampaign ? $promotionCampaign->products : collect();

        if ($campaignProducts->isEmpty()) {
            $this->evolution->sendText(
                $instance,
                $number,
                "😕 Nenhuma categoria disponível no momento."
            );
            return;
        }

        $cards = $campaignProducts->map(fn ($product) => [
            'title' => $product->name,
            'body' => $product->description ?? 'Explore nossos produtos',
            'footer' => '📂 Toque para ver produtos',
            'imageUrl' => $product->image_url ?? 'https://via.placeholder.com/300',
            'buttons' => [
                [
                    'type' => 'reply',
                    'displayText' => 'Ver produtos',
                    'id' => 'SELECT_PRODUCT_' . $product->id,
                ]
            ]
        ])->values()->toArray();

        $this->evolution->sendCarousel(
            $instance,
            $number,
            "🛍️ Produtos",
            $cards
        );
    }
}
