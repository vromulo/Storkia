<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerBanAppeal extends Model
{
    protected $table = 'buyer_ban_appeals';

    protected $fillable = [
        'buyer_id',
        'sanction_id',
        'ban_level',
        'appeal_explanation',
        'supporting_photo_path',
        'status',
        'reviewed_by_admin_id',
        'admin_remarks',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'ban_level'   => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sanction(): BelongsTo
    {
        return $this->belongsTo(BuyerAccountSanction::class, 'sanction_id');
    }
}