<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    public const ACCEPTANCE_DEADLINE_HOURS = 24;
    public const BUYER_CONFIRMATION_HOURS = 72;

    protected $fillable = [
        'order_id',
        'seller_order_number',
        'buyer_id',
        'seller_id',
        'seller_profile_id',
        'logistics_profile_id',
        'status',
        'payment_status',
        'subtotal_amount',
        'shipping_fee',
        'total_amount',
        'total_weight',
        'shipping_tier',
        'pickup_address_snapshot',
        'delivery_address_snapshot',
        'acceptance_deadline_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'ready_for_pickup_at',
        'delivered_at',
        'confirmation_deadline_at',
        'buyer_confirmed_at',
        'has_unresolved_dispute',
        'dispute_reason',
        'dispute_reported_at',
        'dispute_resolved_at',
        'completed_at',
        'cancelled_at',
        'stock_deducted',
        'stock_restored',
        'stock_restored_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount'           => 'decimal:2',
            'shipping_fee'              => 'decimal:2',
            'total_amount'              => 'decimal:2',
            'total_weight'              => 'decimal:3',
            'pickup_address_snapshot'   => 'array',
            'delivery_address_snapshot' => 'array',
            'acceptance_deadline_at'    => 'datetime',
            'accepted_at'               => 'datetime',
            'rejected_at'               => 'datetime',
            'ready_for_pickup_at'       => 'datetime',
            'delivered_at'              => 'datetime',
            'confirmation_deadline_at'  => 'datetime',
            'buyer_confirmed_at'        => 'datetime',
            'has_unresolved_dispute'    => 'boolean',
            'dispute_reported_at'       => 'datetime',
            'dispute_resolved_at'       => 'datetime',
            'completed_at'              => 'datetime',
            'cancelled_at'              => 'datetime',
            'stock_deducted'            => 'boolean',
            'stock_restored'            => 'boolean',
            'stock_restored_at'         => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'seller_order_id')->orderBy('id', 'asc');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'seller_order_id');
    }

    public function paymentSubmission(): HasOne
    {
        return $this->hasOne(OrderPaymentSubmission::class, 'seller_order_id');
    }

    public function extra(): HasOne
    {
        return $this->hasOne(SellerOrderExtra::class, 'seller_order_id');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'seller_order_id');
    }

    public function codCollection(): HasOne
    {
        return $this->hasOne(CodCollection::class, 'seller_order_id');
    }

    public function commissionRecord(): HasOne
    {
        return $this->hasOne(CommissionRecord::class, 'seller_order_id');
    }

    public function settlementItem(): HasOne
    {
        return $this->hasOne(SellerSettlementItem::class, 'seller_order_id');
    }

    public function serviceRating(): HasOne
    {
        return $this->hasOne(OrderServiceRating::class, 'seller_order_id');
    }

    public function itemReviews(): HasMany
    {
        return $this->hasMany(OrderItemReview::class, 'seller_order_id');
    }

    public function rtsClaim(): HasOne
    {
        return $this->hasOne(SellerRtsClaim::class, 'seller_order_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'seller_order_id')->latest('id');
    }

    public function isRatedByBuyer(): bool
    {
        if ($this->relationLoaded('serviceRating')) {
            return $this->serviceRating !== null;
        }

        return $this->serviceRating()->exists();
    }

    public function canBuyerCancelPreShipment(): bool
    {
        if ($this->shipment()->exists()) {
            return false;
        }

        return in_array($this->status, [
            'pending_seller_acceptance',
            'pending_buyer_response',
            'accepted',
            'preparing',
        ], true);
    }

    public function getEffectivePaymentMethodAttribute(): string
    {
        $method = $this->relationLoaded('payment')
            ? $this->payment?->method
            : $this->payment()->value('method');

        return strtoupper((string) ($method ?: 'COD'));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_seller_acceptance' => 'Pending Seller Acceptance',
            'pending_buyer_response'    => 'Pending Buyer Response (Payment Flagged)',
            'accepted'                  => 'Order Accepted',
            'preparing'                 => 'Preparing Order',
            'ready_for_pickup'          => 'Ready for Pickup / Drop-Off',
            'pickup_assigned'           => 'Courier Assigned for Pickup',
            'picked_up'                 => 'Picked Up by Courier',
            'at_logistics_hub'          => 'At Sorting Hub',
            'out_for_delivery'          => 'Out for Delivery',
            'delivered'                 => 'Delivered (Awaiting Confirmation)',
            'delivery_failed'           => 'Delivery Attempt Failed',
            'disputed'                  => 'Delivery Issue / Return Requested',
            'completed'                 => $this->isRatedByBuyer() ? 'Completed (Rated)' : 'Completed (To Rate)',
            'rejected'                  => 'Rejected by Seller',
            'cancelled'                 => 'Cancelled',
            'returned_to_seller'        => 'Returned to Seller',
            default                     => ucwords(str_replace('_', ' ', $this->status)),
        };
    }
}