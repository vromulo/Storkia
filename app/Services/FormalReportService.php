<?php

namespace App\Services;

use App\Models\CodCollection;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsProfile;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FormalReportService
{
    public function resolvePeriodRange(Request $request): array
    {
        $preset = $request->query('preset', 'this_month');
        $now = now();

        if ($request->filled('from_date') && $request->filled('to_date') && $preset === 'custom') {
            $start = Carbon::parse($request->query('from_date'))->startOfDay();
            $end = Carbon::parse($request->query('to_date'))->endOfDay();
        } else {
            [$start, $end] = match ($preset) {
                'last_7_days'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
                'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
                'this_quarter' => [$now->copy()->firstOfQuarter()->startOfDay(), $now->copy()->endOfDay()],
                'this_year'    => [$now->copy()->startOfYear()->startOfDay(), $now->copy()->endOfDay()],
                default        => [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfDay()],
            };
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $daysCount = max(1, (int) $start->diffInDays($end) + 1);
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subDays($daysCount - 1)->startOfDay();

        return [
            'preset'     => $preset,
            'start'      => $start,
            'end'        => $end,
            'from_date'  => $start->toDateString(),
            'to_date'    => $end->toDateString(),
            'days_count' => $daysCount,
            'prev_start' => $prevStart,
            'prev_end'   => $prevEnd,
        ];
    }

    public static function growthPercentage(float $current, float $previous): float
    {
        if ($previous <= 0.009) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function buildRoleReportPayload(string $role, int $entityId, string $entityName, Request $request): array
    {
        $period = $this->resolvePeriodRange($request);
        $start = $period['start'];
        $end = $period['end'];
        $prevStart = $period['prev_start'];
        $prevEnd = $period['prev_end'];

        $documentNumber = 'RPT-' . strtoupper(substr($role, 0, 3)) . '-' . now()->format('Ymd') . '-' . str_pad((string) $entityId, 4, '0', STR_PAD_LEFT);

        if ($role === 'Seller') {
            $currComms = CommissionRecord::with('sellerOrder')->where('seller_profile_id', $entityId)->whereBetween('calculated_at', [$start, $end])->get();
            $prevComms = CommissionRecord::where('seller_profile_id', $entityId)->whereBetween('calculated_at', [$prevStart, $prevEnd])->get();
            $orders = SellerOrder::where('seller_profile_id', $entityId)->whereBetween('created_at', [$start, $end])->get();

            $currNet = (float) $currComms->sum('seller_net_earning');
            $prevNet = (float) $prevComms->sum('seller_net_earning');

            $kpis = [
                ['label' => 'Gross Commissionable Sales', 'value' => '₱' . number_format((float) $currComms->sum('product_subtotal'), 2)],
                ['label' => 'Admin 10% Platform Fee', 'value' => '-₱' . number_format((float) $currComms->sum('admin_commission_amount'), 2)],
                ['label' => 'Net Seller Profit (90%)', 'value' => '₱' . number_format($currNet, 2)],
                ['label' => 'Period-over-Period Growth', 'value' => (self::growthPercentage($currNet, $prevNet) >= 0 ? '+' : '') . self::growthPercentage($currNet, $prevNet) . '%'],
                ['label' => 'Completed vs Total Orders', 'value' => $orders->where('status', 'completed')->count() . ' / ' . $orders->count()],
            ];

            $rows = $currComms->map(fn ($rec) => [
                'date'        => $rec->calculated_at?->format('Y-m-d h:i A'),
                'reference'   => $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id,
                'description' => 'Completed Merchandise Order (90% Seller / 10% Admin)',
                'gross'       => (float) $rec->product_subtotal,
                'deduction'   => (float) $rec->admin_commission_amount,
                'net'         => (float) $rec->seller_net_earning,
                'status'      => strtoupper($rec->status),
            ])->values()->all();
        } elseif ($role === 'Logistics') {
            $currComms = CommissionRecord::with('sellerOrder')->where('logistics_profile_id', $entityId)->whereBetween('calculated_at', [$start, $end])->get();
            $prevComms = CommissionRecord::where('logistics_profile_id', $entityId)->whereBetween('calculated_at', [$prevStart, $prevEnd])->get();
            $shipments = Shipment::where('logistics_profile_id', $entityId)->whereBetween('created_at', [$start, $end])->get();

            $currNet = (float) $currComms->sum('logistics_total_earning');
            $prevNet = (float) $prevComms->sum('logistics_total_earning');

            $kpis = [
                ['label' => 'Gross Shipping Volume', 'value' => '₱' . number_format((float) $currComms->sum('shipping_fee'), 2)],
                ['label' => '10% Logistics Commission', 'value' => '₱' . number_format((float) $currComms->sum('logistics_commission_amount'), 2)],
                ['label' => '80% Operational Revenue', 'value' => '₱' . number_format((float) $currComms->sum('logistics_operational_revenue'), 2)],
                ['label' => '90% Total Hub Revenue', 'value' => '₱' . number_format($currNet, 2) . ' (' . (self::growthPercentage($currNet, $prevNet) >= 0 ? '+' : '') . self::growthPercentage($currNet, $prevNet) . '%)'],
                ['label' => 'Delivered / Total Shipments', 'value' => $shipments->where('status', 'delivered')->count() . ' / ' . $shipments->count()],
            ];

            $rows = $currComms->map(fn ($rec) => [
                'date'        => $rec->calculated_at?->format('Y-m-d h:i A'),
                'reference'   => $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id,
                'description' => '10% Logistics Comm + 80% Ops Share',
                'gross'       => (float) $rec->shipping_fee,
                'deduction'   => (float) $rec->rider_commission_amount,
                'net'         => (float) $rec->logistics_total_earning,
                'status'      => strtoupper($rec->status),
            ])->values()->all();
        } elseif ($role === 'Rider') {
            $currComms = CommissionRecord::with('sellerOrder')->where('rider_id', $entityId)->whereBetween('calculated_at', [$start, $end])->get();
            $prevComms = CommissionRecord::where('rider_id', $entityId)->whereBetween('calculated_at', [$prevStart, $prevEnd])->get();
            $deliveries = DeliveryAssignment::where('rider_id', $entityId)->whereBetween('created_at', [$start, $end])->get();

            $currNet = (float) $currComms->sum('rider_commission_amount');
            $prevNet = (float) $prevComms->sum('rider_commission_amount');

            $kpis = [
                ['label' => 'Total Assigned Deliveries', 'value' => (string) $deliveries->count()],
                ['label' => 'Successful Deliveries', 'value' => (string) $deliveries->where('status', 'delivered')->count()],
                ['label' => 'Failed Attempts (₱0 Pay)', 'value' => (string) $deliveries->where('status', 'failed')->count()],
                ['label' => 'Distance-Scaled Rider Pay', 'value' => '₱' . number_format($currNet, 2)],
                ['label' => 'Period-over-Period Growth', 'value' => (self::growthPercentage($currNet, $prevNet) >= 0 ? '+' : '') . self::growthPercentage($currNet, $prevNet) . '%'],
            ];

            $rows = $currComms->map(fn ($rec) => [
                'date'        => $rec->calculated_at?->format('Y-m-d h:i A'),
                'reference'   => $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id,
                'description' => 'Distance-Scaled Last-Mile Delivery Pay (Base ₱50+)',
                'gross'       => (float) $rec->shipping_fee,
                'deduction'   => 0.00,
                'net'         => (float) $rec->rider_commission_amount,
                'status'      => strtoupper($rec->status),
            ])->values()->all();
        } elseif ($role === 'Courier') {
            $pickups = PickupAssignment::with(['shipment.sellerProfile', 'sellerOrder'])->where('courier_id', $entityId)->whereBetween('created_at', [$start, $end])->get();
            $completed = $pickups->where('status', 'completed');
            $totalWeight = (float) $completed->sum(fn ($p) => (float) ($p->shipment?->total_weight ?? 0));
            $estimatedPay = $completed->count() * CommissionCalculatorService::BASE_COURIER_PICKUP_PAY;

            $kpis = [
                ['label' => 'Total Pickups Assigned', 'value' => (string) $pickups->count()],
                ['label' => 'Completed Hub Drop-offs', 'value' => (string) $completed->count()],
                ['label' => 'Total Weight Hauled', 'value' => number_format($totalWeight, 2) . ' kg'],
                ['label' => 'First-Mile Pickup Earnings', 'value' => '₱' . number_format($estimatedPay, 2)],
                ['label' => 'Completion Rate', 'value' => ($pickups->count() > 0 ? round(($completed->count() / $pickups->count()) * 100, 1) : 100) . '%'],
            ];

            $rows = $pickups->map(fn ($p) => [
                'date'        => $p->created_at?->format('Y-m-d h:i A'),
                'reference'   => $p->shipment->tracking_number ?? 'N/A',
                'description' => 'First-Mile Pickup from ' . ($p->shipment->sellerProfile->business_name ?? 'Seller'),
                'gross'       => $p->status === 'completed' ? 30.00 : 0.00,
                'deduction'   => 0.00,
                'net'         => $p->status === 'completed' ? 30.00 : 0.00,
                'status'      => strtoupper($p->status),
            ])->values()->all();
        } else {
            // Admin Platform-Wide Report
            $currComms = CommissionRecord::with(['sellerOrder', 'sellerProfile', 'logisticsProfile'])->whereBetween('calculated_at', [$start, $end])->get();
            $prevComms = CommissionRecord::whereBetween('calculated_at', [$prevStart, $prevEnd])->get();

            $currNet = (float) $currComms->sum('admin_commission_amount');
            $prevNet = (float) $prevComms->sum('admin_commission_amount');

            $kpis = [
                ['label' => 'Gross Merchandise Volume (GMV)', 'value' => '₱' . number_format((float) $currComms->sum('product_subtotal'), 2)],
                ['label' => 'Gross Shipping Volume', 'value' => '₱' . number_format((float) $currComms->sum('shipping_fee'), 2)],
                ['label' => 'Admin 10% Platform Revenue', 'value' => '₱' . number_format($currNet, 2)],
                ['label' => 'Seller 90% Net Disbursed', 'value' => '₱' . number_format((float) $currComms->sum('seller_net_earning'), 2)],
                ['label' => 'Period-over-Period Growth', 'value' => (self::growthPercentage($currNet, $prevNet) >= 0 ? '+' : '') . self::growthPercentage($currNet, $prevNet) . '%'],
            ];

            $rows = $currComms->map(fn ($rec) => [
                'date'        => $rec->calculated_at?->format('Y-m-d h:i A'),
                'reference'   => $rec->sellerOrder->seller_order_number ?? '#' . $rec->seller_order_id,
                'description' => ($rec->sellerProfile->business_name ?? 'Seller') . ' via ' . ($rec->logisticsProfile->business_name ?? 'Hub'),
                'gross'       => (float) $rec->product_subtotal,
                'deduction'   => (float) $rec->seller_net_earning,
                'net'         => (float) $rec->admin_commission_amount,
                'status'      => strtoupper($rec->status),
            ])->values()->all();
        }

        return [
            'document_number' => $documentNumber,
            'role'            => $role,
            'entity_name'     => $entityName,
            'period'          => $period,
            'kpis'            => $kpis,
            'rows'            => $rows,
            'totals'          => [
                'gross'     => round(collect($rows)->sum('gross'), 2),
                'deduction' => round(collect($rows)->sum('deduction'), 2),
                'net'       => round(collect($rows)->sum('net'), 2),
            ],
            'generated_at'    => now()->format('F d, Y h:i A'),
        ];
    }
}