<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'tenant_id',
        'wallet_id',
        'amount',
        'bank_account_details',
        'status',
        'receipt_url',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bank_account_details' => 'json',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}
