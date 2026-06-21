<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'name'                      => $this->name,
            'marketingCommissionPercent' => (float) $this->marketing_commission_percent,
            'features'                  => $this->features,
            'isActive'                  => $this->is_active,
        ];
    }
}
