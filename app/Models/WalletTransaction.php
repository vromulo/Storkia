<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';

    protected $fillable = [
        'role_wallet_id',
        'transaction_reference',
        'owner_type',
        'owner_id',
        'seller_order_id',
        'shipment_id',
        'entry_type',
        'payment_method',
        'amount',
        'pending_balance_after',
        'available_balance_after',
        'status',
        'description',
        'meta_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount'                  => 'decimal:2',
            'pending_balance_after'   => 'decimal:2',
            'available_balance_after' => 'decimal:2',
            'meta_snapshot'           => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(RoleWallet::class, 'role_wallet_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}