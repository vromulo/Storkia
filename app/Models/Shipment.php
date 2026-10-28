<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $fillable = [
        'tracking_number',
        'order_id',
        'seller_order_id',
        'seller_profile_id',
        'logistics_profile_id',
        'current_courier_id',
        'current_rider_id',
        'status',
        'total_weight',
        'cod_amount',
        'shipping_fee',
        'delivery_attempts_count',
        'pickup_address_snapshot',
        'hub_address_snapshot',
        'delivery_address_snapshot',
        'pickup_requested_at',
        'courier_assigned_at',
        'picked_up_at',
        'arrived_at_hub_at',
        'hub_verified_by',
        'rider_assigned_at',
        'out_for_delivery_at',
        'delivered_at',
        'failed_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'total_weight'              => 'decimal:3',
            'cod_amount'                => 'decimal:2',
            'shipping_fee'              => 'decimal:2',
            'delivery_attempts_count'   => 'integer',
            'pickup_address_snapshot'   => 'array',
            'hub_address_snapshot'      => 'array',
            'delivery_address_snapshot' => 'array',
            'pickup_requested_at'       => 'datetime',
            'courier_assigned_at'       => 'datetime',
            'picked_up_at'              => 'datetime',
            'arrived_at_hub_at'         => 'datetime',
            'rider_assigned_at'         => 'datetime',
            'out_for_delivery_at'       => 'datetime',
            'delivered_at'              => 'datetime',
            'failed_at'                 => 'datetime',
            'returned_at'               => 'datetime',
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

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function currentCourier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'current_courier_id');
    }

    public function currentRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'current_rider_id');
    }

    public function hubVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hub_verified_by');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class, 'shipment_id')->orderBy('package_number');
    }

    public function primaryPackage(): HasOne
    {
        return $this->hasOne(ShipmentPackage::class, 'shipment_id')->orderBy('package_number');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ShipmentVerification::class, 'shipment_id')->latest('id');
    }

    public function trackingLogs(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class, 'shipment_id')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function pickupAssignments(): HasMany
    {
        return $this->hasMany(PickupAssignment::class, 'shipment_id')->latest('id');
    }

    public function latestPickupAssignment(): HasOne
    {
        return $this->hasOne(PickupAssignment::class, 'shipment_id')->latestOfMany();
    }

    public function deliveryAssignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class, 'shipment_id')->latest('id');
    }

    public function latestDeliveryAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class, 'shipment_id')->latestOfMany();
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class, 'shipment_id')->orderByDesc('attempt_number');
    }

    public function deliveryProofs(): HasMany
    {
        return $this->hasMany(DeliveryProof::class, 'shipment_id')->latest('id');
    }

    public function latestDeliveryProof(): HasOne
    {
        return $this->hasOne(DeliveryProof::class, 'shipment_id')->latestOfMany();
    }

    public function codCollection(): HasOne
    {
        return $this->hasOne(CodCollection::class, 'shipment_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pickup_requested'        => 'Pickup Requested',
            'awaiting_seller_drop_off'=> 'Awaiting Seller Drop-Off at Hub',
            'courier_assigned'        => 'Courier Assigned',
            'picked_up_from_seller'   => 'Picked Up from Seller',
            'in_transit_to_hub'       => 'In Transit to Hub',
            'received_at_hub'         => 'Received at Sorting Hub',
            'rider_assigned'          => 'Rider Assigned',
            'out_for_delivery'        => 'Out for Delivery',
            'delivered'               => 'Delivered',
            'delivery_failed'         => 'Delivery Failed (Pending Review)',
            'scheduled_redelivery'    => 'Scheduled for Redelivery',
            'returning_to_seller'     => 'Returning to Seller',
            'returned_to_seller'      => 'Returned to Seller',
            default                   => ucwords(str_replace('_', ' ', $this->status)),
        };
    }
}