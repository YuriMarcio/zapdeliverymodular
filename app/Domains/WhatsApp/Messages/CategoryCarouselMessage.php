<?php

namespace App\Domains\WhatsApp\Messages;

use Illuminate\Support\Collection;

class CategoryCarouselMessage
{
    public static function build(
        Collection $categories
    ): array {

        return [
            'body' => '🍔 Escolha uma categoria',
            'cards' => $categories->map(function ($category) {

                return [
                    'body' => $category->name,
                    'footer' => 'Clique para visualizar',
                    'imageUrl' => $category->image_url,
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'displayText' => 'Ver produtos',
                            'id' => 'category_' . $category->id
                        ]
                    ]
                ];
            })->toArray()
        ];
    }
}