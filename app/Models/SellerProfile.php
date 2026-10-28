<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'contact_no',
        'province',
        'municipality',
        'barangay',
        'street',
        'house_details',
        'business_name',
        'line_of_business',
        'logo_path',
        'id_path',
        'permit_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function applications()
    {
        return $this->hasMany(SellerApplication::class, 'user_id', 'user_id')->latest('version');
    }

    public function otherCategories()
    {
        return $this->hasMany(SellerCategory::class, 'seller_profile_id');
    }

    public function gcashAccount(): HasOne
    {
        return $this->hasOne(SellerGcashAccount::class, 'seller_profile_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(StorkVoucher::class, 'seller_profile_id')->latest('id');
    }

    public function activeClaimableVouchers(): HasMany
    {
        return $this->hasMany(StorkVoucher::class, 'seller_profile_id')
            ->where('status', 'active')
            ->where('visibility', 'claimable')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereColumn('used_count', '<', 'total_usage_limit');
    }

    public function sellerOrders()
    {
        return $this->hasMany(SellerOrder::class, 'seller_profile_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'seller_profile_id');
    }

    public function commissionRecords()
    {
        return $this->hasMany(CommissionRecord::class, 'seller_profile_id');
    }

    public function settlements()
    {
        return $this->hasMany(SellerSettlement::class, 'seller_profile_id');
    }

    public function hasActiveGcashAccount(): bool
    {
        $account = $this->relationLoaded('gcashAccount')
            ? $this->gcashAccount
            : $this->gcashAccount()->first();

        return $account !== null && $account->isCompliantForCheckout();
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->house_details,
            $this->street,
            $this->barangay,
            $this->municipality,
            $this->province,
        ])->filter()->implode(', ');
    }
}