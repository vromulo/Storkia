<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'user_address_id',
        'address_snapshot',
        'payment_method',
        'payment_status',
        'status',
        'subtotal_amount',
        'shipping_fee_total',
        'coins_used_total',
        'coins_discount_total',
        'wallet_paid_total',
        'total_amount',
        'idempotency_key',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot'     => 'array',
            'subtotal_amount'      => 'decimal:2',
            'shipping_fee_total'   => 'decimal:2',
            'coins_used_total'     => 'integer',
            'coins_discount_total' => 'decimal:2',
            'wallet_paid_total'    => 'decimal:2',
            'total_amount'         => 'decimal:2',
            'completed_at'         => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function userAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}