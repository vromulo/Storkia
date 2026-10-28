<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentSubmission extends Model
{
    protected $table = 'order_payment_submissions';

    protected $fillable = [
        'order_id',
        'seller_order_id',
        'payment_id',
        'buyer_id',
        'seller_profile_id',
        'seller_gcash_name',
        'seller_gcash_number',
        'sender_gcash_number',
        'paid_at',
        'reference_number',
        'reference_code',
        'screenshot_path',
        'submitted_amount',
        'status',
        'is_suspicious',
        'suspicious_reason',
        'suspicious_reported_at',
        'reported_by',
        'switched_to_cod',
        'switched_to_cod_at',
        'seller_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at'                => 'datetime',
            'submitted_amount'       => 'decimal:2',
            'is_suspicious'          => 'boolean',
            'suspicious_reported_at' => 'datetime',
            'switched_to_cod'        => 'boolean',
            'switched_to_cod_at'     => 'datetime',
            'seller_verified_at'     => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getScreenshotUrlAttribute(): ?string
    {
        return $this->screenshot_path ? asset('storage/' . $this->screenshot_path) : null;
    }
}