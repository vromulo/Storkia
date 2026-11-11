<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsAdminRemittanceItem extends Model
{
    protected $fillable = [
        'logistics_admin_remittance_id',
        'cod_collection_id',
        'cod_remittance_id',
        'seller_order_id',
        'shipment_id',
        'commission_record_id',
        'cod_amount',
        'product_subtotal',
        'shipping_fee',
        'admin_commission_amount',
        'seller_net_earning',
        'logistics_earning',
        'courier_commission_amount',
        'rider_commission_amount',
    ];

    protected $casts = [
        'cod_amount'                => 'decimal:2',
        'product_subtotal'          => 'decimal:2',
        'shipping_fee'              => 'decimal:2',
        'admin_commission_amount'   => 'decimal:2',
        'seller_net_earning'        => 'decimal:2',
        'logistics_earning'         => 'decimal:2',
        'courier_commission_amount' => 'decimal:2',
        'rider_commission_amount'   => 'decimal:2',
    ];

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(LogisticsAdminRemittance::class, 'logistics_admin_remittance_id');
    }

    public function codCollection(): BelongsTo
    {
        return $this->belongsTo(CodCollection::class, 'cod_collection_id');
    }

    public function codRemittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class, 'cod_remittance_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function commissionRecord(): BelongsTo
    {
        return $this->belongsTo(CommissionRecord::class, 'commission_record_id');
    }
}