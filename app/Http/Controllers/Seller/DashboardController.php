<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Livewire\Admin\Dashboard as AdminDashboardHelper;
use App\Models\CommissionRecord;
use App\Models\OrderItemReview;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $latestApp = SellerApplication::where('user_id', $user->id)
            ->orderByDesc('version')
            ->first();

        $period = (string) $request->query('period', '7d');
        $offset = max(0, (int) $request->query('offset', 0));
        $refDateInput = trim((string) $request->query('ref_date', '')) ?: null;

        $window = AdminDashboardHelper::resolvePeriodWindow($period, $offset, $refDateInput);
        $period = $window['period'];
        $offset = $window['offset'];
        $refDate = $window['refDate'];
        $maxDate = $window['maxDate'];
        $canGoNext = $window['canGoNext'];
        $startDate = $window['startDate'];
        $endDate = $window['endDate'];
        $bucketMode = $window['bucketMode'];
        $periodLabel = $window['periodLabel'];
        $dateRangeLabel = $window['dateRangeLabel'];

        $walletAvailable = 0.0;
        $walletPendingEscrow = 0.0;
        $kpis = [
            'period_net_sales'   => 0.0,
            'period_gross_sales' => 0.0,
            'period_orders'      => 0,
            'pending_acceptance' => 0,
            'to_ship'            => 0,
            'disputed'           => 0,
            'active_products'    => 0,
            'low_stock_products' => 0,
            'avg_rating'         => 0.0,
        ];
        $chartSeries = [];
        $recentOrders = collect();
        $lowStockItems = collect();

        if ($profile) {
            $this->orderCompletionService->runLifecycleMaintenance(sellerId: (int) $user->id);

            $wallet = $this->roleWalletService->getOrCreateWallet(
                ownerType: 'Seller',
                ownerId: (int) $profile->id,
                ownerName: $profile->business_name
            );
            $walletAvailable = (float) $wallet->available_balance;
            $walletPendingEscrow = (float) $wallet->pending_balance;

            $periodOrders = SellerOrder::where('seller_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $periodCommissions = CommissionRecord::where('seller_profile_id', $profile->id)
                ->whereBetween('calculated_at', [$startDate, $endDate])
                ->get();

            $validPeriodOrders = $periodOrders->whereNotIn('status', ['cancelled', 'rejected', 'returned_to_seller']);
            $grossMerchandise = round((float) $validPeriodOrders->sum('subtotal_amount'), 2);
            $realizedNetSales = round((float) $periodCommissions->sum('seller_net_earning'), 2);
            $estimatedNetSales = $realizedNetSales > 0 ? $realizedNetSales : round($grossMerchandise * 0.90, 2);

            $kpis = [
                'period_net_sales'   => $estimatedNetSales,
                'period_gross_sales' => $grossMerchandise,
                'period_orders'      => $periodOrders->count(),
                'pending_acceptance' => SellerOrder::where('seller_id', $user->id)->where('status', 'pending_seller_acceptance')->count(),
                'to_ship'            => SellerOrder::where('seller_id', $user->id)->whereIn('status', ['accepted', 'preparing', 'ready_for_pickup'])->count(),
                'disputed'           => SellerOrder::where('seller_id', $user->id)->where('status', 'disputed')->count(),
                'active_products'    => Product::where('user_id', $user->id)->count(),
                'low_stock_products' => Product::where('user_id', $user->id)->where('stock_quantity', '<=', 5)->count(),
                'avg_rating'         => round((float) (OrderItemReview::where('seller_profile_id', $profile->id)->avg('rating') ?? 0), 2),
            ];

            $chartSeries = $this->buildSellerChartSeries($startDate, $endDate, $bucketMode, $periodOrders, $periodCommissions);

            $recentOrders = SellerOrder::with(['buyer', 'items', 'shipment'])
                ->where('seller_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            $lowStockItems = Product::where('user_id', $user->id)
                ->where('stock_quantity', '<=', 5)
                ->orderBy('stock_quantity', 'asc')
                ->limit(5)
                ->get();
        }

        return view('seller.seller-dashboard', compact(
            'latestApp',
            'profile',
            'period',
            'offset',
            'refDate',
            'maxDate',
            'canGoNext',
            'periodLabel',
            'dateRangeLabel',
            'walletAvailable',
            'walletPendingEscrow',
            'kpis',
            'chartSeries',
            'recentOrders',
            'lowStockItems'
        ));
    }

    public function showReapply(Request $request)
    {
        $latestApp = SellerApplication::where('user_id', $request->user()->id)
            ->orderByDesc('version')
            ->first();

        if (! $latestApp || $latestApp->status !== 'rejected') {
            return redirect()->route('seller.seller-dashboard');
        }

        return view('seller.reapply', compact('latestApp'));
    }

    protected function buildSellerChartSeries(Carbon $startDate, Carbon $endDate, string $bucketMode, $orders, $commissions): array
    {
        $buckets = [];

        if ($bucketMode === 'hour') {
            // 12 AM (00:00) to 11 PM (23:00)
            for ($h = 0; $h <= 23; $h++) {
                $slotStart = $startDate->copy()->addHours($h);
                $slotEnd = $slotStart->copy()->addHour()->subSecond();
                $buckets[$slotStart->format('H:00')] = [
                    'label'       => $slotStart->format('g A'),
                    'full_date'   => $slotStart->format('M d, Y') . ' • ' . $slotStart->format('g:00 A') . ' – ' . $slotStart->format('g:59 A'),
                    'revenue'     => 0.0,
                    'gross_sales' => 0.0,
                    'orders'      => 0,
                    'start'       => $slotStart,
                    'end'         => $slotEnd,
                ];
            }
        } elseif ($bucketMode === 'month') {
            for ($m = 1; $m <= 12; $m++) {
                $slotStart = Carbon::create($startDate->year, $m, 1)->startOfMonth();
                $slotEnd = $slotStart->copy()->endOfMonth();
                $buckets[$slotStart->format('Y-m')] = [
                    'label'       => $slotStart->format('M Y'),
                    'full_date'   => $slotStart->format('F Y') . ' (' . $slotStart->format('M d') . ' – ' . $slotEnd->format('M d, Y') . ')',
                    'revenue'     => 0.0,
                    'gross_sales' => 0.0,
                    'orders'      => 0,
                    'start'       => $slotStart,
                    'end'         => $slotEnd,
                ];
            }
        } else {
            $cursor = $startDate->copy()->startOfDay();
            while ($cursor->lte($endDate)) {
                $buckets[$cursor->format('Y-m-d')] = [
                    'label'       => $cursor->format('M d'),
                    'full_date'   => $cursor->format('D, M d, Y'),
                    'revenue'     => 0.0,
                    'gross_sales' => 0.0,
                    'orders'      => 0,
                    'start'       => $cursor->copy()->startOfDay(),
                    'end'         => $cursor->copy()->endOfDay(),
                ];
                $cursor->addDay();
            }
        }

        foreach ($orders as $ord) {
            $ts = $ord->created_at ? Carbon::parse($ord->created_at) : null;
            if (! $ts) {
                continue;
            }
            foreach ($buckets as &$b) {
                if ($ts->betweenIncluded($b['start'], $b['end'])) {
                    $b['orders']++;
                    if (! in_array($ord->status, ['cancelled', 'rejected', 'returned_to_seller'], true)) {
                        $sub = (float) $ord->subtotal_amount;
                        $b['gross_sales'] = round($b['gross_sales'] + $sub, 2);
                        $b['revenue'] = round($b['revenue'] + ($sub * 0.90), 2);
                    }
                    break;
                }
            }
            unset($b);
        }

        return array_values(array_map(fn ($i) => [
            'label'       => $i['label'],
            'full_date'   => $i['full_date'],
            'revenue'     => $i['revenue'],
            'gross_sales' => $i['gross_sales'],
            'orders'      => $i['orders'],
        ], $buckets));
    }
}