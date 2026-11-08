<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One running Help Center thread between a single user (Buyer, Seller or
 * Logistics) and the Storkia support team (any admin).
 */
class SupportConversation extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'user_id',
        'status',
        'last_message_at',
        'resolved_at',
        'resolved_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'resolved_at'     => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id')->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class, 'conversation_id')->latestOfMany();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Marks the thread as open again (a user wrote back after it was resolved).
     */
    public function reopen(): void
    {
        if ($this->isResolved()) {
            $this->forceFill([
                'status'               => self::STATUS_OPEN,
                'resolved_at'          => null,
                'resolved_by_admin_id' => null,
            ])->save();
        }
    }
}