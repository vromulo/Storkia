<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Message extends Model
{
    protected $fillable = [
        'user_id',
        'receiver_id',
        'role',
        'message',
        'product_id',
        'shipment_id',
        'attachment_path',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $msg) {
            if (empty($msg->role)) {
                $senderRole = Auth::user()?->role;
                if (! $senderRole && $msg->user_id) {
                    $senderRole = User::where('id', $msg->user_id)->value('role');
                }
                $msg->role = $senderRole ?: 'Buyer';
            }
        });
    }

    /**
     * Every message exchanged between two users, in either direction.
     * The OR is grouped so it can be safely chained with other where() calls.
     */
    public function scopeBetween(Builder $query, int $userA, int $userB): Builder
    {
        return $query->where(function (Builder $outer) use ($userA, $userB) {
            $outer->where(function (Builder $q) use ($userA, $userB) {
                $q->where('user_id', $userA)->where('receiver_id', $userB);
            })->orWhere(function (Builder $q) use ($userA, $userB) {
                $q->where('user_id', $userB)->where('receiver_id', $userA);
            });
        });
    }

    /**
     * Messages sent to $userId that have not been opened yet.
     */
    public function scopeUnreadFor(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId)->where('is_read', false);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}