<?php

namespace App\Domains\WhatsApp\Messages;

use Illuminate\Support\Collection;

class ProductCarouselMessage
{
    public static function build(
        Collection $products,
        int $categoryId
    ): array {

        $cards = $products->map(function ($product) {

            return [
                'body' => $product->name . ' 🍔',
                'footer' => 'R$ ' . number_format(
                    $product->price,
                    2,
                    ',',
                    '.'
                ),
                'imageUrl' => $product->image_url,
                'buttons' => [
                    [
                        'type' => 'reply',
                        'displayText' => 'Adicionar',
                        'id' => 'product_' . $product->id
                    ]
                ]
            ];
        })->toArray();

        /*
        |--------------------------------------------------------------------------
        | CARD VER MAIS
        |--------------------------------------------------------------------------
        */

        $cards[] = [
            'body' => '📦 Ver mais produtos',
            'footer' => 'Clique para carregar mais',
            'imageUrl' => 'https://cdn-icons-png.flaticon.com/512/32/32195.png',
            'buttons' => [
                [
                    'type' => 'reply',
                    'displayText' => 'Ver mais',
                    'id' => 'more_' . $categoryId
                ]
            ]
        ];

        return [
            'body' => '🍔 Produtos da categoria',
            'cards' => $cards
        ];
    }
}