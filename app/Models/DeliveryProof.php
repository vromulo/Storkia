<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProof extends Model
{
    protected $fillable = [
        'shipment_id',
        'seller_order_id',
        'delivery_assignment_id',
        'delivery_attempt_id',
        'rider_id',
        'photo_path',
        'recipient_name',
        'notes',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
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

    public function deliveryAttempt(): BelongsTo
    {
        return $this->belongsTo(DeliveryAttempt::class, 'delivery_attempt_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }
}