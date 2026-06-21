<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('users') ? $this->users->firstWhere('role', 'owner') : null;

        $instance = $this->relationLoaded('whatsappInstances') ? $this->whatsappInstances->sortByDesc('id')->first() : null;

        $subscription = $this->relationLoaded('subscriptions') ? $this->subscriptions->first() : null;
        $expiresAt = $subscription?->expires_at;

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'ownerName'      => $owner?->name,
            'ownerEmail'     => $owner?->email,
            'ownerPhone'     => $owner?->phone,
            'plan'           => $this->plan,
            'status'         => $this->status,
            'whatsappStatus' => $instance?->status ?? ($this->whatsapp_connected ? 'connected' : 'disconnected'),
            'createdAt'      => $this->created_at?->toIso8601String(),
            'expiresAt'      => $expiresAt?->toIso8601String(),
            'isExpiringSoon' => $expiresAt !== null && $expiresAt->between(now(), now()->addDays(15)),
            'monthlyRevenue' => $this->when(
                $this->relationLoaded('orders'),
                fn () => round($this->orders->sum('total'), 2)
            ),
        ];
    }
}
