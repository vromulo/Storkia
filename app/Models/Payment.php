<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'seller_order_id',
        'payment_reference',
        'method',
        'status',
        'product_subtotal',
        'shipping_fee',
        'total_amount',
        'collected_at',
        'remitted_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'product_subtotal' => 'decimal:2',
            'shipping_fee'     => 'decimal:2',
            'total_amount'     => 'decimal:2',
            'collected_at'     => 'datetime',
            'remitted_at'      => 'datetime',
            'settled_at'       => 'datetime',
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
}