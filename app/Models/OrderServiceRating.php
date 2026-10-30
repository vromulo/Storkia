<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderServiceRating extends Model
{
    protected $table = 'order_service_ratings';

    protected $fillable = [
        'order_id',
        'seller_order_id',
        'buyer_id',
        'seller_profile_id',
        'logistics_profile_id',
        'rider_id',
        'product_quality_rating',
        'parcel_condition_rating',
        'seller_service_rating',
        'logistics_hub_rating',
        'rider_delivery_rating',
        'overall_average_rating',
        'review_comment',
        'review_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'product_quality_rating'  => 'integer',
            'parcel_condition_rating' => 'integer',
            'seller_service_rating'   => 'integer',
            'logistics_hub_rating'    => 'integer',
            'rider_delivery_rating'   => 'integer',
            'overall_average_rating'  => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }
}