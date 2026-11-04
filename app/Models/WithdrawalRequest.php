<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'withdrawal_reference',
        'requester_type',
        'requester_id',
        'requester_name',
        'role_wallet_id',
        'requested_amount',
        'payout_method',
        'account_name',
        'account_number',
        'status',
        'idempotency_key',
        'reviewed_by_admin_id',
        'rejection_reason',
        'admin_remarks',
        'payout_proof_reference',
        'approved_at',
        'rejected_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'approved_at'      => 'datetime',
            'rejected_at'      => 'datetime',
            'paid_at'          => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(RoleWallet::class, 'role_wallet_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WithdrawalRequestHistory::class, 'withdrawal_request_id')->orderBy('created_at', 'asc');
    }
}