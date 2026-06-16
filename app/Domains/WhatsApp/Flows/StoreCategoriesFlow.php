<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class StoreCategoriesFlow
{
    public function __construct(
        protected EvolutionService $evolution
    ) {
    }

    public function handle(string $instance, string $number): void
    {
        Log::info("Enviando carrossel de categorias para {$number}");

        // Busca a loja pela instância
        $store = Tenant::where('whatsapp_instance', $instance)->first();

        if (!$store) {
            Log::error("Loja não encontrada para a instância {$instance}");
            return;
        }

        // Busca as categorias da loja
        $categories = Category::where('tenant_id', $store->id)->get();

        if ($categories->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Nenhuma categoria disponível no momento.");
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Monta o carrossel de categorias
        |--------------------------------------------------------------------------
        */
        $carouselCards = [];

        foreach ($categories as $category) {
            $carouselCards[] = [
                "body" => $category->name,
                "footer" => $category->description ?? '',
                "imageUrl" => $category->image_url,
                "buttons" => [
                    [
                        "type" => "reply",
                        "displayText" => "Ver " . $category->name,
                        "id" => "VER_CATEGORY_" . $category->id
                    ]
                ]
            ];
        }

        // Envia o carrossel
        $this->evolution->sendCarousel(
            $instance,
            $number,
            "📁 Confira nossas categorias:",
            $carouselCards
        );

        Log::info("Carrossel de categorias enviado com sucesso para {$number}");
    }
}