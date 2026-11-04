<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShippingCalculatorService
{
    public const BASE_SAME_MUNICIPALITY = 45.00;
    public const BASE_SAME_PROVINCE = 65.00;
    public const BASE_DIFFERENT_PROVINCE = 100.00;
    public const EXCESS_WEIGHT_THRESHOLD_KG = 1.00;
    public const EXCESS_RATE_PER_KG = 10.00;

    protected const UNSERVICEABLE_PROVINCES = [
        'batanes',
    ];

    protected const UNSERVICEABLE_MUNICIPALITIES = [
        'itbayat',
        'basco',
        'sabtang',
        'ivana',
        'mahatao',
        'uyugan',
        'kalayaan',
    ];

    public function isAddressServiceable(?string $buyerProvince, ?string $buyerMunicipality): bool
    {
        $normProv = $this->normalizeLocation($buyerProvince);
        $normMun = $this->normalizeLocation($buyerMunicipality);

        if ($normProv === '' || $normMun === '') {
            return false;
        }

        if (in_array($normProv, self::UNSERVICEABLE_PROVINCES, true)) {
            return false;
        }

        if (in_array($normMun, self::UNSERVICEABLE_MUNICIPALITIES, true)) {
            return false;
        }

        if (Schema::hasTable('logistics_shipping_rates')) {
            try {
                $blockedZone = DB::table('logistics_shipping_rates')
                    ->where('is_serviceable', false)
                    ->get()
                    ->first(function ($row) use ($normProv, $normMun) {
                        $dest = $this->normalizeLocation($row->destination_region ?? '');
                        return $dest !== '' && ($dest === $normProv || $dest === $normMun);
                    });

                if ($blockedZone) {
                    return false;
                }
            } catch (\Throwable $e) {
            }
        }

        return true;
    }

    public function calculateForSellerOrder(
        string $sellerProvince,
        string $sellerMunicipality,
        string $buyerProvince,
        string $buyerMunicipality,
        float $totalWeightKg
    ): array {
        $normSellerProv = $this->normalizeLocation($sellerProvince);
        $normSellerMun = $this->normalizeLocation($sellerMunicipality);
        $normBuyerProv = $this->normalizeLocation($buyerProvince);
        $normBuyerMun = $this->normalizeLocation($buyerMunicipality);

        $isServiceable = $this->isAddressServiceable($buyerProvince, $buyerMunicipality);

        if ($normSellerProv !== '' && $normSellerProv === $normBuyerProv) {
            if ($normSellerMun !== '' && $normSellerMun === $normBuyerMun) {
                $baseFee = self::BASE_SAME_MUNICIPALITY;
                $tier = 'same_municipality';
                $tierLabel = 'Same Municipality';
            } else {
                $baseFee = self::BASE_SAME_PROVINCE;
                $tier = 'same_province';
                $tierLabel = 'Same Province';
            }
        } else {
            $baseFee = self::BASE_DIFFERENT_PROVINCE;
            $tier = 'inter_province';
            $tierLabel = 'Provincial / Inter-Province';
        }

        $excessRatePerKg = self::EXCESS_RATE_PER_KG;

        $matchedRate = $this->findConfiguredZoneRate($normSellerProv, $normSellerMun, $normBuyerProv, $normBuyerMun, $tier);
        if ($matchedRate) {
            if (! (bool) $matchedRate->is_serviceable) {
                $isServiceable = false;
            }
            if ((float) $matchedRate->first_kg_rate > 0) {
                $baseFee = round((float) $matchedRate->first_kg_rate, 2);
            }
            if ((float) $matchedRate->additional_kg_rate >= 0) {
                $excessRatePerKg = round((float) $matchedRate->additional_kg_rate, 2);
            }
            if (! empty($matchedRate->zone_name)) {
                $tierLabel = (string) $matchedRate->zone_name;
            }
        }

        $normalizedWeight = max(0.01, round($totalWeightKg, 3));
        $rawExcess = max(0.0, round($normalizedWeight - self::EXCESS_WEIGHT_THRESHOLD_KG, 3));
        $chargeableExcessKg = $rawExcess > 0.0 ? (float) ceil($rawExcess) : 0.0;
        $weightFee = round($chargeableExcessKg * $excessRatePerKg, 2);
        $shippingFee = round($baseFee + $weightFee, 2);

        return [
            'is_serviceable'       => $isServiceable,
            'tier'                 => $tier,
            'tier_label'           => $tierLabel,
            'base_fee'             => $baseFee,
            'total_weight'         => $normalizedWeight,
            'excess_weight'        => $rawExcess,
            'chargeable_excess_kg' => $chargeableExcessKg,
            'weight_fee'           => $weightFee,
            'shipping_fee'         => $shippingFee,
        ];
    }

    protected function findConfiguredZoneRate(
        string $normSellerProv,
        string $normSellerMun,
        string $normBuyerProv,
        string $normBuyerMun,
        string $tier
    ): ?object {
        if (! Schema::hasTable('logistics_shipping_rates')) {
            return null;
        }

        try {
            $rates = DB::table('logistics_shipping_rates')->orderByDesc('id')->get();
            if ($rates->isEmpty()) {
                return null;
            }

            foreach ($rates as $rate) {
                $orig = $this->normalizeLocation($rate->origin_region ?? '');
                $dest = $this->normalizeLocation($rate->destination_region ?? '');

                $originMatches = $orig !== '' && ($orig === $normSellerMun || $orig === $normSellerProv || ($this->isNcrAlias($orig) && $this->isNcrAlias($normSellerProv)));
                $destMatches = $dest !== '' && ($dest === $normBuyerMun || $dest === $normBuyerProv || ($this->isNcrAlias($dest) && $this->isNcrAlias($normBuyerProv)));

                if ($originMatches && $destMatches) {
                    return $rate;
                }
            }

            foreach ($rates as $rate) {
                $code = strtolower(trim((string) ($rate->zone_code ?? '')));
                if ($tier === 'same_municipality' && str_contains($code, 'local')) {
                    return $rate;
                }
                if ($tier === 'same_province' && str_contains($code, 'prov')) {
                    return $rate;
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    protected function isNcrAlias(string $normalized): bool
    {
        return in_array($normalized, ['ncr', 'metro manila', 'national capital region', 'manila'], true);
    }

    protected function normalizeLocation(?string $name): string
    {
        if (! $name) {
            return '';
        }

        $cleaned = strtolower(trim($name));
        $cleaned = preg_replace('/\b(city of|municipality of|province of)\b/', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim((string) $cleaned);
    }
}