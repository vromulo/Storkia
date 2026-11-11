<?php

namespace App\Services;

use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShippingCalculatorService
{
    public const BASE_FEE_SAME_BARANGAY     = 35.00;
    public const BASE_FEE_SAME_MUNICIPALITY = 45.00;
    public const BASE_FEE_SAME_PROVINCE     = 58.00;
    public const BASE_FEE_INTER_PROVINCE    = 75.00;
    public const BASE_FEE_INTER_ISLAND      = 120.00;

    public const EXTRA_WEIGHT_FEE_PER_KG        = 10.00;
    public const EXTRA_WEIGHT_FEE_INTER_ISLAND  = 15.00;

    public function __construct(
        protected AddressService $addressService
    ) {}

    public function isAddressServiceable(?string $province, ?string $municipality = null): bool
    {
        $normProv = $this->addressService->normalizeName($province);
        if ($normProv === '') {
            return false;
        }

        if (Schema::hasTable('logistics_zone_rates')) {
            $blockedZone = DB::table('logistics_zone_rates')
                ->whereRaw('LOWER(TRIM(province)) = ?', [strtolower(trim((string) $province))])
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

        $buyerIsland = $this->resolveIslandGroup($province);

        $hubProvinces = collect();
        if (Schema::hasTable('logistics_hubs')) {
            $hubProvinces = LogisticsHub::where('status', 'active')->pluck('province');
        }
        $mainProvinces = LogisticsProfile::pluck('province');
        $allCoveredProvinces = $hubProvinces->merge($mainProvinces)->filter()->unique();

        if ($allCoveredProvinces->isEmpty()) {
            return true;
        }

        foreach ($allCoveredProvinces as $coveredProv) {
            if ($this->resolveIslandGroup($coveredProv) === $buyerIsland) {
                return true;
            }
        }

        return false;
    }

    public function isRouteServiceable(
        ?string $sellerProvince,
        ?string $sellerMunicipality,
        ?string $buyerProvince,
        ?string $buyerMunicipality
    ): array {
        if (! $this->isAddressServiceable($buyerProvince, $buyerMunicipality)) {
            return [
                'is_serviceable' => false,
                'reason'         => "Delivery to {$buyerMunicipality}, {$buyerProvince} is currently outside our active Storkia Logistics Hub coverage.",
            ];
        }

        $sellerIsland = $this->resolveIslandGroup($sellerProvince);
        $buyerIsland = $this->resolveIslandGroup($buyerProvince);

        if ($sellerIsland !== $buyerIsland) {
            $hasBridgingLogistics = $this->hasInPlatformInterIslandBridge($sellerIsland, $buyerIsland);

            if (! $hasBridgingLogistics) {
                $sGroup = ucfirst($sellerIsland);
                $bGroup = ucfirst($buyerIsland);

                return [
                    'is_serviceable' => false,
                    'reason'         => "Cross-island shipping from {$sellerProvince} ({$sGroup}) to {$buyerProvince} ({$bGroup}) is unavailable because no registered Storkia Logistics Partner operates verified sorting hubs on both islands.",
                ];
            }
        }

        return [
            'is_serviceable' => true,
            'reason'         => null,
        ];
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

        $routeCheck = $this->isRouteServiceable($sellerProvince, $sellerMunicipality, $buyerProvince, $buyerMunicipality);

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