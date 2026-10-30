<?php

namespace App\Services;

use App\Models\SellerOrder;

class CommissionCalculatorService
{
    public const ADMIN_PRODUCT_COMMISSION_RATE = 10.00;
    public const LOGISTICS_SHIPPING_COMMISSION_RATE = 10.00;
    public const RIDER_SHIPPING_COMMISSION_RATE = 10.00;

    public const BASE_COURIER_PICKUP_PAY = 30.00;
    public const BASE_RIDER_DELIVERY_PAY = 50.00;
    public const EXTRA_WEIGHT_BONUS_PER_KG = 5.00;

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

        $basePay = $taskType === 'pickup' ? self::BASE_COURIER_PICKUP_PAY : self::BASE_RIDER_DELIVERY_PAY;

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
                    $distanceBonus = 0.00;
                } else {
                    $distanceTier = 'same_municipality';
                    $distanceBonus = 15.00;
                }
            } else {
                $distanceTier = 'same_province';
                $distanceBonus = 35.00;
            }
        } else {
            $distanceTier = 'inter_provincial';
            $distanceBonus = 60.00;
        }

        $chargeableKg = max(1, (int) ceil(max(0.01, $weightKg)));
        $extraKg = max(0, $chargeableKg - 1);
        $weightBonus = round($extraKg * self::EXTRA_WEIGHT_BONUS_PER_KG, 2);
        $totalPay = round($basePay + $distanceBonus + $weightBonus, 2);

        return [
            'base_pay'       => $basePay,
            'distance_tier'  => $distanceTier,
            'distance_bonus' => $distanceBonus,
            'weight_bonus'   => $weightBonus,
            'total_pay'      => $totalPay,
        ];
    }

    public function calculateSplitBase(float $productSubtotal, float $shippingFee): array
    {
        $cleanSubtotal = max(0.0, round($productSubtotal, 2));
        $cleanShipping = max(0.0, round($shippingFee, 2));
        $totalCod = round($cleanSubtotal + $cleanShipping, 2);

        $adminCommission = round($cleanSubtotal * (self::ADMIN_PRODUCT_COMMISSION_RATE / 100), 2);
        $sellerNetEarning = max(0.0, round($cleanSubtotal - $adminCommission, 2));

        $logisticsCommission = round($cleanShipping * (self::LOGISTICS_SHIPPING_COMMISSION_RATE / 100), 2);
        $riderCommission = max(
            self::BASE_RIDER_DELIVERY_PAY,
            round($cleanShipping * (self::RIDER_SHIPPING_COMMISSION_RATE / 100), 2)
        );
        $logisticsOperational = max(0.0, round($cleanShipping - $logisticsCommission, 2));
        $logisticsTotalEarning = round($logisticsCommission + $logisticsOperational, 2);

        return [
            'product_subtotal'              => $cleanSubtotal,
            'shipping_fee'                  => $cleanShipping,
            'total_cod_amount'              => $totalCod,
            'admin_commission_rate'         => self::ADMIN_PRODUCT_COMMISSION_RATE,
            'admin_commission_amount'       => $adminCommission,
            'seller_net_earning'            => $sellerNetEarning,
            'logistics_commission_rate'     => self::LOGISTICS_SHIPPING_COMMISSION_RATE,
            'logistics_commission_amount'   => $logisticsCommission,
            'rider_commission_rate'         => self::RIDER_SHIPPING_COMMISSION_RATE,
            'rider_commission_amount'       => $riderCommission,
            'logistics_operational_revenue' => $logisticsOperational,
            'logistics_total_earning'       => $logisticsTotalEarning,
        ];
    }

    public function calculateOrderSplitWithSubsidies(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing(['extra.shippingVoucher', 'extra.storeVoucher', 'extra.platformVoucher', 'shipment']);
        $extra = $sellerOrder->extra;

        $originalSubtotal = $extra && (float) $extra->original_subtotal_amount > 0
            ? round((float) $extra->original_subtotal_amount, 2)
            : round((float) $sellerOrder->subtotal_amount, 2);

        $storeVoucherDiscount = $extra ? round((float) $extra->store_voucher_discount, 2) : 0.00;
        $platformVoucherDiscount = $extra ? round((float) $extra->platform_voucher_discount, 2) : 0.00;

        $originalShippingFee = $extra && (float) $extra->original_shipping_fee > 0
            ? round((float) $extra->original_shipping_fee, 2)
            : round((float) $sellerOrder->shipping_fee, 2);

        $shippingVoucherDiscount = $extra ? round((float) $extra->shipping_voucher_discount, 2) : 0.00;

        $shippingSponsor = 'Admin';
        if ($extra?->shippingVoucher && $extra->shippingVoucher->seller_profile_id !== null) {
            $shippingSponsor = 'Seller';
        }

        // Seller's commissionable merchandise base is Original Subtotal minus Seller-sponsored Store Voucher.
        // Admin-sponsored Platform Vouchers (e.g. PLAT100) do NOT reduce Seller's commissionable base!
        $sellerCommissionableBase = max(0.0, round($originalSubtotal - $storeVoucherDiscount, 2));

        $adminGrossCommission = round($sellerCommissionableBase * (self::ADMIN_PRODUCT_COMMISSION_RATE / 100), 2);
        $sellerBaseNetEarning = max(0.0, round($sellerCommissionableBase - $adminGrossCommission, 2));

        $sellerShippingSubsidy = $shippingSponsor === 'Seller' ? $shippingVoucherDiscount : 0.00;
        $adminShippingSubsidy = $shippingSponsor === 'Admin' ? $shippingVoucherDiscount : 0.00;

        $sellerFinalNetEarning = max(0.0, round($sellerBaseNetEarning - $sellerShippingSubsidy, 2));

        $pickupSnap = $sellerOrder->pickup_address_snapshot ?? [];
        $hubSnap = $sellerOrder->shipment?->hub_address_snapshot ?? $pickupSnap;
        $deliverySnap = $sellerOrder->delivery_address_snapshot ?? [];
        $orderWeight = (float) ($sellerOrder->total_weight ?: 0.5);

        $courierPayBreakdown = $this->calculateDistanceScaledDriverPay('pickup', $pickupSnap, $hubSnap, $orderWeight, true);
        $riderPayBreakdown = $this->calculateDistanceScaledDriverPay('delivery', $hubSnap, $deliverySnap, $orderWeight, true);

        $logisticsCommission = round($originalShippingFee * (self::LOGISTICS_SHIPPING_COMMISSION_RATE / 100), 2);
        $riderCommission = $riderPayBreakdown['total_pay'];
        $logisticsOperational = max(0.0, round($originalShippingFee * 0.80, 2));
        $logisticsTotalEarning = round($logisticsCommission + $logisticsOperational, 2);

        $buyerPayableTotal = round((float) $sellerOrder->total_amount, 2);

        return [
            'original_product_subtotal'     => $originalSubtotal,
            'store_voucher_discount'        => $storeVoucherDiscount,
            'platform_voucher_discount'     => $platformVoucherDiscount,
            'product_subtotal'              => $sellerCommissionableBase,
            'original_shipping_fee'         => $originalShippingFee,
            'shipping_voucher_discount'     => $shippingVoucherDiscount,
            'shipping_subsidy_sponsor'      => $shippingSponsor,
            'seller_shipping_subsidy'       => $sellerShippingSubsidy,
            'admin_shipping_subsidy'        => $adminShippingSubsidy,
            'shipping_fee'                  => $originalShippingFee,
            'total_cod_amount'              => $buyerPayableTotal,
            'admin_commission_rate'         => self::ADMIN_PRODUCT_COMMISSION_RATE,
            'admin_commission_amount'       => $adminGrossCommission,
            'seller_net_earning'            => $sellerFinalNetEarning,
            'logistics_commission_rate'     => self::LOGISTICS_SHIPPING_COMMISSION_RATE,
            'logistics_commission_amount'   => $logisticsCommission,
            'rider_commission_rate'         => self::RIDER_SHIPPING_COMMISSION_RATE,
            'rider_commission_amount'       => $riderCommission,
            'courier_pickup_pay'            => $courierPayBreakdown['total_pay'],
            'rider_pay_breakdown'           => $riderPayBreakdown,
            'courier_pay_breakdown'         => $courierPayBreakdown,
            'logistics_operational_revenue' => $logisticsOperational,
            'logistics_total_earning'       => $logisticsTotalEarning,
            'gcash_seller_owes_platform'    => round($adminGrossCommission + $originalShippingFee, 2),
        ];
    }
}