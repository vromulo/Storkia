<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CodCollection extends Model
{
    protected $fillable = [
        'collection_reference',
        'shipment_id',
        'seller_order_id',
        'delivery_assignment_id',
        'logistics_profile_id',
        'rider_id',
        'expected_amount',
        'collected_amount',
        'product_subtotal',
        'shipping_fee',
        'status',
        'collected_at',
        'remitted_at',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount'  => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'product_subtotal' => 'decimal:2',
            'shipping_fee'     => 'decimal:2',
            'collected_at'     => 'datetime',
            'remitted_at'      => 'datetime',
            'verified_at'      => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function deliveryAssignment(): BelongsTo
    {
        return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function remittanceItem(): HasOne
    {
        return $this->hasOne(CodRemittanceItem::class, 'cod_collection_id');
    }

    public function commissionRecord(): HasOne
    {
        return $this->hasOne(CommissionRecord::class, 'cod_collection_id');
    }
}