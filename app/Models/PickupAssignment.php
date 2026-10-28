<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupAssignment extends Model
{
    protected $fillable = [
        'shipment_id',
        'seller_order_id',
        'logistics_profile_id',
        'courier_id',
        'assigned_by',
        'status',
        'assigned_at',
        'accepted_at',
        'picked_up_at',
        'handed_over_at',
        'hub_verified_by',
        'hub_verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'     => 'datetime',
            'accepted_at'     => 'datetime',
            'picked_up_at'    => 'datetime',
            'handed_over_at'  => 'datetime',
            'hub_verified_at' => 'datetime',
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

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function hubVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hub_verified_by');
    }
}