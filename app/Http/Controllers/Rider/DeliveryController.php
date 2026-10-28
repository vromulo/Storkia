<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CodRemittanceItem;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Notifications\OrderLifecycleNotification;
use App\Services\CommissionCalculatorService;
use App\Services\OrderCompletionService;
use App\Services\ShipmentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected CommissionCalculatorService $commissionCalculatorService,
        protected ShipmentVerificationService $shipmentVerificationService
    ) {}

    public function index(Request $request)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();
        $rider->loadMissing(['logisticsProfile', 'currentHubAssignment.hub', 'barangayCoverages']);

        if ($rider->logistics_profile_id) {
            $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(
                logisticsProfileId: (int) $rider->logistics_profile_id
            );
        }

        $tab = $request->query('tab', 'deliveries');

        $activeAssignments = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.logisticsHub',
            'shipment.packages.verifications',
            'sellerOrder.items',
            'sellerOrder.buyer',
        ])->where('rider_id', $rider->id)
          ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
          ->orderBy('assigned_at', 'asc')
          ->orderBy('id', 'asc')
          ->get();

        foreach ($activeAssignments as $activeAssignment) {
            if ($activeAssignment->shipment && $activeAssignment->shipment->packages->isEmpty() && $activeAssignment->sellerOrder) {
                $this->shipmentVerificationService->ensurePackagesForShipment(
                    shipment: $activeAssignment->shipment,
                    sellerOrder: $activeAssignment->sellerOrder
                );
                $activeAssignment->shipment->load('packages.verifications');
            }
        }

        $historyAssignments = DeliveryAssignment::with([
            'shipment.packages',
            'sellerOrder.items',
            'proof',
        ])->where('rider_id', $rider->id)
          ->whereIn('status', ['delivered', 'failed', 'reassigned'])
          ->orderBy('created_at', 'asc')
          ->orderBy('id', 'asc')
          ->paginate(10, ['*'], 'history_page');

        $unremittedCollections = CodCollection::with(['sellerOrder.items', 'shipment'])
            ->where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->orderBy('collected_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $remittances = CodRemittance::with(['items.sellerOrder', 'verifier'])
            ->where('rider_id', $rider->id)
            ->orderBy('submitted_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'remittances_page');

        $unremittedTotal = round((float) $unremittedCollections->sum('collected_amount'), 2);
        $unremittedRiderComm = round((float) $unremittedCollections->sum(fn ($c) => round((float) $c->shipping_fee * 0.10, 2)), 2);

        $metrics = [
            'active_deliveries'       => $activeAssignments->count(),
            'completed_deliveries'    => DeliveryAssignment::where('rider_id', $rider->id)->where('status', 'delivered')->count(),
            'cod_on_hand'             => $unremittedTotal,
            'pending_rider_comm'      => $unremittedRiderComm,
            'verified_rider_earnings' => (float) CodRemittance::where('rider_id', $rider->id)->where('status', 'verified')->sum('total_rider_commission'),
        ];

        return view('rider.dashboard', compact(
            'rider',
            'tab',
            'activeAssignments',
            'historyAssignments',
            'unremittedCollections',
            'remittances',
            'metrics'
        ));
    }

    public function acceptAssignment(Request $request, DeliveryAssignment $assignment)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        DB::transaction(function () use ($assignment, $rider) {
            $locked = DeliveryAssignment::with(['shipment.packages'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'assigned') {
                throw ValidationException::withMessages([
                    'delivery' => 'This delivery assignment has already been accepted or processed.',
                ]);
            }

            /** @var Shipment $shipment */
            $shipment = $locked->shipment;
            if ((int) $shipment->current_rider_id !== (int) $rider->id) {
                throw ValidationException::withMessages([
                    'delivery' => 'You are no longer the active rider assigned to this shipment.',
                ]);
            }

            $locked->update([
                'status' => 'accepted',
            ]);

            foreach ($shipment->packages as $pkg) {
                $pkg->update([
                    'rider_accepted_at' => now(),
                ]);
            }

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $shipment->seller_order_id,
                'status'          => 'rider_assigned',
                'title'           => 'Rider Accepted Delivery Assignment',
                'description'     => "Rider {$rider->full_name} accepted the delivery task (Attempt #{$locked->attempt_number}). Proceed to scan/verify the package QR Code at the hub.",
                'location'        => $rider->assigned_hub?->hub_name ?? ($shipment->logisticsProfile?->business_name ?? 'Sorting Hub'),
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);
        });

        return back()->with('success', 'Step 1 Complete: Delivery assignment accepted! Now scan the package QR Code or verify the package reference before collecting the parcel.');
    }

    public function verifyPackageLookup(Request $request, DeliveryAssignment $assignment)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:150'],
            'verification_method' => ['required', 'in:qr,manual'],
            'stage'               => ['nullable', 'in:rider_collection,delivery_confirmation'],
        ], [
            'lookup_input.required' => 'Please scan the package QR Code or enter the Tracking / Package Reference.',
        ]);

        $shipment = $assignment->shipment;
        if (! $shipment) {
            abort(404);
        }

        $stage = $validated['stage'] ?? ($assignment->status === 'out_for_delivery' ? 'delivery_confirmation' : 'rider_collection');

        try {
            $result = $this->shipmentVerificationService->lookupPackageForActor(
                rawInput: $validated['lookup_input'],
                verificationMethod: $validated['verification_method'],
                stage: $stage,
                actorType: 'Rider',
                actor: $rider
            );

            if ((int) $result['shipment']->id !== (int) $shipment->id) {
                throw ValidationException::withMessages([
                    'lookup_input' => 'Scanned package does not belong to this active delivery assignment.',
                ]);
            }

            $result['verification']->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            if ($stage === 'rider_collection') {
                $result['package']->update([
                    'rider_verified_at'         => now(),
                    'rider_verification_method' => $validated['verification_method'],
                ]);
            } else {
                $result['package']->update([
                    'delivery_verified_at'         => now(),
                    'delivery_verification_method' => $validated['verification_method'],
                ]);
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $msg = $stage === 'rider_collection'
            ? "Package {$result['package']->package_reference} verified via " . strtoupper($validated['verification_method']) . "! Review the manifest and click Confirm Physical Collection & Start Delivery."
            : "Package {$result['package']->package_reference} verified for Buyer handover! You may now confirm COD payment and upload photo proof.";

        return back()->with('success', $msg);
    }

    public function startOutForDelivery(Request $request, DeliveryAssignment $assignment)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $notifications = [];

        DB::transaction(function () use ($assignment, $rider, &$notifications) {
            $locked = DeliveryAssignment::with([
                'shipment.sellerOrder.order',
                'shipment.sellerOrder.buyer',
                'shipment.packages',
            ])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'assigned') {
                throw ValidationException::withMessages([
                    'delivery' => 'You must explicitly accept the delivery assignment before collecting the package.',
                ]);
            }

            if (! in_array($locked->status, ['accepted', 'collected'], true)) {
                throw ValidationException::withMessages([
                    'delivery' => 'This assignment is already out for delivery or completed.',
                ]);
            }

            /** @var Shipment $shipment */
            $shipment = $locked->shipment;
            if ((int) $shipment->current_rider_id !== (int) $rider->id) {
                throw ValidationException::withMessages([
                    'delivery' => 'You are no longer the active rider assigned to this shipment.',
                ]);
            }

            if ($shipment->status !== 'rider_assigned') {
                throw ValidationException::withMessages([
                    'delivery' => 'Shipment must be in Rider Assigned status at the sorting hub before starting delivery.',
                ]);
            }

            foreach ($shipment->packages as $pkg) {
                if ($pkg->rider_accepted_at === null) {
                    throw ValidationException::withMessages([
                        'delivery' => 'You must accept the delivery assignment first.',
                    ]);
                }

                if ($pkg->rider_verified_at === null) {
                    $verifiedBefore = ShipmentVerification::where('shipment_package_id', $pkg->id)
                        ->where('stage', 'rider_collection')
                        ->where('actor_type', 'Rider')
                        ->where('actor_id', $rider->id)
                        ->where('is_confirmed', true)
                        ->exists();

                    if (! $verifiedBefore) {
                        throw ValidationException::withMessages([
                            'delivery' => "Package \"{$pkg->package_reference}\" must be scanned or verified via QR/manual lookup before confirming package collection from the hub.",
                        ]);
                    }
                }

                $pkg->update([
                    'rider_collected_at' => now(),
                ]);
            }

            $locked->update([
                'status'              => 'out_for_delivery',
                'collected_at'        => $locked->collected_at ?? now(),
                'out_for_delivery_at' => now(),
            ]);

            $shipment->update([
                'status'              => 'out_for_delivery',
                'out_for_delivery_at' => now(),
            ]);

            $sellerOrder = $shipment->sellerOrder;
            $prevStatus = $sellerOrder->status;

            $sellerOrder->update([
                'status' => 'out_for_delivery',
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'out_for_delivery',
                'title'           => 'Rider Collected Package — Out for Delivery',
                'description'     => "Rider {$rider->full_name} ({$rider->vehicle_type} - {$rider->plate_number}) verified and collected the parcel from the sorting hub and is now Out for Delivery. Prepare COD ₱" . number_format((float) $shipment->cod_amount, 2) . '.',
                'location'        => ($shipment->delivery_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->delivery_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $prevStatus,
                'to_status'       => 'out_for_delivery',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Rider {$rider->full_name} verified package collection and is out for delivery.",
            ]);

            $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);

            if ($sellerOrder->buyer) {
                $notifications[] = [
                    'recipient' => $sellerOrder->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Your Order is Out for Delivery!',
                        message: "Rider {$rider->full_name} is on the way with Order #{$sellerOrder->seller_order_number}. Please prepare ₱" . number_format((float) $sellerOrder->total_amount, 2) . " COD.",
                        type: 'info',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return back()->with('success', 'Package collection confirmed! Shipment is now Out for Delivery.');
    }

    public function confirmDelivered(Request $request, DeliveryAssignment $assignment)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'proof_photo'         => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'collected_amount'    => ['required', 'numeric', 'min:0'],
            'verification_lookup' => ['nullable', 'string', 'max:150'],
            'verification_method' => ['nullable', 'in:qr,manual'],
            'recipient_name'      => ['nullable', 'string', 'max:120'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ], [
            'proof_photo.required' => 'Mandatory photo proof of delivery is required before confirming delivery.',
            'proof_photo.image'    => 'Delivery proof must be a valid image file.',
        ]);

        $notifications = [];

        DB::transaction(function () use ($request, $assignment, $rider, $validated, &$notifications) {
            $locked = DeliveryAssignment::with([
                'shipment.sellerOrder.order',
                'shipment.sellerOrder.payment',
                'shipment.sellerOrder.buyer',
                'shipment.sellerOrder.seller',
                'shipment.logisticsProfile.user',
                'shipment.packages',
            ])->where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'out_for_delivery') {
                throw ValidationException::withMessages([
                    'delivery' => 'Package must be accepted, verified, collected from the hub, and marked Out for Delivery before confirming delivery.',
                ]);
            }

            /** @var Shipment $shipment */
            $shipment = $locked->shipment;

            if ($shipment->status !== 'out_for_delivery' || (int) $shipment->current_rider_id !== (int) $rider->id) {
                throw ValidationException::withMessages([
                    'delivery' => 'Invalid shipment state or rider assignment.',
                ]);
            }

            foreach ($shipment->packages as $pkg) {
                if ($pkg->rider_collected_at === null) {
                    throw ValidationException::withMessages([
                        'delivery' => 'Cannot confirm delivery before confirming package collection from the sorting hub.',
                    ]);
                }

                $pkg->update([
                    'delivery_verified_at'         => $pkg->delivery_verified_at ?? now(),
                    'delivery_verification_method' => $validated['verification_method'] ?? ($pkg->delivery_verification_method ?: 'manual'),
                ]);
            }

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = $shipment->sellerOrder;

            if (CodCollection::where('seller_order_id', $sellerOrder->id)->exists()) {
                throw ValidationException::withMessages([
                    'cod' => 'COD payment has already been recorded for this shipment.',
                ]);
            }

            $expectedCod = round((float) $sellerOrder->total_amount, 2);
            $collectedCod = round((float) $validated['collected_amount'], 2);

            if (abs($collectedCod - $expectedCod) > 0.009) {
                throw ValidationException::withMessages([
                    'collected_amount' => 'Collected COD amount must match the exact required total of ₱' . number_format($expectedCod, 2) . '.',
                ]);
            }

            $photoPath = $request->file('proof_photo')->store('delivery_proofs', 'local');
            $attemptNo = (int) $shipment->delivery_attempts_count + 1;

            $attempt = DeliveryAttempt::create([
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $locked->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => $attemptNo,
                'outcome'                => 'delivered',
                'attempted_at'           => now(),
            ]);

            DeliveryProof::create([
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $locked->id,
                'delivery_attempt_id'    => $attempt->id,
                'rider_id'               => $rider->id,
                'photo_path'             => $photoPath,
                'recipient_name'         => $validated['recipient_name'] ?: ($sellerOrder->delivery_address_snapshot['recipient_name'] ?? 'Buyer'),
                'notes'                  => $validated['notes'] ?? null,
                'captured_at'            => now(),
            ]);

            CodCollection::create([
                'collection_reference'   => 'COD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $locked->id,
                'logistics_profile_id'   => $locked->logistics_profile_id,
                'rider_id'               => $rider->id,
                'expected_amount'        => $expectedCod,
                'collected_amount'       => $collectedCod,
                'product_subtotal'       => $sellerOrder->subtotal_amount,
                'shipping_fee'           => $sellerOrder->shipping_fee,
                'status'                 => 'collected',
                'collected_at'           => now(),
            ]);

            $locked->update([
                'status'       => 'delivered',
                'completed_at' => now(),
            ]);

            $shipment->update([
                'status'                  => 'delivered',
                'delivery_attempts_count' => $attemptNo,
                'delivered_at'            => now(),
            ]);

            $prevOrderStatus = $sellerOrder->status;
            $confirmationDeadline = now()->addHours(SellerOrder::BUYER_CONFIRMATION_HOURS);

            $sellerOrder->update([
                'status'                   => 'delivered',
                'payment_status'           => 'collected',
                'delivered_at'             => now(),
                'confirmation_deadline_at' => $confirmationDeadline,
            ]);

            $sellerOrder->payment?->update([
                'status'       => 'collected',
                'collected_at' => now(),
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'delivered',
                'title'           => 'Package Delivered & COD Collected',
                'description'     => "Rider {$rider->full_name} delivered the package, uploaded photo proof, and collected COD ₱" . number_format($collectedCod, 2) . ". 72-hour buyer confirmation window started.",
                'location'        => ($shipment->delivery_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->delivery_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $prevOrderStatus,
                'to_status'       => 'delivered',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Delivered with mandatory photo proof and COD ₱" . number_format($collectedCod, 2) . " collected.",
            ]);

            $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);

            if ($sellerOrder->buyer) {
                $notifications[] = [
                    'recipient' => $sellerOrder->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Package Delivered — Please Confirm Receipt',
                        message: "Order #{$sellerOrder->seller_order_number} was delivered. Please confirm receipt or report any problem within 72 hours.",
                        type: 'info',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            if ($sellerOrder->seller) {
                $notifications[] = [
                    'recipient' => $sellerOrder->seller,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Order Delivered & COD Collected',
                        message: "Order #{$sellerOrder->seller_order_number} has been delivered to the buyer and COD was collected by the rider.",
                        type: 'info',
                        url: route('seller.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return back()->with('success', 'Delivery confirmed with photo proof and COD payment recorded! Remember to remit collected COD to your Logistics hub.');
    }

    public function reportFailedDelivery(Request $request, DeliveryAssignment $assignment)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'max:120'],
            'failure_notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'failure_reason.required' => 'Please select or enter the reason for the unsuccessful delivery.',
        ]);

        $notifications = [];

        DB::transaction(function () use ($assignment, $rider, $validated, &$notifications) {
            $locked = DeliveryAssignment::with([
                'shipment.sellerOrder.order',
                'shipment.sellerOrder.buyer',
                'shipment.sellerOrder.seller',
                'shipment.logisticsProfile.user',
                'shipment.packages',
            ])->where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'out_for_delivery') {
                throw ValidationException::withMessages([
                    'delivery' => 'Only packages that have been accepted, verified, collected from the hub, and marked Out for Delivery can be reported as failed.',
                ]);
            }

            /** @var Shipment $shipment */
            $shipment = $locked->shipment;

            if ($shipment->status !== 'out_for_delivery' || (int) $shipment->current_rider_id !== (int) $rider->id) {
                throw ValidationException::withMessages([
                    'delivery' => 'Invalid shipment state for reporting a failed delivery.',
                ]);
            }

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = $shipment->sellerOrder;

            $attemptNo = (int) $shipment->delivery_attempts_count + 1;

            DeliveryAttempt::create([
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $locked->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => $attemptNo,
                'outcome'                => 'failed',
                'failure_reason'         => $validated['failure_reason'],
                'failure_notes'          => $validated['failure_notes'] ?? null,
                'attempted_at'           => now(),
            ]);

            $locked->update([
                'status'    => 'failed',
                'failed_at' => now(),
                'notes'     => $validated['failure_notes'] ?? $locked->notes,
            ]);

            $shipment->update([
                'status'                  => 'delivery_failed',
                'delivery_attempts_count' => $attemptNo,
                'failed_at'               => now(),
            ]);

            $prevOrderStatus = $sellerOrder->status;
            $sellerOrder->update([
                'status' => 'delivery_failed',
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'delivery_failed',
                'title'           => "Delivery Attempt #{$attemptNo} Unsuccessful",
                'description'     => "Reason: {$validated['failure_reason']}. " . ($validated['failure_notes'] ?? 'Awaiting Logistics hub review for redelivery or return.'),
                'location'        => ($shipment->delivery_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->delivery_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $prevOrderStatus,
                'to_status'       => 'delivery_failed',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Delivery attempt #{$attemptNo} failed: {$validated['failure_reason']}",
            ]);

            if ($shipment->logisticsProfile?->user) {
                $notifications[] = [
                    'recipient' => $shipment->logisticsProfile->user,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Failed Delivery Report Submitted',
                        message: "Rider {$rider->full_name} reported a failed delivery on Shipment #{$shipment->tracking_number} ({$validated['failure_reason']}). Please review to schedule redelivery or return.",
                        type: 'warning',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            if ($sellerOrder->buyer) {
                $notifications[] = [
                    'recipient' => $sellerOrder->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Delivery Attempt Unsuccessful',
                        message: "Delivery attempt #{$attemptNo} for Order #{$sellerOrder->seller_order_number} was unsuccessful ({$validated['failure_reason']}).",
                        type: 'warning',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return back()->with('success', 'Failed delivery report submitted to your Logistics hub for review.');
    }

    public function submitRemittance(Request $request)
    {
        /** @var Rider $rider */
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $remittance = DB::transaction(function () use ($rider, $validated) {
            $collections = CodCollection::where('rider_id', $rider->id)
                ->where('status', 'collected')
                ->orderBy('collected_at', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($collections->isEmpty()) {
                throw ValidationException::withMessages([
                    'remittance' => 'You have no unremitted COD collections to submit.',
                ]);
            }

            $totalCollected = 0.0;
            $totalRiderComm = 0.0;

            foreach ($collections as $col) {
                $totalCollected = round($totalCollected + (float) $col->collected_amount, 2);
                $riderComm = round((float) $col->shipping_fee * 0.10, 2);
                $totalRiderComm = round($totalRiderComm + $riderComm, 2);
            }

            $netRemitted = round($totalCollected - $totalRiderComm, 2);

            $newRemittance = CodRemittance::create([
                'remittance_reference'   => 'REM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                'logistics_profile_id'   => $rider->logistics_profile_id,
                'rider_id'               => $rider->id,
                'total_collected_amount' => $totalCollected,
                'total_rider_commission' => $totalRiderComm,
                'net_remitted_amount'    => $netRemitted,
                'status'                 => 'submitted',
                'submitted_at'           => now(),
                'notes'                  => $validated['notes'] ?? null,
            ]);

            foreach ($collections as $col) {
                $riderComm = round((float) $col->shipping_fee * 0.10, 2);

                CodRemittanceItem::create([
                    'cod_remittance_id'       => $newRemittance->id,
                    'cod_collection_id'       => $col->id,
                    'seller_order_id'         => $col->seller_order_id,
                    'collected_amount'        => $col->collected_amount,
                    'rider_commission_amount' => $riderComm,
                ]);

                $col->update([
                    'status'      => 'remitted',
                    'remitted_at' => now(),
                ]);
            }

            return $newRemittance;
        });

        try {
            $rider->logisticsProfile?->user?->notify(new OrderLifecycleNotification(
                title: 'New Rider COD Remittance Submitted',
                message: "Rider {$rider->full_name} submitted COD Remittance #{$remittance->remittance_reference} (Total COD: ₱" . number_format((float) $remittance->total_collected_amount, 2) . ") for verification.",
                type: 'info',
                url: route('logistics.finance.index')
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', "COD Remittance #{$remittance->remittance_reference} submitted to Logistics for verification!");
    }
}