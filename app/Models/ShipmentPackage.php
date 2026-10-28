<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentPackage extends Model
{
    protected $table = 'shipment_packages';

    protected $fillable = [
        'shipment_id',
        'seller_order_id',
        'order_id',
        'seller_profile_id',
        'logistics_profile_id',
        'logistics_hub_id',
        'package_reference',
        'qr_token',
        'package_number',
        'total_packages',
        'handover_method',
        'package_weight',
        'cod_amount',
        'total_items_count',
        'manifest_snapshot',
        'label_printed_count',
        'last_printed_at',
        'seller_prepared_at',
        'courier_verified_at',
        'courier_verification_method',
        'seller_dropped_off_at',
        'hub_verified_at',
        'hub_verified_by',
        'hub_verification_method',
        'rider_accepted_at',
        'rider_verified_at',
        'rider_verification_method',
        'rider_collected_at',
        'delivery_verified_at',
        'delivery_verification_method',
    ];

    protected function casts(): array
    {
        return [
            'package_number'        => 'integer',
            'total_packages'        => 'integer',
            'package_weight'        => 'decimal:3',
            'cod_amount'            => 'decimal:2',
            'total_items_count'     => 'integer',
            'manifest_snapshot'     => 'array',
            'label_printed_count'   => 'integer',
            'last_printed_at'       => 'datetime',
            'seller_prepared_at'    => 'datetime',
            'courier_verified_at'   => 'datetime',
            'seller_dropped_off_at' => 'datetime',
            'hub_verified_at'       => 'datetime',
            'rider_accepted_at'     => 'datetime',
            'rider_verified_at'     => 'datetime',
            'rider_collected_at'    => 'datetime',
            'delivery_verified_at'  => 'datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function logisticsHub(): BelongsTo
    {
        return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id');
    }

    public function hubVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hub_verified_by');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ShipmentVerification::class, 'shipment_package_id')->latest('id');
    }

    public function getHandoverMethodLabelAttribute(): string
    {
        return $this->handover_method === 'self_drop_off'
            ? 'Seller Self Drop-Off at Hub'
            : 'Courier Pickup from Seller';
    }
}