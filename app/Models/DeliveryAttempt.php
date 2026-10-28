<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryAttempt extends Model
{
    protected $fillable = [
        'shipment_id',
        'seller_order_id',
        'delivery_assignment_id',
        'rider_id',
        'attempt_number',
        'outcome',
        'failure_reason',
        'failure_notes',
        'logistics_resolution',
        'reviewed_by',
        'reviewed_at',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'reviewed_at'    => 'datetime',
            'attempted_at'   => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function deliveryAssignment(): BelongsTo
    {
        return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function proof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class, 'delivery_attempt_id');
    }
}