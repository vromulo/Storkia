<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerAccountSanction extends Model
{
    protected $table = 'buyer_account_sanctions';

    public const MAX_STRIKES_BEFORE_BAN = 3;
    public const TEMP_BAN_DAYS = 7;

    protected $fillable = [
        'buyer_id',
        'cod_abandonment_strikes',
        'ban_level',
        'account_status',
        'temp_ban_started_at',
        'temp_ban_ends_at',
        'appeal_required',
        'permanently_banned_at',
        'last_abandoned_seller_order_id',
        'last_abandonment_reason',
        'strike_history',
    ];

    protected function casts(): array
    {
        return [
            'cod_abandonment_strikes' => 'integer',
            'ban_level'               => 'integer',
            'temp_ban_started_at'     => 'datetime',
            'temp_ban_ends_at'        => 'datetime',
            'appeal_required'         => 'boolean',
            'permanently_banned_at'   => 'datetime',
            'strike_history'          => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function lastAbandonedSellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'last_abandoned_seller_order_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(BuyerBanAppeal::class, 'sanction_id')->orderBy('created_at', 'asc');
    }

    public function isPermanentlyBanned(): bool
    {
        return $this->account_status === 'permanently_banned' || (int) $this->ban_level >= 2;
    }

    public function isTemporarilyBannedCountdownActive(): bool
    {
        return $this->account_status === 'temp_banned'
            && $this->temp_ban_ends_at !== null
            && now()->lt($this->temp_ban_ends_at);
    }

    public function canSubmitAppealNow(): bool
    {
        if ($this->isPermanentlyBanned()) {
            return false;
        }

        if (! $this->appeal_required && $this->account_status === 'active') {
            return false;
        }

        if ($this->temp_ban_ends_at !== null && now()->lt($this->temp_ban_ends_at)) {
            return false;
        }

        return true;
    }

    public function isBlockedFromMarketplace(): bool
    {
        return $this->isPermanentlyBanned()
            || $this->account_status === 'temp_banned'
            || $this->account_status === 'awaiting_appeal_review'
            || $this->appeal_required;
    }
}