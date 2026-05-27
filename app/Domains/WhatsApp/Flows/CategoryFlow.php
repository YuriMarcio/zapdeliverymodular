<?php

namespace App\Domains\WhatsApp\Flows;

use App\Models\Product;
use App\Domains\WhatsApp\Services\EvolutionService;
use App\Domains\WhatsApp\Messages\ProductCarouselMessage;

class CategoryFlow
{
    public function __construct(
        protected EvolutionService $evolution
    ) {}

    public function handle(
        string $instance,
        string $number,
        int $categoryId
    ): void {

        $products = Product::query()
            ->where('category_id', $categoryId)
            ->take(9)
            ->get();

        $message = ProductCarouselMessage::build(
            $products,
            $categoryId
        );

        $this->evolution->sendCarousel(
            $instance,
            $number,
            $message['body'],
            $message['cards']
        );
    }
}