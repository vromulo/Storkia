<?php

namespace App\Models;

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
}