<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastMessage = $this->whenLoaded('messages', fn () => $this->messages->last());

        $customer = $this->whenLoaded('customer', fn () => $this->customer);

        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'tags'         => $this->parseTags($this->tag),
            'unread_count' => (int) $this->unread_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),

            'customer' => $customer ? [
                'id'           => $customer->id,
                'name'         => $customer->name ?? $customer->phone,
                'phone'        => $customer->phone,
                'avatar'       => $this->initials($customer->name ?? $customer->phone),
                'total_orders' => $this->resolvePaidOrders($customer)->count(),
                'avg_ticket'   => $this->whenLoaded('customer', fn () => $this->resolveAvgTicket($customer)),
                'member_since' => $customer->created_at?->format('M/Y'),
            ] : null,

            'last_message' => $lastMessage ? [
                'body'    => $lastMessage->body,
                'from_me' => (bool) $lastMessage->from_me,
                'type'    => $lastMessage->message_type,
                'created_at' => $lastMessage->created_at?->toIso8601String(),
            ] : null,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function parseTags(?string $tag): array
    {
        if (empty($tag)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $tag))));
    }

    private function initials(?string $name): string
    {
        if (!$name) return '?';
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        }
        return strtoupper(mb_substr($words[0], 0, 2));
    }

    private function resolvePaidOrders($customer)
    {
        if (!$customer->relationLoaded('orders')) {
            return collect();
        }
        return $customer->orders->where('payment_status', 'paid');
    }

    private function resolveAvgTicket($customer): float
    {
        $orders = $this->resolvePaidOrders($customer);
        if ($orders->isEmpty()) return 0.0;
        return round($orders->avg('total') ?? 0, 2);
    }
}
