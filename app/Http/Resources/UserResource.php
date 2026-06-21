<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $planConfig = $this->tenant?->planConfig();

        return [
            'id'                          => $this->id,
            'name'                        => $this->name,
            'email'                       => $this->email,
            'role'                        => $this->role,
            'tenant_id'                   => $this->tenant_id,
            'plan'                        => $planConfig?->slug,
            'planName'                    => $planConfig?->name,
            'features'                    => $planConfig?->features ?? [],
            'marketingCommissionPercent'  => $planConfig ? (float) $planConfig->marketing_commission_percent : null,
            'mustChangePassword'          => (bool) $this->must_change_password,
        ];
    }
}
