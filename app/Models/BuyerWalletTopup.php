<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerWalletTopup extends Model
{
    protected $table = 'buyer_wallet_topups';

    protected $fillable = [
        'topup_reference',
        'buyer_id',
        'role_wallet_id',
        'admin_gcash_name',
        'admin_gcash_number',
        'sender_account_name',
        'sender_account_number',
        'reference_number',
        'screenshot_path',
        'approved_amount',
        'status',
        'reviewed_by_admin_id',
        'admin_remarks',
        'rejection_reason',
        'reviewed_at',
        'wallet_transaction_id',
    ];

    protected $casts = [
        'approved_amount' => 'decimal:2',
        'reviewed_at'     => 'datetime',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(RoleWallet::class, 'role_wallet_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    public function getScreenshotUrlAttribute(): string
    {
        return $this->screenshot_path ? asset('storage/' . ltrim($this->screenshot_path, '/')) : '';
    }
}