<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorkVoucher extends Model
{
    protected $table = 'stork_vouchers';

    protected $fillable = [
        'seller_profile_id',
        'created_by',
        'code',
        'title',
        'description',
        'scope',
        'discount_type',
        'discount_value',
        'max_discount_cap',
        'min_spend',
        'total_usage_limit',
        'used_count',
        'per_buyer_limit',
        'visibility',
        'applicable_product_ids',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_value'         => 'decimal:2',
            'max_discount_cap'       => 'decimal:2',
            'min_spend'              => 'decimal:2',
            'total_usage_limit'      => 'integer',
            'used_count'             => 'integer',
            'per_buyer_limit'        => 'integer',
            'applicable_product_ids' => 'array',
            'starts_at'              => 'datetime',
            'ends_at'                => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(BuyerVoucherClaim::class, 'voucher_id');
    }

    public function isCurrentlyOngoing(): bool
    {
        return $this->status === 'active'
            && $this->starts_at !== null
            && $this->ends_at !== null
            && now()->greaterThanOrEqualTo($this->starts_at)
            && now()->lessThanOrEqualTo($this->ends_at)
            && $this->used_count < $this->total_usage_limit;
    }

    public function getBadgeLabelAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            $pct = rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.');
            $cap = $this->max_discount_cap ? ' (Cap ₱' . number_format((float) $this->max_discount_cap, 0) . ')' : '';
            return "{$pct}% OFF{$cap}";
        }

        return '₱' . number_format((float) $this->discount_value, 0) . ' OFF';
    }
}