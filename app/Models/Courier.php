<?php

namespace App\Models;

use App\Services\AddressService;
use App\Services\CourierMobileSchemaService;
use App\Services\ShippingCalculatorService;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Courier extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'couriers';

    protected $fillable = [
        'logistics_profile_id',
        'first_name',
        'last_name',
        'birthday',
        'email',
        'email_verified_at',
        'contact_no',
        'avatar_path',
        'license_no',
        'license_type',
        'dl_codes',
        'license_expiry_date',
        'vehicle_type',
        'plate_number',
        'load_capacity',
        'home_hub',
        'license_path',
        'or_cr_path',
        'nbi_clearance_path',
        'password',
        'password_changed_at',
        'status',
        'is_online',
        'last_seen_at',
        'sla_strikes',
        'current_latitude',
        'current_longitude',
        'location_accuracy',
        'location_updated_at',
        'vehicle_verification_status',
        'last_checklist_passed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birthday'                 => 'date',
            'license_expiry_date'      => 'date',
            'email_verified_at'        => 'datetime',
            'dl_codes'                 => 'array',
            'load_capacity'            => 'integer',
            'password'                 => 'hashed',
            'password_changed_at'      => 'datetime',
            'is_online'                => 'boolean',
            'last_seen_at'             => 'datetime',
            'sla_strikes'              => 'integer',
            'current_latitude'         => 'decimal:7',
            'current_longitude'        => 'decimal:7',
            'location_accuracy'        => 'decimal:2',
            'location_updated_at'      => 'datetime',
            'last_checklist_passed_at' => 'datetime',
        ];
    }

    public function logisticsProfile()
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function hubAssignments()
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'courier_id')->latest('id');
    }

    public function currentHubAssignment()
    {
        return $this->hasOne(LogisticsEmployeeHubAssignment::class, 'courier_id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function pickupAssignments()
    {
        return $this->hasMany(PickupAssignment::class, 'courier_id')->latest('id');
    }

    public function returnShipments()
    {
        return $this->hasMany(ReturnShipment::class, 'current_courier_id')->latest('id');
    }

    public function interHubTransfers()
    {
        return $this->hasMany(InterHubTransfer::class, 'courier_id')->latest('id');
    }

    public function locations()
    {
        return $this->hasMany(CourierLocation::class, 'courier_id')->latest('recorded_at');
    }

    public function latestLocation()
    {
        return $this->hasOne(CourierLocation::class, 'courier_id')->latestOfMany('recorded_at');
    }

    public function activeShipments()
    {
        return $this->hasMany(Shipment::class, 'current_courier_id');
    }

    public function hasActiveWorkAssignments(bool $includeAssigned = false): bool
    {
        $pickupStatuses = $includeAssigned
            ? ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub']
            : ['accepted', 'picked_up', 'in_transit_to_hub'];

        if (PickupAssignment::where('courier_id', $this->id)->whereIn('status', $pickupStatuses)->exists()) {
            return true;
        }

        if (Schema::hasTable('return_shipments')) {
            $returnStatuses = $includeAssigned
                ? [ReturnShipment::STATUS_COURIER_ASSIGNED, ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY]
                : [ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY];

            if (ReturnShipment::where('current_courier_id', $this->id)->whereIn('status', $returnStatuses)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('inter_hub_transfers')) {
            $transferStatuses = $includeAssigned
                ? ['assigned', 'accepted', 'in_transit', 'arrived_destination_hub']
                : ['accepted', 'in_transit', 'arrived_destination_hub'];

            if (InterHubTransfer::where('courier_id', $this->id)->whereIn('status', $transferStatuses)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function getAssignedHubAttribute(): ?LogisticsHub
    {
        $assignment = $this->relationLoaded('currentHubAssignment')
            ? $this->currentHubAssignment
            : $this->currentHubAssignment()->with('hub')->first();

        if ($assignment && $assignment->hub) {
            return $assignment->hub;
        }

        return $this->logisticsProfile?->ensureMainHubExists();
    }

    public function getAssignedMunicipalityAttribute(): string
    {
        return $this->assigned_hub?->municipality
            ?? $this->logisticsProfile?->municipality
            ?? '';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFormattedDlCodesAttribute(): string
    {
        return is_array($this->dl_codes) ? implode(', ', $this->dl_codes) : (string) $this->dl_codes;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            return asset('storage/' . ltrim($this->avatar_path, '/'));
        }

        return null;
    }

    public function formattedPasswordLastUpdated(): string
    {
        if ($this->password_changed_at) {
            return 'Last updated ' . $this->password_changed_at->diffForHumans();
        }

        return 'Issued by Logistics Hub';
    }

    public function getActiveAssignedWeightKgAttribute(): float
    {
        $activePickupWeight = (float) PickupAssignment::where('courier_id', $this->id)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])
            ->with('shipment:id,total_weight')
            ->get()
            ->sum(fn ($pa) => (float) ($pa->shipment?->total_weight ?? 0));

        $activeReturnWeight = Schema::hasTable('return_shipments')
            ? (float) ReturnShipment::where('current_courier_id', $this->id)
                ->whereIn('status', [
                    ReturnShipment::STATUS_COURIER_ASSIGNED,
                    ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
                ])
                ->sum('total_weight')
            : 0.0;

        $activeLinehaulWeight = Schema::hasTable('inter_hub_transfers')
            ? (float) InterHubTransfer::where('courier_id', $this->id)
                ->whereIn('status', ['assigned', 'accepted', 'in_transit', 'arrived_destination_hub'])
                ->with('shipment:id,total_weight')
                ->get()
                ->sum(fn ($iht) => (float) ($iht->shipment?->total_weight ?? 0))
            : 0.0;

        return round($activePickupWeight + $activeReturnWeight + $activeLinehaulWeight, 2);
    }

    public function getRemainingLoadCapacityKgAttribute(): float
    {
        $maxCap = max(1.0, (float) ($this->load_capacity ?: 100));

        return max(0.0, round($maxCap - $this->active_assigned_weight_kg, 2));
    }

    public function distanceToSnapshotKm(array $targetSnapshot): float
    {
        $hub = $this->assigned_hub;
        $originSnap = [
            'province'     => $hub?->province ?? ($this->logisticsProfile?->province ?? ''),
            'municipality' => $hub?->municipality ?? ($this->logisticsProfile?->municipality ?? ''),
            'barangay'     => $hub?->barangay ?? ($this->logisticsProfile?->barangay ?? ''),
            'latitude'     => $this->current_latitude ?: $hub?->latitude,
            'longitude'    => $this->current_longitude ?: $hub?->longitude,
        ];

        return CourierMobileSchemaService::calculateDistanceKmBetweenLocations($originSnap, $targetSnapshot, $hub, null);
    }

    public function evaluatePickupEligibilityForSnapshot(array $pickupSnapshot, float $shipmentWeightKg = 0.0): array
    {
        $hub = $this->assigned_hub;
        if (! $hub) {
            return [
                'is_eligible'              => false,
                'has_load_capacity'        => false,
                'same_island'              => false,
                'same_municipality'        => false,
                'same_province'            => false,
                'distance_from_hub_km'     => 9999.0,
                'distance_from_courier_km' => 9999.0,
                'active_load_kg'           => 0.0,
                'remaining_capacity_kg'    => (float) ($this->load_capacity ?: 100),
                'reason'                   => 'Courier does not have an assigned sorting hub.',
            ];
        }

        $addressService = app(AddressService::class);
        $shippingService = app(ShippingCalculatorService::class);

        $hubProv = $addressService->normalizeName($hub->province);
        $hubMun = $addressService->normalizeName($hub->municipality);

        $origProv = $addressService->normalizeName($pickupSnapshot['province'] ?? '');
        $origMun = $addressService->normalizeName($pickupSnapshot['municipality'] ?? '');

        $hubIsland = $shippingService->resolveIslandGroup($hub->province);
        $origIsland = $shippingService->resolveIslandGroup($pickupSnapshot['province'] ?? '');

        $distFromHubKm = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
            [
                'province'     => $hub->province,
                'municipality' => $hub->municipality,
                'barangay'     => $hub->barangay,
                'latitude'     => $hub->latitude,
                'longitude'    => $hub->longitude,
            ],
            $pickupSnapshot,
            $hub,
            null
        );

        $distFromCourierKm = $this->distanceToSnapshotKm($pickupSnapshot);

        $maxCourierRadius = Schema::hasColumn('logistics_hubs', 'max_courier_pickup_radius_km') && (float) ($hub->max_courier_pickup_radius_km ?? 0) > 0
            ? (float) $hub->max_courier_pickup_radius_km
            : ShippingCalculatorService::MAX_COURIER_PICKUP_RADIUS_KM;

        $sameIsland = $hubIsland !== '' && $origIsland !== '' && $hubIsland === $origIsland;
        $sameProvince = $sameIsland && $hubProv !== '' && $origProv !== '' && $hubProv === $origProv;
        $sameMunicipality = $sameProvince && $hubMun !== '' && $origMun !== '' && $hubMun === $origMun;

        $withinRange = $sameIsland && ($sameProvince || $distFromHubKm <= $maxCourierRadius);

        $activeLoadKg = $this->active_assigned_weight_kg;
        $remainingCapKg = $this->remaining_load_capacity_kg;
        $hasCapacity = $shipmentWeightKg <= 0 || $remainingCapKg >= $shipmentWeightKg;

        $reason = null;
        if (! $sameIsland) {
            $reason = "Courier {$this->full_name} is based at {$hub->hub_name} (" . ucfirst($hubIsland) . ") and cannot perform First-Mile Seller Pickup on another island (" . ucfirst($origIsland) . ").";
        } elseif (! $withinRange) {
            $reason = "Seller in {$pickupSnapshot['municipality']}, {$pickupSnapshot['province']} is {$distFromHubKm} km from {$hub->hub_name} (exceeds {$maxCourierRadius} km First-Mile Courier limit).";
        } elseif (! $hasCapacity) {
            $reason = "Courier {$this->full_name} has {$activeLoadKg} kg active load / {$this->load_capacity} kg max capacity (needs {$shipmentWeightKg} kg).";
        }

        return [
            'is_eligible'              => $withinRange && $hasCapacity,
            'within_range'             => $withinRange,
            'has_load_capacity'        => $hasCapacity,
            'same_island'              => $sameIsland,
            'same_municipality'        => $sameMunicipality,
            'same_province'            => $sameProvince,
            'distance_from_hub_km'     => $distFromHubKm,
            'distance_from_courier_km' => $distFromCourierKm,
            'active_load_kg'           => $activeLoadKg,
            'remaining_capacity_kg'    => $remainingCapKg,
            'reason'                   => $reason,
        ];
    }

    public function evaluateLinehaulEligibilityForTransfer(
        LogisticsHub $originHub,
        LogisticsHub $destinationHub,
        float $shipmentWeightKg = 0.0
    ): array {
        $assignedHub = $this->assigned_hub;
        if (! $assignedHub || (int) $this->logistics_profile_id !== (int) $originHub->parent_logistics_profile_id) {
            return [
                'is_eligible'           => false,
                'has_load_capacity'     => false,
                'remaining_capacity_kg' => $this->remaining_load_capacity_kg,
                'reason'                => 'Courier does not belong to this Logistics company.',
            ];
        }

        $activeLoadKg = $this->active_assigned_weight_kg;
        $remainingCapKg = $this->remaining_load_capacity_kg;
        $hasCapacity = $shipmentWeightKg <= 0 || $remainingCapKg >= $shipmentWeightKg;

        return [
            'is_eligible'           => $hasCapacity,
            'has_load_capacity'     => $hasCapacity,
            'active_load_kg'        => $activeLoadKg,
            'remaining_capacity_kg' => $remainingCapKg,
            'origin_hub_name'       => $originHub->hub_name,
            'destination_hub_name'  => $destinationHub->hub_name,
            'reason'                => $hasCapacity
                ? null
                : "Courier {$this->full_name} has {$activeLoadKg} kg active load / {$this->load_capacity} kg max capacity (needs {$shipmentWeightKg} kg).",
        ];
    }
}