<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerVoucherClaim extends Model
{
    protected $table = 'buyer_voucher_claims';

    protected $fillable = [
        'voucher_id',
        'buyer_id',
        'times_used',
        'last_seller_order_id',
        'claimed_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'times_used'   => 'integer',
            'claimed_at'   => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(StorkVoucher::class, 'voucher_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function lastSellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'last_seller_order_id');
    }
}