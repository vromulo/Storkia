<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuyerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'avatar_path',
        'username_changed_at',
    ];

    protected $casts = [
        'username_changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Auto-generate a unique, case-insensitive username using email prefix + 4 random numbers.
     */
    public static function generateUniqueUsername(string $email): string
    {
        $prefix = Str::before($email, '@');
        $cleanPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(trim($prefix)));

        if (empty($cleanPrefix)) {
            $cleanPrefix = 'buyer';
        }

        // Limit prefix length to 26 characters so that with 4 numbers it stays within the 30-char limit
        $cleanPrefix = substr($cleanPrefix, 0, 26);

        do {
            $randomNumber = random_int(1000, 9999);
            $candidate = $cleanPrefix . $randomNumber;
        } while (self::whereRaw('LOWER(TRIM(username)) = ?', [$candidate])->exists());

        return $candidate;
    }

    /**
     * Check if the buyer can change their username (1-week cooldown).
     */
    public function canChangeUsername(): bool
    {
        if (! $this->username_changed_at) {
            return true;
        }

        return $this->username_changed_at->copy()->addDays(7)->isPast();
    }

    /**
     * Get the public asset URL for the avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return asset('storage/' . $this->avatar_path);
        }

        return null;
    }
}