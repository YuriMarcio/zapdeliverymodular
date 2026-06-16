<?php

namespace App\Domains\WhatsApp\Flows;

use App\Domains\WhatsApp\Services\EvolutionService;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Fluxo Híbrido: 
 * 1. Exibe carrossel de categorias (se não houver ID).
 * 2. Exibe carrossel de produtos (se o ID da categoria for fornecido).
 */
class CategoryProductsFlow
{
    public function __construct(
        protected EvolutionService $evolution,
    ) {}

    // Alteramos a assinatura para aceitar o $categoryId como nulo (opcional)
    public function handle(string $instance, string $number, ?int $categoryId = null): void
    {
        if ($categoryId) {
            // Se o ID da categoria chegou, envia os PRODUTOS daquela categoria
            $this->sendProducts($instance, $number, $categoryId);
        } else {
            // Se não tem ID, envia as CATEGORIAS
            $this->sendCategories($instance, $number);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Envia o Carrossel de Categorias
    |--------------------------------------------------------------------------
    */
    private function sendCategories(string $instance, string $number): void
    {
        Log::info("CategoryProductsFlow | Listando categorias para {$number}");

        $categories = Category::where('active', true)->get();

        if ($categories->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Nenhuma categoria disponível no momento.");
            return;
        }

        $cards = $categories->map(fn ($category) => [
            'title'    => $category->name,
            'body'     => $category->description ?? 'Explore nossos produtos',
            'footer'   => '📂 Toque para ver',
            'imageUrl' => $category->image_url ?? 'https://via.placeholder.com/300',
            'buttons'  => [
                [
                    'type'        => 'reply',
                    'displayText' => 'Ver produtos',
                    'id'          => 'SELECT_CATEGORY_' . $category->id,
                ]
            ]
        ])->values()->toArray();

        $this->evolution->sendCarousel(
            $instance,
            $number,
            "🛍️ *Catálogo de Categorias*\nEscolha uma opção abaixo:",
            $cards
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Envia o Carrossel de Produtos de uma Categoria Específica
    |--------------------------------------------------------------------------
    */
    private function sendProducts(string $instance, string $number, int $categoryId): void
    {
        Log::info("CategoryProductsFlow | Listando produtos da categoria {$categoryId} para {$number}");

        $category = Category::find($categoryId);
        
        if (!$category) {
            $this->evolution->sendText($instance, $number, "😕 Categoria não encontrada. Digite *oi* para recomeçar.");
            return;
        }

        // Dica: verifique se a sua coluna no banco de dados é 'active' ou 'is_active'
        $products = Product::where('category_id', $categoryId)
            ->where('is_active', true) 
            ->get();

        if ($products->isEmpty()) {
            $this->evolution->sendText($instance, $number, "😕 Nenhum produto disponível nesta categoria no momento.");
            return;
        }

        $cards = $products->map(function ($product) {
            $hasPromo = !is_null($product->promotion_price);
            $footer   = $hasPromo
                ? 'R$ ' . number_format($product->price, 2, ',', '.') . ' ➡️ 🔥 R$ ' . number_format($product->promotion_price, 2, ',', '.')
                : '💰 R$ ' . number_format($product->price, 2, ',', '.');

            return [
            'title'    => $product->name,
            'body'     => $product->description ?? $product->name,
            'footer'   => $footer,
            'imageUrl' => $product->image_url ?? 'https://via.placeholder.com/300',
            'buttons'  => [
                [
                    'type'        => 'reply',
                    'displayText' => '🤤 Quero um desse!',
                    'id'          => 'ADD_PRODUCT_' . $product->id,
                ],
                [
                    'type'        => 'reply',
                    'displayText' => '🔢 Quero mais de um',
                    'id'          => 'CHOOSE_QTY_' . $product->id,
                ]
            ]
        ]; })->values()->toArray();

        $this->evolution->sendCarousel(
            $instance,
            $number,
            "📖 *{$category->name}*\nVeja o que temos para você:",
            $cards
        );
    }
}