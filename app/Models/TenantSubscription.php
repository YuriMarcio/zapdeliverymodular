<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_name',
        'gateway_subscription_id',
        'status',
        'trial_ends_at',
        'expires_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class, 'subscription_id');
    }
}
