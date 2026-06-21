<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'body'         => $this->body,
            'from_me'      => (bool) $this->from_me,
            'message_type' => $this->message_type,
            'media_url'    => $this->media_url,
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
