<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerRtsClaim extends Model
{
    protected $table = 'seller_rts_claims';

    protected $fillable = [
        'seller_order_id',
        'shipment_id',
        'seller_profile_id',
        'logistics_profile_id',
        'buyer_id',
        'abandonment_reason',
        'failed_attempts_count',
        'rts_status',
        'seller_received_parcel',
        'seller_confirmed_received_at',
        'is_damaged_or_lost',
        'claimed_product_expense',
        'claim_description',
        'evidence_photo_path',
        'reimbursement_status',
        'resolved_by_admin_id',
        'admin_resolution_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_attempts_count'        => 'integer',
            'seller_received_parcel'       => 'boolean',
            'seller_confirmed_received_at' => 'datetime',
            'is_damaged_or_lost'           => 'boolean',
            'claimed_product_expense'      => 'decimal:2',
            'resolved_at'                  => 'datetime',
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

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}