<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommissionRecord extends Model
{
    protected $fillable = [
        'seller_order_id',
        'shipment_id',
        'cod_collection_id',
        'seller_profile_id',
        'logistics_profile_id',
        'rider_id',
        'product_subtotal',
        'shipping_fee',
        'total_cod_amount',
        'admin_commission_rate',
        'admin_commission_amount',
        'seller_net_earning',
        'logistics_commission_rate',
        'logistics_commission_amount',
        'rider_commission_rate',
        'rider_commission_amount',
        'logistics_operational_revenue',
        'logistics_total_earning',
        'status',
        'calculated_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'product_subtotal'              => 'decimal:2',
            'shipping_fee'                  => 'decimal:2',
            'total_cod_amount'              => 'decimal:2',
            'admin_commission_rate'         => 'decimal:2',
            'admin_commission_amount'       => 'decimal:2',
            'seller_net_earning'            => 'decimal:2',
            'logistics_commission_rate'     => 'decimal:2',
            'logistics_commission_amount'   => 'decimal:2',
            'rider_commission_rate'         => 'decimal:2',
            'rider_commission_amount'       => 'decimal:2',
            'logistics_operational_revenue' => 'decimal:2',
            'logistics_total_earning'       => 'decimal:2',
            'calculated_at'                 => 'datetime',
            'settled_at'                    => 'datetime',
        ];
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function codCollection(): BelongsTo
    {
        return $this->belongsTo(CodCollection::class, 'cod_collection_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function settlementItem(): HasOne
    {
        return $this->hasOne(SellerSettlementItem::class, 'commission_record_id');
    }
}