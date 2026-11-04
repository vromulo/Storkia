<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ProductApproval extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'reviewed_by',
        'status',
        'disapproval_type',
        'remarks',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductApproval $approval) {
            if (empty($approval->user_id)) {
                $approval->user_id = Auth::id()
                    ?: Product::withTrashed()->where('id', $approval->product_id)->value('user_id');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}