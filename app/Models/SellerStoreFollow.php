<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerStoreFollow extends Model
{
    protected $table = 'seller_store_follows';

    protected $fillable = [
        'seller_profile_id',
        'follower_user_id',
        'is_following',
        'reward_voucher_id',
        'reward_voucher_code',
        'reward_issued_at',
    ];

    protected function casts(): array
    {
        return [
            'is_following'     => 'boolean',
            'reward_issued_at' => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    public function rewardVoucher(): BelongsTo
    {
        return $this->belongsTo(StorkVoucher::class, 'reward_voucher_id');
    }
}