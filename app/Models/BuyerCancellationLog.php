<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerCancellationLog extends Model
{
    protected $table = 'buyer_cancellation_logs';

    public const WINDOW_DAYS = 7;
    public const LIMIT_BY_PRODUCT = 2;
    public const LIMIT_BY_SELLER = 3;
    public const LIMIT_BY_BUYER = 5;

    protected $fillable = [
        'buyer_id',
        'seller_order_id',
        'seller_profile_id',
        'product_id',
        'cancel_reason',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }
}