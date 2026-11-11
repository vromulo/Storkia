<?php

namespace App\Services;

use App\Models\SellerOrder;

class CommissionCalculatorService
{
    public const ADMIN_PRODUCT_COMMISSION_RATE = 10.00;

    public const RIDER_SHIPPING_SHARE_PERCENT     = 45.00;
    public const LOGISTICS_SHIPPING_SHARE_PERCENT = 30.00;
    public const COURIER_SHIPPING_SHARE_PERCENT   = 25.00;

    public const RIDER_SELF_DROPOFF_SHARE_PERCENT     = 55.00;
    public const LOGISTICS_SELF_DROPOFF_SHARE_PERCENT = 45.00;

    public const EXTRA_WEIGHT_BONUS_PER_KG = 10.00;

    public function calculateStandardShippingPoolForSnapshots(
        array $originSnapshot,
        array $destinationSnapshot,
        float $weightKg
    ): array {
        $origProv = strtolower(trim((string) ($originSnapshot['province'] ?? '')));
        $destProv = strtolower(trim((string) ($destinationSnapshot['province'] ?? '')));
        $origMun = strtolower(trim((string) ($originSnapshot['municipality'] ?? '')));
        $destMun = strtolower(trim((string) ($destinationSnapshot['municipality'] ?? '')));
        $origBrgy = strtolower(trim((string) ($originSnapshot['barangay'] ?? '')));
        $destBrgy = strtolower(trim((string) ($destinationSnapshot['barangay'] ?? '')));

        if ($origProv !== '' && $origProv === $destProv) {
            if ($origMun !== '' && $origMun === $destMun) {
                if ($origBrgy !== '' && $origBrgy === $destBrgy) {
                    $distanceTier = 'same_barangay';
                    $basePool = ShippingCalculatorService::BASE_FEE_SAME_BARANGAY;
                } else {
                    $distanceTier = 'same_municipality';
                    $basePool = ShippingCalculatorService::BASE_FEE_SAME_MUNICIPALITY;
                }
            } else {
                $distanceTier = 'same_province';
                $basePool = ShippingCalculatorService::BASE_FEE_SAME_PROVINCE;
            }
        } else {
            $distanceTier = 'inter_provincial';
            $basePool = ShippingCalculatorService::BASE_FEE_INTER_PROVINCE;
        }

        $chargeableKg = max(1, (int) ceil(max(0.01, $weightKg)));
        $extraKg = max(0, $chargeableKg - 1);
        $extraWeightPool = round($extraKg * self::EXTRA_WEIGHT_BONUS_PER_KG, 2);
        $totalShippingPool = round($basePool + $extraWeightPool, 2);

        return [
            'distance_tier'       => $distanceTier,
            'base_shipping_pool'  => $basePool,
            'extra_weight_pool'   => $extraWeightPool,
            'total_shipping_pool' => $totalShippingPool,
        ];
    }

    public function calculateDistanceScaledDriverPay(
        string $taskType,
        array $originSnapshot,
        array $destinationSnapshot,
        float $weightKg,
        bool $succeeded = true
    ): array {
        if (! $succeeded) {
            return [
                'base_pay'       => 0.00,
                'distance_tier'  => 'failed_attempt',
                'distance_bonus' => 0.00,
                'weight_bonus'   => 0.00,
                'total_pay'      => 0.00,
            ];
        }

        $isPickup = $taskType === 'pickup';
        $shareRate = $isPickup
            ? (self::COURIER_SHIPPING_SHARE_PERCENT / 100)
            : (self::RIDER_SHIPPING_SHARE_PERCENT / 100);

        $poolInfo = $this->calculateStandardShippingPoolForSnapshots($originSnapshot, $destinationSnapshot, $weightKg);
        $minBaseShipping = ShippingCalculatorService::BASE_FEE_SAME_BARANGAY;

        $basePay = round($minBaseShipping * $shareRate, 2);
        $distanceBonus = max(0.00, round(($poolInfo['base_shipping_pool'] - $minBaseShipping) * $shareRate, 2));
        $weightBonus = round($poolInfo['extra_weight_pool'] * $shareRate, 2);
        $totalPay = round($poolInfo['total_shipping_pool'] * $shareRate, 2);

        return [
            'base_pay'       => $basePay,
            'distance_tier'  => $poolInfo['distance_tier'],
            'distance_bonus' => $distanceBonus,
            'weight_bonus'   => $weightBonus,
            'total_pay'      => $totalPay,
        ];
    }

    public function calculateSplitBase(
        float $productSubtotal,
        float $shippingFee,
        float $weightKg = 1.0,
        bool $hasCourierPickup = true
    ): array {
        $cleanSubtotal = max(0.0, round($productSubtotal, 2));
        $cleanShipping = max(0.0, round($shippingFee, 2));
        $totalCod = round($cleanSubtotal + $cleanShipping, 2);

        $adminCommission = round($cleanSubtotal * (self::ADMIN_PRODUCT_COMMISSION_RATE / 100), 2);
        $sellerNetEarning = max(0.0, round($cleanSubtotal - $adminCommission, 2));

        $allocatedShipping = $this->allocatePercentageFromShippingPool($cleanShipping, $hasCourierPickup);

        return [
            'product_subtotal'              => $cleanSubtotal,
            'shipping_fee'                  => $cleanShipping,
            'operational_fare_fee'          => $allocatedShipping['operational_fare_fee'],
            'total_cod_amount'              => $totalCod,
            'admin_commission_rate'         => self::ADMIN_PRODUCT_COMMISSION_RATE,
            'admin_commission_amount'       => $adminCommission,
            'seller_net_earning'            => $sellerNetEarning,
            'logistics_commission_rate'     => $allocatedShipping['logistics_commission_rate'],
            'logistics_commission_amount'   => $allocatedShipping['logistics_commission_amount'],
            'courier_commission_rate'       => $allocatedShipping['courier_commission_rate'],
            'courier_commission_amount'     => $allocatedShipping['courier_commission_amount'],
            'courier_pickup_pay'            => $allocatedShipping['courier_commission_amount'],
            'rider_commission_rate'         => $allocatedShipping['rider_commission_rate'],
            'rider_commission_amount'       => $allocatedShipping['rider_commission_amount'],
            'logistics_operational_revenue' => $allocatedShipping['logistics_operational_revenue'],
            'logistics_total_earning'       => $allocatedShipping['logistics_total_earning'],
        ];
    }

    public function calculateOrderSplitWithSubsidies(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing([
            'extra.shippingVoucher',
            'extra.storeVoucher',
            'extra.platformVoucher',
            'shipment.packages',
            'shipment.latestPickupAssignment',
        ]);

        $extra = $sellerOrder->extra;

        $originalSubtotal = $extra && (float) $extra->original_subtotal_amount > 0
            ? round((float) $extra->original_subtotal_amount, 2)
            : round((float) $sellerOrder->subtotal_amount, 2);

        $storeVoucherDiscount = $extra ? round((float) $extra->store_voucher_discount, 2) : 0.00;
        $platformVoucherDiscount = $extra ? round((float) $extra->platform_voucher_discount, 2) : 0.00;
        $coinsUsed = $extra ? (int) ($extra->coins_used ?? 0) : 0;
        $coinsDiscountAmount = $extra ? round((float) ($extra->coins_discount_amount ?? 0), 2) : 0.00;

        $originalShippingFee = $extra && (float) $extra->original_shipping_fee > 0
            ? round((float) $extra->original_shipping_fee, 2)
            : round((float) $sellerOrder->shipping_fee, 2);

        $shippingVoucherDiscount = $extra ? round((float) $extra->shipping_voucher_discount, 2) : 0.00;

        $shippingSponsor = 'Admin';
        if ($extra?->shippingVoucher && $extra->shippingVoucher->seller_profile_id !== null) {
            $shippingSponsor = 'Seller';
        }

        $sellerCommissionableBase = max(0.0, round($originalSubtotal - $storeVoucherDiscount, 2));
        $adminGrossCommission = round($sellerCommissionableBase * (self::ADMIN_PRODUCT_COMMISSION_RATE / 100), 2);
        $sellerBaseNetEarning = max(0.0, round($sellerCommissionableBase - $adminGrossCommission, 2));

        $sellerShippingSubsidy = $shippingSponsor === 'Seller' ? $shippingVoucherDiscount : 0.00;
        $adminShippingSubsidy = $shippingSponsor === 'Admin' ? $shippingVoucherDiscount : 0.00;

        $sellerFinalNetEarning = max(0.0, round($sellerBaseNetEarning - $sellerShippingSubsidy, 2));

        $shipment = $sellerOrder->shipment;
        $pickupSnap = $sellerOrder->pickup_address_snapshot ?? [];
        $hubSnap = $shipment?->hub_address_snapshot ?? $pickupSnap;
        $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
        $orderWeight = (float) ($sellerOrder->total_weight ?: 0.5);

        $primaryPkg = $shipment?->packages?->first();
        $isSelfDropOff = ($primaryPkg && $primaryPkg->handover_method === 'self_drop_off')
            && $shipment?->current_courier_id === null
            && $shipment?->latestPickupAssignment === null;

        $hasCourierPickup = ! $isSelfDropOff;
        $allocatedShipping = $this->allocatePercentageFromShippingPool($originalShippingFee, $hasCourierPickup);

        $courierPayBreakdown = $this->calculateDistanceScaledDriverPay(
            'pickup',
            $pickupSnap,
            $hubSnap,
            $orderWeight,
            $hasCourierPickup
        );
        $courierPayBreakdown['total_pay'] = $allocatedShipping['courier_commission_amount'];

        $riderPayBreakdown = $this->calculateDistanceScaledDriverPay(
            'delivery',
            $hubSnap,
            $deliverySnap,
            $orderWeight,
            true
        );
        $riderPayBreakdown['total_pay'] = $allocatedShipping['rider_commission_amount'];

        $buyerPayableTotal = round((float) $sellerOrder->total_amount, 2);

        return [
            'original_product_subtotal'     => $originalSubtotal,
            'store_voucher_discount'        => $storeVoucherDiscount,
            'platform_voucher_discount'     => $platformVoucherDiscount,
            'coins_used'                    => $coinsUsed,
            'coins_discount_amount'         => $coinsDiscountAmount,
            'product_subtotal'              => $sellerCommissionableBase,
            'original_shipping_fee'         => $originalShippingFee,
            'shipping_voucher_discount'     => $shippingVoucherDiscount,
            'shipping_subsidy_sponsor'      => $shippingSponsor,
            'seller_shipping_subsidy'       => $sellerShippingSubsidy,
            'admin_shipping_subsidy'        => $adminShippingSubsidy,
            'shipping_fee'                  => $originalShippingFee,
            'operational_fare_fee'          => $allocatedShipping['operational_fare_fee'],
            'total_cod_amount'              => $buyerPayableTotal,
            'admin_commission_rate'         => self::ADMIN_PRODUCT_COMMISSION_RATE,
            'admin_commission_amount'       => $adminGrossCommission,
            'seller_net_earning'            => $sellerFinalNetEarning,
            'logistics_commission_rate'     => $allocatedShipping['logistics_commission_rate'],
            'logistics_commission_amount'   => $allocatedShipping['logistics_commission_amount'],
            'courier_commission_rate'       => $allocatedShipping['courier_commission_rate'],
            'courier_commission_amount'     => $allocatedShipping['courier_commission_amount'],
            'courier_pickup_pay'            => $allocatedShipping['courier_commission_amount'],
            'rider_commission_rate'         => $allocatedShipping['rider_commission_rate'],
            'rider_commission_amount'       => $allocatedShipping['rider_commission_amount'],
            'rider_pay_breakdown'           => $riderPayBreakdown,
            'courier_pay_breakdown'         => $courierPayBreakdown,
            'logistics_operational_revenue' => $allocatedShipping['logistics_operational_revenue'],
            'logistics_total_earning'       => $allocatedShipping['logistics_total_earning'],
            'gcash_seller_owes_platform'    => round($adminGrossCommission + $originalShippingFee, 2),
        ];
    }

    protected function allocatePercentageFromShippingPool(float $shippingFeePool, bool $hasCourierPickup = true): array
    {
        $pool = max(0.0, round($shippingFeePool, 2));

        if ($pool <= 0.0) {
            return [
                'courier_commission_rate'       => 0.00,
                'courier_commission_amount'     => 0.00,
                'rider_commission_rate'         => 0.00,
                'rider_commission_amount'       => 0.00,
                'logistics_commission_rate'     => 0.00,
                'logistics_commission_amount'   => 0.00,
                'logistics_operational_revenue' => 0.00,
                'logistics_total_earning'       => 0.00,
                'operational_fare_fee'          => 0.00,
            ];
        }

        if ($hasCourierPickup) {
            $courierRate = self::COURIER_SHIPPING_SHARE_PERCENT;
            $riderRate = self::RIDER_SHIPPING_SHARE_PERCENT;
            $logisticsRate = self::LOGISTICS_SHIPPING_SHARE_PERCENT;
        } else {
            $courierRate = 0.00;
            $riderRate = self::RIDER_SELF_DROPOFF_SHARE_PERCENT;
            $logisticsRate = self::LOGISTICS_SELF_DROPOFF_SHARE_PERCENT;
        }

        $courierShare = round($pool * ($courierRate / 100), 2);
        $riderShare = round($pool * ($riderRate / 100), 2);
        $hubTotalShare = max(0.0, round($pool - $courierShare - $riderShare, 2));

        $logisticsCommission = round($hubTotalShare * 0.3333, 2);
        $logisticsOperational = max(0.0, round($hubTotalShare - $logisticsCommission, 2));

        return [
            'courier_commission_rate'       => $courierRate,
            'courier_commission_amount'     => $courierShare,
            'rider_commission_rate'         => $riderRate,
            'rider_commission_amount'       => $riderShare,
            'logistics_commission_rate'     => $logisticsRate,
            'logistics_commission_amount'   => $logisticsCommission,
            'logistics_operational_revenue' => $logisticsOperational,
            'logistics_total_earning'       => $hubTotalShare,
            'operational_fare_fee'          => round($courierShare + $riderShare + $logisticsOperational, 2),
        ];
    }
}