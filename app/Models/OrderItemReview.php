<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemReview extends Model
{
    protected $table = 'order_item_reviews';

    protected $fillable = [
        'order_id',
        'seller_order_id',
        'order_item_id',
        'product_id',
        'seller_profile_id',
        'buyer_id',
        'rating',
        'review_text',
        'review_photo_path',
        'review_photos',
        'edit_count',
        'edited_at',
        'seller_reply',
        'seller_replied_at',
        'seller_reply_edit_count',
        'seller_reply_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'                  => 'integer',
            'review_photos'           => 'array',
            'edit_count'              => 'integer',
            'edited_at'               => 'datetime',
            'seller_replied_at'       => 'datetime',
            'seller_reply_edit_count' => 'integer',
            'seller_reply_edited_at'  => 'datetime',
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

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}