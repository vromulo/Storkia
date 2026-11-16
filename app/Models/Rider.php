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

class Rider extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'riders';

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
        'license_expiry_date',
        'vehicle_type',
        'plate_number',
        'license_path',
        'or_cr_path',
        'password',
        'password_changed_at',
        'status',
        'approved_at',
        'rejected_at',
        'is_online',
        'last_seen_at',
        'last_checklist_passed_at',
        'sla_strikes',
        'current_latitude',
        'current_longitude',
        'location_accuracy',
        'location_updated_at',
        'vehicle_verification_status',
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
            'approved_at'              => 'datetime',
            'rejected_at'              => 'datetime',
            'password'                 => 'hashed',
            'password_changed_at'      => 'datetime',
            'is_online'                => 'boolean',
            'last_seen_at'             => 'datetime',
            'last_checklist_passed_at' => 'datetime',
            'location_updated_at'      => 'datetime',
            'sla_strikes'              => 'integer',
            'current_latitude'         => 'decimal:7',
            'current_longitude'        => 'decimal:7',
            'location_accuracy'        => 'decimal:2',
        ];
    }

    public function logisticsProfile()
    {
        return $this->belongsTo(LogisticsProfile::class, 'logistics_profile_id');
    }

    public function hubAssignments()
    {
        return $this->hasMany(LogisticsEmployeeHubAssignment::class, 'rider_id')->latest('id');
    }

    public function currentHubAssignment()
    {
        return $this->hasOne(LogisticsEmployeeHubAssignment::class, 'rider_id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function barangayCoverages()
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'rider_id');
    }

    public function activeBarangayCoverages()
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'rider_id')
            ->where('is_active', true)
            ->orderBy('barangay_name');
    }

    public function allBarangayCoverages()
    {
        return $this->hasMany(RiderBarangayCoverage::class, 'rider_id')->latest('id');
    }

    public function deliveryAssignments()
    {
        return $this->hasMany(DeliveryAssignment::class, 'rider_id')->latest('id');
    }

    public function deliveryAttempts()
    {
        return $this->hasMany(DeliveryAttempt::class, 'rider_id')->latest('id');
    }

    public function deliveryProofs()
    {
        return $this->hasMany(DeliveryProof::class, 'rider_id')->latest('id');
    }

    public function codCollections()
    {
        return $this->hasMany(CodCollection::class, 'rider_id')->latest('id');
    }

    public function codRemittances()
    {
        return $this->hasMany(CodRemittance::class, 'rider_id')->latest('id');
    }

    public function commissionRecords()
    {
        return $this->hasMany(CommissionRecord::class, 'rider_id')->latest('id');
    }

    public function locations()
    {
        return $this->hasMany(RiderLocation::class, 'rider_id')->latest('recorded_at');
    }

    public function latestLocation()
    {
        return $this->hasOne(RiderLocation::class, 'rider_id')->latestOfMany('recorded_at');
    }

    public function activeShipments()
    {
        return $this->hasMany(Shipment::class, 'current_rider_id');
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

    public function getCoveredBarangayNamesAttribute(): array
    {
        if ($this->relationLoaded('activeBarangayCoverages')) {
            $coverages = $this->activeBarangayCoverages;
        } elseif ($this->relationLoaded('barangayCoverages')) {
            $coverages = $this->barangayCoverages->where('is_active', true);
        } else {
            $coverages = $this->activeBarangayCoverages()->get();
        }

        return $coverages->pluck('barangay_name')->filter()->unique()->values()->all();
    }

    public function getFormattedBarangayCoverageAttribute(): string
    {
        $names = $this->covered_barangay_names;

        return ! empty($names) ? implode(', ', $names) : 'All Hub Barangays (' . $this->assigned_municipality . ')';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! empty($this->avatar_path) && Storage::disk('public')->exists($this->avatar_path)) {
            return asset('storage/' . ltrim((string) $this->avatar_path, '/'));
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

    public function evaluateDeliveryEligibilityForSnapshot(array $deliverySnapshot, ?int $requiredHubId = null): array
    {
        $hub = $this->assigned_hub;
        if (! $hub) {
            return [
                'is_eligible'            => false,
                'exact_barangay_match'   => false,
                'same_municipality'      => false,
                'same_province'          => false,
                'distance_from_hub_km'   => 9999.0,
                'distance_from_rider_km' => 9999.0,
                'max_radius_km'          => ShippingCalculatorService::MAX_RIDER_DELIVERY_RADIUS_KM,
                'reason'                 => 'Rider does not have an assigned sorting hub.',
            ];
        }

        $addressService = app(AddressService::class);
        $shippingService = app(ShippingCalculatorService::class);

        $hubProv = $addressService->normalizeName($hub->province);
        $hubMun = $addressService->normalizeName($hub->municipality);

        $destProv = $addressService->normalizeName($deliverySnapshot['province'] ?? '');
        $destMun = $addressService->normalizeName($deliverySnapshot['municipality'] ?? '');
        $destBrgy = $addressService->normalizeName($deliverySnapshot['barangay'] ?? '');

        $hubIsland = $shippingService->resolveIslandGroup($hub->province);
        $destIsland = $shippingService->resolveIslandGroup($deliverySnapshot['province'] ?? '');

        $distFromHubKm = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
            [
                'province'     => $hub->province,
                'municipality' => $hub->municipality,
                'barangay'     => $hub->barangay,
                'latitude'     => $hub->latitude,
                'longitude'    => $hub->longitude,
            ],
            $deliverySnapshot,
            $hub,
            null
        );

        $distFromRiderKm = $this->distanceToSnapshotKm($deliverySnapshot);

        $maxRiderRadius = Schema::hasColumn('logistics_hubs', 'max_rider_delivery_radius_km') && (float) ($hub->max_rider_delivery_radius_km ?? 0) > 0
            ? (float) $hub->max_rider_delivery_radius_km
            : ShippingCalculatorService::MAX_RIDER_DELIVERY_RADIUS_KM;

        $sameProvince = $hubProv !== '' && $destProv !== '' && $hubProv === $destProv && $hubIsland === $destIsland;
        $sameMunicipality = $sameProvince && $hubMun !== '' && $destMun !== '' && $hubMun === $destMun;

        $matchesRequiredHub = $requiredHubId === null
            || (int) $hub->id === (int) $requiredHubId
            || $sameMunicipality;

        $coverages = $this->relationLoaded('barangayCoverages')
            ? $this->barangayCoverages->where('is_active', true)
            : $this->activeBarangayCoverages()->get();

        $hasRestrictedBarangays = $coverages->isNotEmpty();

        $exactBrgyMatch = false;
        if (! $hasRestrictedBarangays) {
            $exactBrgyMatch = $sameMunicipality;
        } elseif ($destBrgy !== '' && $sameMunicipality) {
            $exactBrgyMatch = $coverages->contains(function ($cov) use ($addressService, $destMun, $destBrgy) {
                $covMun = $addressService->normalizeName($cov->municipality ?? '');
                $covBrgy = $addressService->normalizeName($cov->barangay_name ?? '');
                if ($destMun !== '' && $covMun !== '' && $covMun !== $destMun) {
                    return false;
                }
                return $covBrgy === $destBrgy;
            });
        }

        $isEligible = $sameProvince && $sameMunicipality && $matchesRequiredHub && (! $hasRestrictedBarangays || $exactBrgyMatch);

        $reason = null;
        if (! $isEligible) {
            if ($hubIsland !== $destIsland || ! $sameProvince) {
                $reason = "Rider {$this->full_name} is assigned to {$hub->municipality}, {$hub->province} and is strictly prohibited from traveling province-to-province to {$deliverySnapshot['municipality']}, {$deliverySnapshot['province']}.";
            } elseif (! $sameMunicipality) {
                $reason = "Rider {$this->full_name} is assigned to {$hub->municipality} and is strictly prohibited from traveling municipality-to-municipality to {$deliverySnapshot['municipality']} (Riders only operate barangay-to-barangay within {$hub->municipality}).";
            } elseif ($hasRestrictedBarangays && ! $exactBrgyMatch) {
                $buyerBrgyName = $deliverySnapshot['barangay'] ?? 'the buyer barangay';
                $reason = "Rider {$this->full_name} in {$hub->municipality} is only assigned to barangays [{$this->formatted_barangay_coverage}] and does not cover Brgy. {$buyerBrgyName}.";
            } else {
                $reason = "Rider {$this->full_name} is not assigned to the destination hub covering {$deliverySnapshot['municipality']}.";
            }
        }

        return [
            'is_eligible'            => $isEligible,
            'exact_barangay_match'   => $exactBrgyMatch,
            'same_municipality'      => $sameMunicipality,
            'same_province'          => $sameProvince,
            'distance_from_hub_km'   => $distFromHubKm,
            'distance_from_rider_km' => $distFromRiderKm,
            'max_radius_km'          => $maxRiderRadius,
            'reason'                 => $reason,
        ];
    }
}