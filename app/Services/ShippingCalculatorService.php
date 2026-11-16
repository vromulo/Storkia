<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use App\Models\Rider;
use App\Models\SellerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShippingCalculatorService
{
    public const BASE_FEE_SAME_BARANGAY     = 35.00;
    public const BASE_FEE_SAME_MUNICIPALITY = 45.00;
    public const BASE_FEE_SAME_PROVINCE     = 58.00;
    public const BASE_FEE_INTER_PROVINCE    = 75.00;
    public const BASE_FEE_INTER_ISLAND      = 120.00;

    public const EXTRA_WEIGHT_FEE_PER_KG       = 10.00;
    public const EXTRA_WEIGHT_FEE_INTER_ISLAND = 15.00;

    public const MAX_COURIER_PICKUP_RADIUS_KM = 50.00;
    public const MAX_RIDER_DELIVERY_RADIUS_KM = 20.00;

    public function __construct(
        protected AddressService $addressService
    ) {}

    public function isAddressServiceable(?string $province, ?string $municipality = null, ?string $barangay = null): bool
    {
        $normProv = $this->addressService->normalizeName($province);
        if ($normProv === '') {
            return false;
        }

        if (Schema::hasTable('logistics_zone_rates')) {
            $blockedZone = DB::table('logistics_zone_rates')
                ->whereRaw('LOWER(TRIM(province)) = ?', [strtolower(trim((string) $province))])
                ->where(function ($q) use ($municipality) {
                    $q->whereNull('municipality')
                      ->orWhere('municipality', '');
                    if (! empty($municipality)) {
                        $q->orWhereRaw('LOWER(TRIM(municipality)) = ?', [strtolower(trim((string) $municipality))]);
                    }
                })
                ->where('is_serviceable', false)
                ->exists();

            if ($blockedZone) {
                return false;
            }
        }

        $hasLogisticsProfiles = Schema::hasTable('logistics_profiles') && LogisticsProfile::count() > 0;
        if (! $hasLogisticsProfiles) {
            return true;
        }

        $activeHubs = $this->getAllActivePlatformHubs();
        if ($activeHubs->isEmpty()) {
            return true;
        }

        foreach ($activeHubs as $hub) {
            $eval = $this->evaluateDestinationHubForBuyer($hub, (string) $province, (string) $municipality, (string) $barangay, false);
            if ($eval['is_covered']) {
                return true;
            }
        }

        return false;
    }

    public function isRouteServiceable(
        ?string $sellerProvince,
        ?string $sellerMunicipality,
        ?string $buyerProvince,
        ?string $buyerMunicipality,
        ?string $sellerBarangay = null,
        ?string $buyerBarangay = null
    ): array {
        $normBuyerProv = $this->addressService->normalizeName($buyerProvince);
        if ($normBuyerProv === '') {
            return [
                'is_serviceable' => false,
                'reason'         => 'Please select a valid delivery address.',
            ];
        }

        if (Schema::hasTable('logistics_zone_rates')) {
            $blockedZone = DB::table('logistics_zone_rates')
                ->whereRaw('LOWER(TRIM(province)) = ?', [strtolower(trim((string) $buyerProvince))])
                ->where('is_serviceable', false)
                ->exists();

            if ($blockedZone) {
                return [
                    'is_serviceable' => false,
                    'reason'         => "Delivery to {$buyerMunicipality}, {$buyerProvince} is currently marked unserviceable by Logistics Zone Rules.",
                ];
            }
        }

        if (! Schema::hasTable('logistics_profiles') || LogisticsProfile::count() === 0) {
            return [
                'is_serviceable' => true,
                'reason'         => null,
            ];
        }

        $evaluations = $this->evaluateLogisticsProvidersForRoute(
            sellerProvince: (string) $sellerProvince,
            sellerMunicipality: (string) $sellerMunicipality,
            buyerProvince: (string) $buyerProvince,
            buyerMunicipality: (string) $buyerMunicipality,
            sellerBarangay: (string) $sellerBarangay,
            buyerBarangay: (string) $buyerBarangay
        );

        $serviceableProviders = collect($evaluations)->where('is_route_serviceable', true);

        if ($serviceableProviders->isNotEmpty()) {
            return [
                'is_serviceable' => true,
                'reason'         => null,
            ];
        }

        $anyOriginCovered = collect($evaluations)->contains(fn ($e) => ! empty($e['origin_hub']));
        $anyDestCovered = collect($evaluations)->contains(fn ($e) => ! empty($e['destination_hub']));

        $sellerLocLabel = trim(($sellerMunicipality ? $sellerMunicipality . ', ' : '') . $sellerProvince);
        $buyerLocLabel = trim(($buyerMunicipality ? $buyerMunicipality . ', ' : '') . $buyerProvince);

        if (! $anyDestCovered) {
            return [
                'is_serviceable' => false,
                'reason'         => "Delivery to {$buyerLocLabel} is currently outside our Last-Mile Rider coverage (Riders only deliver within their assigned hub municipality or within " . number_format(self::MAX_RIDER_DELIVERY_RADIUS_KM, 0) . " km in the same province to protect rider fuel expenses).",
            ];
        }

        if (! $anyOriginCovered) {
            return [
                'is_serviceable' => false,
                'reason'         => "No Logistics Sorting Hub is currently within First-Mile Courier range (" . number_format(self::MAX_COURIER_PICKUP_RADIUS_KM, 0) . " km / same province) of the seller in {$sellerLocLabel}.",
            ];
        }

        return [
            'is_serviceable' => false,
            'reason'         => "Shipping from {$sellerLocLabel} to {$buyerLocLabel} is unavailable because no single Storkia Logistics Partner operates both an Origin Hub near the seller and a Destination Last-Mile Hub covering {$buyerLocLabel}.",
        ];
    }

    public function evaluateLogisticsProvidersForRoute(
        string $sellerProvince,
        string $sellerMunicipality,
        string $buyerProvince,
        string $buyerMunicipality,
        string $sellerBarangay = '',
        string $buyerBarangay = '',
        ?SellerProfile $sellerProfile = null
    ): array {
        if (! Schema::hasTable('logistics_profiles')) {
            return [];
        }

        $profiles = LogisticsProfile::with([
            'hubs' => fn ($q) => $q->where('status', 'active'),
            'couriers.currentHubAssignment.hub',
            'riders.currentHubAssignment.hub',
            'riders.barangayCoverages',
        ])->orderBy('business_name')->get();

        $results = [];

        foreach ($profiles as $profile) {
            $profile->ensureMainHubExists();
            $activeHubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
                ->where('status', 'active')
                ->get();

            $originCandidates = [];
            $destCandidates = [];

            foreach ($activeHubs as $hub) {
                $origEval = $this->evaluateOriginHubForSeller(
                    hub: $hub,
                    sellerProvince: $sellerProvince,
                    sellerMunicipality: $sellerMunicipality,
                    sellerBarangay: $sellerBarangay,
                    couriers: $profile->couriers,
                    sellerProfile: $sellerProfile
                );

                if ($origEval['is_covered']) {
                    $originCandidates[] = $origEval;
                }

                $destEval = $this->evaluateDestinationHubForBuyer(
                    hub: $hub,
                    buyerProvince: $buyerProvince,
                    buyerMunicipality: $buyerMunicipality,
                    buyerBarangay: $buyerBarangay,
                    requireApprovedRider: false,
                    riders: $profile->riders
                );

                if ($destEval['is_covered']) {
                    $destCandidates[] = $destEval;
                }
            }

            usort($originCandidates, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);
            usort($destCandidates, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

            $bestOrigin = $originCandidates[0] ?? null;
            $bestDest = $destCandidates[0] ?? null;

            $isRouteServiceable = $bestOrigin !== null && $bestDest !== null;
            $hasActiveFleetForBothEnds = $isRouteServiceable
                && ($bestOrigin['active_couriers_count'] > 0 || true)
                && ($bestDest['approved_riders_count'] > 0);

            $unserviceableReason = null;
            if (! $bestOrigin) {
                $unserviceableReason = "No hub in {$sellerProvince} (within " . number_format(self::MAX_COURIER_PICKUP_RADIUS_KM, 0) . " km of store).";
            } elseif (! $bestDest) {
                $unserviceableReason = "No last-mile hub in {$buyerProvince} covering {$buyerMunicipality} (within " . number_format(self::MAX_RIDER_DELIVERY_RADIUS_KM, 0) . " km of buyer).";
            } elseif ($bestDest['approved_riders_count'] <= 0) {
                $unserviceableReason = "Destination hub ({$bestDest['hub']->hub_name}) currently has 0 approved last-mile riders.";
            }

            $results[$profile->id] = [
                'logistics_profile'             => $profile,
                'is_route_serviceable'          => $isRouteServiceable,
                'is_fleet_ready'                => $hasActiveFleetForBothEnds,
                'requires_self_drop_off'        => $bestOrigin ? ($bestOrigin['active_couriers_count'] <= 0) : false,
                'origin_hub'                    => $bestOrigin ? $bestOrigin['hub'] : null,
                'origin_distance_km'            => $bestOrigin ? $bestOrigin['distance_km'] : null,
                'origin_couriers_count'         => $bestOrigin ? $bestOrigin['active_couriers_count'] : 0,
                'destination_hub'               => $bestDest ? $bestDest['hub'] : null,
                'destination_distance_km'       => $bestDest ? $bestDest['distance_km'] : null,
                'destination_riders_count'      => $bestDest ? $bestDest['approved_riders_count'] : 0,
                'destination_exact_brgy_riders' => $bestDest ? $bestDest['exact_barangay_riders_count'] : 0,
                'origin_candidates'             => $originCandidates,
                'destination_candidates'        => $destCandidates,
                'unserviceable_reason'          => $unserviceableReason,
            ];
        }

        return $results;
    }

    public function evaluateOriginHubForSeller(
        LogisticsHub $hub,
        string $sellerProvince,
        string $sellerMunicipality,
        string $sellerBarangay = '',
        ?Collection $couriers = null,
        ?SellerProfile $sellerProfile = null
    ): array {
        $normHubProv = $this->addressService->normalizeName($hub->province);
        $normSellerProv = $this->addressService->normalizeName($sellerProvince);
        $normHubMun = $this->addressService->normalizeName($hub->municipality);
        $normSellerMun = $this->addressService->normalizeName($sellerMunicipality);

        $hubIsland = $this->resolveIslandGroup($hub->province);
        $sellerIsland = $this->resolveIslandGroup($sellerProvince);

        $distanceKm = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
            [
                'province'     => $sellerProvince,
                'municipality' => $sellerMunicipality,
                'barangay'     => $sellerBarangay,
                'latitude'     => $sellerProfile?->latitude,
                'longitude'    => $sellerProfile?->longitude,
            ],
            [
                'province'     => $hub->province,
                'municipality' => $hub->municipality,
                'barangay'     => $hub->barangay,
                'latitude'     => $hub->latitude,
                'longitude'    => $hub->longitude,
            ],
            $sellerProfile,
            $hub
        );

        $maxCourierRadius = Schema::hasColumn('logistics_hubs', 'max_courier_pickup_radius_km') && (float) ($hub->max_courier_pickup_radius_km ?? 0) > 0
            ? (float) $hub->max_courier_pickup_radius_km
            : self::MAX_COURIER_PICKUP_RADIUS_KM;

        $sameProvince = $normHubProv !== '' && $normHubProv === $normSellerProv;
        $sameMunicipality = $sameProvince && $normHubMun !== '' && $normHubMun === $normSellerMun;
        $withinCourierRadius = ($hubIsland === $sellerIsland) && ($sameProvince || $distanceKm <= $maxCourierRadius);

        $allCouriers = $couriers ?? Courier::with('currentHubAssignment.hub')
            ->where('logistics_profile_id', $hub->parent_logistics_profile_id)
            ->whereIn('status', ['active', 'Active', 'on_delivery'])
            ->get();

        $hubCouriers = $allCouriers->filter(function (Courier $c) use ($hub, $normHubMun) {
            if (! in_array($c->status, ['active', 'Active', 'on_delivery'], true)) {
                return false;
            }
            $assignedHub = $c->assigned_hub;
            if ($assignedHub && (int) $assignedHub->id === (int) $hub->id) {
                return true;
            }
            if ($assignedHub && $this->addressService->normalizeName($assignedHub->municipality) === $normHubMun) {
                return true;
            }
            return false;
        });

        return [
            'hub'                   => $hub,
            'is_covered'            => $withinCourierRadius,
            'same_province'         => $sameProvince,
            'same_municipality'     => $sameMunicipality,
            'distance_km'           => $distanceKm,
            'max_radius_km'         => $maxCourierRadius,
            'active_couriers_count' => $hubCouriers->count(),
        ];
    }

    public function evaluateDestinationHubForBuyer(
        LogisticsHub $hub,
        string $buyerProvince,
        string $buyerMunicipality,
        string $buyerBarangay = '',
        bool $requireApprovedRider = false,
        ?Collection $riders = null
    ): array {
        $normHubProv = $this->addressService->normalizeName($hub->province);
        $normBuyerProv = $this->addressService->normalizeName($buyerProvince);
        $normHubMun = $this->addressService->normalizeName($hub->municipality);
        $normBuyerMun = $this->addressService->normalizeName($buyerMunicipality);
        $normBuyerBrgy = $this->addressService->normalizeName($buyerBarangay);

        $hubIsland = $this->resolveIslandGroup($hub->province);
        $buyerIsland = $this->resolveIslandGroup($buyerProvince);

        $distanceKm = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
            [
                'province'     => $hub->province,
                'municipality' => $hub->municipality,
                'barangay'     => $hub->barangay,
                'latitude'     => $hub->latitude,
                'longitude'    => $hub->longitude,
            ],
            [
                'province'     => $buyerProvince,
                'municipality' => $buyerMunicipality,
                'barangay'     => $buyerBarangay,
            ],
            $hub,
            null
        );

        $maxRiderRadius = Schema::hasColumn('logistics_hubs', 'max_rider_delivery_radius_km') && (float) ($hub->max_rider_delivery_radius_km ?? 0) > 0
            ? (float) $hub->max_rider_delivery_radius_km
            : self::MAX_RIDER_DELIVERY_RADIUS_KM;

        $allRiders = $riders ?? Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->where('logistics_profile_id', $hub->parent_logistics_profile_id)
            ->where('status', 'approved')
            ->get();

        $hubRiders = $allRiders->filter(function (Rider $r) use ($hub, $normHubMun) {
            if ($r->status !== 'approved') {
                return false;
            }
            $assignedHub = $r->assigned_hub;
            if ($assignedHub && (int) $assignedHub->id === (int) $hub->id) {
                return true;
            }
            if ($assignedHub && $this->addressService->normalizeName($assignedHub->municipality) === $normHubMun) {
                return true;
            }
            return false;
        });

        $explicitCoverageMatch = $hubRiders->contains(function (Rider $r) use ($normBuyerMun, $normBuyerBrgy) {
            return $r->barangayCoverages->contains(function ($cov) use ($normBuyerMun, $normBuyerBrgy) {
                if (! $cov->is_active) {
                    return false;
                }
                $covMun = $this->addressService->normalizeName($cov->municipality ?? '');
                $covBrgy = $this->addressService->normalizeName($cov->barangay_name ?? '');
                if ($normBuyerMun !== '' && $covMun !== '' && $covMun !== $normBuyerMun) {
                    return false;
                }
                return $normBuyerBrgy === '' || $covBrgy === $normBuyerBrgy;
            });
        });

        $exactBrgyRidersCount = $hubRiders->filter(function (Rider $r) use ($normBuyerBrgy) {
            if ($normBuyerBrgy === '') {
                return false;
            }
            return $r->barangayCoverages->contains(function ($cov) use ($normBuyerBrgy) {
                return $cov->is_active && $this->addressService->normalizeName($cov->barangay_name ?? '') === $normBuyerBrgy;
            });
        })->count();

        $sameProvince = $normHubProv !== '' && $normHubProv === $normBuyerProv;
        $sameMunicipality = $sameProvince && ($normBuyerMun === '' || $normHubMun === $normBuyerMun);

        $isGeographicallyCovered = ($hubIsland === $buyerIsland)
            && $sameProvince
            && ($sameMunicipality || $explicitCoverageMatch || $distanceKm <= $maxRiderRadius);

        $isCovered = $requireApprovedRider
            ? ($isGeographicallyCovered && $hubRiders->count() > 0)
            : $isGeographicallyCovered;

        return [
            'hub'                         => $hub,
            'is_covered'                  => $isCovered,
            'same_province'               => $sameProvince,
            'same_municipality'           => $sameMunicipality,
            'explicit_barangay_coverage'  => $explicitCoverageMatch,
            'distance_km'                 => $distanceKm,
            'max_radius_km'               => $maxRiderRadius,
            'approved_riders_count'       => $hubRiders->count(),
            'exact_barangay_riders_count' => $exactBrgyRidersCount,
        ];
    }

    protected function getAllActivePlatformHubs(): Collection
    {
        $hubs = collect();
        if (Schema::hasTable('logistics_hubs')) {
            $hubs = LogisticsHub::where('status', 'active')->get();
        }

        if ($hubs->isEmpty() && Schema::hasTable('logistics_profiles')) {
            foreach (LogisticsProfile::all() as $lp) {
                $hubs->push($lp->ensureMainHubExists());
            }
        }

        return $hubs;
    }

    public function calculateForSellerOrder(
        string $sellerProvince,
        string $sellerMunicipality,
        string $buyerProvince,
        string $buyerMunicipality,
        float $totalWeightKg,
        string $sellerBarangay = '',
        string $buyerBarangay = ''
    ): array {
        $normSellerProv = $this->addressService->normalizeName($sellerProvince);
        $normBuyerProv = $this->addressService->normalizeName($buyerProvince);
        $normSellerMun = $this->addressService->normalizeName($sellerMunicipality);
        $normBuyerMun = $this->addressService->normalizeName($buyerMunicipality);
        $normSellerBrgy = $this->addressService->normalizeName($sellerBarangay);
        $normBuyerBrgy = $this->addressService->normalizeName($buyerBarangay);

        $sellerIsland = $this->resolveIslandGroup($sellerProvince);
        $buyerIsland = $this->resolveIslandGroup($buyerProvince);

        $routeCheck = $this->isRouteServiceable(
            $sellerProvince,
            $sellerMunicipality,
            $buyerProvince,
            $buyerMunicipality,
            $sellerBarangay,
            $buyerBarangay
        );

        $distanceKm = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
            [
                'province'     => $sellerProvince,
                'municipality' => $sellerMunicipality,
                'barangay'     => $sellerBarangay,
            ],
            [
                'province'     => $buyerProvince,
                'municipality' => $buyerMunicipality,
                'barangay'     => $buyerBarangay,
            ]
        );

        if ($sellerIsland !== $buyerIsland) {
            $tier = 'inter_island';
            $tierLabel = 'Inter-Island (' . ucfirst($sellerIsland) . ' to ' . ucfirst($buyerIsland) . ')';
            $baseFee = self::BASE_FEE_INTER_ISLAND;
            $perExtraKgRate = self::EXTRA_WEIGHT_FEE_INTER_ISLAND;
        } elseif ($normSellerProv !== '' && $normSellerProv === $normBuyerProv) {
            if ($normSellerMun !== '' && $normSellerMun === $normBuyerMun) {
                if ($normSellerBrgy !== '' && $normSellerBrgy === $normBuyerBrgy) {
                    $tier = 'same_barangay';
                    $tierLabel = 'Same Barangay (Local)';
                    $baseFee = self::BASE_FEE_SAME_BARANGAY;
                } else {
                    $tier = 'same_municipality';
                    $tierLabel = 'Same Municipality / City';
                    $baseFee = self::BASE_FEE_SAME_MUNICIPALITY;
                }
            } else {
                $tier = 'same_province';
                $tierLabel = 'Same Province';
                $baseFee = self::BASE_FEE_SAME_PROVINCE;
            }
            $perExtraKgRate = self::EXTRA_WEIGHT_FEE_PER_KG;
        } else {
            $tier = 'inter_province';
            $tierLabel = 'Provincial / Regional (' . ucfirst($buyerIsland) . ')';
            $baseFee = self::BASE_FEE_INTER_PROVINCE;
            $perExtraKgRate = self::EXTRA_WEIGHT_FEE_PER_KG;
        }

        if (Schema::hasTable('logistics_zone_rates') && $buyerProvince !== '') {
            $customZone = DB::table('logistics_zone_rates')
                ->whereRaw('LOWER(TRIM(province)) = ?', [strtolower(trim($buyerProvince))])
                ->where(function ($q) use ($buyerMunicipality) {
                    $q->whereNull('municipality')
                      ->orWhere('municipality', '')
                      ->orWhereRaw('LOWER(TRIM(municipality)) = ?', [strtolower(trim($buyerMunicipality))]);
                })
                ->where('is_serviceable', true)
                ->orderByRaw('CASE WHEN municipality IS NOT NULL AND municipality != "" THEN 0 ELSE 1 END')
                ->first();

            if ($customZone && (float) ($customZone->base_rate_1kg ?? 0) > 0) {
                $baseFee = min(round((float) $customZone->base_rate_1kg, 2), self::BASE_FEE_INTER_ISLAND);
                if ((float) ($customZone->per_extra_kg_rate ?? 0) > 0) {
                    $perExtraKgRate = min(round((float) $customZone->per_extra_kg_rate, 2), self::EXTRA_WEIGHT_FEE_INTER_ISLAND);
                }
            }
        }

        $chargeableKg = max(1, (int) ceil(max(0.01, $totalWeightKg)));
        $extraWeightKg = max(0, $chargeableKg - 1);
        $weightSurcharge = round($extraWeightKg * $perExtraKgRate, 2);
        $totalShippingFee = round($baseFee + $weightSurcharge, 2);

        return [
            'tier'                 => $tier,
            'tier_label'           => $tierLabel,
            'distance_km'          => $distanceKm,
            'base_fee'             => $baseFee,
            'chargeable_weight_kg' => $chargeableKg,
            'extra_weight_kg'      => $extraWeightKg,
            'per_extra_kg_rate'    => $perExtraKgRate,
            'weight_surcharge'     => $weightSurcharge,
            'shipping_fee'         => $totalShippingFee,
            'seller_island_group'  => $sellerIsland,
            'buyer_island_group'   => $buyerIsland,
            'is_serviceable'       => (bool) $routeCheck['is_serviceable'],
            'unserviceable_reason' => $routeCheck['reason'],
        ];
    }

    protected function hasInPlatformInterIslandBridge(string $sellerIsland, string $buyerIsland): bool
    {
        if (! Schema::hasTable('logistics_profiles')) {
            return false;
        }

        $profiles = LogisticsProfile::with(['hubs' => fn ($q) => $q->where('status', 'active')])->get();

        foreach ($profiles as $profile) {
            $islandsCovered = [$this->resolveIslandGroup($profile->province)];

            foreach ($profile->hubs as $hub) {
                if (! empty($hub->province)) {
                    $islandsCovered[] = $this->resolveIslandGroup($hub->province);
                }
            }

            $islandsCovered = array_unique(array_filter($islandsCovered));

            if (in_array($sellerIsland, $islandsCovered, true) && in_array($buyerIsland, $islandsCovered, true)) {
                return true;
            }
        }

        return false;
    }

    public function resolveIslandGroup(?string $provinceName): string
    {
        $norm = $this->addressService->normalizeName($provinceName);
        if ($norm === '') {
            return 'luzon';
        }

        $visayasProvinces = [
            'aklan', 'antique', 'capiz', 'guimaras', 'iloilo', 'negros occidental',
            'bohol', 'cebu', 'negros oriental', 'siquijor',
            'biliran', 'eastern samar', 'leyte', 'northern samar', 'samar', 'western samar', 'southern leyte',
        ];

        $mindanaoProvinces = [
            'zamboanga del norte', 'zamboanga del sur', 'zamboanga sibugay', 'zamboanga',
            'bukidnon', 'camiguin', 'lanao del norte', 'misamis occidental', 'misamis oriental', 'cagayan de oro',
            'davao de oro', 'compostela valley', 'davao del norte', 'davao del sur', 'davao occidental', 'davao oriental', 'davao',
            'cotabato', 'north cotabato', 'sarangani', 'south cotabato', 'sultan kudarat', 'general santos',
            'agusan del norte', 'agusan del sur', 'dinagat islands', 'surigao del norte', 'surigao del sur', 'butuan',
            'basilan', 'lanao del sur', 'maguindanao', 'maguindanao del norte', 'maguindanao del sur', 'sulu', 'tawi tawi',
        ];

        foreach ($visayasProvinces as $vp) {
            if ($norm === $vp || str_contains($norm, $vp)) {
                return 'visayas';
            }
        }

        foreach ($mindanaoProvinces as $mp) {
            if ($norm === $mp || str_contains($norm, $mp)) {
                return 'mindanao';
            }
        }

        $resolved = $this->addressService->resolveProvinceByName((string) $provinceName);
        if ($resolved && ! empty($resolved['islandGroupCode'])) {
            $ig = strtolower(trim((string) $resolved['islandGroupCode']));
            if (in_array($ig, ['luzon', 'visayas', 'mindanao'], true)) {
                return $ig;
            }
        }

        return 'luzon';
    }
}