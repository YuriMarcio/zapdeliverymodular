<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'number'         => $this->number,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'subtotal'       => (float) $this->subtotal,
            'delivery_fee'   => (float) $this->delivery_fee,
            'discount'       => (float) $this->discount,
            'total'          => (float) $this->total,
            'notes'          => $this->notes,
            'address'        => $this->address,
            'customer'       => $this->whenLoaded('customer', fn () => [
                'id'    => $this->customer->id,
                'name'  => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ]),
            'items'      => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'          => $item->id,
                'name'        => $item->name,
                'quantity'    => $item->quantity,
                'unit_price'  => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'notes'       => $item->notes,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
