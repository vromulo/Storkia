<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerRewardWallet extends Model
{
    protected $table = 'buyer_reward_wallets';

    public const COOLDOWN_HOURS = 24;

    protected $fillable = [
        'buyer_id',
        'coins_balance',
        'lifetime_coins_earned',
        'lifetime_coins_spent',
        'spin_tickets',
        'last_daily_login_spin_at',
        'last_spin_played_at',
        'last_chest_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'coins_balance'            => 'integer',
            'lifetime_coins_earned'    => 'integer',
            'lifetime_coins_spent'     => 'integer',
            'spin_tickets'             => 'integer',
            'last_daily_login_spin_at' => 'datetime',
            'last_spin_played_at'      => 'datetime',
            'last_chest_opened_at'     => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BuyerRewardTransaction::class, 'reward_wallet_id')->latest('id');
    }

    public function canClaimDailyLoginSpin(): bool
    {
        if (! $this->last_daily_login_spin_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo(
            $this->last_daily_login_spin_at->copy()->addHours(self::COOLDOWN_HOURS)
        );
    }

    public function secondsUntilNextDailyLoginSpin(): int
    {
        if (! $this->last_daily_login_spin_at) {
            return 0;
        }

        $unlockAt = $this->last_daily_login_spin_at->copy()->addHours(self::COOLDOWN_HOURS);

        return $unlockAt->isFuture() ? (int) now()->diffInSeconds($unlockAt) : 0;
    }

    public function canOpenDailyChest(): bool
    {
        if (! $this->last_chest_opened_at) {
            return true;
        }

        return now()->greaterThanOrEqualTo(
            $this->last_chest_opened_at->copy()->addHours(self::COOLDOWN_HOURS)
        );
    }

    public function secondsUntilNextDailyChest(): int
    {
        if (! $this->last_chest_opened_at) {
            return 0;
        }

        $unlockAt = $this->last_chest_opened_at->copy()->addHours(self::COOLDOWN_HOURS);

        return $unlockAt->isFuture() ? (int) now()->diffInSeconds($unlockAt) : 0;
    }
}