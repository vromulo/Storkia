<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationOtp extends Model
{
    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'last_sent_at',
        'code_expires_at',
        'verified_at',
        'verification_token',
        'reservation_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
        ];
    }

    public const CODE_TTL_MINUTES = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;
    public const RESERVATION_TTL_MINUTES = 30;

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function isCodeExpired(): bool
    {
        return ! $this->code_expires_at || $this->code_expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public function isReservationActive(): bool
    {
        return $this->isVerified()
            && $this->reservation_expires_at !== null
            && $this->reservation_expires_at->isFuture();
    }

    public function secondsUntilResendAllowed(): int
    {
        if (! $this->last_sent_at) {
            return 0;
        }

        $unlockAt = $this->last_sent_at->copy()->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        if (! $unlockAt->isFuture()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInSeconds($unlockAt)));
    }

    public function attemptsRemaining(): int
    {
        return max(0, self::MAX_ATTEMPTS - (int) $this->attempts);
    }
}