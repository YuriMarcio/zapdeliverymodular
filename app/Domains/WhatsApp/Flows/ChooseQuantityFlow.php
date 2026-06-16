<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Product;

class ChooseQuantityFlow
{
    public function __construct(
        protected EvolutionService $evolution,
    ) {}

    public function handle(string $instance, string $number, int $productId): void
    {
        $product = Product::find($productId);

        if (!$product) {
            $this->evolution->sendText($instance, $number, "😕 Produto não encontrado.");
            return;
        }

        // Criamos 3 botões. Note como o ID armazena tanto o Produto quanto a Quantidade
        $buttons = [
            [
                'type'        => 'reply',
                'displayText' => '2 unidades',
                'id'          => "ADD_PROD_{$productId}_QTY_2",
            ],
            [
                'type'        => 'reply',
                'displayText' => '3 unidades',
                'id'          => "ADD_PROD_{$productId}_QTY_3",
            ],
            [
                'type'        => 'reply',
                'displayText' => '4 unidades',
                'id'          => "ADD_PROD_{$productId}_QTY_4",
            ]
        ];

        // Certifique-se de usar o método correto da sua EvolutionService para enviar botões simples (geralmente sendButtons ou sendInteractive)
        $this->evolution->sendButtons(
            $instance,
            $number,
            "📦 Quantas unidades de *{$product->name}* você deseja adicionar ao carrinho?",
            "Selecione uma opção abaixo:", // Descrição/Body
            "Opções rápidas",              // Footer
            $buttons
        );
    }
}