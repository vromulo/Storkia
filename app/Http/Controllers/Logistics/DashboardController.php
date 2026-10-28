<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Models\LogisticsApplication;
use App\Models\Shipment;
use App\Models\User;
use App\Services\OrderCompletionService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService
    ) {}

    public function index()
    {
        /** @var User $user */
        $user = Auth::user();
        $profile = $user->logisticsProfile;
        $latestApp = LogisticsApplication::where('user_id', $user->id)->latest('version')->first();

        $pendingPickupsCount = 0;
        $atHubCount = 0;
        $inTransitCount = 0;
        $failedDeliveriesCount = 0;
        $pendingRemittancesCount = 0;
        $recentShipments = collect();

        if ($profile) {
            $profile->ensureMainHubExists();
            $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(logisticsProfileId: (int) $profile->id);

            $shipmentsBaseQuery = Shipment::where('logistics_profile_id', $profile->id);

            if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
                $branchHub = $user->branchLogisticsHub;
                $shipmentsBaseQuery->where(function ($q) use ($branchHub) {
                    $q->whereHas('packages', fn ($pq) => $pq->where('logistics_hub_id', $branchHub->id))
                      ->orWhere('pickup_address_snapshot->municipality', 'like', '%' . $branchHub->municipality . '%')
                      ->orWhere('delivery_address_snapshot->municipality', 'like', '%' . $branchHub->municipality . '%');
                });
            }

            $pendingPickupsCount = (clone $shipmentsBaseQuery)
                ->whereIn('status', ['pickup_requested', 'awaiting_seller_drop_off', 'courier_assigned'])
                ->count();

            $atHubCount = (clone $shipmentsBaseQuery)
                ->whereIn('status', ['picked_up_from_seller', 'in_transit_to_hub', 'received_at_hub', 'rider_assigned', 'scheduled_redelivery'])
                ->count();

            $inTransitCount = (clone $shipmentsBaseQuery)
                ->where('status', 'out_for_delivery')
                ->count();

            $failedDeliveriesCount = (clone $shipmentsBaseQuery)
                ->where('status', 'delivery_failed')
                ->count();

            $pendingRemittancesCount = CodRemittance::where('logistics_profile_id', $profile->id)
                ->where('status', 'submitted')
                ->count();

            $recentShipments = (clone $shipmentsBaseQuery)
                ->with(['sellerOrder.items', 'sellerProfile', 'currentCourier', 'currentRider', 'packages.logisticsHub'])
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->take(6)
                ->get();
        }

        return view('logistics.logistics-dashboard', compact(
            'profile',
            'latestApp',
            'pendingPickupsCount',
            'atHubCount',
            'inTransitCount',
            'failedDeliveriesCount',
            'pendingRemittancesCount',
            'recentShipments'
        ));
    }

    public function showReapply()
    {
        $latestApp = LogisticsApplication::where('user_id', Auth::id())->latest('version')->first();

        if (! $latestApp || $latestApp->status !== 'rejected') {
            return redirect()->route('logistics.logistics-dashboard');
        }

        return view('logistics.auth.reapply', compact('latestApp'));
    }
}