<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappInstance extends Model
{
    protected $fillable = [
        'tenant_id',
        'instance_name',
        'status',
        'qrcode',
        'connected_at',
        'last_seen_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
