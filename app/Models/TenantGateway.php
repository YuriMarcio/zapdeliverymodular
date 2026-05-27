<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantGateway extends Model
{
    protected $fillable = [
        'tenant_id',
        'provider',
        'mp_user_id',
        'access_token',
        'refresh_token',
        'public_key',
        'token_expires_at',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'token_expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
