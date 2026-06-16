<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCampaignProduct extends Model
{
    protected $table = 'promotion_campaign_products';

    protected $fillable = [
        'promotion_campaign_id',
        'product_id',
        'promotion_price',
    ];

    protected $casts = [
        'promotion_price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            PromotionCampaign::class,
            'promotion_campaign_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}