<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Client;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category; 
use Illuminate\Support\Facades\Log;

class MainMenuFlow
{
    public function __construct(
        protected EvolutionService $evolution
    ) {
    }

    public function handle(
        string $instance,
        string $number
    ): void {
        Log::info("Enviando menu principal para {$number}");

        $client = Client::where('number', $number)->first();
        $store = Store::where('whatsapp_instance', $instance)->first();

        if (!$store) {
            Log::error("Loja não encontrada para a instância {$instance}");
            return;
        }

        // 1. Correção na formatação da saudação
        $storeName = $store->name ?? 'nossa loja'; 
        $greeting = $client 
            ? "🍔 Bem-vindo de volta!" 
            : "🍔 Bem-vindo ao {$storeName}!";

        $description = "Estou aqui para matar sua fome em poucos segundos 🚀";

        // 2. Verifica se existe algum produto em promoção ativo nesta loja
        $promoProduct = Product::where('store_id', $store->id)
            ->where('is_promotion', true) // Ajuste o nome da coluna do seu DB
            ->first();

        // 3. Fluxo 1: Com Promoção (Envia Imagem + Botão)
        if ($promoProduct) {
            
            $buttons = [
                [
                    "type" => "reply",
                    "displayText" => "🔥 Ver promoções",
                    "id" => "VER_PRODUCTS_PROMO"
                ],
                [
                    "type" => "reply",
                    "displayText" => "🏪 Ver cardápio",
                    "id" => "VER_MENU"
                ]
            ];

            // Supondo que você crie/tenha um método para enviar botões com anexo de mídia na Evolution API
            $this->evolution->sendMediaButtons(
                $instance,
                $number,
                $greeting,
                $description,
                "Escolha uma opção",
                $promoProduct->image_url, // URL da imagem do produto
                $buttons
            );

            return;
        }

        // 4. Fluxo 2: Sem Promoção (Envia Mensagem + Carrossel de Categorias)
        
        // Primeiro, envia a mensagem de boas-vindas
        $this->evolution->sendText(
            $instance, 
            $number, 
            "{$greeting}\n{$description}\n\nConfira nossas categorias abaixo 👇"
        );

        // Busca as categorias da loja
        $categories = Category::where('store_id', $store->id)->get();
        $carouselCards = [];

        foreach ($categories as $category) {
            // Estrutura padrão de Cards para Carrossel na Evolution API (Baileys)
            $carouselCards[] = [
                "header" => [
                    "title" => $category->name,
                    "subtitle" => "Ver itens",
                    "hasMediaAttachment" => true,
                    "imageMessage" => $category->image_url // Ajuste conforme necessário
                ],
                "body" => [
                    "text" => $category->description ?? "Explore nossos pratos de {$category->name}"
                ],
                "nativeFlowMessage" => [
                    "buttons" => [
                        [
                            "name" => "quick_reply",
                            "buttonParamsJson" => json_encode([
                                "display_text" => "Ver " . $category->name,
                                "id" => "VER_CATEGORY_" . $category->id
                            ])
                        ]
                    ]
                ]
            ];
        }

        // Chama o método para enviar o carrossel (ajuste conforme a assinatura no seu EvolutionService)
        if (!empty($carouselCards)) {
            $this->evolution->sendCarousel(
                $instance,
                $number,
                $carouselCards
            );
        }
    }
}