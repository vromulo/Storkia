<?php

namespace App\Services;

use App\Http\Controllers\Courier\PickupController;
use App\Http\Controllers\Rider\DeliveryController;
use App\Models\Admin;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsProfile;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\RoleWallet;
use App\Models\SellerProfile;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class FormalReportService
{
    public function buildRoleReportPayload(
        string $role,
        int $entityId,
        string $entityName,
        Request $request
    ): array {
        StorkSchemaBootstrapService::ensureTablesExist();

        $period = $this->resolvePeriod($request);
        $from = $period['from'];
        $to = $period['to'];
        $normalizedRole = ucfirst(strtolower(trim($role)));

        $verifierName = $entityName;
        $verifierTitle = 'Authorized Account Holder / Verifier';
        $businessName = $entityName;

        $kpis = [];
        $roleSummaries = [];
        $rows = [];
        $grossTotal = 0.00;
        $deductionTotal = 0.00;
        $netTotal = 0.00;

        if ($normalizedRole === 'Seller') {
            $profile = SellerProfile::with('user')->find($entityId);
            $verifierName = trim((string) ($profile?->user?->full_name ?: Auth::user()?->full_name ?: $entityName));
            $businessName = $profile?->business_name ?: $entityName;
            $verifierTitle = 'Registered Store Owner & Authorized Verifier';

            $wallet = RoleWallet::where('owner_type', 'Seller')->where('owner_id', $entityId)->first();

            $records = CommissionRecord::with(['sellerOrder', 'logisticsProfile'])
                ->where('seller_profile_id', $entityId)
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($records as $rec) {
                $gross = round((float) $rec->product_subtotal, 2);
                $deduction = round((float) $rec->admin_commission_amount, 2);
                $net = round((float) ($rec->seller_net_earning ?? $rec->seller_net_amount ?? 0), 2);

                $grossTotal += $gross;
                $deductionTotal += $deduction;
                $netTotal += $net;

                $orderNo = $rec->sellerOrder->seller_order_number ?? ('#' . $rec->seller_order_id);
                $payMethod = $rec->sellerOrder?->effective_payment_method ?? 'COD';

                $rows[] = [
                    'date'        => $rec->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '',
                    'reference'   => $orderNo,
                    'description' => "Completed Order ({$payMethod}) — 90% Seller Share / 10% Admin Commission",
                    'gross'       => $gross,
                    'deduction'   => $deduction,
                    'net'         => $net,
                    'status'      => strtoupper((string) ($rec->status ?: 'CREDITED')),
                ];
            }

            $kpis = [
                ['label' => 'Completed Orders', 'value' => number_format($records->count())],
                ['label' => 'Gross Product Sales', 'value' => '₱' . number_format($grossTotal, 2)],
                ['label' => 'Admin 10% Fee', 'value' => '-₱' . number_format($deductionTotal, 2)],
                ['label' => 'Net Realized (90%)', 'value' => '₱' . number_format($netTotal, 2)],
                ['label' => 'Available E-Wallet', 'value' => '₱' . number_format((float) ($wallet?->available_balance ?? 0), 2)],
            ];
        } elseif ($normalizedRole === 'Logistics') {
            $profile = LogisticsProfile::with('user')->find($entityId);
            $verifierName = trim((string) ($profile?->user?->full_name ?: Auth::user()?->full_name ?: $entityName));
            $businessName = $profile?->business_name ?: $entityName;
            $verifierTitle = 'Authorized Logistics Hub Manager / Verifier';

            $wallet = RoleWallet::where('owner_type', 'Logistics')->where('owner_id', $entityId)->first();

            $records = CommissionRecord::with(['sellerOrder', 'shipment', 'sellerProfile'])
                ->where('logistics_profile_id', $entityId)
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($records as $rec) {
                $gross = round((float) $rec->shipping_fee, 2);
                $riderShare = round((float) $rec->rider_commission_amount, 2);
                $opsShare = (float) ($rec->logistics_operational_revenue ?? $rec->logistics_operational_amount ?? 0);
                $net = round((float) ($rec->logistics_total_earning ?: ((float) $rec->logistics_commission_amount + $opsShare)), 2);

                $grossTotal += $gross;
                $deductionTotal += $riderShare;
                $netTotal += $net;

                $trk = $rec->shipment->tracking_number ?? ('SHP-#' . $rec->shipment_id);
                $storeName = $rec->sellerProfile->business_name ?? 'Merchant';

                $rows[] = [
                    'date'        => $rec->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '',
                    'reference'   => $trk,
                    'description' => "Delivered Shipment from {$storeName} — 90% Hub Share / 10% Rider Share",
                    'gross'       => $gross,
                    'deduction'   => $riderShare,
                    'net'         => $net,
                    'status'      => 'COMPLETED',
                ];
            }

            $kpis = [
                ['label' => 'Delivered Shipments', 'value' => number_format($records->count())],
                ['label' => 'Gross Shipping Base', 'value' => '₱' . number_format($grossTotal, 2)],
                ['label' => 'Rider 10% Allocation', 'value' => '-₱' . number_format($deductionTotal, 2)],
                ['label' => 'Hub Net (90%)', 'value' => '₱' . number_format($netTotal, 2)],
                ['label' => 'Available Hub Wallet', 'value' => '₱' . number_format((float) ($wallet?->available_balance ?? 0), 2)],
            ];
        } elseif ($normalizedRole === 'Rider') {
            $rider = Rider::with('logisticsProfile')->find($entityId);
            $verifierName = trim((string) ($rider?->full_name ?: $entityName));
            $businessName = $rider?->logisticsProfile?->business_name
                ? ($rider->full_name . ' (' . $rider->logisticsProfile->business_name . ')')
                : $verifierName;
            $verifierTitle = 'Authorized Last-Mile Delivery Rider';

            $wallet = RoleWallet::where('owner_type', 'Rider')->where('owner_id', $entityId)->first();

            $deliveries = DeliveryAssignment::with(['shipment', 'sellerOrder'])
                ->where('rider_id', $entityId)
                ->where('status', 'delivered')
                ->whereBetween('updated_at', [$from, $to])
                ->orderBy('updated_at', 'asc')
                ->get();

            foreach ($deliveries as $deliv) {
                $shipment = $deliv->shipment;
                $pay = $shipment
                    ? DeliveryController::calculateDistanceScaledRiderPay($shipment)
                    : ['total_pay' => 50.00, 'distance_tier' => 'Same Barangay'];
                $amount = round((float) $pay['total_pay'], 2);

                $grossTotal += $amount;
                $netTotal += $amount;

                $rows[] = [
                    'date'        => ($deliv->completed_at ?? $deliv->updated_at)?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '',
                    'reference'   => $shipment->tracking_number ?? ('ASG-#' . $deliv->id),
                    'description' => "Last-Mile Delivery ({$pay['distance_tier']}) — Order #" . ($deliv->sellerOrder->seller_order_number ?? 'N/A'),
                    'gross'       => $amount,
                    'deduction'   => 0.00,
                    'net'         => $amount,
                    'status'      => 'DELIVERED',
                ];
            }

            $kpis = [
                ['label' => 'Completed Deliveries', 'value' => number_format($deliveries->count())],
                ['label' => 'Gross Rider Pay', 'value' => '₱' . number_format($grossTotal, 2)],
                ['label' => 'Deductions', 'value' => '₱0.00'],
                ['label' => 'Net Rider Earnings', 'value' => '₱' . number_format($netTotal, 2)],
                ['label' => 'Available E-Wallet', 'value' => '₱' . number_format((float) ($wallet?->available_balance ?? 0), 2)],
            ];
        } elseif ($normalizedRole === 'Courier') {
            $courier = Courier::with('logisticsProfile')->find($entityId);
            $verifierName = trim((string) ($courier?->full_name ?: $entityName));
            $businessName = $courier?->logisticsProfile?->business_name
                ? ($courier->full_name . ' (' . $courier->logisticsProfile->business_name . ')')
                : $verifierName;
            $verifierTitle = 'Authorized First-Mile Courier';

            $wallet = RoleWallet::where('owner_type', 'Courier')->where('owner_id', $entityId)->first();

            $pickups = PickupAssignment::with(['shipment.sellerProfile', 'sellerOrder'])
                ->where('courier_id', $entityId)
                ->whereIn('status', ['in_transit_to_hub', 'completed'])
                ->whereBetween('updated_at', [$from, $to])
                ->orderBy('updated_at', 'asc')
                ->get();

            foreach ($pickups as $p) {
                $shipment = $p->shipment;
                $pay = $shipment
                    ? PickupController::calculateFirstMileCourierPay($shipment)
                    : ['total_pay' => 30.00, 'distance_tier' => 'Same Municipality'];
                $amount = round((float) $pay['total_pay'], 2);

                $grossTotal += $amount;
                $netTotal += $amount;

                $rows[] = [
                    'date'        => $p->updated_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '',
                    'reference'   => $shipment->tracking_number ?? ('PKP-#' . $p->id),
                    'description' => "First-Mile Pickup ({$pay['distance_tier']}) from " . ($shipment->sellerProfile->business_name ?? 'Seller'),
                    'gross'       => $amount,
                    'deduction'   => 0.00,
                    'net'         => $amount,
                    'status'      => strtoupper($p->status),
                ];
            }

            $kpis = [
                ['label' => 'Completed Pickups', 'value' => number_format($pickups->count())],
                ['label' => 'Gross Haulage Pay', 'value' => '₱' . number_format($grossTotal, 2)],
                ['label' => 'Deductions', 'value' => '₱0.00'],
                ['label' => 'Net Courier Earnings', 'value' => '₱' . number_format($netTotal, 2)],
                ['label' => 'Available E-Wallet', 'value' => '₱' . number_format((float) ($wallet?->available_balance ?? 0), 2)],
            ];
        } else {
            $adminUser = Auth::guard('admin')->user() ?: Admin::find($entityId) ?: Admin::first();
            $verifierName = trim((string) ($adminUser?->name ?: 'Storkia System Administrator'));
            $businessName = 'Storkia Marketplace Administration';
            $verifierTitle = 'Authorized Storkia Platform Administrator';

            $records = CommissionRecord::with(['sellerOrder.extra', 'sellerProfile', 'logisticsProfile', 'shipment'])
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at', 'asc')
                ->get();

            $totalProductVolume = 0.00;
            $totalShippingVolume = 0.00;
            $totalSellerNet90 = 0.00;
            $totalLogisticsNet90 = 0.00;
            $totalRiderShare10 = 0.00;

            foreach ($records as $rec) {
                $prodSub = round((float) $rec->product_subtotal, 2);
                $shipFee = round((float) $rec->shipping_fee, 2);
                $admin10 = round((float) $rec->admin_commission_amount, 2);
                $seller90 = round((float) ($rec->seller_net_earning ?? $rec->seller_net_amount ?? 0), 2);
                $opsShare = (float) ($rec->logistics_operational_revenue ?? $rec->logistics_operational_amount ?? 0);
                $hub90 = round((float) ($rec->logistics_total_earning ?: ((float) $rec->logistics_commission_amount + $opsShare)), 2);
                $rider10 = round((float) $rec->rider_commission_amount, 2);

                $extra = $rec->sellerOrder?->extra;
                $subsidy = $extra
                    ? round((float) $extra->platform_voucher_discount + (float) $extra->shipping_voucher_discount, 2)
                    : 0.00;

                $netAdminOrder = round($admin10 - $subsidy, 2);

                $totalProductVolume += $prodSub;
                $totalShippingVolume += $shipFee;
                $totalSellerNet90 += $seller90;
                $totalLogisticsNet90 += $hub90;
                $totalRiderShare10 += $rider10;

                $grossTotal += $admin10;
                $deductionTotal += $subsidy;
                $netTotal += $netAdminOrder;

                $orderNo = $rec->sellerOrder->seller_order_number ?? ('#' . $rec->seller_order_id);
                $storeName = $rec->sellerProfile->business_name ?? 'Store';
                $hubName = $rec->logisticsProfile->business_name ?? 'Logistics Hub';

                $rows[] = [
                    'date'        => $rec->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? '',
                    'reference'   => $orderNo,
                    'description' => "{$storeName} via {$hubName} (Base ₱" . number_format($prodSub, 2) . " | Ship ₱" . number_format($shipFee, 2) . ")",
                    'gross'       => $admin10,
                    'deduction'   => $subsidy,
                    'net'         => $netAdminOrder,
                    'status'      => 'SETTLED',
                ];
            }

            $disbursedSeller = Schema::hasTable('withdrawal_requests')
                ? round((float) WithdrawalRequest::where('requester_type', 'Seller')->where('status', 'completed')->whereBetween('paid_at', [$from, $to])->sum('requested_amount'), 2)
                : 0.00;
            $disbursedLogistics = Schema::hasTable('withdrawal_requests')
                ? round((float) WithdrawalRequest::where('requester_type', 'Logistics')->where('status', 'completed')->whereBetween('paid_at', [$from, $to])->sum('requested_amount'), 2)
                : 0.00;
            $disbursedRider = Schema::hasTable('withdrawal_requests')
                ? round((float) WithdrawalRequest::where('requester_type', 'Rider')->where('status', 'completed')->whereBetween('paid_at', [$from, $to])->sum('requested_amount'), 2)
                : 0.00;
            $disbursedCourier = Schema::hasTable('withdrawal_requests')
                ? round((float) WithdrawalRequest::where('requester_type', 'Courier')->where('status', 'completed')->whereBetween('paid_at', [$from, $to])->sum('requested_amount'), 2)
                : 0.00;

            $roleSummaries = [
                [
                    'role'       => 'Seller Stores (90% Merchandise Share)',
                    'count'      => SellerProfile::count(),
                    'gross_base' => round($totalProductVolume, 2),
                    'net_share'  => round($totalSellerNet90, 2),
                    'disbursed'  => $disbursedSeller,
                ],
                [
                    'role'       => 'Logistics Sorting Hubs (90% Shipping Share)',
                    'count'      => LogisticsProfile::count(),
                    'gross_base' => round($totalShippingVolume, 2),
                    'net_share'  => round($totalLogisticsNet90, 2),
                    'disbursed'  => $disbursedLogistics,
                ],
                [
                    'role'       => 'Last-Mile Delivery Riders (10% Ship + Distance Pay)',
                    'count'      => Rider::count(),
                    'gross_base' => round($totalShippingVolume, 2),
                    'net_share'  => round($totalRiderShare10, 2),
                    'disbursed'  => $disbursedRider,
                ],
                [
                    'role'       => 'First-Mile Couriers (Haulage Pickup Pay)',
                    'count'      => Courier::count(),
                    'gross_base' => round($totalShippingVolume, 2),
                    'net_share'  => round((float) RoleWallet::where('owner_type', 'Courier')->sum('total_earned'), 2),
                    'disbursed'  => $disbursedCourier,
                ],
            ];

            $kpis = [
                ['label' => 'Completed Orders', 'value' => number_format($records->count())],
                ['label' => 'Gross Merchandise GMV', 'value' => '₱' . number_format($totalProductVolume, 2)],
                ['label' => 'Admin 10% Commission', 'value' => '₱' . number_format($grossTotal, 2)],
                ['label' => 'Voucher Subsidies', 'value' => '-₱' . number_format($deductionTotal, 2)],
                ['label' => 'Net Admin Profit', 'value' => '₱' . number_format($netTotal, 2)],
            ];
        }

        $pages = $this->paginateRowsForPrint($rows, ! empty($roleSummaries));

        return [
            'document_number'     => 'RPT-' . strtoupper(substr($normalizedRole, 0, 3)) . '-' . now()->timezone('Asia/Manila')->format('Ymd-His'),
            'role'                => $normalizedRole,
            'entity_id'           => $entityId,
            'entity_name'         => $businessName,
            'business_name'       => $businessName,
            'verifier_name'       => $verifierName,
            'account_holder_name' => $verifierName,
            'verifier_title'      => $verifierTitle,
            'generated_at'        => now()->timezone('Asia/Manila')->format('M d, Y h:i A') . ' (PH Time)',
            'period'              => [
                'preset'     => $period['preset'],
                'from_date'  => $period['from_date'],
                'to_date'    => $period['to_date'],
                'days_count' => $period['days_count'],
            ],
            'kpis'                => $kpis,
            'role_summaries'      => $roleSummaries,
            'rows'                => $rows,
            'pages'               => $pages,
            'total_pages'         => count($pages),
            'totals'              => [
                'gross'     => round($grossTotal, 2),
                'deduction' => round($deductionTotal, 2),
                'net'       => round($netTotal, 2),
            ],
        ];
    }

    protected function paginateRowsForPrint(array $rows, bool $hasRoleSummaryTable = false): array
    {
        if (empty($rows)) {
            return [[]];
        }

        $firstPageCapacity = $hasRoleSummaryTable ? 12 : 16;
        $subsequentPageCapacity = 24;

        $pages = [];
        $pages[] = array_slice($rows, 0, $firstPageCapacity);

        $remaining = array_slice($rows, $firstPageCapacity);
        if (! empty($remaining)) {
            foreach (array_chunk($remaining, $subsequentPageCapacity) as $chunk) {
                $pages[] = $chunk;
            }
        }

        return $pages;
    }

    protected function resolvePeriod(Request $request): array
    {
        $preset = (string) $request->query('preset', 'this_month');
        $now = now()->timezone('Asia/Manila');

        if ($preset === 'today') {
            $from = $now->copy()->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'last_7_days') {
            $from = $now->copy()->subDays(6)->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'last_30_days') {
            $from = $now->copy()->subDays(29)->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'this_quarter') {
            $from = $now->copy()->firstOfQuarter()->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'this_year') {
            $from = $now->copy()->startOfYear()->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif (($preset === 'custom' || $request->filled('from_date')) && $request->filled('from_date') && $request->filled('to_date')) {
            $preset = 'custom';
            $from = Carbon::parse($request->query('from_date'), 'Asia/Manila')->startOfDay();
            $to = Carbon::parse($request->query('to_date'), 'Asia/Manila')->endOfDay();
            if ($to->lt($from)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
        } else {
            $preset = 'this_month';
            $from = $now->copy()->startOfMonth()->startOfDay();
            $to = $now->copy()->endOfDay();
        }

        $daysCount = max(1, (int) $from->diffInDays($to) + 1);

        return [
            'preset'     => $preset,
            'from'       => $from,
            'to'         => $to,
            'from_date'  => $from->toDateString(),
            'to_date'    => $to->toDateString(),
            'days_count' => $daysCount,
        ];
    }
}