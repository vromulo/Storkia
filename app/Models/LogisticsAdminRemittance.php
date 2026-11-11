<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsAdminRemittance extends Model
{
    protected $fillable = [
        'remittance_reference',
        'logistics_profile_id',
        'submitted_by_user_id',
        'total_cod_amount',
        'total_product_subtotal',
        'total_shipping_fee',
        'total_admin_commission',
        'total_seller_net',
        'total_logistics_earning',
        'total_courier_commission',
        'total_rider_commission',
        'payout_method',
        'admin_gcash_name',
        'admin_gcash_number',
        'sender_account_name',
        'sender_account_number',
        'gcash_reference_number',
        'proof_image_path',
        'status',
        'reviewed_by_admin_id',
        'admin_remarks',
        'rejection_reason',
        'submitted_at',
        'verified_at',
        'rejected_at',
    ];

    protected $casts = [
        'total_cod_amount'         => 'decimal:2',
        'total_product_subtotal'   => 'decimal:2',
        'total_shipping_fee'       => 'decimal:2',
        'total_admin_commission'   => 'decimal:2',
        'total_seller_net'         => 'decimal:2',
        'total_logistics_earning'  => 'decimal:2',
        'total_courier_commission' => 'decimal:2',
        'total_rider_commission'   => 'decimal:2',
        'submitted_at'             => 'datetime',
        'verified_at'              => 'datetime',
        'rejected_at'              => 'datetime',
    ];

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogisticsAdminRemittanceItem::class, 'logistics_admin_remittance_id');
    }
}