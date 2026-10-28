<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentVerification extends Model
{
    protected $table = 'shipment_verifications';

    protected $fillable = [
        'shipment_id',
        'shipment_package_id',
        'seller_order_id',
        'logistics_profile_id',
        'logistics_hub_id',
        'stage',
        'verification_method',
        'lookup_token_or_number',
        'actor_type',
        'actor_id',
        'is_confirmed',
        'confirmed_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ShipmentPackage::class, 'shipment_package_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function logisticsHub(): BelongsTo
    {
        return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id');
    }
}