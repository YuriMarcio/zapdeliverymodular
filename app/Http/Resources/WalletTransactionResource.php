<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'description'  => $this->description,
            'gross_amount' => (float) $this->gross_amount,
            'gateway_fee'  => (float) $this->gateway_fee,
            'platform_fee' => (float) $this->platform_fee,
            'net_amount'   => (float) $this->net_amount,
            'status'       => $this->status,
            'available_at' => $this->available_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
