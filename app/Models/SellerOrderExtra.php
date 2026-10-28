<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOrderExtra extends Model
{
    protected $table = 'seller_order_extras';

    protected $fillable = [
        'order_id',
        'seller_order_id',
        'buyer_note',
        'store_voucher_id',
        'store_voucher_code',
        'store_voucher_discount',
        'platform_voucher_id',
        'platform_voucher_code',
        'platform_voucher_discount',
        'shipping_voucher_id',
        'shipping_voucher_code',
        'shipping_voucher_discount',
        'original_subtotal_amount',
        'original_shipping_fee',
        'buyer_cancel_reason',
        'buyer_cancel_notes',
        'cancelled_by_buyer_id',
        'dispute_evidence_photos',
        'dispute_reason_category',
        'seller_return_decision',
        'seller_return_Decision_reason',
        'seller_return_responded_at',
        'is_escalated_to_admin',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'store_voucher_discount'     => 'decimal:2',
            'platform_voucher_discount'  => 'decimal:2',
            'shipping_voucher_discount'  => 'decimal:2',
            'original_subtotal_amount'   => 'decimal:2',
            'original_shipping_fee'      => 'decimal:2',
            'dispute_evidence_photos'    => 'array',
            'seller_return_responded_at' => 'datetime',
            'is_escalated_to_admin'      => 'boolean',
            'escalated_at'               => 'datetime',
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

    public function storeVoucher(): BelongsTo
    {
        return $this->belongsTo(StorkVoucher::class, 'store_voucher_id');
    }

    public function platformVoucher(): BelongsTo
    {
        return $this->belongsTo(StorkVoucher::class, 'platform_voucher_id');
    }

    public function shippingVoucher(): BelongsTo
    {
        return $this->belongsTo(StorkVoucher::class, 'shipping_voucher_id');
    }
}