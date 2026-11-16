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

        if (! Schema::hasTable('logistics_profiles') || LogisticsProfile::count() === 0) {
            return false;
        }

        $activeHubs = $this->getAllActivePlatformHubs();
        if ($activeHubs->isEmpty()) {
            return false;
        }

        $normMun = $this->addressService->normalizeName($municipality);
        if ($normMun === '') {
            foreach ($activeHubs as $hub) {
                if ($this->addressService->normalizeName($hub->province) === $normProv) {
                    return true;
                }
            }

            return false;
        }

        foreach ($activeHubs as $hub) {
            $eval = $this->evaluateDestinationHubForBuyer(
                hub: $hub,
                buyerProvince: (string) $province,
                buyerMunicipality: (string) $municipality,
                buyerBarangay: (string) $barangay,
                requireApprovedRider: true
            );
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
        ?string $buyerBarangay = null,
        float $totalWeightKg = 0.5,
        ?SellerProfile $sellerProfile = null
    ): array {
        $normBuyerProv = $this->addressService->normalizeName($buyerProvince);
        $normBuyerMun = $this->addressService->normalizeName($buyerMunicipality);

        if ($normBuyerProv === '' || $normBuyerMun === '') {
            return [
                'is_serviceable'              => false,
                'reason'                      => 'Please select a complete delivery address with Province, Municipality, and Barangay.',
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        if (Schema::hasTable('logistics_zone_rates')) {
            $blockedZone = DB::table('logistics_zone_rates')
                ->whereRaw('LOWER(TRIM(province)) = ?', [strtolower(trim((string) $buyerProvince))])
                ->where(function ($q) use ($buyerMunicipality) {
                    $q->whereNull('municipality')
                      ->orWhere('municipality', '')
                      ->orWhereRaw('LOWER(TRIM(municipality)) = ?', [strtolower(trim((string) $buyerMunicipality))]);
                })
                ->where('is_serviceable', false)
                ->exists();

            if ($blockedZone) {
                return [
                    'is_serviceable'              => false,
                    'reason'                      => "Delivery to {$buyerMunicipality}, {$buyerProvince} is currently marked unserviceable by Logistics Zone Rules.",
                    'matched_provider'            => null,
                    'origin_hub'                  => null,
                    'destination_hub'             => null,
                    'requires_inter_hub_transfer' => false,
                    'route_badge_label'           => null,
                ];
            }
        }

        if (! Schema::hasTable('logistics_profiles') || LogisticsProfile::count() === 0) {
            return [
                'is_serviceable'              => false,
                'reason'                      => 'No active Logistics Partners are registered on the platform yet.',
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        $evaluations = $this->evaluateLogisticsProvidersForRoute(
            sellerProvince: (string) $sellerProvince,
            sellerMunicipality: (string) $sellerMunicipality,
            buyerProvince: (string) $buyerProvince,
            buyerMunicipality: (string) $buyerMunicipality,
            sellerBarangay: (string) $sellerBarangay,
            buyerBarangay: (string) $buyerBarangay,
            sellerProfile: $sellerProfile,
            totalWeightKg: $totalWeightKg
        );

        $fleetReadyProviders = collect($evaluations)
            ->where('is_fleet_ready', true)
            ->sortBy(function (array $eval) {
                $interHubPenalty = ! empty($eval['requires_inter_hub_transfer']) ? 20 : 0;
                $brgyBonus = ((int) ($eval['destination_exact_brgy_riders'] ?? 0) > 0) ? -10 : 0;
                return $interHubPenalty + $brgyBonus + (float) ($eval['origin_distance_km'] ?? 0) + (float) ($eval['destination_distance_km'] ?? 0);
            })
            ->values();

        if ($fleetReadyProviders->isNotEmpty()) {
            $best = $fleetReadyProviders->first();
            /** @var LogisticsProfile $logProfile */
            $logProfile = $best['logistics_profile'];
            /** @var LogisticsHub $origHub */
            $origHub = $best['origin_hub'];
            /** @var LogisticsHub $destHub */
            $destHub = $best['destination_hub'];
            $requiresTransfer = (int) $origHub->id !== (int) $destHub->id;

            $routeLabel = $requiresTransfer
                ? "{$logProfile->business_name}: {$origHub->municipality} Hub → {$destHub->municipality} Branch Hub → Rider ({$buyerBarangay})"
                : "{$logProfile->business_name}: {$origHub->municipality} Hub → Rider ({$buyerBarangay})";

            return [
                'is_serviceable'              => true,
                'reason'                      => null,
                'matched_provider'            => $logProfile,
                'origin_hub'                  => $origHub,
                'destination_hub'             => $destHub,
                'requires_inter_hub_transfer' => $requiresTransfer,
                'route_badge_label'           => $routeLabel,
            ];
        }

        $sellerIsland = ucfirst($this->resolveIslandGroup($sellerProvince));
        $buyerIsland = ucfirst($this->resolveIslandGroup($buyerProvince));
        $sellerLocLabel = trim(($sellerMunicipality ? $sellerMunicipality . ', ' : '') . $sellerProvince, ', ');
        $buyerBrgyLabel = $buyerBarangay ? "Brgy. {$buyerBarangay}, " : '';
        $buyerLocLabel = trim($buyerBrgyLabel . ($buyerMunicipality ? $buyerMunicipality . ', ' : '') . $buyerProvince, ', ');

        $allPlatformHubs = $this->getAllActivePlatformHubs();
        $hasAnyHubInBuyerProvince = $allPlatformHubs->contains(
            fn (LogisticsHub $h) => $this->addressService->normalizeName($h->province) === $normBuyerProv
        );
        $hasAnyHubInBuyerMunicipality = $allPlatformHubs->contains(
            fn (LogisticsHub $h) => $this->addressService->normalizeName($h->province) === $normBuyerProv
                && $this->addressService->normalizeName($h->municipality) === $normBuyerMun
        );

        $anyOriginHubCovered = collect($evaluations)->contains(fn ($e) => ! empty($e['origin_hub']));
        $anyOriginCourierReady = collect($evaluations)->contains(fn ($e) => ! empty($e['origin_hub']) && (int) ($e['origin_couriers_count'] ?? 0) > 0);
        $anyDestHubCovered = collect($evaluations)->contains(fn ($e) => ! empty($e['destination_hub']));
        $anyDestRiderReady = collect($evaluations)->contains(fn ($e) => ! empty($e['destination_hub']) && (int) ($e['destination_riders_count'] ?? 0) > 0);
        $anySameCompanyBothHubs = collect($evaluations)->contains(fn ($e) => ! empty($e['origin_hub']) && ! empty($e['destination_hub']));

        if (! $hasAnyHubInBuyerProvince) {
            return [
                'is_serviceable'              => false,
                'reason'                      => "Checkout Restricted: Storkia currently has no registered Logistics Hub in {$buyerProvince} ({$buyerIsland}). Please select a serviceable delivery address.",
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        if (! $hasAnyHubInBuyerMunicipality) {
            return [
                'is_serviceable'              => false,
                'reason'                      => "Checkout Restricted: No Logistics Hub is registered in {$buyerMunicipality}, {$buyerProvince}. Last-Mile Riders are strictly limited to barangay-to-barangay delivery within the same municipality and cannot travel municipality-to-municipality.",
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        if (! $anyDestRiderReady && $anyDestHubCovered) {
            return [
                'is_serviceable'              => false,
                'reason'                      => "Checkout Restricted: No approved Last-Mile Rider is currently assigned to cover {$buyerLocLabel} (Riders only operate barangay-to-barangay within {$buyerMunicipality}).",
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        if (! $anyOriginHubCovered) {
            return [
                'is_serviceable'              => false,
                'reason'                      => "Checkout Restricted: No Logistics Origin Hub on {$sellerIsland} covers the seller's location in {$sellerLocLabel}.",
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        if (! $anyOriginCourierReady) {
            return [
                'is_serviceable'              => false,
                'reason'                      => "Checkout Restricted: The Logistics Origin Hub covering {$sellerLocLabel} currently has 0 active First-Mile Couriers with available load capacity to pick up the seller's parcel.",
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        if (! $anySameCompanyBothHubs) {
            return [
                'is_serviceable'              => false,
                'reason'                      => "Checkout Restricted (No Connected Branch Hub): The seller is in {$sellerLocLabel} ({$sellerIsland}) and your address is in {$buyerLocLabel} ({$buyerIsland}), but the Logistics company covering the seller does not have an active Branch Hub in {$buyerMunicipality}, {$buyerProvince} to receive the inter-hub transfer and dispatch a local Rider.",
                'matched_provider'            => null,
                'origin_hub'                  => null,
                'destination_hub'             => null,
                'requires_inter_hub_transfer' => false,
                'route_badge_label'           => null,
            ];
        }

        $firstSpecificReason = collect($evaluations)->pluck('unserviceable_reason')->filter()->first();

        return [
            'is_serviceable'              => false,
            'reason'                      => $firstSpecificReason ?: "Checkout Restricted: No single Logistics Partner currently has an active Origin Courier in {$sellerLocLabel} connected to a Destination Hub with an approved Rider in {$buyerLocLabel}.",
            'matched_provider'            => null,
            'origin_hub'                  => null,
            'destination_hub'             => null,
            'requires_inter_hub_transfer' => false,
            'route_badge_label'           => null,
        ];
    }

    public function evaluateLogisticsProvidersForRoute(
        string $sellerProvince,
        string $sellerMunicipality,
        string $buyerProvince,
        string $buyerMunicipality,
        string $sellerBarangay = '',
        string $buyerBarangay = '',
        ?SellerProfile $sellerProfile = null,
        float $totalWeightKg = 0.5
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
                    sellerProfile: $sellerProfile,
                    totalWeightKg: $totalWeightKg
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

            usort($originCandidates, function ($a, $b) {
                $aHasCourier = ((int) $a['active_couriers_count'] > 0) ? 0 : 1;
                $bHasCourier = ((int) $b['active_couriers_count'] > 0) ? 0 : 1;
                if ($aHasCourier !== $bHasCourier) {
                    return $aHasCourier <=> $bHasCourier;
                }
                return $a['distance_km'] <=> $b['distance_km'];
            });

            usort($destCandidates, function ($a, $b) {
                $aHasRider = ((int) $a['approved_riders_count'] > 0) ? 0 : 1;
                $bHasRider = ((int) $b['approved_riders_count'] > 0) ? 0 : 1;
                if ($aHasRider !== $bHasRider) {
                    return $aHasRider <=> $bHasRider;
                }
                return $a['distance_km'] <=> $b['distance_km'];
            });

            $bestOrigin = $originCandidates[0] ?? null;
            $bestDest = $destCandidates[0] ?? null;

            $isRouteServiceable = $bestOrigin !== null && $bestDest !== null;
            $hasActiveFleetForBothEnds = $isRouteServiceable
                && ((int) ($bestOrigin['active_couriers_count'] ?? 0) > 0)
                && ((int) ($bestDest['approved_riders_count'] ?? 0) > 0);

            $requiresInterHubTransfer = $isRouteServiceable
                && ((int) $bestOrigin['hub']->id !== (int) $bestDest['hub']->id);

            $unserviceableReason = null;
            if (! $bestOrigin) {
                $sellerIslandLabel = ucfirst($this->resolveIslandGroup($sellerProvince));
                $unserviceableReason = "{$profile->business_name} has no active Origin Hub on {$sellerIslandLabel} covering {$sellerMunicipality}, {$sellerProvince}.";
            } elseif ((int) $bestOrigin['active_couriers_count'] <= 0) {
                $unserviceableReason = "Origin Hub ({$bestOrigin['hub']->hub_name}) currently has 0 active First-Mile Couriers with available capacity.";
            } elseif (! $bestDest) {
                $unserviceableReason = "{$profile->business_name} has no active Branch/Main Hub in {$buyerMunicipality}, {$buyerProvince} (Riders only deliver barangay-to-barangay within the same municipality).";
            } elseif ((int) $bestDest['approved_riders_count'] <= 0) {
                $brgyNote = $buyerBarangay !== '' ? "Brgy. {$buyerBarangay}, {$buyerMunicipality}" : $buyerMunicipality;
                $unserviceableReason = "Destination Hub ({$bestDest['hub']->hub_name}) currently has 0 approved Last-Mile Riders covering {$brgyNote}.";
            }

            $results[$profile->id] = [
                'logistics_profile'             => $profile,
                'is_route_serviceable'          => $hasActiveFleetForBothEnds,
                'is_hub_network_connected'      => $isRouteServiceable,
                'is_fleet_ready'                => $hasActiveFleetForBothEnds,
                'requires_inter_hub_transfer'   => $requiresInterHubTransfer,
                'requires_self_drop_off'        => $bestOrigin ? ((int) $bestOrigin['active_couriers_count'] <= 0) : false,
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
        ?SellerProfile $sellerProfile = null,
        float $totalWeightKg = 0.5
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

        $sameIsland = $hubIsland !== '' && $hubIsland === $sellerIsland;
        $sameProvince = $sameIsland && $normHubProv !== '' && $normHubProv === $normSellerProv;
        $sameMunicipality = $sameProvince && $normHubMun !== '' && $normHubMun === $normSellerMun;
        $withinCourierRadius = $sameIsland && ($sameProvince || $distanceKm <= $maxCourierRadius);

        $allCouriers = $couriers ?? Courier::with('currentHubAssignment.hub')
            ->where('logistics_profile_id', $hub->parent_logistics_profile_id)
            ->whereIn('status', ['active', 'Active', 'on_delivery'])
            ->get();

        $hubCouriers = $allCouriers->filter(function (Courier $c) use ($hub, $normHubMun, $totalWeightKg) {
            if (! in_array($c->status, ['active', 'Active', 'on_delivery'], true)) {
                return false;
            }
            if ($totalWeightKg > 0 && $c->remaining_load_capacity_kg < $totalWeightKg) {
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
            'same_island'           => $sameIsland,
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

        $sameIsland = $hubIsland !== '' && $hubIsland === $buyerIsland;
        $sameProvince = $sameIsland && $normHubProv !== '' && $normHubProv === $normBuyerProv;
        $sameMunicipality = $sameProvince && $normHubMun !== '' && $normBuyerMun !== '' && $normHubMun === $normBuyerMun;

        $allRiders = $riders ?? Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->where('logistics_profile_id', $hub->parent_logistics_profile_id)
            ->where('status', 'approved')
            ->get();

        $hubRidersInSameMunicipality = $allRiders->filter(function (Rider $r) use ($hub, $normHubProv, $normHubMun, $normBuyerMun, $normBuyerBrgy) {
            if ($r->status !== 'approved') {
                return false;
            }

            $assignedHub = $r->assigned_hub;
            if (! $assignedHub) {
                return false;
            }

            $riderHubProv = $this->addressService->normalizeName($assignedHub->province);
            $riderHubMun = $this->addressService->normalizeName($assignedHub->municipality);

            $belongsToThisHub = ((int) $assignedHub->id === (int) $hub->id)
                || ($riderHubProv === $normHubProv && $riderHubMun === $normHubMun);

            if (! $belongsToThisHub) {
                return false;
            }

            if ($normBuyerMun !== '' && $riderHubMun !== $normBuyerMun) {
                return false;
            }

            $activeCoverages = $r->relationLoaded('barangayCoverages')
                ? $r->barangayCoverages->where('is_active', true)
                : $r->activeBarangayCoverages()->get();

            if ($activeCoverages->isEmpty()) {
                return true;
            }

            if ($normBuyerBrgy === '') {
                return true;
            }

            return $activeCoverages->contains(function ($cov) use ($normBuyerMun, $normBuyerBrgy) {
                $covMun = $this->addressService->normalizeName($cov->municipality ?? '');
                $covBrgy = $this->addressService->normalizeName($cov->barangay_name ?? '');
                if ($covMun !== '' && $normBuyerMun !== '' && $covMun !== $normBuyerMun) {
                    return false;
                }
                return $covBrgy === $normBuyerBrgy;
            });
        });

        $exactBrgyRidersCount = $hubRidersInSameMunicipality->filter(function (Rider $r) use ($normBuyerBrgy) {
            if ($normBuyerBrgy === '') {
                return false;
            }
            $activeCoverages = $r->relationLoaded('barangayCoverages')
                ? $r->barangayCoverages->where('is_active', true)
                : $r->activeBarangayCoverages()->get();

            if ($activeCoverages->isEmpty()) {
                return true;
            }

            return $activeCoverages->contains(function ($cov) use ($normBuyerBrgy) {
                return $this->addressService->normalizeName($cov->barangay_name ?? '') === $normBuyerBrgy;
            });
        })->count();

        $isGeographicallyCovered = $sameProvince && $sameMunicipality;

        $isCovered = $requireApprovedRider
            ? ($isGeographicallyCovered && $hubRidersInSameMunicipality->count() > 0)
            : $isGeographicallyCovered;

        return [
            'hub'                         => $hub,
            'is_covered'                  => $isCovered,
            'same_province'               => $sameProvince,
            'same_municipality'           => $sameMunicipality,
            'explicit_barangay_coverage'  => $exactBrgyRidersCount > 0,
            'distance_km'                 => $distanceKm,
            'max_radius_km'               => $maxRiderRadius,
            'approved_riders_count'       => $hubRidersInSameMunicipality->count(),
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
        string $buyerBarangay = '',
        ?SellerProfile $sellerProfile = null
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
            sellerProvince: $sellerProvince,
            sellerMunicipality: $sellerMunicipality,
            buyerProvince: $buyerProvince,
            buyerMunicipality: $buyerMunicipality,
            sellerBarangay: $sellerBarangay,
            buyerBarangay: $buyerBarangay,
            totalWeightKg: $totalWeightKg,
            sellerProfile: $sellerProfile
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

        /** @var LogisticsProfile|null $matchedProvider */
        $matchedProvider = $routeCheck['matched_provider'] ?? null;
        /** @var LogisticsHub|null $originHub */
        $originHub = $routeCheck['origin_hub'] ?? null;
        /** @var LogisticsHub|null $destHub */
        $destHub = $routeCheck['destination_hub'] ?? null;

        return [
            'tier'                        => $tier,
            'tier_label'                  => $tierLabel,
            'distance_km'                 => $distanceKm,
            'base_fee'                    => $baseFee,
            'chargeable_weight_kg'        => $chargeableKg,
            'extra_weight_kg'             => $extraWeightKg,
            'per_extra_kg_rate'           => $perExtraKgRate,
            'weight_surcharge'            => $weightSurcharge,
            'shipping_fee'                => $totalShippingFee,
            'seller_island_group'         => $sellerIsland,
            'buyer_island_group'          => $buyerIsland,
            'is_serviceable'              => (bool) $routeCheck['is_serviceable'],
            'unserviceable_reason'        => $routeCheck['reason'],
            'matched_logistics_id'        => $matchedProvider?->id,
            'matched_logistics_name'      => $matchedProvider?->business_name,
            'origin_hub_id'               => $originHub?->id,
            'origin_hub_name'             => $originHub?->hub_name,
            'origin_hub_municipality'     => $originHub?->municipality,
            'destination_hub_id'          => $destHub?->id,
            'destination_hub_name'        => $destHub?->hub_name,
            'destination_hub_municipality'=> $destHub?->municipality,
            'requires_inter_hub_transfer' => (bool) ($routeCheck['requires_inter_hub_transfer'] ?? false),
            'route_badge_label'           => $routeCheck['route_badge_label'] ?? null,
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