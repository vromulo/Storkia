<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'seller_order_id',
        'order_item_id',
        'product_id',
        'seller_profile_id',
        'buyer_id',
        'rating',
        'purchased_variant_label',
        'review_text',
        'review_photo_path',
        'review_photos',
        'edit_count',
        'edited_at',
        'seller_reply',
        'seller_replied_at',
        'seller_reply_edit_count',
        'seller_reply_edited_at',
        'helpful_count',
        'coins_rewarded',
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
            'helpful_count'           => 'integer',
            'coins_rewarded'          => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->review_photo_path ? asset('storage/' . ltrim($this->review_photo_path, '/')) : null;
    }
}