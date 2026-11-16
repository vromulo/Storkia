<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterHubTransfer extends Model
{
    protected $table = 'inter_hub_transfers';

    protected $fillable = [
        'transfer_reference',
        'shipment_id',
        'seller_order_id',
        'logistics_profile_id',
        'origin_hub_id',
        'destination_hub_id',
        'courier_id',
        'dispatched_by',
        'received_by',
        'status',
        'distance_km',
        'assigned_at',
        'departed_origin_hub_at',
        'arrived_destination_hub_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'distance_km'                => 'decimal:2',
            'assigned_at'                => 'datetime',
            'departed_origin_hub_at'     => 'datetime',
            'arrived_destination_hub_at' => 'datetime',
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

    public function originHub(): BelongsTo
    {
        return $this->belongsTo(LogisticsHub::class, 'origin_hub_id');
    }

    public function destinationHub(): BelongsTo
    {
        return $this->belongsTo(LogisticsHub::class, 'destination_hub_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}