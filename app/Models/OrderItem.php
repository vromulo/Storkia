<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'seller_order_id',
        'product_id',
        'product_name',
        'product_image',
        'variant_id',
        'variant_name',
        'sub_variant_id',
        'sub_variant_name',
        'unit_price',
        'discount_percent',
        'discounted_unit_price',
        'unit_weight',
        'quantity',
        'line_total',
        'line_weight',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'            => 'decimal:2',
            'discount_percent'      => 'decimal:2',
            'discounted_unit_price' => 'decimal:2',
            'unit_weight'           => 'decimal:3',
            'quantity'              => 'integer',
            'line_total'            => 'decimal:2',
            'line_weight'           => 'decimal:3',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }
}