<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequestHistory extends Model
{
    protected $fillable = [
        'withdrawal_request_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'actor_name',
        'remarks',
    ];

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
    }
}