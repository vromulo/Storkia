<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerRewardTransaction extends Model
{
    protected $table = 'buyer_reward_transactions';

    protected $fillable = [
        'buyer_id',
        'reward_wallet_id',
        'seller_order_id',
        'voucher_id',
        'reference_code',
        'source_type',
        'reward_type',
        'coins_delta',
        'spins_delta',
        'coins_balance_after',
        'spins_balance_after',
        'stage_level',
        'description',
        'meta_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'coins_delta'         => 'integer',
            'spins_delta'         => 'integer',
            'coins_balance_after' => 'integer',
            'spins_balance_after' => 'integer',
            'stage_level'         => 'integer',
            'meta_snapshot'       => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(BuyerRewardWallet::class, 'reward_wallet_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(StorkVoucher::class, 'voucher_id');
    }
}