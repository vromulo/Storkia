<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
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
        'total_amount',
        'idempotency_key',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot'   => 'array',
            'subtotal_amount'    => 'decimal:2',
            'shipping_fee_total' => 'decimal:2',
            'total_amount'       => 'decimal:2',
            'completed_at'       => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function userAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class, 'order_id')->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id')->orderBy('id', 'asc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->orderBy('id', 'asc');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id')->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->latest('id');
    }
}