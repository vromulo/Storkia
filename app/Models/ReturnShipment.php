<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReturnShipment extends Model
{
    public const STATUS_AWAITING_RIDER          = 'awaiting_rider_assignment';
    public const STATUS_RIDER_ASSIGNED          = 'rider_assigned';
    public const STATUS_PICKED_UP_FROM_BUYER    = 'picked_up_from_buyer';
    public const STATUS_PICKUP_FAILED           = 'pickup_failed';
    public const STATUS_RECEIVED_AT_HUB         = 'received_at_hub';
    public const STATUS_COURIER_ASSIGNED        = 'courier_assigned';
    public const STATUS_OUT_FOR_RETURN_DELIVERY = 'out_for_return_delivery';
    public const STATUS_DELIVERY_FAILED         = 'delivery_failed';
    public const STATUS_DELIVERED_TO_SELLER     = 'delivered_to_seller';
    public const STATUS_CANCELLED               = 'cancelled';

    public const MAX_PICKUP_ATTEMPTS   = 3;
    public const MAX_DELIVERY_ATTEMPTS = 3;

    public const OPEN_STATUSES = [
        self::STATUS_AWAITING_RIDER,
        self::STATUS_RIDER_ASSIGNED,
        self::STATUS_PICKED_UP_FROM_BUYER,
        self::STATUS_PICKUP_FAILED,
        self::STATUS_RECEIVED_AT_HUB,
        self::STATUS_COURIER_ASSIGNED,
        self::STATUS_OUT_FOR_RETURN_DELIVERY,
        self::STATUS_DELIVERY_FAILED,
    ];

    protected $fillable = [
        'tracking_number',
        'return_request_id',
        'seller_order_id',
        'shipment_id',
        'logistics_profile_id',
        'current_rider_id',
        'current_courier_id',
        'status',
        'total_weight',
        'pickup_attempts_count',
        'delivery_attempts_count',
        'pickup_address_snapshot',
        'hub_address_snapshot',
        'return_address_snapshot',
        'rider_pay',
        'courier_pay',
        'rider_paid_at',
        'courier_paid_at',
        'rider_assigned_at',
        'picked_up_from_buyer_at',
        'arrived_at_hub_at',
        'hub_verified_by',
        'courier_assigned_at',
        'picked_up_from_hub_at',
        'delivered_to_seller_at',
        'failed_at',
        'delivery_failed_at',
        'cancelled_at',
        'pickup_failure_reason',
        'delivery_failure_reason',
        'buyer_pickup_photo',
        'seller_delivery_photo',
    ];

    protected function casts(): array
    {
        return [
            'total_weight'            => 'decimal:3',
            'pickup_attempts_count'   => 'integer',
            'delivery_attempts_count' => 'integer',
            'pickup_address_snapshot' => 'array',
            'hub_address_snapshot'    => 'array',
            'return_address_snapshot' => 'array',
            'rider_pay'               => 'decimal:2',
            'courier_pay'             => 'decimal:2',
            'rider_paid_at'           => 'datetime',
            'courier_paid_at'         => 'datetime',
            'rider_assigned_at'       => 'datetime',
            'picked_up_from_buyer_at' => 'datetime',
            'arrived_at_hub_at'       => 'datetime',
            'courier_assigned_at'     => 'datetime',
            'picked_up_from_hub_at'   => 'datetime',
            'delivered_to_seller_at'  => 'datetime',
            'failed_at'               => 'datetime',
            'delivery_failed_at'      => 'datetime',
            'cancelled_at'            => 'datetime',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'current_rider_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'current_courier_id');
    }

    public function hubVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hub_verified_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnShipmentEvent::class, 'return_shipment_id')
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc');
    }

    public static function generateTrackingNumber(): string
    {
        do {
            $number = 'RTN-' . now()->format('ymd') . '-' . strtoupper(Str::random(7));
        } while (self::where('tracking_number', $number)->exists());

        return $number;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function hasPickupAttemptsLeft(): bool
    {
        return (int) $this->pickup_attempts_count < self::MAX_PICKUP_ATTEMPTS;
    }

    public function hasDeliveryAttemptsLeft(): bool
    {
        return (int) ($this->delivery_attempts_count ?? 0) < self::MAX_DELIVERY_ATTEMPTS;
    }

    public function getTotalReturnPayAttribute(): float
    {
        return round((float) $this->rider_pay + (float) $this->courier_pay, 2);
    }

    public function recordEvent(
        string $title,
        ?string $description = null,
        ?string $location = null,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $status = null
    ): ReturnShipmentEvent {
        return ReturnShipmentEvent::create([
            'return_shipment_id' => $this->id,
            'return_request_id'  => $this->return_request_id,
            'status'             => $status ?? $this->status,
            'title'              => $title,
            'description'        => $description,
            'location'           => $location,
            'actor_type'         => $actorType,
            'actor_id'           => $actorId,
            'occurred_at'        => now(),
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_AWAITING_RIDER          => 'Waiting for Rider Assignment',
            self::STATUS_RIDER_ASSIGNED          => 'Rider Assigned for Return Pickup',
            self::STATUS_PICKED_UP_FROM_BUYER    => 'Picked Up From Buyer',
            self::STATUS_PICKUP_FAILED           => 'Return Pickup Failed',
            self::STATUS_RECEIVED_AT_HUB         => 'Received at Sorting Hub',
            self::STATUS_COURIER_ASSIGNED        => 'Courier Assigned for Return Delivery',
            self::STATUS_OUT_FOR_RETURN_DELIVERY => 'On the Way Back to Seller',
            self::STATUS_DELIVERY_FAILED         => 'Return Delivery to Seller Failed',
            self::STATUS_DELIVERED_TO_SELLER     => 'Delivered to Seller',
            self::STATUS_CANCELLED               => 'Return Cancelled',
            default                              => ucwords(str_replace('_', ' ', (string) $this->status)),
        };
    }
}