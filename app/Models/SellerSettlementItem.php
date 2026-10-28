<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerSettlementItem extends Model
{
    protected $fillable = [
        'seller_settlement_id',
        'seller_order_id',
        'commission_record_id',
        'product_subtotal',
        'admin_commission_amount',
        'seller_net_earning',
    ];

    protected function casts(): array
    {
        return [
            'product_subtotal'        => 'decimal:2',
            'admin_commission_amount' => 'decimal:2',
            'seller_net_earning'      => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class, 'seller_settlement_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function commissionRecord(): BelongsTo
    {
        return $this->belongsTo(CommissionRecord::class, 'commission_record_id');
    }
}