<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CommissionRecord;
use App\Models\InterHubTransfer;
use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use App\Models\OrderItemReview;
use App\Models\OrderServiceRating;
use App\Models\OrderStatusHistory;
use App\Models\ProductQuestion;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\SellerProfile;
use App\Models\SellerRtsClaim;
use App\Models\SellerSettlement;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerOrderNotificationService;
use App\Services\BuyerRewardService;
use App\Services\CourierMobileSchemaService;
use App\Services\FormalReportService;
use App\Services\InventoryStockService;
use App\Services\OperationalSlaGuardService;
use App\Services\OrderCompletionService;
use App\Services\RealtimeBroadcastService;
use App\Services\RoleWalletService;
use App\Services\ShipmentVerificationService;
use App\Services\ShippingCalculatorService;
use App\Services\StorkSchemaBootstrapService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected OrderCompletionService $orderCompletionService,
        protected ShipmentVerificationService $shipmentVerificationService,
        protected VoucherService $voucherService,
        protected RoleWalletService $roleWalletService,
        protected FormalReportService $formalReportService,
        protected BuyerOrderNotificationService $buyerNotifications,
        protected ?BuyerRewardService $buyerRewardService = null,
        protected ?ShippingCalculatorService $shippingCalculatorService = null,
        protected ?OperationalSlaGuardService $slaGuardService = null
    ) {
        $this->buyerRewardService = $buyerRewardService ?? app(BuyerRewardService::class);
        $this->shippingCalculatorService = $shippingCalculatorService ?? app(ShippingCalculatorService::class);
        $this->slaGuardService = $slaGuardService ?? app(OperationalSlaGuardService::class);
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();

        $this->orderCompletionService->autoCancelUnserviceablePendingOrders(sellerId: (int) $user->id);
        $this->orderCompletionService->runLifecycleMaintenance(sellerId: (int) $user->id);
        $this->backfillSellerOrderDistances((int) $user->id);

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $tierFilter = trim((string) $request->query('tier', ''));
        $locationFilter = trim((string) $request->query('location', ''));

        $sortInput = $request->query('sort');
        $queueSort = SellerOrder::normalizeQueueSort(is_string($sortInput) ? $sortInput : null);

        $baseQuery = SellerOrder::where('seller_id', $user->id);

        $counts = [
            'all'        => (clone $baseQuery)->count(),
            'new'        => (clone $baseQuery)->where('status', 'pending_seller_acceptance')->count(),
            'pending'    => (clone $baseQuery)->where('status', 'pending_buyer_response')->count(),
            'to_process' => (clone $baseQuery)->whereIn('status', ['accepted', 'preparing'])->count(),
            'to_ship'    => (clone $baseQuery)->whereIn('status', ['ready_for_pickup', 'pickup_assigned'])->count(),
            'shipped'    => (clone $baseQuery)->whereIn('status', ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivery_failed'])->count(),
            'delivered'  => (clone $baseQuery)->whereIn('status', ['delivered', 'completed'])->count(),
            'cancelled'  => (clone $baseQuery)->whereIn('status', ['rejected', 'cancelled', 'returned_to_seller', 'disputed'])->count(),
        ];

        $availableLocations = SellerOrder::where('seller_id', $user->id)
            ->select(['delivery_address_snapshot'])
            ->get()
            ->map(function (SellerOrder $so) {
                $snap = (array) ($so->delivery_address_snapshot ?? []);
                $mun = trim((string) ($snap['municipality'] ?? ''));
                $prov = trim((string) ($snap['province'] ?? ''));
                return $mun !== '' && $prov !== '' ? "{$mun}, {$prov}" : ($prov ?: null);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $query = SellerOrder::with([
            'order',
            'buyer',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'shipment.currentCourier',
            'shipment.currentRider',
            'shipment.packages',
            'payment',
            'paymentSubmission',
            'extra',
            'rtsClaim',
        ])->where('seller_id', $user->id);

        match ($statusTab) {
            'new'        => $query->where('status', 'pending_seller_acceptance'),
            'pending'    => $query->where('status', 'pending_buyer_response'),
            'to_process' => $query->whereIn('status', ['accepted', 'preparing']),
            'to_ship'    => $query->whereIn('status', ['ready_for_pickup', 'pickup_assigned']),
            'shipped'    => $query->whereIn('status', ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivery_failed']),
            'delivered'  => $query->whereIn('status', ['delivered', 'completed']),
            'cancelled'  => $query->whereIn('status', ['rejected', 'cancelled', 'returned_to_seller', 'disputed']),
            default      => null,
        };

        if ($tierFilter !== '') {
            $query->where('shipping_tier', $tierFilter);
        }

        if ($locationFilter !== '') {
            $parts = array_map('trim', explode(',', $locationFilter, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $query->where('delivery_address_snapshot->municipality', $parts[0])
                      ->where('delivery_address_snapshot->province', $parts[1]);
            } else {
                $query->where(function ($q) use ($locationFilter) {
                    $q->where('delivery_address_snapshot->province', 'like', "%{$locationFilter}%")
                      ->orWhere('delivery_address_snapshot->municipality', 'like', "%{$locationFilter}%");
                });
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('seller_order_number', 'like', "%{$search}%")
                  ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                  ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('items', fn ($iq) => $iq->where('product_name', 'like', "%{$search}%"))
                  ->orWhereHas('shipment', fn ($sq) => $sq->where('tracking_number', 'like', "%{$search}%"))
                  ->orWhereHas('paymentSubmission', fn ($pq) => $pq->where('reference_number', 'like', "%{$search}%"));
            });
        }

        $orders = $query
            ->orderByOperationalQueue($queueSort)
            ->paginate(10)
            ->withQueryString();

        return view('seller.orders.index', compact(
            'orders',
            'statusTab',
            'search',
            'counts',
            'queueSort',
            'tierFilter',
            'locationFilter',
            'availableLocations'
        ));
    }

    protected function backfillSellerOrderDistances(int $sellerId): void
    {
        if (! Schema::hasColumn('seller_orders', 'seller_to_buyer_distance_km')) {
            return;
        }

        $missing = SellerOrder::where('seller_id', $sellerId)
            ->whereNull('seller_to_buyer_distance_km')
            ->limit(40)
            ->get();

        foreach ($missing as $so) {
            $dist = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
                (array) ($so->pickup_address_snapshot ?? []),
                (array) ($so->delivery_address_snapshot ?? [])
            );
            $so->update(['seller_to_buyer_distance_km' => $dist]);
        }
    }

    public function show(Request $request, SellerOrder $sellerOrder)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $this->orderCompletionService->autoCancelUnserviceablePendingOrders(
            sellerId: (int) $user->id,
            specificSellerOrderId: (int) $sellerOrder->id
        );
        $this->orderCompletionService->runLifecycleMaintenance(sellerId: (int) $user->id);
        $sellerOrder->refresh();

        $sellerOrder->load([
            'order',
            'buyer',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'originLogisticsHub',
            'destinationLogisticsHub',
            'payment',
            'paymentSubmission',
            'extra',
            'rtsClaim',
            'itemReviews.buyer',
            'statusHistories',
            'shipment.packages.logisticsHub',
            'shipment.trackingLogs',
            'shipment.currentCourier',
            'shipment.linehaulCourier',
            'shipment.currentRider',
            'shipment.deliveryAttempts',
            'shipment.deliveryProofs',
            'commissionRecord',
            'settlementItem.settlement',
        ]);

        if ($sellerOrder->shipment) {
            if ($sellerOrder->shipment->packages->isEmpty()) {
                $this->shipmentVerificationService->ensurePackagesForShipment(
                    shipment: $sellerOrder->shipment,
                    sellerOrder: $sellerOrder
                );
            }
            $this->backfillShipmentTrackingIfEmpty($sellerOrder->shipment, $sellerOrder);
            $sellerOrder->load(['shipment.packages.logisticsHub', 'shipment.trackingLogs']);
        }

        $pickupSnap = (array) ($sellerOrder->pickup_address_snapshot ?? []);
        $deliverySnap = (array) ($sellerOrder->delivery_address_snapshot ?? []);

        $routeEvaluationMap = $this->shippingCalculatorService->evaluateLogisticsProvidersForRoute(
            sellerProvince: (string) ($pickupSnap['province'] ?? ($user->sellerProfile?->province ?? '')),
            sellerMunicipality: (string) ($pickupSnap['municipality'] ?? ($user->sellerProfile?->municipality ?? '')),
            buyerProvince: (string) ($deliverySnap['province'] ?? ''),
            buyerMunicipality: (string) ($deliverySnap['municipality'] ?? ''),
            sellerBarangay: (string) ($pickupSnap['barangay'] ?? ($user->sellerProfile?->barangay ?? '')),
            buyerBarangay: (string) ($deliverySnap['barangay'] ?? ''),
            sellerProfile: $user->sellerProfile,
            totalWeightKg: (float) ($sellerOrder->total_weight ?: 0.5)
        );

        $eligibleLogistics = $this->getEligibleLogisticsProviders($user->sellerProfile);
        $eligibleHubs = $this->getEligibleLogisticsHubs($eligibleLogistics, $user->sellerProfile, $sellerOrder, $routeEvaluationMap);

        $hasFleetReadyLogistics = collect($routeEvaluationMap)->contains(fn ($eval) => ! empty($eval['is_fleet_ready']));
        $hasRouteServiceableLogistics = collect($routeEvaluationMap)->contains(fn ($eval) => ! empty($eval['is_route_serviceable']));
        $canRejectUnserviceable = false;

        return view('seller.orders.show', compact(
            'sellerOrder',
            'eligibleLogistics',
            'eligibleHubs',
            'routeEvaluationMap',
            'hasFleetReadyLogistics',
            'hasRouteServiceableLogistics',
            'canRejectUnserviceable'
        ));
    }

    protected function backfillShipmentTrackingIfEmpty(Shipment $shipment, SellerOrder $sellerOrder): void
    {
        if (! Schema::hasTable('shipment_tracking')) {
            return;
        }

        if ($shipment->trackingLogs()->count() > 0) {
            return;
        }

        $originLoc = trim(($sellerOrder->pickup_address_snapshot['municipality'] ?? '') . ', ' . ($sellerOrder->pickup_address_snapshot['province'] ?? ''), ', ') ?: 'Seller Facility';
        $hubLoc = $shipment->hub_address_snapshot['hub_name'] ?? ($shipment->logisticsProfile?->business_name ?? 'Sorting Hub');
        $destLoc = trim(($sellerOrder->delivery_address_snapshot['barangay'] ?? '') . ', ' . ($sellerOrder->delivery_address_snapshot['municipality'] ?? ''), ', ') ?: 'Buyer Address';

        ShipmentTracking::create([
            'shipment_id'     => $shipment->id,
            'seller_order_id' => $sellerOrder->id,
            'status'          => 'pickup_requested',
            'title'           => 'Shipment Created & Ready for Handover',
            'description'     => "Tracking #{$shipment->tracking_number} generated by Seller.",
            'location'        => $originLoc,
            'actor_type'      => 'Seller',
            'actor_id'        => $sellerOrder->seller_id,
            'occurred_at'     => $shipment->pickup_requested_at ?? $shipment->created_at ?? now(),
        ]);

        if ($shipment->picked_up_at) {
            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'picked_up_from_seller',
                'title'           => 'Picked Up by First-Mile Courier',
                'description'     => 'Package collected from Seller facility and in transit to Sorting Hub.',
                'location'        => $originLoc,
                'actor_type'      => 'Courier',
                'actor_id'        => $shipment->current_courier_id,
                'occurred_at'     => $shipment->picked_up_at,
            ]);
        }

        if ($shipment->arrived_at_hub_at) {
            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'received_at_hub',
                'title'           => 'Arrived & Verified at Logistics Sorting Hub',
                'description'     => "Parcel received at {$hubLoc} for last-mile sorting.",
                'location'        => $hubLoc,
                'actor_type'      => 'Logistics',
                'actor_id'        => $shipment->hub_verified_by,
                'occurred_at'     => $shipment->arrived_at_hub_at,
            ]);
        }

        if ($shipment->out_for_delivery_at) {
            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'out_for_delivery',
                'title'           => 'Out for Last-Mile Delivery',
                'description'     => 'Assigned Rider is en route to the recipient delivery address.',
                'location'        => $destLoc,
                'actor_type'      => 'Rider',
                'actor_id'        => $shipment->current_rider_id,
                'occurred_at'     => $shipment->out_for_delivery_at,
            ]);
        }

        if ($shipment->delivered_at) {
            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'delivered',
                'title'           => 'Package Delivered',
                'description'     => 'Package delivered to recipient with proof of delivery.',
                'location'        => $destLoc,
                'actor_type'      => 'Rider',
                'actor_id'        => $shipment->current_rider_id,
                'occurred_at'     => $shipment->delivered_at,
            ]);
        }
    }

    public function ratingsIndex(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403, 'Approved Seller profile required.');
        }

        $activeTab = $request->query('tab', 'products');
        $starFilter = (int) $request->query('stars', 0);

        $itemReviews = collect();
        if (Schema::hasTable('order_item_reviews')) {
            $itemQuery = OrderItemReview::with(['buyer', 'product', 'sellerOrder', 'orderItem'])
                ->where('seller_profile_id', $profile->id)
                ->orderByDesc('created_at');

            if ($starFilter >= 1 && $starFilter <= 5) {
                $itemQuery->where('rating', $starFilter);
            }
            $itemReviews = $itemQuery->paginate(15, ['*'], 'prod_page')->withQueryString();
        }

        $serviceRatings = collect();
        if (Schema::hasTable('order_service_ratings')) {
            $srvQuery = OrderServiceRating::with(['buyer', 'sellerOrder', 'logisticsProfile', 'rider'])
                ->where('seller_profile_id', $profile->id)
                ->orderByDesc('created_at');

            if ($starFilter >= 1 && $starFilter <= 5) {
                $srvQuery->where('seller_service_rating', $starFilter);
            }
            $serviceRatings = $srvQuery->paginate(15, ['*'], 'srv_page')->withQueryString();
        }

        $productQuestions = collect();
        if (Schema::hasTable('product_questions')) {
            $productQuestions = ProductQuestion::with(['buyer', 'product', 'answeredBy'])
                ->where('seller_profile_id', $profile->id)
                ->orderByRaw('CASE WHEN answer IS NULL THEN 0 ELSE 1 END')
                ->orderByDesc('created_at')
                ->paginate(15, ['*'], 'qna_page')
                ->withQueryString();
        }

        return view('seller.ratings.index', compact(
            'profile',
            'activeTab',
            'starFilter',
            'itemReviews',
            'serviceRatings',
            'productQuestions'
        ));
    }

    public function answerProductQuestion(Request $request, ProductQuestion $question)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile || (int) $question->seller_profile_id !== (int) $profile->id) {
            abort(403, 'You can only answer questions on your own store products.');
        }

        $validated = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $question->update([
            'answer'              => trim($validated['answer']),
            'answered_by_user_id' => $user->id,
            'answered_at'         => now(),
        ]);

        try {
            $question->loadMissing(['buyer', 'product']);
            $question->buyer?->notify(new OrderLifecycleNotification(
                title: 'Seller Answered Your Product Question',
                message: "{$profile->business_name} replied to your question on \"" . ($question->product->name ?? 'Product') . '".',
                type: 'success',
                url: route('product.show', $question->product_id)
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', 'Your answer has been published on the product page.');
    }

    public function confirmRtsReceived(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($sellerOrder, $user) {
            $locked = SellerOrder::with(['rtsClaim', 'shipment'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            $this->inventoryStockService->restoreStockForSellerOrder($locked);

            if ($locked->rtsClaim) {
                $locked->rtsClaim->update([
                    'rts_status'                   => 'received_by_seller',
                    'seller_received_parcel'       => true,
                    'seller_confirmed_received_at' => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $locked->status,
                'to_status'       => 'returned_to_seller',
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'remarks'         => 'Seller confirmed physical receipt of returned (RTS) parcel. Product inventory stock restored.',
            ]);
        });

        return back()->with('success', 'Returned parcel receipt confirmed! Product stock has been restored to your inventory.');
    }

    public function submitRtsClaim(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'claimed_product_expense' => ['required', 'numeric', 'min:1'],
            'claim_description'       => ['required', 'string', 'min:10', 'max:500'],
            'evidence_photo'          => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $photoPath = $request->file('evidence_photo')->store('rts_claims', 'public');

        SellerRtsClaim::updateOrCreate(
            ['seller_order_id' => $sellerOrder->id],
            [
                'shipment_id'             => $sellerOrder->shipment?->id,
                'seller_profile_id'       => $sellerOrder->seller_profile_id,
                'logistics_profile_id'    => $sellerOrder->logistics_profile_id,
                'buyer_id'                => $sellerOrder->buyer_id,
                'abandonment_reason'      => $sellerOrder->rtsClaim?->abandonment_reason ?? 'Returned to seller damaged/lost',
                'rts_status'              => 'damaged_claim_filed',
                'is_damaged_or_lost'      => true,
                'claimed_product_expense' => $validated['claimed_product_expense'],
                'claim_description'       => trim($validated['claim_description']),
                'evidence_photo_path'     => $photoPath,
                'reimbursement_status'    => 'pending_admin_review',
            ]
        );

        return back()->with('success', 'RTS Damaged/Lost Expense Claim submitted to Storkia Admin for reimbursement review.');
    }

    public function replyToReview(Request $request, OrderItemReview $review)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile || (int) $review->seller_profile_id !== (int) $profile->id) {
            abort(403, 'You can only reply to reviews on your own store products.');
        }

        $validated = $request->validate([
            'seller_reply' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $editCount = (int) ($review->seller_reply_edit_count ?? 0);
        $isInitialReply = empty($review->seller_reply);

        if (! $isInitialReply && $editCount >= 1) {
            return back()->withErrors([
                'seller_reply' => 'You have already edited your reply once. A second edit is not permitted.',
            ]);
        }

        $updatePayload = [
            'seller_reply'      => trim($validated['seller_reply']),
            'seller_replied_at' => $review->seller_replied_at ?? now(),
        ];

        if (! $isInitialReply && Schema::hasColumn('order_item_reviews', 'seller_reply_edit_count')) {
            $updatePayload['seller_reply_edit_count'] = $editCount + 1;
            $updatePayload['seller_reply_edited_at'] = now();
        }

        $review->update($updatePayload);

        return back()->with('success', $isInitialReply ? 'Your reply has been posted under the buyer review.' : 'Your reply has been updated.');
    }

    public function verifyOnlinePayment(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($sellerOrder, $user) {
            $locked = SellerOrder::with(['payment', 'paymentSubmission'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();
            $submission = $locked->paymentSubmission;

            if (! $submission) {
                throw ValidationException::withMessages([
                    'payment' => 'No online payment submission found for this order.',
                ]);
            }

            if ($locked->status === 'pending_buyer_response') {
                throw ValidationException::withMessages([
                    'payment' => 'This order is currently awaiting buyer response regarding a flagged payment.',
                ]);
            }

            $submission->update([
                'status'             => 'verified',
                'is_suspicious'      => false,
                'seller_verified_at' => now(),
            ]);

            $locked->update([
                'payment_status' => 'paid',
            ]);

            $locked->payment?->update([
                'status'       => 'paid',
                'collected_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $locked->status,
                'to_status'       => $locked->status,
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'remarks'         => "Seller verified Buyer's online payment (Ref #{$submission->reference_number}).",
            ]);
        });

        return back()->with('success', 'Online payment verified successfully.');
    }

    public function reportSuspiciousPayment(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'suspicious_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($sellerOrder, $user, $validated) {
            $locked = SellerOrder::with(['order', 'payment', 'paymentSubmission'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['pending_seller_acceptance', 'accepted', 'preparing'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Suspicious payment can only be reported before package handover.',
                ]);
            }

            $submission = $locked->paymentSubmission;
            if (! $submission || $submission->switched_to_cod) {
                throw ValidationException::withMessages([
                    'order' => 'This order does not have an active GCash online payment submission to report.',
                ]);
            }

            $prevStatus = $locked->status;

            $submission->update([
                'status'                 => 'flagged_suspicious',
                'is_suspicious'          => true,
                'suspicious_reason'      => trim($validated['suspicious_reason']),
                'suspicious_reported_at' => now(),
                'reported_by'            => $user->id,
            ]);

            $locked->update([
                'status'         => 'pending_buyer_response',
                'payment_status' => 'flagged',
            ]);

            $locked->payment?->update([
                'status' => 'flagged',
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $prevStatus,
                'to_status'       => 'pending_buyer_response',
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'remarks'         => 'Seller flagged GCash payment as suspicious: ' . trim($validated['suspicious_reason']),
            ]);

            $this->orderCompletionService->syncParentOrderStatus($locked->order);
        });

        return redirect()
            ->route('seller.orders.index', ['status' => 'pending'])
            ->with('success', "Suspicious payment report sent to Buyer. Order #{$sellerOrder->seller_order_number} moved to Pending.");
    }

    public function accept(Request $request, SellerOrder $sellerOrder)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $autoCancelled = $this->orderCompletionService->autoCancelUnserviceablePendingOrders(
            sellerId: (int) $user->id,
            specificSellerOrderId: (int) $sellerOrder->id
        );
        $this->orderCompletionService->runLifecycleMaintenance(sellerId: (int) $user->id);
        $sellerOrder->refresh();

        if ($autoCancelled > 0 || $sellerOrder->status === 'cancelled') {
            return redirect()
                ->route('seller.orders.show', $sellerOrder->id)
                ->with('info', "Order #{$sellerOrder->seller_order_number} was automatically cancelled by the platform with 0 SLA strikes because the Logistics/Rider route became unavailable.");
        }

        if ($sellerOrder->status !== 'pending_seller_acceptance') {
            return back()->withErrors([
                'order' => 'This order can no longer be accepted because its status is ' . $sellerOrder->status_label . '.',
            ]);
        }

        DB::transaction(function () use ($sellerOrder, $user) {
            $locked = SellerOrder::with(['paymentSubmission', 'payment'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->paymentSubmission && ! $locked->paymentSubmission->switched_to_cod && ! $locked->paymentSubmission->is_suspicious) {
                $locked->paymentSubmission->update([
                    'status'             => 'verified',
                    'seller_verified_at' => $locked->paymentSubmission->seller_verified_at ?? now(),
                ]);
                $locked->payment?->update([
                    'status'       => 'paid',
                    'collected_at' => $locked->payment->collected_at ?? now(),
                ]);
                $locked->payment_status = 'paid';
            } elseif (strtoupper((string) ($locked->payment?->method ?? '')) === 'WALLET') {
                $locked->payment?->update([
                    'status'       => 'paid',
                    'collected_at' => $locked->payment->collected_at ?? now(),
                ]);
                $locked->payment_status = 'paid';
            }

            $locked->update([
                'status'         => 'preparing',
                'payment_status' => $locked->payment_status,
                'accepted_at'    => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => 'pending_seller_acceptance',
                'to_status'       => 'preparing',
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'remarks'         => 'Seller accepted the order and started preparing the items.',
            ]);

            $this->orderCompletionService->syncParentOrderStatus($locked->order);
        });

        $this->buyerNotifications->accepted($sellerOrder);

        return back()->with('success', "Order #{$sellerOrder->seller_order_number} accepted.");
    }

    public function rejectUnserviceableOrder(Request $request, SellerOrder $sellerOrder)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $autoCancelled = $this->orderCompletionService->autoCancelUnserviceablePendingOrders(
            sellerId: (int) $user->id,
            specificSellerOrderId: (int) $sellerOrder->id
        );

        if ($autoCancelled > 0) {
            return redirect()
                ->route('seller.orders.show', $sellerOrder->id)
                ->with('success', "Order #{$sellerOrder->seller_order_number} was automatically cancelled due to unserviceable route with 0 SLA violation strikes.");
        }

        return back()->withErrors([
            'order' => 'This order currently has an active Logistics Hub & Last-Mile Rider route covering the buyer and can be fulfilled normally.',
        ]);
    }

    public function reject(Request $request, SellerOrder $sellerOrder)
    {
        return $this->rejectUnserviceableOrder($request, $sellerOrder);
    }

    public function respondToReturnRequest(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason'   => ['required_if:decision,rejected', 'nullable', 'string', 'min:10', 'max:300'],
        ]);

        DB::transaction(function () use ($sellerOrder, $user, $validated) {
            $locked = SellerOrder::with(['order', 'payment', 'extra', 'buyer'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'disputed' || ! $locked->has_unresolved_dispute) {
                throw ValidationException::withMessages([
                    'dispute' => 'There is no active return/dispute request on this order.',
                ]);
            }

            $extra = $locked->extra ?: SellerOrderExtra::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
            ]);

            if ($validated['decision'] === 'approved') {
                $extra->update([
                    'seller_return_decision'        => 'approved',
                    'seller_return_decision_reason' => $validated['reason'] ? trim($validated['reason']) : 'Return & refund approved by seller.',
                    'seller_return_responded_at'    => now(),
                    'refund_status'                 => 'refund_released',
                    'refund_released_at'            => now(),
                ]);

                $this->roleWalletService->refundBuyerWalletForOrder(
                    $locked,
                    (float) $locked->total_amount,
                    'Seller-Approved Return & Refund'
                );
                $this->buyerRewardService?->restoreCoinsForSellerOrder($locked);
                $this->voucherService->restoreVouchersForSellerOrder($locked);

                $locked->update([
                    'status'                 => 'returned_to_seller',
                    'payment_status'         => 'cancelled',
                    'has_unresolved_dispute' => false,
                    'dispute_resolved_at'    => now(),
                    'cancelled_at'           => now(),
                ]);

                $locked->payment?->update([
                    'status' => 'cancelled',
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => 'disputed',
                    'to_status'       => 'returned_to_seller',
                    'actor_type'      => 'Seller',
                    'actor_id'        => $user->id,
                    'remarks'         => 'Seller approved buyer return/refund request. Refund credited to Buyer E-Wallet and redeemed Storkia Coins restored.',
                ]);

                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            } else {
                $extra->update([
                    'seller_return_decision'        => 'rejected',
                    'seller_return_decision_reason' => trim((string) $validated['reason']),
                    'seller_return_responded_at'    => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => 'disputed',
                    'to_status'       => 'disputed',
                    'actor_type'      => 'Seller',
                    'actor_id'        => $user->id,
                    'remarks'         => 'Seller rejected return request: ' . trim((string) $validated['reason']),
                ]);
            }
        });

        return back()->with('success', 'Return/dispute response recorded.');
    }

    public function markReadyForPickup(Request $request, SellerOrder $sellerOrder)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $autoCancelled = $this->orderCompletionService->autoCancelUnserviceablePendingOrders(
            sellerId: (int) $user->id,
            specificSellerOrderId: (int) $sellerOrder->id
        );
        if ($autoCancelled > 0) {
            return redirect()
                ->route('seller.orders.show', $sellerOrder->id)
                ->with('info', "Order #{$sellerOrder->seller_order_number} was automatically cancelled by the platform with 0 SLA strikes because the Logistics/Rider route became unavailable.");
        }

        $validated = $request->validate([
            'handover_method'      => ['required', 'in:courier_pickup,self_drop_off'],
            'logistics_hub_id'     => ['nullable', 'integer', 'exists:logistics_hubs,id'],
            'logistics_profile_id' => ['nullable', 'integer', 'exists:logistics_profiles,id'],
            'package_count'        => ['nullable', 'integer', 'min:1', 'max:10'],
            'manifest_confirmed'   => ['required', 'accepted'],
        ]);

        $pickupSnap = (array) ($sellerOrder->pickup_address_snapshot ?? []);
        $deliverySnap = (array) ($sellerOrder->delivery_address_snapshot ?? []);

        $routeEvaluations = $this->shippingCalculatorService->evaluateLogisticsProvidersForRoute(
            sellerProvince: (string) ($pickupSnap['province'] ?? ($user->sellerProfile?->province ?? '')),
            sellerMunicipality: (string) ($pickupSnap['municipality'] ?? ($user->sellerProfile?->municipality ?? '')),
            buyerProvince: (string) ($deliverySnap['province'] ?? ''),
            buyerMunicipality: (string) ($deliverySnap['municipality'] ?? ''),
            sellerBarangay: (string) ($pickupSnap['barangay'] ?? ($user->sellerProfile?->barangay ?? '')),
            buyerBarangay: (string) ($deliverySnap['barangay'] ?? ''),
            sellerProfile: $user->sellerProfile,
            totalWeightKg: (float) ($sellerOrder->total_weight ?: 0.5)
        );

        if (! empty($validated['logistics_hub_id'])) {
            $selectedHub = LogisticsHub::with('parentLogisticsProfile.user')->findOrFail((int) $validated['logistics_hub_id']);
            $logisticsProfile = $selectedHub->parentLogisticsProfile;
        } elseif (! empty($validated['logistics_profile_id'])) {
            $logisticsProfile = LogisticsProfile::with('user')->findOrFail((int) $validated['logistics_profile_id']);
            $selectedHub = $logisticsProfile->ensureMainHubExists();
        } else {
            return back()->withErrors(['logistics_hub_id' => 'Please select an approved Logistics hub.']);
        }

        $providerEval = $routeEvaluations[$logisticsProfile->id] ?? null;
        $originEval = $this->shippingCalculatorService->evaluateOriginHubForSeller(
            hub: $selectedHub,
            sellerProvince: (string) ($pickupSnap['province'] ?? ($user->sellerProfile?->province ?? '')),
            sellerMunicipality: (string) ($pickupSnap['municipality'] ?? ($user->sellerProfile?->municipality ?? '')),
            sellerBarangay: (string) ($pickupSnap['barangay'] ?? ($user->sellerProfile?->barangay ?? '')),
            sellerProfile: $user->sellerProfile,
            totalWeightKg: (float) ($sellerOrder->total_weight ?: 0.5)
        );

        if (! $originEval['is_covered']) {
            return back()->withErrors([
                'logistics_hub_id' => "Origin Hub \"{$selectedHub->hub_name}\" ({$selectedHub->municipality}, {$selectedHub->province}) is outside First-Mile Courier pickup range or on a different island from your store.",
            ]);
        }

        if (! $providerEval || ! $providerEval['destination_hub']) {
            $buyerLoc = trim(($deliverySnap['municipality'] ?? '') . ', ' . ($deliverySnap['province'] ?? ''), ', ');
            return back()->withErrors([
                'logistics_hub_id' => "{$logisticsProfile->business_name} does not have an active Branch/Main Hub in the buyer's municipality ({$buyerLoc}). Please select a Logistics partner with a connected hub in {$buyerLoc}.",
            ]);
        }

        if ((int) ($providerEval['destination_riders_count'] ?? 0) <= 0) {
            return back()->withErrors([
                'logistics_hub_id' => "{$logisticsProfile->business_name}'s destination hub ({$providerEval['destination_hub']->hub_name}) currently has 0 approved Last-Mile Riders covering Brgy. {$deliverySnap['barangay']}, {$deliverySnap['municipality']}.",
            ]);
        }

        $handoverMethod = $validated['handover_method'];
        if ($handoverMethod === 'courier_pickup' && (int) ($originEval['active_couriers_count'] ?? 0) <= 0) {
            return back()->withErrors([
                'handover_method' => "{$selectedHub->hub_name} currently has 0 active First-Mile Couriers on duty. Please select 'Option B: Seller Self Drop-Off at Logistics Hub' or choose another hub with active Couriers.",
            ]);
        }

        $destinationHub = $providerEval['destination_hub'] ?? $selectedHub;
        $requiresInterHubTransfer = (int) $selectedHub->id !== (int) $destinationHub->id;
        $originDistanceKm = (float) ($originEval['distance_km'] ?? 0);
        $destDistanceKm = (float) ($providerEval['destination_distance_km'] ?? 0);
        $packageCount = (int) ($validated['package_count'] ?? 1);

        $shipment = DB::transaction(function () use (
            $sellerOrder,
            $user,
            $logisticsProfile,
            $selectedHub,
            $destinationHub,
            $requiresInterHubTransfer,
            $originDistanceKm,
            $destDistanceKm,
            $handoverMethod,
            $packageCount
        ) {
            $locked = SellerOrder::with(['order', 'items', 'payment', 'extra'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['accepted', 'preparing'], true) || $locked->shipment()->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'Shipment has already been created or order is not in Preparing state.',
                ]);
            }

            $previousStatus = $locked->status;

            $orderUpdatePayload = [
                'logistics_profile_id' => $logisticsProfile->id,
                'status'               => 'ready_for_pickup',
                'ready_for_pickup_at'  => now(),
            ];
            if (Schema::hasColumn('seller_orders', 'origin_logistics_hub_id')) {
                $orderUpdatePayload['origin_logistics_hub_id'] = $selectedHub->id;
            }
            if (Schema::hasColumn('seller_orders', 'destination_logistics_hub_id')) {
                $orderUpdatePayload['destination_logistics_hub_id'] = $destinationHub->id;
            }

            $locked->update($orderUpdatePayload);

            $hubSnapshot = [
                'logistics_hub_id'             => $selectedHub->id,
                'hub_code'                     => $selectedHub->hub_code,
                'hub_name'                     => $selectedHub->hub_name,
                'is_main_hub'                  => (bool) $selectedHub->is_main_hub,
                'handover_method'              => $handoverMethod,
                'business_name'                => $logisticsProfile->business_name,
                'contact_no'                   => $selectedHub->contact_no ?: $logisticsProfile->contact_no,
                'province'                     => $selectedHub->province ?: $logisticsProfile->province,
                'municipality'                 => $selectedHub->municipality ?: $logisticsProfile->municipality,
                'barangay'                     => $selectedHub->barangay ?: $logisticsProfile->barangay,
                'full_address'                 => $selectedHub->full_address ?: $logisticsProfile->full_address,
                'origin_to_hub_distance_km'    => $originDistanceKm,
                'destination_logistics_hub_id' => $destinationHub->id,
                'requires_inter_hub_transfer'  => $requiresInterHubTransfer,
                'hub_to_buyer_distance_km'     => $destDistanceKm,
                'destination_hub'              => [
                    'logistics_hub_id' => $destinationHub->id,
                    'hub_code'         => $destinationHub->hub_code,
                    'hub_name'         => $destinationHub->hub_name,
                    'province'         => $destinationHub->province,
                    'municipality'     => $destinationHub->municipality,
                    'barangay'         => $destinationHub->barangay,
                    'full_address'     => $destinationHub->full_address,
                ],
            ];

            $initialShipmentStatus = $handoverMethod === 'self_drop_off'
                ? 'awaiting_seller_drop_off'
                : 'pickup_requested';

            $payMethod = strtoupper((string) ($locked->payment?->method ?? 'COD'));
            $isPrepaid = in_array($payMethod, ['GCASH', 'WALLET'], true)
                && in_array($locked->payment_status, ['submitted', 'paid', 'verified'], true);
            $codToCollect = $isPrepaid ? 0.00 : (float) $locked->total_amount;

            $shipmentPayload = [
                'tracking_number'           => $this->generateUniqueTrackingNumber(),
                'order_id'                  => $locked->order_id,
                'seller_order_id'           => $locked->id,
                'seller_profile_id'         => $locked->seller_profile_id,
                'logistics_profile_id'      => $logisticsProfile->id,
                'status'                    => $initialShipmentStatus,
                'total_weight'              => $locked->total_weight,
                'cod_amount'                => $codToCollect,
                'shipping_fee'              => $locked->shipping_fee,
                'pickup_address_snapshot'   => $locked->pickup_address_snapshot,
                'hub_address_snapshot'      => $hubSnapshot,
                'delivery_address_snapshot' => $locked->delivery_address_snapshot,
                'pickup_requested_at'       => now(),
            ];

            if (Schema::hasColumn('shipments', 'origin_logistics_hub_id')) {
                $shipmentPayload['origin_logistics_hub_id'] = $selectedHub->id;
            }
            if (Schema::hasColumn('shipments', 'destination_logistics_hub_id')) {
                $shipmentPayload['destination_logistics_hub_id'] = $destinationHub->id;
                $shipmentPayload['origin_to_hub_distance_km'] = $originDistanceKm;
                $shipmentPayload['hub_to_buyer_distance_km'] = $destDistanceKm;
            }
            if (Schema::hasColumn('shipments', 'current_logistics_hub_id')) {
                $shipmentPayload['current_logistics_hub_id'] = $selectedHub->id;
            }
            if (Schema::hasColumn('shipments', 'requires_inter_hub_transfer')) {
                $shipmentPayload['requires_inter_hub_transfer'] = $requiresInterHubTransfer;
            }

            $newShipment = Shipment::create($shipmentPayload);

            if ($requiresInterHubTransfer && Schema::hasTable('inter_hub_transfers')) {
                $hubToHubDist = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
                    [
                        'province'     => $selectedHub->province,
                        'municipality' => $selectedHub->municipality,
                        'barangay'     => $selectedHub->barangay,
                        'latitude'     => $selectedHub->latitude,
                        'longitude'    => $selectedHub->longitude,
                    ],
                    [
                        'province'     => $destinationHub->province,
                        'municipality' => $destinationHub->municipality,
                        'barangay'     => $destinationHub->barangay,
                        'latitude'     => $destinationHub->latitude,
                        'longitude'    => $destinationHub->longitude,
                    ],
                    $selectedHub,
                    $destinationHub
                );

                InterHubTransfer::create([
                    'transfer_reference'   => 'IHT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                    'shipment_id'          => $newShipment->id,
                    'seller_order_id'      => $locked->id,
                    'logistics_profile_id' => $logisticsProfile->id,
                    'origin_hub_id'        => $selectedHub->id,
                    'destination_hub_id'   => $destinationHub->id,
                    'status'               => 'pending_origin_arrival',
                    'distance_km'          => $hubToHubDist,
                    'notes'                => "Scheduled inter-hub transfer from {$selectedHub->hub_name} ({$selectedHub->municipality}) to {$destinationHub->hub_name} ({$destinationHub->municipality}).",
                ]);
            }

            $this->shipmentVerificationService->ensurePackagesForShipment(
                shipment: $newShipment,
                sellerOrder: $locked,
                handoverMethod: $handoverMethod,
                logisticsHubId: $selectedHub->id,
                packageCount: $packageCount
            );

            $relayNote = $requiresInterHubTransfer
                ? " (Origin Hub: {$selectedHub->hub_name} -> Inter-Hub Transfer to Destination Branch: {$destinationHub->hub_name})"
                : '';

            ShipmentTracking::create([
                'shipment_id'     => $newShipment->id,
                'seller_order_id' => $locked->id,
                'status'          => $initialShipmentStatus,
                'title'           => $handoverMethod === 'self_drop_off' ? 'Seller Selected Self Drop-Off' : 'Seller Selected Courier Pickup',
                'description'     => "Shipping Label #{$newShipment->tracking_number} generated for {$selectedHub->hub_name} ({$selectedHub->municipality}){$relayNote}.",
                'location'        => ($locked->pickup_address_snapshot['municipality'] ?? '') . ', ' . ($locked->pickup_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $previousStatus,
                'to_status'       => 'ready_for_pickup',
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'remarks'         => "Shipment #{$newShipment->tracking_number} prepared{$relayNote}.",
            ]);

            $this->orderCompletionService->syncParentOrderStatus($locked->order);

            return $newShipment;
        });

        $this->buyerNotifications->readyForPickup($sellerOrder, $shipment, $handoverMethod);

        return back()->with('success', "Shipment #{$shipment->tracking_number} created via {$selectedHub->hub_name}!");
    }

    public function shippingLabel(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $sellerOrder->load(['order', 'items', 'sellerProfile', 'logisticsProfile', 'extra', 'shipment.packages.logisticsHub']);
        $shipment = $sellerOrder->shipment;
        if (! $shipment) {
            return redirect()->route('seller.orders.show', $sellerOrder->id);
        }

        $packages = $this->shipmentVerificationService->ensurePackagesForShipment($shipment, $sellerOrder);
        $autoPrint = $request->boolean('print');

        return view('seller.orders.shipping-label', compact('sellerOrder', 'shipment', 'packages', 'autoPrint'));
    }

    public function packingSlip(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $sellerOrder->load(['order', 'items', 'sellerProfile', 'logisticsProfile', 'extra', 'shipment.packages.logisticsHub']);
        $shipment = $sellerOrder->shipment;
        if (! $shipment) {
            return redirect()->route('seller.orders.show', $sellerOrder->id);
        }

        $packages = $this->shipmentVerificationService->ensurePackagesForShipment($shipment, $sellerOrder);
        $autoPrint = $request->boolean('print');

        return view('seller.orders.packing-slip', compact('sellerOrder', 'shipment', 'packages', 'autoPrint'));
    }

    public function settlements(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->sellerProfile;

        $this->orderCompletionService->runLifecycleMaintenance(sellerId: (int) $user->id);

        $completedOrders = SellerOrder::where('seller_id', $user->id)
            ->where('status', 'completed')
            ->get();

        foreach ($completedOrders as $compOrder) {
            $this->roleWalletService->releaseCompletedOrderEarnings($compOrder);
        }

        $sellerWallet = $this->roleWalletService->getOrCreateWallet(
            ownerType: 'Seller',
            ownerId: (int) $profile->id,
            ownerName: $profile->business_name
        );

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $commissionRecords = CommissionRecord::with(['sellerOrder.items', 'sellerOrder.extra', 'logisticsProfile', 'settlementItem.settlement'])
            ->where('seller_profile_id', $profile->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'commissions_page');

        $settlements = SellerSettlement::with(['logisticsProfile', 'items.sellerOrder'])
            ->where('seller_profile_id', $profile->id)
            ->orderBy('settled_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'settlements_page');

        $payoutRequests = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::with('histories')
                ->where('requester_type', 'Seller')
                ->where('requester_id', $profile->id)
                ->orderByDesc('created_at')
                ->get()
            : (Schema::hasTable('seller_payout_requests')
                ? collect(DB::table('seller_payout_requests')->where('seller_profile_id', $profile->id)->orderByDesc('created_at')->get())
                : collect());

        $periodOrders = SellerOrder::with(['items', 'extra'])
            ->where('seller_profile_id', $profile->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderByOperationalQueue()
            ->get();

        $periodCommissions = CommissionRecord::where('seller_profile_id', $profile->id)
            ->whereBetween('calculated_at', [$start, $end])
            ->get();

        $walletTransactions = WalletTransaction::with('sellerOrder')
            ->where('owner_type', 'Seller')
            ->where('owner_id', $profile->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $reportSummary = [
            'from_date'                => $start->toDateString(),
            'to_date'                  => $end->toDateString(),
            'period_orders_total'      => $periodOrders->count(),
            'period_completed_count'   => $periodOrders->where('status', 'completed')->count(),
            'period_cancelled_rts'     => $periodOrders->whereIn('status', ['cancelled', 'rejected', 'returned_to_seller'])->count(),
            'period_gross_merchandise' => round((float) $periodCommissions->sum('product_subtotal'), 2),
            'period_admin_10_fee'      => round((float) $periodCommissions->sum('admin_commission_amount'), 2),
            'period_seller_90_net'     => round((float) $periodCommissions->sum('seller_net_earning'), 2),
            'period_store_voucher_sub' => round((float) $periodOrders->sum(fn ($o) => (float) ($o->extra?->store_voucher_discount ?? 0)), 2),
        ];

        $arrearsAmount = (float) ($sellerWallet->platform_commission_arrears ?? ($sellerWallet->available_balance < 0 ? abs((float) $sellerWallet->available_balance) : 0));

        $metrics = [
            'wallet_available'       => (float) $sellerWallet->available_balance,
            'wallet_pending_escrow'  => (float) $sellerWallet->pending_balance,
            'wallet_arrears'         => $arrearsAmount,
            'gross_product_sales'    => (float) CommissionRecord::where('seller_profile_id', $profile->id)->sum('product_subtotal'),
            'admin_commission_total' => (float) CommissionRecord::where('seller_profile_id', $profile->id)->sum('admin_commission_amount'),
            'net_seller_earnings'    => (float) CommissionRecord::where('seller_profile_id', $profile->id)->sum('seller_net_earning'),
            'pending_settlement'     => (float) CommissionRecord::where('seller_profile_id', $profile->id)->where('status', 'recorded')->sum('seller_net_earning'),
            'settled_total'          => (float) CommissionRecord::where('seller_profile_id', $profile->id)->where('status', 'settled')->sum('seller_net_earning'),
        ];

        return view('seller.orders.settlements', compact(
            'profile',
            'sellerWallet',
            'fromDate',
            'toDate',
            'commissionRecords',
            'settlements',
            'payoutRequests',
            'walletTransactions',
            'reportSummary',
            'metrics'
        ));
    }

    public function requestPayout(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $validated = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:100'],
            'payout_method'    => ['nullable', 'in:GCASH,BANK'],
            'account_name'     => ['required', 'string', 'max:120'],
            'account_number'   => ['required', 'string', 'max:50'],
            'idempotency_key'  => ['nullable', 'string', 'max:64'],
        ]);

        $withdrawal = $this->roleWalletService->requestSellerPayout(
            sellerProfileId: (int) $profile->id,
            sellerName: (string) $profile->business_name,
            amount: (float) $validated['requested_amount'],
            payoutMethod: 'GCASH',
            accountName: $validated['account_name'],
            accountNumber: $validated['account_number'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return back()->with('success', 'Payout withdrawal request of ₱' . number_format((float) $withdrawal->requested_amount, 2) . ' via GCash submitted! Your Available E-Wallet Balance has been updated.');
    }

    public function settleArrearsFromWallet(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if (! $profile) {
            abort(403);
        }

        $result = $this->roleWalletService->settleSellerArrearsFromAvailableBalance(
            sellerProfileId: (int) $profile->id,
            sellerName: (string) $profile->business_name
        );

        $offsetFormatted = number_format((float) $result['offset_amount'], 2);
        $remainingFormatted = number_format((float) $result['remaining_arrears'], 2);

        $msg = (float) $result['remaining_arrears'] <= 0.0
            ? "Successfully settled ₱{$offsetFormatted} of platform commission arrears directly from your Available Seller E-Wallet! Your arrears balance is now ₱0.00."
            : "Offset ₱{$offsetFormatted} of platform commission arrears from your Available Seller E-Wallet. Remaining arrears: ₱{$remainingFormatted}.";

        return back()->with('success', $msg);
    }

    public function submitArrearsRemittance(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $validated = $request->validate([
            'amount_remitted'        => ['required', 'numeric', 'gt:0'],
            'sender_gcash_number'    => ['required', 'regex:/^09\d{9}$/'],
            'gcash_reference_number' => ['required', 'string', 'min:4', 'max:80'],
            'screenshot'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $wallet = $this->roleWalletService->getOrCreateWallet('Seller', (int) $profile->id, $profile->business_name);
        $screenshotPath = $request->hasFile('screenshot')
            ? $request->file('screenshot')->store('seller_arrears_remittances', 'public')
            : null;

        DB::transaction(function () use ($profile, $wallet, $validated, $screenshotPath) {
            $remitAmount = round((float) $validated['amount_remitted'], 2);

            DB::table('seller_commission_remittances')->insert([
                'remittance_reference'   => 'ACR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'seller_profile_id'      => $profile->id,
                'role_wallet_id'         => $wallet->id,
                'amount_remitted'        => $remitAmount,
                'sender_gcash_number'    => $validated['sender_gcash_number'],
                'gcash_reference_number' => strtoupper(trim($validated['gcash_reference_number'])),
                'screenshot_path'        => $screenshotPath,
                'status'                 => 'verified',
                'verified_at'            => now(),
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $newAvailable = round((float) $wallet->available_balance + $remitAmount, 2);
            $newArrears = max(0.00, round((float) $wallet->platform_commission_arrears - $remitAmount, 2));

            $wallet->update([
                'available_balance'           => $newAvailable,
                'platform_commission_arrears' => $newArrears,
            ]);

            if ($newArrears < RoleWalletService::GCASH_ARREARS_CAP && Schema::hasColumn('seller_profiles', 'gcash_blocked_for_arrears')) {
                $profile->update(['gcash_blocked_for_arrears' => false]);
            }

            WalletTransaction::create([
                'role_wallet_id'          => $wallet->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(7)),
                'owner_type'              => 'Seller',
                'owner_id'                => $profile->id,
                'entry_type'              => 'gcash_arrears_remitted',
                'payment_method'          => 'GCASH',
                'amount'                  => $remitAmount,
                'pending_balance_after'   => (float) $wallet->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => "Seller remitted ₱" . number_format($remitAmount, 2) . " via GCash (Ref #{$validated['gcash_reference_number']}) to clear platform commission arrears.",
            ]);
        });

        return back()->with('success', 'GCash platform commission arrears remittance recorded and credited to your Seller E-Wallet!');
    }

    public function formalPdfReport(Request $request)
    {
        $profile = $request->user()->sellerProfile;

        $report = $this->formalReportService->buildRoleReportPayload(
            role: 'Seller',
            entityId: (int) $profile->id,
            entityName: $profile->business_name,
            request: $request
        );

        return view('reports.formal-pdf-report', compact('report'));
    }

    protected function getEligibleLogisticsProviders(?SellerProfile $sellerProfile): Collection
    {
        if (! $sellerProfile) {
            return new Collection();
        }

        foreach (LogisticsProfile::all() as $lp) {
            $lp->ensureMainHubExists();
        }

        return LogisticsProfile::with(['hubs' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('business_name')
            ->get();
    }

    protected function getEligibleLogisticsHubs(
        Collection $eligibleProviders,
        ?SellerProfile $sellerProfile,
        ?SellerOrder $sellerOrder = null,
        array $routeEvaluationMap = []
    ): Collection {
        if ($eligibleProviders->isEmpty()) {
            return new Collection();
        }

        $providerIds = $eligibleProviders->pluck('id')->all();
        $pickupSnap = (array) ($sellerOrder?->pickup_address_snapshot ?? []);
        $sellerProv = (string) ($pickupSnap['province'] ?? ($sellerProfile?->province ?? ''));
        $sellerMun = (string) ($pickupSnap['municipality'] ?? ($sellerProfile?->municipality ?? ''));
        $sellerBrgy = (string) ($pickupSnap['barangay'] ?? ($sellerProfile?->barangay ?? ''));
        $orderWeight = (float) ($sellerOrder?->total_weight ?: 0.5);

        $hubs = LogisticsHub::with('parentLogisticsProfile')
            ->whereIn('parent_logistics_profile_id', $providerIds)
            ->where('status', 'active')
            ->get();

        $evaluatedHubs = $hubs->map(function (LogisticsHub $hub) use ($sellerProv, $sellerMun, $sellerBrgy, $sellerProfile, $orderWeight, $routeEvaluationMap) {
            $origEval = $this->shippingCalculatorService->evaluateOriginHubForSeller(
                hub: $hub,
                sellerProvince: $sellerProv,
                sellerMunicipality: $sellerMun,
                sellerBarangay: $sellerBrgy,
                sellerProfile: $sellerProfile,
                totalWeightKg: $orderWeight
            );

            $providerEval = $routeEvaluations[$hub->parent_logistics_profile_id] ?? ($routeEvaluationMap[$hub->parent_logistics_profile_id] ?? null);
            $destHub = $providerEval['destination_hub'] ?? null;
            $destRidersCount = (int) ($providerEval['destination_riders_count'] ?? 0);
            $destDistanceKm = $providerEval['destination_distance_km'] ?? null;

            $isSelectable = $origEval['is_covered'] && $destHub !== null && $destRidersCount > 0;

            $hub->setAttribute('origin_distance_km', $origEval['distance_km']);
            $hub->setAttribute('origin_couriers_count', $origEval['active_couriers_count']);
            $hub->setAttribute('is_origin_covered', $origEval['is_covered']);
            $hub->setAttribute('destination_hub_name', $destHub?->hub_name);
            $hub->setAttribute('destination_hub_municipality', $destHub?->municipality);
            $hub->setAttribute('destination_distance_km', $destDistanceKm);
            $hub->setAttribute('destination_riders_count', $destRidersCount);
            $hub->setAttribute('requires_inter_hub_transfer', $destHub && (int) $hub->id !== (int) $destHub->id);
            $hub->setAttribute('is_route_serviceable', $isSelectable);
            $hub->setAttribute('requires_self_drop_off', (int) $origEval['active_couriers_count'] <= 0);
            $hub->setAttribute('unserviceable_reason', ! $origEval['is_covered']
                ? "Outside First-Mile Courier range ({$origEval['distance_km']} km from your store)"
                : ($providerEval['unserviceable_reason'] ?? null)
            );

            return $hub;
        });

        $sorted = $evaluatedHubs->sortBy(function (LogisticsHub $hub) {
            $tierPenalty = $hub->getAttribute('is_route_serviceable') ? 0 : 100000;
            $courierPenalty = ((int) $hub->getAttribute('origin_couriers_count') > 0) ? 0 : 5000;
            return $tierPenalty + $courierPenalty + (float) ($hub->getAttribute('origin_distance_km') ?? 999);
        })->values();

        return new Collection($sorted->all());
    }

    protected function normalizeLocation(?string $value): string
    {
        if (! $value) {
            return '';
        }

        $cleaned = strtolower(trim($value));
        $cleaned = preg_replace('/\b(city of|municipality of|province of)\b/', '', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim((string) $cleaned);
    }

    protected function generateUniqueTrackingNumber(): string
    {
        do {
            $candidate = 'TRK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Shipment::where('tracking_number', $candidate)->exists());

        return $candidate;
    }
}