<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CommissionRecord;
use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use App\Models\OrderPaymentSubmission;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\SellerProfile;
use App\Models\SellerSettlement;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Notifications\OrderLifecycleNotification;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\ShipmentVerificationService;
use App\Services\VoucherService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected OrderCompletionService $orderCompletionService,
        protected ShipmentVerificationService $shipmentVerificationService,
        protected VoucherService $voucherService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->expireOverdueAcceptanceOrders($user->id);
        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(sellerId: (int) $user->id);

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

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

        $query = SellerOrder::with([
            'order',
            'buyer',
            'items',
            'logisticsProfile',
            'shipment.currentCourier',
            'shipment.currentRider',
            'shipment.packages',
            'payment',
            'paymentSubmission',
            'extra',
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
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('seller.orders.index', compact(
            'orders',
            'statusTab',
            'search',
            'counts'
        ));
    }

    public function show(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $this->expireOverdueAcceptanceOrders($user->id);
        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(sellerId: (int) $user->id);
        $sellerOrder->refresh();

        $sellerOrder->load([
            'order',
            'buyer',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'payment',
            'paymentSubmission',
            'extra',
            'statusHistories',
            'shipment.packages.logisticsHub',
            'shipment.trackingLogs',
            'shipment.currentCourier',
            'shipment.currentRider',
            'shipment.deliveryAttempts',
            'shipment.deliveryProofs',
            'commissionRecord',
            'settlementItem.settlement',
        ]);

        if ($sellerOrder->shipment && $sellerOrder->shipment->packages->isEmpty()) {
            $this->shipmentVerificationService->ensurePackagesForShipment(
                shipment: $sellerOrder->shipment,
                sellerOrder: $sellerOrder
            );
            $sellerOrder->load('shipment.packages.logisticsHub');
        }

        $eligibleLogistics = $this->getEligibleLogisticsProviders($user->sellerProfile);
        $eligibleHubs = $this->getEligibleLogisticsHubs($eligibleLogistics, $user->sellerProfile);

        return view('seller.orders.show', compact('sellerOrder', 'eligibleLogistics', 'eligibleHubs'));
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
                'remarks'         => "Seller verified Buyer's GCash payment (Ref #{$submission->reference_number}).",
            ]);
        });

        return back()->with('success', 'GCash online payment verified successfully.');
    }

    public function reportSuspiciousPayment(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'suspicious_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'suspicious_reason.required' => 'Please provide the reason why the submitted GCash payment details appear suspicious.',
            'suspicious_reason.min'      => 'Reason must be at least 5 characters.',
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
                'remarks'         => 'Seller flagged GCash payment as suspicious: ' . trim($validated['suspicious_reason']) . '. Order moved to Pending awaiting buyer response (Switch to COD or Cancel).',
            ]);

            $this->orderCompletionService->syncParentOrderStatus($locked->order);
        });

        try {
            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Action Required: Online Payment Flagged by Seller',
                message: "{$user->sellerProfile?->business_name} reported an issue with your GCash payment on Order #{$sellerOrder->seller_order_number}. Please review your payment details to either continue by switching to Cash on Delivery (COD) or cancel the order.",
                type: 'warning',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return redirect()
            ->route('seller.orders.index', ['status' => 'pending'])
            ->with('success', "Suspicious payment report sent to Buyer. Order #{$sellerOrder->seller_order_number} has been moved to the Pending tab until the Buyer responds.");
    }

    public function accept(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $this->expireOverdueAcceptanceOrders($user->id);
        $sellerOrder->refresh();

        if ($sellerOrder->status === 'pending_buyer_response') {
            return back()->withErrors([
                'order' => 'This order is currently in the Pending tab awaiting the Buyer\'s response to your suspicious payment report.',
            ]);
        }

        if ($sellerOrder->status !== 'pending_seller_acceptance') {
            return back()->withErrors([
                'order' => 'This order can no longer be accepted because its status is ' . $sellerOrder->status_label . '.',
            ]);
        }

        DB::transaction(function () use ($sellerOrder, $user) {
            $locked = SellerOrder::with(['paymentSubmission', 'payment'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending_seller_acceptance') {
                throw ValidationException::withMessages([
                    'order' => 'Order status has already changed.',
                ]);
            }

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

        try {
            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Order Accepted by Seller',
                message: "{$user->sellerProfile?->business_name} has accepted your order #{$sellerOrder->seller_order_number} and is preparing your package.",
                type: 'info',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', "Order #{$sellerOrder->seller_order_number} accepted. Please prepare the package, verify the product checklist, and select your package handover option.");
    }

    public function reject(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Please provide a reason for rejecting this order.',
            'rejection_reason.min'      => 'Rejection reason must be at least 5 characters.',
        ]);

        DB::transaction(function () use ($sellerOrder, $user, $validated) {
            $locked = SellerOrder::with(['order', 'payment'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['pending_seller_acceptance', 'accepted', 'preparing'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Only orders that have not yet been handed over to Logistics can be rejected or cancelled by the seller.',
                ]);
            }

            $prevStatus = $locked->status;

            $locked->update([
                'status'           => 'rejected',
                'payment_status'   => 'cancelled',
                'rejected_at'      => now(),
                'cancelled_at'     => now(),
                'rejection_reason' => trim($validated['rejection_reason']),
            ]);

            $locked->payment?->update([
                'status' => 'cancelled',
            ]);

            $this->inventoryStockService->restoreStockForSellerOrder($locked);
            $this->voucherService->restoreVouchersForSellerOrder($locked);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $prevStatus,
                'to_status'       => 'rejected',
                'actor_type'      => 'Seller',
                'actor_id'        => $user->id,
                'remarks'         => 'Order rejected by seller: ' . trim($validated['rejection_reason']),
            ]);

            $this->orderCompletionService->syncParentOrderStatus($locked->order);
        });

        try {
            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Seller Order Rejected',
                message: "Order #{$sellerOrder->seller_order_number} was rejected by the seller. Reason: {$validated['rejection_reason']}",
                type: 'danger',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', "Order #{$sellerOrder->seller_order_number} has been rejected, reserved stock was restored, and any applied vouchers were returned to the buyer.");
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
        ], [
            'reason.required_if' => 'Please provide a reason (10–300 characters) when rejecting a buyer return/dispute request.',
            'reason.min'         => 'The rejection reason must be at least 10 characters.',
            'reason.max'         => 'The rejection reason must not exceed 300 characters.',
        ]);

        DB::transaction(function () use ($sellerOrder, $user, $validated) {
            $locked = SellerOrder::with(['order', 'payment', 'extra'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'disputed' || ! $locked->has_unresolved_dispute) {
                throw ValidationException::withMessages([
                    'dispute' => 'There is no active return/dispute request on this order.',
                ]);
            }

            $extra = $locked->extra ?: SellerOrderExtra::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
            ]);

            if ($extra->is_escalated_to_admin) {
                throw ValidationException::withMessages([
                    'dispute' => 'This dispute has already been escalated to Storkia Admin and can no longer be modified by the seller.',
                ]);
            }

            if ($validated['decision'] === 'approved') {
                $extra->update([
                    'seller_return_decision'        => 'approved',
                    'seller_return_Decision_reason' => $validated['reason'] ? trim($validated['reason']) : 'Return approved by seller.',
                    'seller_return_responded_at'    => now(),
                ]);

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

                $this->inventoryStockService->restoreStockForSellerOrder($locked);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => 'disputed',
                    'to_status'       => 'returned_to_seller',
                    'actor_type'      => 'Seller',
                    'actor_id'        => $user->id,
                    'remarks'         => 'Seller approved buyer return/refund request.',
                ]);

                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            } else {
                $extra->update([
                    'seller_return_decision'        => 'rejected',
                    'seller_return_Decision_reason' => trim((string) $validated['reason']),
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

        try {
            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: $validated['decision'] === 'approved' ? 'Return Request Approved' : 'Return Request Declined by Seller',
                message: $validated['decision'] === 'approved'
                    ? "Your return request for Order #{$sellerOrder->seller_order_number} has been approved by the seller."
                    : "Seller declined your return request for Order #{$sellerOrder->seller_order_number}. Reason: {$validated['reason']}. You may escalate to Admin if needed.",
                type: $validated['decision'] === 'approved' ? 'info' : 'warning',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', 'Return/dispute response recorded.');
    }

    public function markReadyForPickup(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        if ($sellerOrder->status === 'pending_buyer_response') {
            return back()->withErrors([
                'order' => 'Cannot prepare shipment while waiting for the Buyer\'s response on a flagged payment.',
            ]);
        }

        $eligibleProviders = $this->getEligibleLogisticsProviders($user->sellerProfile);
        $eligibleHubs = $this->getEligibleLogisticsHubs($eligibleProviders, $user->sellerProfile);
        $eligibleHubIds = $eligibleHubs->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        $validated = $request->validate([
            'handover_method'      => ['required', 'in:courier_pickup,self_drop_off'],
            'logistics_hub_id'     => ['nullable', 'integer', 'exists:logistics_hubs,id'],
            'logistics_profile_id' => ['nullable', 'integer', 'exists:logistics_profiles,id'],
            'package_count'        => ['nullable', 'integer', 'min:1', 'max:10'],
            'manifest_confirmed'   => ['required', 'accepted'],
        ], [
            'handover_method.required'    => 'Please select how you will hand over the prepared package to Logistics.',
            'manifest_confirmed.required' => 'Please confirm the manual product checklist before generating the shipment.',
            'manifest_confirmed.accepted' => 'Please confirm that all ordered items have been verified and packed.',
        ]);

        $selectedHub = null;
        if (! empty($validated['logistics_hub_id'])) {
            if (! in_array((int) $validated['logistics_hub_id'], $eligibleHubIds, true)) {
                return back()->withErrors([
                    'logistics_hub_id' => 'The selected Logistics hub is not eligible for your area.',
                ]);
            }
            $selectedHub = LogisticsHub::with('parentLogisticsProfile.user')->findOrFail((int) $validated['logistics_hub_id']);
            $logisticsProfile = $selectedHub->parentLogisticsProfile;
        } elseif (! empty($validated['logistics_profile_id'])) {
            $eligibleProfileIds = $eligibleProviders->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            if (! in_array((int) $validated['logistics_profile_id'], $eligibleProfileIds, true)) {
                return back()->withErrors([
                    'logistics_profile_id' => 'The selected Logistics provider does not service your area.',
                ]);
            }
            /** @var LogisticsProfile $logisticsProfile */
            $logisticsProfile = LogisticsProfile::with('user')->findOrFail((int) $validated['logistics_profile_id']);
            $selectedHub = $logisticsProfile->ensureMainHubExists();
        } else {
            return back()->withErrors([
                'logistics_hub_id' => 'Please select an approved Logistics hub.',
            ]);
        }

        $handoverMethod = $validated['handover_method'];
        $packageCount = (int) ($validated['package_count'] ?? 1);

        $shipment = DB::transaction(function () use ($sellerOrder, $user, $logisticsProfile, $selectedHub, $handoverMethod, $packageCount) {
            $locked = SellerOrder::with(['order', 'items', 'payment'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['accepted', 'preparing'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'This order must be in Accepted or Preparing state before generating the shipment.',
                ]);
            }

            if ($locked->shipment()->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'A shipment and tracking number have already been created for this order.',
                ]);
            }

            $previousStatus = $locked->status;

            $locked->update([
                'logistics_profile_id' => $logisticsProfile->id,
                'status'               => 'ready_for_pickup',
                'ready_for_pickup_at'  => now(),
            ]);

            $hubSnapshot = [
                'logistics_hub_id' => $selectedHub->id,
                'hub_code'         => $selectedHub->hub_code,
                'hub_name'         => $selectedHub->hub_name,
                'is_main_hub'      => (bool) $selectedHub->is_main_hub,
                'handover_method'  => $handoverMethod,
                'business_name'    => $logisticsProfile->business_name,
                'contact_no'       => $selectedHub->contact_no ?: $logisticsProfile->contact_no,
                'province'         => $selectedHub->province ?: $logisticsProfile->province,
                'municipality'     => $selectedHub->municipality ?: $logisticsProfile->municipality,
                'barangay'         => $selectedHub->barangay ?: $logisticsProfile->barangay,
                'street'           => $selectedHub->street ?: $logisticsProfile->street,
                'house_details'    => $selectedHub->house_details ?: $logisticsProfile->house_details,
                'full_address'     => $selectedHub->full_address ?: $logisticsProfile->full_address,
            ];

            $initialShipmentStatus = $handoverMethod === 'self_drop_off'
                ? 'awaiting_seller_drop_off'
                : 'pickup_requested';

            $isPrepaidGcash = strtoupper((string) ($locked->payment?->method ?? 'COD')) === 'GCASH'
                && in_array($locked->payment_status, ['submitted', 'paid', 'verified'], true);

            $codToCollect = $isPrepaidGcash ? 0.00 : (float) $locked->total_amount;

            $newShipment = Shipment::create([
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
            ]);

            $this->shipmentVerificationService->ensurePackagesForShipment(
                shipment: $newShipment,
                sellerOrder: $locked,
                handoverMethod: $handoverMethod,
                logisticsHubId: $selectedHub->id,
                packageCount: $packageCount
            );

            $trackingTitle = $handoverMethod === 'self_drop_off'
                ? 'Seller Selected Self Drop-Off at Logistics Hub'
                : 'Seller Selected Courier Pickup — Package Ready';

            $trackingDesc = $handoverMethod === 'self_drop_off'
                ? "Seller verified the product manifest, generated Shipping Label #{$newShipment->tracking_number}, and selected Self Drop-Off at {$selectedHub->hub_name} ({$selectedHub->municipality})."
                : "Seller verified the product manifest, generated Shipping Label #{$newShipment->tracking_number}, and requested Courier Pickup via {$selectedHub->hub_name} ({$selectedHub->municipality}).";

            ShipmentTracking::create([
                'shipment_id'     => $newShipment->id,
                'seller_order_id' => $locked->id,
                'status'          => $initialShipmentStatus,
                'title'           => $trackingTitle,
                'description'     => $trackingDesc,
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
                'remarks'         => $trackingDesc,
            ]);

            $this->orderCompletionService->syncParentOrderStatus($locked->order);

            return $newShipment;
        });

        try {
            $notifyTitle = $handoverMethod === 'self_drop_off'
                ? 'Incoming Seller Self Drop-Off'
                : 'New Courier Pickup Request';

            $notifyMsg = $handoverMethod === 'self_drop_off'
                ? "{$user->sellerProfile?->business_name} scheduled a Self Drop-Off at {$selectedHub->hub_name} for Tracking #{$shipment->tracking_number}."
                : "{$user->sellerProfile?->business_name} requested a Courier Pickup at {$selectedHub->hub_name} for Tracking #{$shipment->tracking_number}.";

            $logisticsProfile->user?->notify(new OrderLifecycleNotification(
                title: $notifyTitle,
                message: $notifyMsg,
                type: 'info',
                url: route('logistics.shipments.show', $shipment->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id,
                shipmentId: $shipment->id
            ));

            if ($selectedHub->branchUser) {
                $selectedHub->branchUser->notify(new OrderLifecycleNotification(
                    title: $notifyTitle,
                    message: $notifyMsg,
                    type: 'info',
                    url: route('logistics.shipments.show', $shipment->id),
                    orderId: $sellerOrder->order_id,
                    sellerOrderId: $sellerOrder->id,
                    shipmentId: $shipment->id
                ));
            }

            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Package Prepared for Dispatch',
                message: "Your order #{$sellerOrder->seller_order_number} is packed with Tracking #{$shipment->tracking_number}.",
                type: 'info',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id,
                shipmentId: $shipment->id
            ));
        } catch (\Throwable $e) {
        }

        $successText = $handoverMethod === 'self_drop_off'
            ? "Shipment #{$shipment->tracking_number} created for Self Drop-Off at {$selectedHub->hub_name}! Please print and attach the Shipping Label before bringing the package to the hub."
            : "Shipment #{$shipment->tracking_number} marked Ready for Courier Pickup via {$selectedHub->hub_name}! Please print and attach the Shipping Label.";

        return back()->with('success', $successText);
    }

    public function shippingLabel(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $sellerOrder->load([
            'order',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'extra',
            'shipment.packages.logisticsHub',
        ]);

        $shipment = $sellerOrder->shipment;
        if (! $shipment) {
            return redirect()
                ->route('seller.orders.show', $sellerOrder->id)
                ->withErrors(['shipment' => 'Please select a Logistics hub and handover option first to generate the Shipping Label.']);
        }

        $packages = $this->shipmentVerificationService->ensurePackagesForShipment(
            shipment: $shipment,
            sellerOrder: $sellerOrder
        );

        $autoPrint = $request->boolean('print');

        if ($autoPrint || $request->boolean('reprint')) {
            foreach ($packages as $pkg) {
                $pkg->increment('label_printed_count');
                $pkg->update(['last_printed_at' => now()]);
            }
        }

        return view('seller.orders.shipping-label', compact(
            'sellerOrder',
            'shipment',
            'packages',
            'autoPrint'
        ));
    }

    public function packingSlip(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->seller_id !== (int) $user->id) {
            abort(403);
        }

        $sellerOrder->load([
            'order',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'extra',
            'shipment.packages.logisticsHub',
        ]);

        $shipment = $sellerOrder->shipment;
        if (! $shipment) {
            return redirect()
                ->route('seller.orders.show', $sellerOrder->id)
                ->withErrors(['shipment' => 'Please select a Logistics hub and handover option first to generate the Packing Slip / Product Manifest.']);
        }

        $packages = $this->shipmentVerificationService->ensurePackagesForShipment(
            shipment: $shipment,
            sellerOrder: $sellerOrder
        );

        $autoPrint = $request->boolean('print');

        return view('seller.orders.packing-slip', compact(
            'sellerOrder',
            'shipment',
            'packages',
            'autoPrint'
        ));
    }

    public function settlements(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(sellerId: (int) $user->id);

        $commissionRecords = CommissionRecord::with(['sellerOrder.items', 'logisticsProfile', 'settlementItem.settlement'])
            ->where('seller_profile_id', $profile->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'commissions_page');

        $settlements = SellerSettlement::with(['logisticsProfile', 'items.sellerOrder'])
            ->where('seller_profile_id', $profile->id)
            ->orderBy('settled_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'settlements_page');

        $metrics = [
            'gross_product_sales'    => (float) CommissionRecord::where('seller_profile_id', $profile->id)->sum('product_subtotal'),
            'admin_commission_total' => (float) CommissionRecord::where('seller_profile_id', $profile->id)->sum('admin_commission_amount'),
            'net_seller_earnings'    => (float) CommissionRecord::where('seller_profile_id', $profile->id)->sum('seller_net_earning'),
            'pending_settlement'     => (float) CommissionRecord::where('seller_profile_id', $profile->id)->where('status', 'recorded')->sum('seller_net_earning'),
            'settled_total'          => (float) CommissionRecord::where('seller_profile_id', $profile->id)->where('status', 'settled')->sum('seller_net_earning'),
        ];

        return view('seller.orders.settlements', compact(
            'profile',
            'commissionRecords',
            'settlements',
            'metrics'
        ));
    }

    protected function getEligibleLogisticsProviders(?SellerProfile $sellerProfile): Collection
    {
        if (! $sellerProfile) {
            return new Collection();
        }

        foreach (LogisticsProfile::all() as $lp) {
            $lp->ensureMainHubExists();
        }

        $normSellerMun = $this->normalizeLocation($sellerProfile->municipality);
        $normSellerProv = $this->normalizeLocation($sellerProfile->province);

        $allApproved = LogisticsProfile::with(['hubs' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('business_name')
            ->get();

        $sameMunicipality = $allApproved->filter(function (LogisticsProfile $company) use ($normSellerMun, $normSellerProv) {
            if (
                $this->normalizeLocation($company->municipality) === $normSellerMun &&
                $this->normalizeLocation($company->province) === $normSellerProv
            ) {
                return true;
            }

            return $company->hubs->contains(function (LogisticsHub $hub) use ($normSellerMun, $normSellerProv) {
                return $this->normalizeLocation($hub->municipality) === $normSellerMun &&
                    $this->normalizeLocation($hub->province) === $normSellerProv;
            });
        })->values();

        if ($sameMunicipality->isNotEmpty()) {
            return $sameMunicipality;
        }

        $sameProvince = $allApproved->filter(function (LogisticsProfile $company) use ($normSellerProv) {
            if ($this->normalizeLocation($company->province) === $normSellerProv) {
                return true;
            }

            return $company->hubs->contains(function (LogisticsHub $hub) use ($normSellerProv) {
                return $this->normalizeLocation($hub->province) === $normSellerProv;
            });
        })->values();

        return $sameProvince->isNotEmpty() ? $sameProvince : $allApproved;
    }

    protected function getEligibleLogisticsHubs(Collection $eligibleProviders, ?SellerProfile $sellerProfile): Collection
    {
        if ($eligibleProviders->isEmpty()) {
            return new Collection();
        }

        $providerIds = $eligibleProviders->pluck('id')->all();
        $normSellerMun = $this->normalizeLocation($sellerProfile?->municipality);

        $hubs = LogisticsHub::with('parentLogisticsProfile')
            ->whereIn('parent_logistics_profile_id', $providerIds)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality')
            ->get();

        $sorted = $hubs->sortByDesc(function (LogisticsHub $hub) use ($normSellerMun) {
            return $this->normalizeLocation($hub->municipality) === $normSellerMun ? 1 : 0;
        })->values();

        return new Collection($sorted->all());
    }

    protected function expireOverdueAcceptanceOrders(int $sellerId): void
    {
        $overdueOrders = SellerOrder::with(['order', 'payment', 'buyer'])
            ->where('seller_id', $sellerId)
            ->where('status', 'pending_seller_acceptance')
            ->whereNotNull('acceptance_deadline_at')
            ->where('acceptance_deadline_at', '<', now())
            ->get();

        foreach ($overdueOrders as $overdue) {
            DB::transaction(function () use ($overdue) {
                $locked = SellerOrder::with(['order', 'payment'])->where('id', $overdue->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'pending_seller_acceptance') {
                    return;
                }

                $locked->update([
                    'status'           => 'cancelled',
                    'payment_status'   => 'cancelled',
                    'cancelled_at'     => now(),
                    'rejection_reason' => 'Automatically cancelled because the 24-hour seller acceptance deadline expired.',
                ]);

                $locked->payment?->update([
                    'status' => 'cancelled',
                ]);

                $this->inventoryStockService->restoreStockForSellerOrder($locked);
                $this->voucherService->restoreVouchersForSellerOrder($locked);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => 'pending_seller_acceptance',
                    'to_status'       => 'cancelled',
                    'actor_type'      => 'System',
                    'actor_id'        => null,
                    'remarks'         => 'Order automatically cancelled after the 24-hour seller acceptance deadline expired.',
                ]);

                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            });
        }
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