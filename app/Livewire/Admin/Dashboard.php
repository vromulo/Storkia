<?php

namespace App\Livewire\Admin;

use App\Models\BuyerAccountSanction;
use App\Models\BuyerBanAppeal;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\IdentityVerification;
use App\Models\LogisticsApplication;
use App\Models\LogisticsProfile;
use App\Models\OrderPaymentSubmission;
use App\Models\ProductApproval;
use App\Models\Rider;
use App\Models\RoleWallet;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\OrderCompletionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'Storkia - Admin Dashboard'])]
class Dashboard extends Component
{
    #[Url(as: 'period', except: '7d')]
    public string $period = '7d';

    #[Url(as: 'offset', except: 0)]
    public int $offset = 0;

    #[Url(as: 'ref_date', except: '')]
    public string $refDate = '';

    public string $chartMetric = 'gmv';

    public function mount(): void
    {
        $reqPeriod = request()->query('period');
        if (is_string($reqPeriod) && in_array($reqPeriod, ['today', '7d', '30d', 'year'], true)) {
            $this->period = $reqPeriod;
        }
        $this->offset = max(0, (int) request()->query('offset', 0));
        $this->refDate = trim((string) request()->query('ref_date', ''));
    }

    public static function resolvePeriodWindow(string $period = '7d', int $offset = 0, ?string $refDate = null): array
    {
        if (! in_array($period, ['today', '7d', '30d', 'year'], true)) {
            $period = '7d';
        }
        $offset = max(0, $offset);
        $now = now();

        $anchor = $now->copy();
        if (! empty($refDate)) {
            try {
                $parsed = Carbon::parse($refDate);
                if ($parsed->lte($now)) {
                    $anchor = $parsed;
                }
            } catch (\Throwable $e) {
            }
        }

        if ($period === 'today') {
            $targetDay = $anchor->copy()->subDays($offset);
            if ($targetDay->gt($now)) {
                $targetDay = $now->copy();
            }
            $startDate = $targetDay->copy()->startOfDay();
            $endDate = $targetDay->copy()->endOfDay();
            $bucketMode = 'hour';
            $isCurrent = $targetDay->isSameDay($now);
            $periodLabel = $isCurrent
                ? 'Today (' . $startDate->format('M d, Y') . ' • 12 AM – 11 PM)'
                : $startDate->format('M d, Y') . ' (12 AM – 11 PM)';
            $dateRangeLabel = $startDate->format('M d, Y') . ' (12:00 AM – 11:00 PM)';
        } elseif ($period === '30d') {
            $endDate = $anchor->copy()->subDays($offset * 30)->endOfDay();
            if ($endDate->gt($now)) {
                $endDate = $now->copy()->endOfDay();
            }
            $startDate = $endDate->copy()->subDays(29)->startOfDay();
            $bucketMode = 'day';
            $isCurrent = $endDate->isSameDay($now);
            $periodLabel = $isCurrent ? 'Last 30 Days' : '30-Day Window';
            $dateRangeLabel = $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y');
        } elseif ($period === 'year') {
            $targetYear = $anchor->copy()->subYears($offset)->year;
            if ($targetYear > $now->year) {
                $targetYear = $now->year;
            }
            $startDate = Carbon::create($targetYear, 1, 1)->startOfYear();
            $endDate = $targetYear === $now->year
                ? $now->copy()->endOfDay()
                : Carbon::create($targetYear, 12, 31)->endOfYear();
            $bucketMode = 'month';
            $isCurrent = $targetYear === $now->year;
            $periodLabel = $isCurrent ? 'This Year (' . $targetYear . ')' : 'Year ' . $targetYear;
            $dateRangeLabel = $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y');
        } else {
            $endDate = $anchor->copy()->subDays($offset * 7)->endOfDay();
            if ($endDate->gt($now)) {
                $endDate = $now->copy()->endOfDay();
            }
            $startDate = $endDate->copy()->subDays(6)->startOfDay();
            $bucketMode = 'day';
            $isCurrent = $endDate->isSameDay($now);
            $periodLabel = $isCurrent ? 'Last 7 Days' : '7-Day Window';
            $dateRangeLabel = $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y');
        }

        return [
            'period'         => $period,
            'offset'         => $offset,
            'refDate'        => $endDate->toDateString(),
            'maxDate'        => $now->toDateString(),
            'canGoNext'      => ! $isCurrent,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'bucketMode'     => $bucketMode,
            'periodLabel'    => $periodLabel,
            'dateRangeLabel' => $dateRangeLabel,
        ];
    }

    public static function buildDashboardPayload(string $period = '7d', int $offset = 0, ?string $refDate = null): array
    {
        try {
            app(OrderCompletionService::class)->runLifecycleMaintenance();
        } catch (\Throwable $e) {
        }

        $window = self::resolvePeriodWindow($period, $offset, $refDate);
        $startDate = $window['startDate'];
        $endDate = $window['endDate'];
        $bucketMode = $window['bucketMode'];
        $periodLabel = $window['periodLabel'];
        $dateRangeLabel = $window['dateRangeLabel'];

        $adminWallet = Schema::hasTable('role_wallets')
            ? RoleWallet::where('owner_type', 'Admin')->first()
            : null;

        $completedCommissions = Schema::hasTable('commission_records')
            ? CommissionRecord::whereBetween('calculated_at', [$startDate, $endDate])->get()
            : collect();

        $periodOrders = Schema::hasTable('seller_orders')
            ? SellerOrder::whereBetween('created_at', [$startDate, $endDate])->get()
            : collect();

        $allTimeGrossGmv = Schema::hasTable('commission_records')
            ? (float) CommissionRecord::sum('product_subtotal')
            : 0.0;
        $allTimeAdmin10 = Schema::hasTable('commission_records')
            ? (float) CommissionRecord::sum('admin_commission_amount')
            : 0.0;
        $allTimeSeller90 = Schema::hasTable('commission_records')
            ? (float) CommissionRecord::sum('seller_net_earning')
            : 0.0;
        $allTimeShipping = Schema::hasTable('commission_records')
            ? (float) CommissionRecord::sum('shipping_fee')
            : 0.0;

        $pendingIdentityCount = Schema::hasTable('identity_verifications')
            ? IdentityVerification::where('status', 'pending')->count()
            : 0;
        $pendingSellerAppsCount = Schema::hasTable('seller_applications')
            ? SellerApplication::where('status', 'pending')->count()
            : 0;
        $pendingLogisticsAppsCount = Schema::hasTable('logistics_applications')
            ? LogisticsApplication::where('status', 'pending')->count()
            : 0;
        $pendingProductApprovals = Schema::hasTable('product_approvals')
            ? ProductApproval::where('status', 'Pending')->count()
            : 0;
        $unresolvedDisputesCount = Schema::hasTable('seller_orders')
            ? SellerOrder::where('has_unresolved_dispute', true)->count()
            : 0;
        $pendingWithdrawalsCount = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::whereIn('status', ['pending', 'approved'])->count()
            : 0;
        $pendingWithdrawalsAmount = Schema::hasTable('withdrawal_requests')
            ? (float) WithdrawalRequest::whereIn('status', ['pending', 'approved'])->sum('requested_amount')
            : 0.0;
        $pendingBanAppealsCount = Schema::hasTable('buyer_ban_appeals')
            ? BuyerBanAppeal::where('status', 'pending')->count()
            : 0;
        $pendingGcashVerifications = Schema::hasTable('order_payment_submissions')
            ? OrderPaymentSubmission::whereIn('status', ['submitted', 'refund_required_seller_timeout'])->count()
            : 0;

        $roleCounts = [
            'buyers'    => Schema::hasTable('users') ? User::where('role', 'Buyer')->whereNull('archived_at')->count() : 0,
            'sellers'   => Schema::hasTable('seller_profiles') ? SellerProfile::count() : 0,
            'logistics' => Schema::hasTable('logistics_profiles') ? LogisticsProfile::count() : 0,
            'couriers'  => Schema::hasTable('couriers') ? Courier::count() : 0,
            'riders'    => Schema::hasTable('riders') ? Rider::count() : 0,
            'suspended' => Schema::hasTable('buyer_account_sanctions')
                ? BuyerAccountSanction::whereIn('account_status', ['temp_banned', 'permanently_banned'])->count()
                : 0,
        ];

        $chartSeries = self::buildChartSeries($startDate, $endDate, $bucketMode, $completedCommissions, $periodOrders);

        $recentOrders = Schema::hasTable('seller_orders')
            ? SellerOrder::with(['buyer', 'sellerProfile', 'logisticsProfile', 'shipment'])
                ->latest('created_at')
                ->limit(8)
                ->get()
            : collect();

        $recentWithdrawals = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::whereIn('status', ['pending', 'approved'])
                ->orderBy('created_at', 'asc')
                ->limit(6)
                ->get()
            : collect();

        $kpis = [
            'admin_wallet_available'   => (float) ($adminWallet?->available_balance ?? $allTimeAdmin10),
            'admin_wallet_pending'     => (float) ($adminWallet?->pending_balance ?? 0),
            'period_gmv'               => round((float) $completedCommissions->sum('product_subtotal'), 2),
            'period_admin_commission'  => round((float) $completedCommissions->sum('admin_commission_amount'), 2),
            'period_seller_net'        => round((float) $completedCommissions->sum('seller_net_earning'), 2),
            'period_shipping_volume'   => round((float) $completedCommissions->sum('shipping_fee'), 2),
            'period_orders_total'      => $periodOrders->count(),
            'period_orders_completed'  => $periodOrders->where('status', 'completed')->count(),
            'period_orders_cancelled'  => $periodOrders->whereIn('status', ['cancelled', 'rejected', 'returned_to_seller'])->count(),
            'all_time_gmv'             => round($allTimeGrossGmv, 2),
            'all_time_admin_10'        => round($allTimeAdmin10, 2),
            'all_time_seller_90'       => round($allTimeSeller90, 2),
            'all_time_shipping'        => round($allTimeShipping, 2),
            'pending_identity'         => $pendingIdentityCount,
            'pending_seller_apps'      => $pendingSellerAppsCount,
            'pending_logistics_apps'   => $pendingLogisticsAppsCount,
            'pending_apps_total'       => $pendingIdentityCount + $pendingSellerAppsCount + $pendingLogisticsAppsCount,
            'pending_compliance'       => $pendingProductApprovals,
            'unresolved_disputes'      => $unresolvedDisputesCount,
            'pending_withdrawals'      => $pendingWithdrawalsCount,
            'pending_withdrawals_amt'  => round($pendingWithdrawalsAmount, 2),
            'pending_appeals'          => $pendingBanAppealsCount,
            'pending_gcash_escrow'     => $pendingGcashVerifications,
            'active_shipments'         => Schema::hasTable('shipments')
                ? Shipment::whereNotIn('status', ['delivered', 'returned_to_seller'])->count()
                : 0,
        ];

        return [
            'period'            => $window['period'],
            'offset'            => $window['offset'],
            'refDate'           => $window['refDate'],
            'maxDate'           => $window['maxDate'],
            'canGoNext'         => $window['canGoNext'],
            'periodLabel'       => $periodLabel,
            'dateRangeLabel'    => $dateRangeLabel,
            'kpis'              => $kpis,
            'roleCounts'        => $roleCounts,
            'chartSeries'       => $chartSeries,
            'recentOrders'      => $recentOrders,
            'recentWithdrawals' => $recentWithdrawals,
        ];
    }

    protected static function buildChartSeries(
        Carbon $startDate,
        Carbon $endDate,
        string $bucketMode,
        $commissions,
        $orders
    ): array {
        $buckets = [];

        if ($bucketMode === 'hour') {
            // Hourly operation buckets from 12 AM (00:00) through 11 PM (23:00)
            for ($h = 0; $h <= 23; $h++) {
                $slotStart = $startDate->copy()->addHours($h);
                $slotEnd = $slotStart->copy()->addHour()->subSecond();
                $key = $slotStart->format('H:00');
                $buckets[$key] = [
                    'label'            => $slotStart->format('g A'),
                    'full_date'        => $slotStart->format('M d, Y') . ' • ' . $slotStart->format('g:00 A') . ' – ' . $slotStart->format('g:59 A'),
                    'gmv'              => 0.0,
                    'admin_commission' => 0.0,
                    'shipping'         => 0.0,
                    'orders'           => 0,
                    'start'            => $slotStart,
                    'end'              => $slotEnd,
                ];
            }
        } elseif ($bucketMode === 'month') {
            for ($m = 1; $m <= 12; $m++) {
                $slotStart = Carbon::create($startDate->year, $m, 1)->startOfMonth();
                $slotEnd = $slotStart->copy()->endOfMonth();
                $key = $slotStart->format('Y-m');
                $buckets[$key] = [
                    'label'            => $slotStart->format('M Y'),
                    'full_date'        => $slotStart->format('F Y') . ' (' . $slotStart->format('M d') . ' – ' . $slotEnd->format('M d, Y') . ')',
                    'gmv'              => 0.0,
                    'admin_commission' => 0.0,
                    'shipping'         => 0.0,
                    'orders'           => 0,
                    'start'            => $slotStart,
                    'end'              => $slotEnd,
                ];
            }
        } else {
            $cursor = $startDate->copy()->startOfDay();
            while ($cursor->lte($endDate)) {
                $key = $cursor->format('Y-m-d');
                $buckets[$key] = [
                    'label'            => $cursor->format('M d'),
                    'full_date'        => $cursor->format('D, M d, Y'),
                    'gmv'              => 0.0,
                    'admin_commission' => 0.0,
                    'shipping'         => 0.0,
                    'orders'           => 0,
                    'start'            => $cursor->copy()->startOfDay(),
                    'end'              => $cursor->copy()->endOfDay(),
                ];
                $cursor->addDay();
            }
        }

        foreach ($commissions as $rec) {
            $ts = $rec->calculated_at ? Carbon::parse($rec->calculated_at) : ($rec->created_at ? Carbon::parse($rec->created_at) : null);
            if (! $ts) {
                continue;
            }
            foreach ($buckets as &$b) {
                if ($ts->betweenIncluded($b['start'], $b['end'])) {
                    $b['gmv'] = round($b['gmv'] + (float) $rec->product_subtotal, 2);
                    $b['admin_commission'] = round($b['admin_commission'] + (float) $rec->admin_commission_amount, 2);
                    $b['shipping'] = round($b['shipping'] + (float) $rec->shipping_fee, 2);
                    break;
                }
            }
            unset($b);
        }

        foreach ($orders as $ord) {
            $ts = $ord->created_at ? Carbon::parse($ord->created_at) : null;
            if (! $ts) {
                continue;
            }
            foreach ($buckets as &$b) {
                if ($ts->betweenIncluded($b['start'], $b['end'])) {
                    $b['orders']++;
                    break;
                }
            }
            unset($b);
        }

        return array_values(array_map(fn ($item) => [
            'label'            => $item['label'],
            'full_date'        => $item['full_date'],
            'gmv'              => $item['gmv'],
            'admin_commission' => $item['admin_commission'],
            'shipping'         => $item['shipping'],
            'orders'           => $item['orders'],
        ], $buckets));
    }

    public function render()
    {
        $payload = self::buildDashboardPayload($this->period, $this->offset, $this->refDate ?: null);
        $payload['chartMetric'] = $this->chartMetric;

        return view('livewire.admin.dashboard', $payload);
    }
}