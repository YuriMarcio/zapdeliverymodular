<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'marketing_commission_percent',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'marketing_commission_percent' => 'decimal:2',
    ];

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? [], true);
    }
}
