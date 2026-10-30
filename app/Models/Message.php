<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'user_id',
        'receiver_id',
        'role',
        'message',
        'product_id',
        'attachment_path',
        'is_read',
    ];

    protected $appends = [
        'attachment_url',
        'product_card',
    ];

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'product_id' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->attachment_path, '/'));
    }

    public function getProductCardAttribute(): ?array
    {
        if (! $this->product_id) {
            return null;
        }

        $prod = $this->relationLoaded('product')
            ? $this->product
            : Product::withTrashed()->find($this->product_id);

        if (! $prod) {
            return null;
        }

        $firstPic = (! empty($prod->pictures) && is_array($prod->pictures))
            ? asset('storage/' . $prod->pictures[0])
            : null;

        $basePrice = (float) ($prod->price ?? 0);
        $discount = (float) ($prod->discount ?? 0);
        $effectivePrice = $discount > 0
            ? round($basePrice - ($basePrice * ($discount / 100)), 2)
            : round($basePrice, 2);

        return [
            'id'              => $prod->id,
            'name'            => $prod->name,
            'price'           => $basePrice,
            'effective_price' => $effectivePrice,
            'discount'        => $discount,
            'stock_quantity'  => (int) ($prod->stock_quantity ?? 0),
            'image_url'       => $firstPic,
            'url'             => route('product.show', $prod->id),
        ];
    }
}