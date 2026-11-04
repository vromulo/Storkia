<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Livewire\Admin\Dashboard as AdminDashboardHelper;
use App\Models\CodRemittance;
use App\Models\LogisticsApplication;
use App\Models\Shipment;
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
        $profile = $user->logisticsProfile;

        $latestApp = LogisticsApplication::where('user_id', $user->id)
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
        $pendingPickupsCount = 0;
        $atHubCount = 0;
        $inTransitCount = 0;
        $failedDeliveriesCount = 0;
        $pendingRemittancesCount = 0;
        $totalShipmentsCount = 0;
        $recentShipments = collect();
        $chartSeries = [];

        if ($profile) {
            $this->orderCompletionService->runLifecycleMaintenance(logisticsProfileId: (int) $profile->id);

            $wallet = $this->roleWalletService->getOrCreateWallet(
                ownerType: 'Logistics',
                ownerId: (int) $profile->id,
                ownerName: $profile->business_name
            );
            $walletAvailable = (float) $wallet->available_balance;
            $walletPendingEscrow = (float) $wallet->pending_balance;

            $baseQuery = Shipment::where('logistics_profile_id', $profile->id);

            $totalShipmentsCount = (clone $baseQuery)->count();
            $pendingPickupsCount = (clone $baseQuery)->whereIn('status', ['pickup_requested', 'courier_assigned', 'awaiting_seller_drop_off'])->count();
            $atHubCount = (clone $baseQuery)->whereIn('status', ['received_at_hub', 'rider_assigned', 'scheduled_redelivery'])->count();
            $inTransitCount = (clone $baseQuery)->where('status', 'out_for_delivery')->count();
            $failedDeliveriesCount = (clone $baseQuery)->whereIn('status', ['delivery_failed', 'returning_to_seller', 'returned_to_seller'])->count();
            $pendingRemittancesCount = CodRemittance::where('logistics_profile_id', $profile->id)->where('status', 'submitted')->count();

            $periodShipments = (clone $baseQuery)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $chartSeries = $this->buildLogisticsChartSeries($startDate, $endDate, $bucketMode, $periodShipments);

            $recentShipments = Shipment::with(['sellerProfile', 'currentCourier', 'currentRider', 'sellerOrder'])
                ->where('logistics_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->paginate(10);
        }

        return view('logistics.logistics-dashboard', compact(
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
            'pendingPickupsCount',
            'atHubCount',
            'inTransitCount',
            'failedDeliveriesCount',
            'pendingRemittancesCount',
            'totalShipmentsCount',
            'recentShipments',
            'chartSeries'
        ));
    }

    public function showReapply(Request $request)
    {
        $latestApp = LogisticsApplication::where('user_id', $request->user()->id)
            ->orderByDesc('version')
            ->first();

        if (! $latestApp || $latestApp->status !== 'rejected') {
            return redirect()->route('logistics.logistics-dashboard');
        }

        return view('logistics.reapply', compact('latestApp'));
    }

    protected function buildLogisticsChartSeries(Carbon $startDate, Carbon $endDate, string $bucketMode, $shipments): array
    {
        $buckets = [];

        if ($bucketMode === 'hour') {
            // 12 AM (00:00) to 11 PM (23:00)
            for ($h = 0; $h <= 23; $h++) {
                $slotStart = $startDate->copy()->addHours($h);
                $slotEnd = $slotStart->copy()->addHour()->subSecond();
                $buckets[$slotStart->format('H:00')] = [
                    'label'          => $slotStart->format('g A'),
                    'full_date'      => $slotStart->format('M d, Y') . ' • ' . $slotStart->format('g:00 A') . ' – ' . $slotStart->format('g:59 A'),
                    'revenue'        => 0.0,
                    'gross_shipping' => 0.0,
                    'shipments'      => 0,
                    'delivered'      => 0,
                    'start'          => $slotStart,
                    'end'            => $slotEnd,
                ];
            }
        } elseif ($bucketMode === 'month') {
            for ($m = 1; $m <= 12; $m++) {
                $slotStart = Carbon::create($startDate->year, $m, 1)->startOfMonth();
                $slotEnd = $slotStart->copy()->endOfMonth();
                $buckets[$slotStart->format('Y-m')] = [
                    'label'          => $slotStart->format('M Y'),
                    'full_date'      => $slotStart->format('F Y') . ' (' . $slotStart->format('M d') . ' – ' . $slotEnd->format('M d, Y') . ')',
                    'revenue'        => 0.0,
                    'gross_shipping' => 0.0,
                    'shipments'      => 0,
                    'delivered'      => 0,
                    'start'          => $slotStart,
                    'end'            => $slotEnd,
                ];
            }
        } else {
            $cursor = $startDate->copy()->startOfDay();
            while ($cursor->lte($endDate)) {
                $buckets[$cursor->format('Y-m-d')] = [
                    'label'          => $cursor->format('M d'),
                    'full_date'      => $cursor->format('D, M d, Y'),
                    'revenue'        => 0.0,
                    'gross_shipping' => 0.0,
                    'shipments'      => 0,
                    'delivered'      => 0,
                    'start'          => $cursor->copy()->startOfDay(),
                    'end'            => $cursor->copy()->endOfDay(),
                ];
                $cursor->addDay();
            }
        }

        foreach ($shipments as $sh) {
            $ts = $sh->created_at ? Carbon::parse($sh->created_at) : null;
            if (! $ts) {
                continue;
            }
            foreach ($buckets as &$b) {
                if ($ts->betweenIncluded($b['start'], $b['end'])) {
                    $b['shipments']++;
                    if ($sh->status === 'delivered') {
                        $b['delivered']++;
                    }
                    $fee = (float) $sh->shipping_fee;
                    $b['gross_shipping'] = round($b['gross_shipping'] + $fee, 2);
                    $b['revenue'] = round($b['revenue'] + ($fee * 0.90), 2);
                    break;
                }
            }
            unset($b);
        }

        return array_values(array_map(fn ($i) => [
            'label'          => $i['label'],
            'full_date'      => $i['full_date'],
            'revenue'        => $i['revenue'],
            'gross_shipping' => $i['gross_shipping'],
            'shipments'      => $i['shipments'],
            'delivered'      => $i['delivered'],
        ], $buckets));
    }
}