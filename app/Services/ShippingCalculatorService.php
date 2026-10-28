<?php

namespace App\Services;

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

        $normalizedWeight = max(0.01, round($totalWeightKg, 3));
        $rawExcess = max(0.0, round($normalizedWeight - self::EXCESS_WEIGHT_THRESHOLD_KG, 3));

        $chargeableExcessKg = $rawExcess > 0.0 ? (float) ceil($rawExcess) : 0.0;
        $weightFee = round($chargeableExcessKg * self::EXCESS_RATE_PER_KG, 2);
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