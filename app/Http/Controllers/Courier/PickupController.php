<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\OrderServiceRating;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Models\WalletTransaction;
use App\Notifications\OrderLifecycleNotification;
use App\Services\CommissionCalculatorService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\ShippingCalculatorService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PickupController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService
    ) {}

    public static function calculateFirstMileCourierPay(Shipment $shipment): array
    {
        $shipment->loadMissing('sellerOrder.extra');

        $pickup = $shipment->pickup_address_snapshot ?? [];
        $hub = $shipment->hub_address_snapshot ?? [];

        $originProv = strtolower(trim((string) ($pickup['province'] ?? '')));
        $originMuni = strtolower(trim((string) ($pickup['municipality'] ?? '')));
        $originBrgy = strtolower(trim((string) ($pickup['barangay'] ?? '')));
        $hubProv = strtolower(trim((string) ($hub['province'] ?? '')));
        $hubMuni = strtolower(trim((string) ($hub['municipality'] ?? '')));
        $hubBrgy = strtolower(trim((string) ($hub['barangay'] ?? '')));

        if ($originProv !== '' && $originProv === $hubProv && $originMuni !== '' && $originMuni === $hubMuni) {
            if ($originBrgy !== '' && $originBrgy === $hubBrgy) {
                $distanceTier = 'Same Barangay (25% Share)';
                $tierBasePool = ShippingCalculatorService::BASE_FEE_SAME_BARANGAY;
            } else {
                $distanceTier = 'Same Municipality (25% Share)';
                $tierBasePool = ShippingCalculatorService::BASE_FEE_SAME_MUNICIPALITY;
            }
        } elseif ($originProv !== '' && $originProv === $hubProv) {
            $distanceTier = 'Same Province (25% Share)';
            $tierBasePool = ShippingCalculatorService::BASE_FEE_SAME_PROVINCE;
        } else {
            $distanceTier = 'Inter-Provincial (25% Share)';
            $tierBasePool = ShippingCalculatorService::BASE_FEE_INTER_PROVINCE;
        }

        $shareRate = CommissionCalculatorService::COURIER_SHIPPING_SHARE_PERCENT / 100;
        $extraKg = max(0, (int) ceil(max(0.01, (float) $shipment->total_weight)) - 1);
        $extraWeightPool = round($extraKg * ShippingCalculatorService::EXTRA_WEIGHT_FEE_PER_KG, 2);

        $extra = $shipment->sellerOrder?->extra;
        $actualShippingPool = $extra && (float) $extra->original_shipping_fee > 0
            ? round((float) $extra->original_shipping_fee, 2)
            : round((float) $shipment->shipping_fee, 2);

        if ($actualShippingPool <= 0) {
            $actualShippingPool = round($tierBasePool + $extraWeightPool, 2);
        }

        $minBaseShipping = ShippingCalculatorService::BASE_FEE_SAME_BARANGAY;
        $basePay = round($minBaseShipping * $shareRate, 2);
        $weightBonus = round($extraWeightPool * $shareRate, 2);
        $totalPay = round($actualShippingPool * $shareRate, 2);
        $distanceBonus = max(0.00, round($totalPay - $basePay - $weightBonus, 2));

        return [
            'base_pay'       => $basePay,
            'distance_tier'  => $distanceTier,
            'distance_bonus' => $distanceBonus,
            'weight_bonus'   => $weightBonus,
            'total_pay'      => $totalPay,
        ];
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();
        $statusFilter = $request->query('status', 'active');

        $query = PickupAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages',
            'sellerOrder.items',
            'sellerOrder.extra',
        ])
            ->where('courier_id', $courier->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if ($statusFilter === 'active') {
            $query->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub']);
        } elseif ($statusFilter === 'completed') {
            $query->where('status', 'completed');
        }

        $assignments = $query->paginate(15)->withQueryString();

        $counts = [
            'active'    => PickupAssignment::where('courier_id', $courier->id)
                ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])
                ->count(),
            'completed' => PickupAssignment::where('courier_id', $courier->id)
                ->where('status', 'completed')
                ->count(),
            'all'       => PickupAssignment::where('courier_id', $courier->id)->count(),
        ];

        $courierWallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? collect(
                DB::table('withdrawal_requests')
                    ->where('requester_type', 'Courier')
                    ->where('requester_id', (int) $courier->id)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get()
            )
            : collect();

        return view('courier.dashboard', compact(
            'courier',
            'assignments',
            'statusFilter',
            'counts',
            'courierWallet',
            'withdrawalRequests'
        ));
    }

    public function acceptAssignment(PickupAssignment $assignment)
    {
        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        if ($assignment->status !== 'assigned') {
            return back()->withErrors(['pickup' => 'This pickup assignment cannot be accepted in its current state.']);
        }

        $assignment->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        ShipmentTracking::create([
            'shipment_id'     => $assignment->shipment_id,
            'seller_order_id' => $assignment->seller_order_id,
            'status'          => 'courier_assigned',
            'title'           => 'First-Mile Courier En Route to Seller',
            'description'     => "Courier {$courier->full_name} accepted the pickup assignment and is traveling to the Seller's location.",
            'location'        => $courier->home_hub,
            'actor_type'      => 'Courier',
            'actor_id'        => $courier->id,
            'occurred_at'     => now(),
        ]);

        return back()->with('success', 'Pickup assignment accepted. Please scan or verify the package QR / Reference upon arrival at the Seller.');
    }

    public function verifyPackageLookup(Request $request, PickupAssignment $assignment)
    {
        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        if (! in_array($assignment->status, ['assigned', 'accepted'], true)) {
            return back()->withErrors(['lookup_input' => 'This pickup assignment has already been collected.']);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:120'],
            'verification_method' => ['required', 'in:qr,manual'],
        ]);

        $rawInput = trim($validated['lookup_input']);
        if (str_starts_with($rawInput, 'STORK-QR|')) {
            $parts = explode('|', $rawInput);
            $rawInput = end($parts) ?: $rawInput;
        }

        $method = $validated['verification_method'];
        $shipment = $assignment->shipment()->with('packages')->firstOrFail();

        $matchedPackage = $shipment->packages->first(function ($pkg) use ($rawInput, $shipment) {
            return strcasecmp($pkg->qr_token, $rawInput) === 0
                || strcasecmp($pkg->package_reference, $rawInput) === 0
                || strcasecmp($shipment->tracking_number, $rawInput) === 0;
        });

        if (! $matchedPackage) {
            throw ValidationException::withMessages([
                'lookup_input' => 'No package in this pickup assignment matched "' . $rawInput . '". Please scan the QR code or enter the Package Reference.',
            ]);
        }

        DB::transaction(function () use ($assignment, $matchedPackage, $shipment, $courier, $method, $rawInput) {
            if ($assignment->status === 'assigned') {
                $assignment->update([
                    'status'      => 'accepted',
                    'accepted_at' => now(),
                ]);
            }

            $matchedPackage->update([
                'courier_verified_at'         => now(),
                'courier_verification_method' => $method,
            ]);

            ShipmentVerification::create([
                'shipment_id'            => $shipment->id,
                'shipment_package_id'    => $matchedPackage->id,
                'seller_order_id'        => $shipment->seller_order_id,
                'logistics_profile_id'   => $shipment->logistics_profile_id,
                'logistics_hub_id'       => $matchedPackage->logistics_hub_id,
                'stage'                  => 'courier_pickup',
                'verification_method'    => $method,
                'lookup_token_or_number' => $rawInput,
                'actor_type'             => 'Courier',
                'actor_id'               => $courier->id,
                'is_confirmed'           => true,
                'confirmed_at'           => now(),
                'remarks'                => "Verified by Courier {$courier->full_name} at Seller location",
            ]);
        });

        return back()->with('success', "Package {$matchedPackage->package_reference} verified via " . strtoupper($method) . "! Inspect the manifest and click 'Confirm Package Picked Up from Seller'.");
    }

    public function confirmPickup(PickupAssignment $assignment)
    {
        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        if (! in_array($assignment->status, ['assigned', 'accepted'], true)) {
            return back()->withErrors(['pickup' => 'Package has already been marked as picked up.']);
        }

        $shipment = $assignment->shipment()->with('packages')->firstOrFail();
        $unverifiedCount = $shipment->packages->whereNull('courier_verified_at')->count();

        if ($unverifiedCount > 0) {
            return back()->withErrors([
                'pickup' => "Please verify all {$shipment->packages->count()} package(s) by scanning the QR code or entering the Package Reference before confirming pickup.",
            ]);
        }

        $notificationsToSend = [];

        DB::transaction(function () use ($assignment, $courier, &$notificationsToSend) {
            $lockedAssignment = PickupAssignment::with(['shipment.sellerOrder.order', 'shipment.sellerOrder.buyer', 'shipment.sellerOrder.seller', 'shipment.logisticsProfile.user'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shipment = $lockedAssignment->shipment;
            $sellerOrder = $shipment->sellerOrder;
            $oldOrderStatus = $sellerOrder->status;

            $lockedAssignment->update([
                'status'       => 'picked_up',
                'accepted_at'  => $lockedAssignment->accepted_at ?? now(),
                'picked_up_at' => now(),
            ]);

            $shipment->update([
                'status'       => 'picked_up_from_seller',
                'picked_up_at' => now(),
            ]);

            $sellerOrder->update([
                'status' => 'picked_up',
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $oldOrderStatus,
                'to_status'       => 'picked_up',
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'remarks'         => "Package picked up from Seller by First-Mile Courier {$courier->full_name} ({$courier->vehicle_type} - {$courier->plate_number}).",
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'picked_up_from_seller',
                'title'           => 'Picked Up from Seller',
                'description'     => "Courier {$courier->full_name} verified and collected the package from " . ($shipment->pickup_address_snapshot['business_name'] ?? 'Seller') . ".",
                'location'        => ($shipment->pickup_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->pickup_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);

            if ($sellerOrder->buyer) {
                $notificationsToSend[] = [
                    'user' => $sellerOrder->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Package Picked Up from Seller',
                        message: "Order #{$sellerOrder->seller_order_number} has been picked up by Courier {$courier->full_name} and is heading to the sorting hub.",
                        type: 'info',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }
        });

        foreach ($notificationsToSend as $item) {
            try {
                $item['user']->notify($item['notification']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Package pickup confirmed! Proceed to transport the parcel to the Logistics Sorting Hub.');
    }

    public function confirmTransportToHub(PickupAssignment $assignment)
    {
        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        if ($assignment->status !== 'picked_up') {
            return back()->withErrors(['pickup' => 'Package must be marked as Picked Up from Seller first.']);
        }

        $notificationsToSend = [];
        $payInfo = ['total_pay' => 11.25, 'distance_tier' => 'Same Municipality (25% Share)'];
        $isCod = true;

        DB::transaction(function () use ($assignment, $courier, &$notificationsToSend, &$payInfo, &$isCod) {
            $lockedAssignment = PickupAssignment::with(['shipment.sellerOrder.payment', 'shipment.sellerOrder.extra', 'shipment.logisticsProfile.user'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shipment = $lockedAssignment->shipment;
            $sellerOrder = $shipment->sellerOrder;
            $payInfo = self::calculateFirstMileCourierPay($shipment);
            $isCod = strtoupper((string) ($sellerOrder?->effective_payment_method ?? 'COD')) === 'COD';

            $lockedAssignment->update([
                'status'         => 'in_transit_to_hub',
                'handed_over_at' => now(),
            ]);

            $shipment->update([
                'status' => 'in_transit_to_hub',
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'in_transit_to_hub',
                'title'           => 'Arrived at Sorting Facility — Pending Hub Verification',
                'description'     => "Courier {$courier->full_name} handed over the package at the sorting facility (" . ($shipment->hub_address_snapshot['business_name'] ?? 'Logistics Hub') . ").",
                'location'        => ($shipment->hub_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->hub_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            $this->roleWalletService->recordFirstMileCourierTaskCredit($shipment, $courier, $payInfo);

            if ($shipment->logisticsProfile?->user) {
                $notificationsToSend[] = [
                    'user' => $shipment->logisticsProfile->user,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Courier Handover Ready for Hub Verification',
                        message: "Courier {$courier->full_name} delivered Shipment #{$shipment->tracking_number} to your sorting hub. Please verify arrival.",
                        type: 'info',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }
        });

        foreach ($notificationsToSend as $item) {
            try {
                $item['user']->notify($item['notification']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with(
            'success',
            $isCod
                ? "Handover recorded! ₱" . number_format((float) $payInfo['total_pay'], 2) . " First-Mile Pickup Task Pay (25% of Shipping/Ops Fee) credited to your Pending Escrow and will release to Available E-Wallet upon Admin COD Remittance verification."
                : "Handover recorded and ₱" . number_format((float) $payInfo['total_pay'], 2) . " First-Mile Pickup Task Pay (25% of Shipping/Ops Fee) credited to your Available Courier E-Wallet!"
        );
    }

    public function requestWithdrawal(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        $validated = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:100', 'max:500000'],
            'payout_method'    => ['nullable', 'in:GCASH,BANK'],
            'account_name'     => ['required', 'string', 'min:2', 'max:120'],
            'account_number'   => ['required', 'string', 'min:8', 'max:50'],
            'idempotency_key'  => ['nullable', 'string', 'max:64'],
        ]);

        $withdrawal = $this->roleWalletService->submitWithdrawalRequest(
            ownerType: 'Courier',
            ownerId: (int) $courier->id,
            ownerName: $courier->full_name,
            amount: (float) $validated['requested_amount'],
            payoutMethod: 'GCASH',
            accountName: $validated['account_name'],
            accountNumber: $validated['account_number'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return back()->with('success', "Withdrawal Request #{$withdrawal->withdrawal_reference} for ₱" . number_format((float) $withdrawal->requested_amount, 2) . " via GCash submitted to Storkia Admin for approval!");
    }

    public function ratings(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        $pickupOrderIds = PickupAssignment::where('courier_id', $courier->id)
            ->pluck('seller_order_id')
            ->unique()
            ->values();

        $ratings = Schema::hasTable('order_service_ratings')
            ? OrderServiceRating::with(['buyer', 'sellerOrder', 'sellerProfile', 'logisticsProfile'])
                ->whereIn('seller_order_id', $pickupOrderIds)
                ->orderByDesc('created_at')
                ->paginate(20)
            : collect();

        $avgRating = Schema::hasTable('order_service_ratings') && $pickupOrderIds->isNotEmpty()
            ? round((float) OrderServiceRating::whereIn('seller_order_id', $pickupOrderIds)->avg('logistics_hub_rating'), 2)
            : 0.00;

        $totalRatings = Schema::hasTable('order_service_ratings') && $pickupOrderIds->isNotEmpty()
            ? OrderServiceRating::whereIn('seller_order_id', $pickupOrderIds)->count()
            : 0;

        return view('courier.ratings', compact('courier', 'ratings', 'avgRating', 'totalRatings'));
    }

    public function report(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Courier $courier */
        $courier = Auth::guard('courier')->user();

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        $assignmentsInPeriod = PickupAssignment::with(['shipment.sellerProfile', 'sellerOrder.extra'])
            ->where('courier_id', $courier->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $completedPickups = $assignmentsInPeriod->where('status', 'completed');
        $totalWeightHauled = $completedPickups->sum(fn ($a) => (float) ($a->shipment->total_weight ?? 0));

        $courierWallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        $walletTransactions = WalletTransaction::with(['sellerOrder', 'shipment'])
            ->where('owner_type', 'Courier')
            ->where('owner_id', $courier->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $summary = [
            'total_assigned'      => $assignmentsInPeriod->count(),
            'completed_count'     => $completedPickups->count(),
            'active_count'        => $assignmentsInPeriod->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])->count(),
            'reassigned_count'    => $assignmentsInPeriod->where('status', 'reassigned')->count(),
            'total_weight_hauled' => round((float) $totalWeightHauled, 2),
            'wallet_available'    => round((float) $courierWallet->available_balance, 2),
            'wallet_pending'      => round((float) $courierWallet->pending_balance, 2),
            'total_earned'        => round((float) $courierWallet->total_earned, 2),
        ];

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? collect(
                DB::table('withdrawal_requests')
                    ->where('requester_type', 'Courier')
                    ->where('requester_id', (int) $courier->id)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get()
            )
            : collect();

        return view('courier.report', compact(
            'courier',
            'fromDate',
            'toDate',
            'assignmentsInPeriod',
            'summary',
            'courierWallet',
            'walletTransactions',
            'withdrawalRequests'
        ));
    }

    public function logout(Request $request)
    {
        Auth::guard('courier')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login')->with('info', 'Logged out of First-Mile Courier Portal.');
    }
}