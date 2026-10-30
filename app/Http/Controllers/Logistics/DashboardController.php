<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Models\LogisticsApplication;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService
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
        $totalShipmentsCount = 0;
        $walletAvailable = 0.0;
        $walletPendingEscrow = 0.0;
        $recentShipments = collect();

        if ($profile) {
            $profile->ensureMainHubExists();
            $this->orderCompletionService->runLifecycleMaintenance(logisticsProfileId: (int) $profile->id);

            $completedOrders = SellerOrder::where('logistics_profile_id', $profile->id)
                ->where('status', 'completed')
                ->get();

            foreach ($completedOrders as $compOrder) {
                $this->roleWalletService->releaseCompletedOrderEarnings($compOrder);
            }

            $logisticsWallet = $this->roleWalletService->getOrCreateWallet(
                ownerType: 'Logistics',
                ownerId: (int) $profile->id,
                ownerName: $profile->business_name
            );

            $walletAvailable = (float) $logisticsWallet->available_balance;
            $walletPendingEscrow = (float) $logisticsWallet->pending_balance;

            // Strict isolation: ONLY shipments explicitly assigned to THIS logistics_profile_id
            $shipmentsBaseQuery = Shipment::where('logistics_profile_id', $profile->id)
                ->whereHas('sellerOrder', fn ($q) => $q->where('logistics_profile_id', $profile->id));

            // If logged in as a Branch Hub, strictly scope to packages routed to this exact Branch Hub ID
            if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
                $branchHub = $user->branchLogisticsHub;
                $shipmentsBaseQuery->where(function ($q) use ($branchHub) {
                    $q->whereHas('packages', fn ($pq) => $pq->where('logistics_hub_id', $branchHub->id))
                      ->orWhere('hub_address_snapshot->logistics_hub_id', $branchHub->id);
                });
            }

            $totalShipmentsCount = (clone $shipmentsBaseQuery)->count();

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
                ->whereIn('status', ['delivery_failed', 'returning_to_seller'])
                ->count();

            $pendingRemittancesCount = CodRemittance::where('logistics_profile_id', $profile->id)
                ->where('status', 'submitted')
                ->count();

            $recentShipments = (clone $shipmentsBaseQuery)
                ->with(['sellerOrder.items', 'sellerProfile', 'currentCourier', 'currentRider', 'packages.logisticsHub'])
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->paginate(10);
        }

        return view('logistics.logistics-dashboard', compact(
            'profile',
            'latestApp',
            'pendingPickupsCount',
            'atHubCount',
            'inTransitCount',
            'failedDeliveriesCount',
            'pendingRemittancesCount',
            'totalShipmentsCount',
            'walletAvailable',
            'walletPendingEscrow',
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