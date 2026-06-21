<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'banner_url'  => $this->banner_url,
            'is_active'   => (bool) $this->is_active,
            'starts_at'   => $this->starts_at?->toIso8601String(),
            'ends_at'     => $this->ends_at?->toIso8601String(),
            'products'    => $this->whenLoaded('products', fn () => $this->products->map(fn ($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'price'           => (float) $p->price,
                'promotion_price' => $p->pivot->promotion_price ? (float) $p->pivot->promotion_price : null,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
