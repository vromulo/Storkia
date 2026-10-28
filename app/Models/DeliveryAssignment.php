<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryAssignment extends Model
{
    protected $fillable = [
        'shipment_id',
        'seller_order_id',
        'logistics_profile_id',
        'rider_id',
        'assigned_by',
        'attempt_number',
        'status',
        'assigned_at',
        'collected_at',
        'out_for_delivery_at',
        'completed_at',
        'failed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number'      => 'integer',
            'assigned_at'         => 'datetime',
            'collected_at'        => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'completed_at'        => 'datetime',
            'failed_at'           => 'datetime',
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

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'rider_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class, 'delivery_assignment_id');
    }

    public function proof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class, 'delivery_assignment_id');
    }
}