<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailChangeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'new_email',
        'code_hash',
        'attempts',
        'last_sent_at',
        'code_expires_at',
    ];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'code_expires_at' => 'datetime',
    ];

    public const RESEND_COOLDOWN_SECONDS = 60;
    public const CODE_TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isExpired(): bool
    {
        return ! $this->code_expires_at || $this->code_expires_at->isPast();
    }

    public function secondsUntilResend(): int
    {
        if (! $this->last_sent_at) {
            return 0;
        }

        $unlock = $this->last_sent_at->copy()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        if (! $unlock->isFuture()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInSeconds($unlock)));
    }
}