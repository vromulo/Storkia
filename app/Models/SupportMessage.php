<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    public const SENDER_USER = 'user';
    public const SENDER_ADMIN = 'admin';

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_user_id',
        'sender_admin_id',
        'message',
        'attachment_path',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_admin_id');
    }

    public function isFromAdmin(): bool
    {
        return $this->sender_type === self::SENDER_ADMIN;
    }

    /**
     * Messages the *user* has not read yet (i.e. sent by an admin).
     */
    public function scopeUnreadByUser(Builder $query): Builder
    {
        return $query->where('sender_type', self::SENDER_ADMIN)->where('is_read', false);
    }

    /**
     * Messages the *support team* has not read yet (i.e. sent by a user).
     */
    public function scopeUnreadByAdmin(Builder $query): Builder
    {
        return $query->where('sender_type', self::SENDER_USER)->where('is_read', false);
    }
}