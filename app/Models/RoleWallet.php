<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleWallet extends Model
{
    protected $table = 'role_wallets';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'owner_name',
        'pending_balance',
        'available_balance',
        'total_earned',
        'total_withdrawn',
        'last_credited_at',
    ];

    protected function casts(): array
    {
        return [
            'pending_balance'   => 'decimal:2',
            'available_balance' => 'decimal:2',
            'total_earned'      => 'decimal:2',
            'total_withdrawn'   => 'decimal:2',
            'last_credited_at'  => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'role_wallet_id')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }
}