<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowStep extends Model
{
    protected $fillable = [
        'tenant_id',
        'flow_id',
        'type',
        'content',
        'delay_seconds',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'json',
        'delay_seconds' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }
}
