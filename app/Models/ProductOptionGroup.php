<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'required',
        'min_select',
        'max_select',
    ];

    protected $casts = [
        'required' => 'boolean',
        'min_select' => 'integer',
        'max_select' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'group_id');
    }
}
