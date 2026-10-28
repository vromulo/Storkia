<?php

namespace App\Services;

class CommissionCalculatorService
{
    public const ADMIN_RATE_PERCENT = 10.00;
    public const LOGISTICS_RATE_PERCENT = 10.00;
    public const RIDER_RATE_PERCENT = 10.00;

    public function calculateSplitBase(float $productSubtotal, float $shippingFee): array
    {
        $productSubtotal = round($productSubtotal, 2);
        $shippingFee = round($shippingFee, 2);
        $totalCodAmount = round($productSubtotal + $shippingFee, 2);

        $adminCommission = round($productSubtotal * (self::ADMIN_RATE_PERCENT / 100), 2);
        $sellerNetEarning = round($productSubtotal - $adminCommission, 2);

        $logisticsCommission = round($shippingFee * (self::LOGISTICS_RATE_PERCENT / 100), 2);
        $riderCommission = round($shippingFee * (self::RIDER_RATE_PERCENT / 100), 2);
        $logisticsOperationalRevenue = round($shippingFee - $logisticsCommission - $riderCommission, 2);
        $logisticsTotalEarning = round($logisticsCommission + $logisticsOperationalRevenue, 2);

        return [
            'product_subtotal'              => $productSubtotal,
            'shipping_fee'                  => $shippingFee,
            'total_cod_amount'              => $totalCodAmount,
            'admin_commission_rate'         => self::ADMIN_RATE_PERCENT,
            'admin_commission_amount'       => $adminCommission,
            'seller_net_earning'            => $sellerNetEarning,
            'logistics_commission_rate'     => self::LOGISTICS_RATE_PERCENT,
            'logistics_commission_amount'   => $logisticsCommission,
            'rider_commission_rate'         => self::RIDER_RATE_PERCENT,
            'rider_commission_amount'       => $riderCommission,
            'logistics_operational_revenue' => $logisticsOperationalRevenue,
            'logistics_total_earning'       => $logisticsTotalEarning,
        ];
    }
}