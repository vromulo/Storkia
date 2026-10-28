<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerSettlement extends Model
{
    protected $fillable = [
        'settlement_reference',
        'logistics_profile_id',
        'seller_profile_id',
        'seller_id',
        'gross_product_subtotal',
        'admin_commission_total',
        'net_settlement_amount',
        'status',
        'processed_by',
        'settled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_product_subtotal' => 'decimal:2',
            'admin_commission_total' => 'decimal:2',
            'net_settlement_amount'  => 'decimal:2',
            'settled_at'             => 'datetime',
        ];
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SellerSettlementItem::class, 'seller_settlement_id');
    }
}