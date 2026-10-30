<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Models\WalletTransaction;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerAbandonmentService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\ShipmentVerificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class DeliveryController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected ShipmentVerificationService $shipmentVerificationService,
        protected RoleWalletService $roleWalletService,
        protected BuyerAbandonmentService $buyerAbandonmentService
    ) {}

    public function index(Request $request)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        $rider->loadMissing(['logisticsProfile', 'currentHubAssignment.hub', 'barangayCoverages']);

        $this->orderCompletionService->runLifecycleMaintenance(
            logisticsProfileId: (int) $rider->logistics_profile_id
        );

        $tab = $request->query('tab', 'active');

        $baseQuery = DeliveryAssignment::where('rider_id', $rider->id);

        $counts = [
            'active'     => (clone $baseQuery)->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])->count(),
            'completed'  => (clone $baseQuery)->whereIn('status', ['delivered', 'failed'])->count(),
            'remittance' => CodCollection::where('rider_id', $rider->id)->whereIn('status', ['collected', 'remitted'])->count(),
        ];

        $assignments = DeliveryAssignment::with([
            'shipment.sellerOrder.items',
            'shipment.sellerOrder.buyer',
            'shipment.sellerOrder.payment',
            'shipment.sellerProfile',
            'shipment.codCollection',
            'shipment.deliveryProofs',
            'shipment.packages.logisticsHub',
            'shipment.packages.verifications',
        ])
            ->where('rider_id', $rider->id)
            ->when($tab === 'active', fn ($q) => $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']))
            ->when($tab === 'completed', fn ($q) => $q->whereIn('status', ['delivered', 'failed']))
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        foreach ($assignments as $assign) {
            if ($assign->shipment && $assign->shipment->packages->isEmpty() && $assign->shipment->sellerOrder) {
                $this->shipmentVerificationService->ensurePackagesForShipment(
                    shipment: $assign->shipment,
                    sellerOrder: $assign->shipment->sellerOrder
                );
                $assign->shipment->load('packages.verifications');
            }
        }

        $codCollections = CodCollection::with(['sellerOrder', 'shipment'])
            ->where('rider_id', $rider->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10, ['*'], 'cod_page')
            ->withQueryString();

        $codMetrics = [
            'cash_on_hand'      => (float) CodCollection::where('rider_id', $rider->id)->where('status', 'collected')->sum('collected_amount'),
            'remitted_pending'  => (float) CodCollection::where('rider_id', $rider->id)->where('status', 'remitted')->sum('remitted_amount'),
            'verified_remitted' => (float) CodCollection::where('rider_id', $rider->id)->where('status', 'verified')->sum('remitted_amount'),
        ];

        $riderWallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        return view('rider.dashboard', compact(
            'rider',
            'tab',
            'counts',
            'assignments',
            'codCollections',
            'codMetrics',
            'riderWallet'
        ));
    }

    public function report(Request $request)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        $rider->loadMissing(['logisticsProfile', 'currentHubAssignment.hub']);

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());

        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $riderWallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        $assignmentsInPeriod = DeliveryAssignment::with(['shipment.sellerOrder', 'shipment.sellerProfile'])
            ->where('rider_id', $rider->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $walletTransactions = WalletTransaction::with(['sellerOrder', 'shipment'])
            ->where('owner_type', 'Rider')
            ->where('owner_id', $rider->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $codInPeriod = CodCollection::with(['sellerOrder', 'shipment'])
            ->where('rider_id', $rider->id)
            ->whereBetween('collected_at', [$start, $end])
            ->orderBy('collected_at', 'asc')
            ->get();

        $summary = [
            'total_assigned'         => $assignmentsInPeriod->count(),
            'delivered_count'        => $assignmentsInPeriod->where('status', 'delivered')->count(),
            'failed_count'           => $assignmentsInPeriod->where('status', 'failed')->count(),
            'active_count'           => $assignmentsInPeriod->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])->count(),
            'period_realized_income' => (float) $walletTransactions->where('entry_type', 'commission_released')->sum('amount'),
            'period_pending_escrow'  => (float) $walletTransactions->where('entry_type', 'escrow_pending')->where('status', 'pending')->sum('amount'),
            'period_cod_collected'   => (float) $codInPeriod->sum('collected_amount'),
            'period_cod_verified'    => (float) $codInPeriod->where('status', 'verified')->sum('remitted_amount'),
        ];

        return view('rider.report', compact(
            'rider',
            'riderWallet',
            'fromDate',
            'toDate',
            'assignmentsInPeriod',
            'walletTransactions',
            'codInPeriod',
            'summary'
        ));
    }

    public function acceptAssignment(Request $request, DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        DB::transaction(function () use ($assignment, $rider) {
            $locked = DeliveryAssignment::with('shipment.packages')->where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'assigned') {
                throw ValidationException::withMessages([
                    'assignment' => 'This delivery assignment has already been accepted or processed.',
                ]);
            }

            $locked->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            foreach ($locked->shipment->packages as $pkg) {
                $pkg->update([
                    'rider_accepted_at' => now(),
                ]);
            }

            ShipmentTracking::create([
                'shipment_id'     => $locked->shipment_id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'rider_assigned',
                'title'           => 'Rider Accepted Delivery Task',
                'description'     => "Rider {$rider->full_name} accepted the delivery assignment. Proceeding to scan/verify package at the sorting hub.",
                'location'        => $rider->assigned_hub?->hub_name ?? $rider->home_hub,
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);
        });

        return back()->with('success', 'Step 1 Complete: Delivery task accepted! Next, scan the package QR code or enter the reference number to verify the package.');
    }

    public function verifyPackageLookup(Request $request, DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:150'],
            'verification_method' => ['required', 'in:qr,manual'],
            'stage'               => ['nullable', 'in:rider_collection,delivery_completion'],
        ]);

        $stage = $validated['stage'] ?? ($assignment->status === 'out_for_delivery' ? 'delivery_completion' : 'rider_collection');

        if ($stage === 'rider_collection' && $assignment->status === 'assigned') {
            return back()->withErrors([
                'assignment' => 'Please complete Step 1 (Accept Delivery Task) before scanning or verifying the package.',
            ]);
        }

        $result = $this->shipmentVerificationService->lookupPackageForActor(
            rawInput: $validated['lookup_input'],
            verificationMethod: $validated['verification_method'],
            stage: $stage,
            actorType: 'Rider',
            actor: $rider
        );

        if ((int) $result['shipment']->id !== (int) $assignment->shipment_id) {
            throw ValidationException::withMessages([
                'lookup_input' => 'The scanned/entered package reference belongs to a different shipment.',
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

            return back()->with(
                'success',
                "Step 2 Complete: Package {$result['package']->package_reference} identified and verified via " . strtoupper($validated['verification_method']) . ". Review the manifest below and click 'Confirm Package Collected & Out for Delivery'."
            );
        }

        $result['package']->update([
            'delivery_verified_at'         => now(),
            'delivery_verification_method' => $validated['verification_method'],
        ]);

        return back()->with(
            'success',
            "Doorstep Package {$result['package']->package_reference} verified via " . strtoupper($validated['verification_method']) . ". You may now upload the delivery photo proof and finalize delivery."
        );
    }

    public function startDelivery(Request $request, DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        DB::transaction(function () use ($assignment, $rider) {
            $locked = DeliveryAssignment::with(['shipment.sellerOrder.order', 'shipment.sellerOrder.buyer', 'shipment.packages'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'assigned') {
                throw ValidationException::withMessages([
                    'assignment' => 'Please accept the delivery task first (Step 1).',
                ]);
            }

            if (! in_array($locked->status, ['accepted', 'collected'], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'This assignment is already out for delivery or completed.',
                ]);
            }

            foreach ($locked->shipment->packages as $pkg) {
                if ($pkg->rider_verified_at === null) {
                    throw ValidationException::withMessages([
                        'assignment' => "Please verify Package \"{$pkg->package_reference}\" via QR Scan or Manual Reference Lookup (Step 2) before confirming collection from the hub.",
                    ]);
                }

                $pkg->update([
                    'rider_collected_at' => now(),
                ]);
            }

            $locked->update([
                'status'              => 'out_for_delivery',
                'accepted_at'         => $locked->accepted_at ?? now(),
                'out_for_delivery_at' => now(),
            ]);

            $locked->shipment->update([
                'status'              => 'out_for_delivery',
                'out_for_delivery_at' => now(),
            ]);

            $prevOrderStatus = $locked->shipment->sellerOrder->status;
            $locked->shipment->sellerOrder->update([
                'status' => 'out_for_delivery',
            ]);

            $hubLocation = $rider->assigned_hub?->hub_name ?? $rider->home_hub;

            ShipmentTracking::create([
                'shipment_id'     => $locked->shipment_id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'out_for_delivery',
                'title'           => 'Package Collected by Rider — Out for Delivery',
                'description'     => "Rider {$rider->full_name} verified and collected the package from {$hubLocation} and is now en route to the buyer's address ({$locked->shipment->delivery_address_snapshot['barangay']}, {$locked->shipment->delivery_address_snapshot['municipality']}).",
                'location'        => $locked->shipment->delivery_address_snapshot['municipality'] ?? $hubLocation,
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->shipment->order_id,
                'seller_order_id' => $locked->seller_order_id,
                'from_status'     => $prevOrderStatus,
                'to_status'       => 'out_for_delivery',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Rider {$rider->full_name} verified package collection and started last-mile delivery.",
            ]);
        });

        try {
            $assignment->shipment->sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Your Package is Out for Delivery!',
                message: "Rider {$rider->full_name} is on the way with your order #{$assignment->shipment->sellerOrder->seller_order_number}.",
                type: 'info',
                url: route('user.orders.show', $assignment->seller_order_id),
                orderId: $assignment->shipment->order_id,
                sellerOrderId: $assignment->seller_order_id,
                shipmentId: $assignment->shipment_id
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', 'Step 3 Complete: Package marked as Collected & Out for Delivery!');
    }

    public function completeDelivery(Request $request, DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $shipment = $assignment->shipment;
        $expectedCod = round((float) $shipment->cod_amount, 2);
        $isPrepaidOrder = $expectedCod <= 0.009;

        $validated = $request->validate([
            'proof_photo'          => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'collected_cod_amount' => [$isPrepaidOrder ? 'nullable' : 'required', 'numeric', 'min:0'],
            'recipient_name'       => ['required', 'string', 'min:2', 'max:120'],
            'recipient_relation'   => ['nullable', 'string', 'max:80'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'verification_method'  => ['nullable', 'in:qr,manual'],
        ], [
            'proof_photo.required'          => 'A photo proof of delivery is strictly required before completing delivery.',
            'proof_photo.image'             => 'The delivery proof must be a valid image file.',
            'collected_cod_amount.required' => 'Please enter the exact Cash on Delivery (COD) amount collected from the buyer.',
            'recipient_name.required'       => 'Please enter the name of the recipient who received the parcel.',
        ]);

        $enteredCod = $isPrepaidOrder ? 0.00 : round((float) ($validated['collected_cod_amount'] ?? 0), 2);

        if (! $isPrepaidOrder && abs($enteredCod - $expectedCod) > 0.009) {
            return back()->withErrors([
                'collected_cod_amount' => 'Exact COD amount required: Expected ₱' . number_format($expectedCod, 2) . ', but you entered ₱' . number_format($enteredCod, 2) . '.',
            ])->withInput();
        }

        $photoFile = $request->file('proof_photo');
        $filename = 'pod_' . $shipment->tracking_number . '_' . time() . '.webp';
        $relativePath = 'delivery_proofs/' . $filename;

        $manager = ImageManager::usingDriver(Driver::class);
        $img = $manager->decode($photoFile->getRealPath());
        $img->scaleDown(width: 1200, height: 1200);
        $encoded = $img->encode(new WebpEncoder(quality: 82));

        Storage::disk('public')->put($relativePath, $encoded->toString());

        $deliveredOrder = null;

        DB::transaction(function () use ($assignment, $rider, $validated, $relativePath, $expectedCod, $isPrepaidOrder, &$deliveredOrder) {
            $lockedAssignment = DeliveryAssignment::with([
                'shipment.sellerOrder.order',
                'shipment.sellerOrder.payment',
                'shipment.packages',
            ])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssignment->status !== 'out_for_delivery') {
                throw ValidationException::withMessages([
                    'assignment' => 'Only packages currently Out for Delivery can be marked as Delivered.',
                ]);
            }

            $lockedShipment = $lockedAssignment->shipment;
            $sellerOrder = $lockedShipment->sellerOrder;
            $attemptNo = (int) $lockedShipment->delivery_attempts_count + 1;
            $verMethod = $validated['verification_method'] ?? 'manual';

            foreach ($lockedShipment->packages as $pkg) {
                $pkg->update([
                    'delivery_verified_at'         => $pkg->delivery_verified_at ?? now(),
                    'delivery_verification_method' => $pkg->delivery_verification_method ?? $verMethod,
                ]);

                ShipmentVerification::create([
                    'shipment_id'             => $lockedShipment->id,
                    'shipment_package_id'     => $pkg->id,
                    'seller_order_id'         => $sellerOrder->id,
                    'logistics_profile_id'    => $rider->logistics_profile_id,
                    'logistics_hub_id'        => $pkg->logistics_hub_id,
                    'stage'                   => 'delivery_completion',
                    'verification_method'     => $verMethod,
                    'lookup_token_or_number'  => $pkg->package_reference,
                    'actor_type'              => 'Rider',
                    'actor_id'                => $rider->id,
                    'is_confirmed'            => true,
                    'confirmed_at'            => now(),
                    'remarks'                 => 'Delivery completed with mandatory photo proof.',
                ]);
            }

            $lockedAssignment->update([
                'status'       => 'delivered',
                'completed_at' => now(),
            ]);

            $attempt = DeliveryAttempt::create([
                'shipment_id'             => $lockedShipment->id,
                'delivery_assignment_id'  => $lockedAssignment->id,
                'seller_order_id'         => $sellerOrder->id,
                'rider_id'                => $rider->id,
                'attempt_number'          => $attemptNo,
                'outcome'                 => 'delivered',
                'attempted_at'            => now(),
            ]);

            DeliveryProof::create([
                'shipment_id'            => $lockedShipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $lockedAssignment->id,
                'delivery_attempt_id'    => $attempt->id,
                'rider_id'               => $rider->id,
                'photo_path'             => $relativePath,
                'recipient_name'         => trim($validated['recipient_name']),
                'recipient_relation'     => $validated['recipient_relation'] ?? null,
                'cod_collected_amount'   => $expectedCod,
                'notes'                  => $validated['notes'] ?? null,
                'captured_at'            => now(),
            ]);

            if (! $isPrepaidOrder) {
                CodCollection::updateOrCreate(
                    ['shipment_id' => $lockedShipment->id],
                    [
                        'seller_order_id'      => $sellerOrder->id,
                        'order_id'             => $sellerOrder->order_id,
                        'logistics_profile_id' => $rider->logistics_profile_id,
                        'rider_id'             => $rider->id,
                        'expected_amount'      => $expectedCod,
                        'collected_amount'     => $expectedCod,
                        'status'               => 'collected',
                        'collected_at'         => now(),
                    ]
                );
            }

            $lockedShipment->update([
                'status'                  => 'delivered',
                'delivery_attempts_count' => $attemptNo,
                'delivered_at'            => now(),
            ]);

            $prevOrderStatus = $sellerOrder->status;
            $confirmationDeadline = now()->addHours(SellerOrder::BUYER_CONFIRMATION_HOURS);

            $sellerOrder->update([
                'status'                   => 'delivered',
                'payment_status'           => $isPrepaidOrder ? 'paid' : 'collected',
                'delivered_at'             => now(),
                'confirmation_deadline_at' => $confirmationDeadline,
            ]);

            $sellerOrder->payment?->update([
                'status'               => $isPrepaidOrder ? 'paid' : 'collected',
                'collected_by_rider_id'=> $rider->id,
                'collected_at'         => $sellerOrder->payment->collected_at ?? now(),
            ]);

            $trackingDesc = $isPrepaidOrder
                ? "Delivered to {$validated['recipient_name']} by Rider {$rider->full_name}. Prepaid via GCash (₱0.00 COD collected). Photo proof uploaded."
                : "Delivered to {$validated['recipient_name']} by Rider {$rider->full_name}. Exact COD ₱" . number_format($expectedCod, 2) . " collected and photo proof uploaded.";

            ShipmentTracking::create([
                'shipment_id'     => $lockedShipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'delivered',
                'title'           => 'Package Delivered Successfully',
                'description'     => $trackingDesc,
                'location'        => ($lockedShipment->delivery_address_snapshot['municipality'] ?? '') . ', ' . ($lockedShipment->delivery_address_snapshot['province'] ?? ''),
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
                'remarks'         => $trackingDesc . " 72-hour buyer confirmation window started.",
            ]);

            $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
            $deliveredOrder = $sellerOrder;
        });

        if ($deliveredOrder) {
            $this->roleWalletService->recordPendingEscrowOnDelivery($deliveredOrder);
        }

        try {
            $shipment->sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Order Delivered — Please Confirm Receipt',
                message: "Order #{$shipment->sellerOrder->seller_order_number} has been delivered! Please confirm receipt within 72 hours to unlock product & service ratings.",
                type: 'info',
                url: route('user.orders.show', $shipment->seller_order_id),
                orderId: $shipment->order_id,
                sellerOrderId: $shipment->seller_order_id,
                shipmentId: $shipment->id
            ));

            $shipment->sellerOrder->seller?->notify(new OrderLifecycleNotification(
                title: 'Order Delivered to Buyer (Escrow Credited)',
                message: "Order #{$shipment->sellerOrder->seller_order_number} was delivered by Rider {$rider->full_name}. Your 90% net earning is now in Pending E-Wallet Escrow awaiting Buyer confirmation.",
                type: 'info',
                url: route('seller.orders.show', $shipment->seller_order_id),
                orderId: $shipment->order_id,
                sellerOrderId: $shipment->seller_order_id,
                shipmentId: $shipment->id
            ));
        } catch (\Throwable $e) {
        }

        return back()->with(
            'success',
            $isPrepaidOrder
                ? "Prepaid Order #{$shipment->tracking_number} marked as Delivered with photo proof! Your 10% Rider Shipping Commission has been added to your Pending E-Wallet."
                : "Shipment #{$shipment->tracking_number} marked as Delivered! Please remit ₱" . number_format($expectedCod, 2) . " to your Logistics hub."
        );
    }

    public function reportFailedDelivery(Request $request, DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'min:3', 'max:150'],
            'failure_notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'failure_reason.required' => 'Please select or enter the reason why the delivery attempt failed.',
        ]);

        $autoAbandonmentTriggered = false;
        $targetShipment = null;
        $targetSellerOrder = null;
        $finalAttemptNo = 1;
        $fullReasonText = '';

        DB::transaction(function () use (
            $assignment,
            $rider,
            $validated,
            &$autoAbandonmentTriggered,
            &$targetShipment,
            &$targetSellerOrder,
            &$finalAttemptNo,
            &$fullReasonText
        ) {
            $lockedAssignment = DeliveryAssignment::with(['shipment.sellerOrder.order', 'shipment.sellerOrder.payment'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssignment->status !== 'out_for_delivery') {
                throw ValidationException::withMessages([
                    'assignment' => 'Only packages currently Out for Delivery can be reported as a failed delivery attempt.',
                ]);
            }

            $lockedShipment = $lockedAssignment->shipment;
            $sellerOrder = $lockedShipment->sellerOrder;
            $attemptNo = (int) $lockedShipment->delivery_attempts_count + 1;
            $finalAttemptNo = $attemptNo;

            $isImmediateRefusal = str_contains(strtolower($validated['failure_reason']), 'refused')
                || str_contains(strtolower($validated['failure_reason']), 'abandoned');

            $shouldAutoRts = $isImmediateRefusal || $attemptNo >= 3;
            $autoAbandonmentTriggered = $shouldAutoRts;
            $fullReasonText = $validated['failure_reason'] . ($validated['failure_notes'] ? ' (' . trim($validated['failure_notes']) . ')' : '');

            $lockedAssignment->update([
                'status'       => 'failed',
                'completed_at' => now(),
            ]);

            DeliveryAttempt::create([
                'shipment_id'            => $lockedShipment->id,
                'delivery_assignment_id' => $lockedAssignment->id,
                'seller_order_id'        => $sellerOrder->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => $attemptNo,
                'outcome'                => 'failed',
                'failure_reason'         => $validated['failure_reason'],
                'failure_notes'          => $validated['failure_notes'] ?? null,
                'logistics_resolution'   => $shouldAutoRts ? 'return_to_seller' : 'pending_review',
                'attempted_at'           => now(),
            ]);

            $lockedShipment->update([
                'status'                  => 'delivery_failed',
                'delivery_attempts_count' => $attemptNo,
                'failed_at'               => now(),
            ]);

            $prevOrderStatus = $sellerOrder->status;
            $sellerOrder->update([
                'status' => 'delivery_failed',
            ]);

            $trackingTitle = $shouldAutoRts
                ? "Delivery Attempt #{$attemptNo}/3 Failed — Buyer Abandonment / RTS Flagged"
                : "Delivery Attempt #{$attemptNo}/3 Failed";

            ShipmentTracking::create([
                'shipment_id'     => $lockedShipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'delivery_failed',
                'title'           => $trackingTitle,
                'description'     => "Rider {$rider->full_name} reported unsuccessful delivery (Attempt #{$attemptNo} of 3): {$fullReasonText}.",
                'location'        => ($lockedShipment->delivery_address_snapshot['municipality'] ?? '') . ', ' . ($lockedShipment->delivery_address_snapshot['province'] ?? ''),
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
                'remarks'         => "Delivery attempt #{$attemptNo}/3 failed: {$fullReasonText}",
            ]);

            $targetShipment = $lockedShipment;
            $targetSellerOrder = $sellerOrder;
        });

        if ($autoAbandonmentTriggered && $targetShipment && $targetSellerOrder) {
            $this->buyerAbandonmentService->recordCodParcelAbandonment(
                shipment: $targetShipment,
                sellerOrder: $targetSellerOrder,
                abandonmentReason: $fullReasonText,
                failedAttemptsCount: $finalAttemptNo
            );
        } else {
            try {
                $shipment = $assignment->shipment;
                $shipment->logisticsProfile?->user?->notify(new OrderLifecycleNotification(
                    title: "Failed Delivery Attempt #{$finalAttemptNo}/3 Reported",
                    message: "Rider {$rider->full_name} reported a failed delivery on Tracking #{$shipment->tracking_number} ({$validated['failure_reason']}).",
                    type: 'danger',
                    url: route('logistics.shipments.show', $shipment->id),
                    orderId: $shipment->order_id,
                    sellerOrderId: $shipment->seller_order_id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        }

        return back()->with(
            'success',
            $autoAbandonmentTriggered
                ? "Failed delivery attempt #{$finalAttemptNo}/3 recorded. Buyer COD abandonment strike logged and Seller, Logistics, and Admin have been notified for Return-to-Seller (RTS)."
                : "Failed delivery attempt #{$finalAttemptNo}/3 recorded. Logistics has been notified to schedule redelivery or initiate Return to Seller."
        );
    }

    public function remitCod(Request $request, CodCollection $codCollection)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();
        if ((int) $codCollection->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'remittance_reference' => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($codCollection, $rider, $validated) {
            $locked = CodCollection::with('logisticsProfile.user')->where('id', $codCollection->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'collected') {
                throw ValidationException::withMessages([
                    'cod' => 'This COD collection has already been remitted.',
                ]);
            }

            $locked->update([
                'remitted_amount'      => $locked->collected_amount,
                'status'               => 'remitted',
                'remittance_reference' => $validated['remittance_reference'] ?: ('RMT-' . now()->format('YmdHis') . '-' . $locked->id),
                'notes'                => $validated['notes'] ?? null,
                'remitted_at'          => now(),
            ]);

            try {
                $locked->logisticsProfile?->user?->notify(new OrderLifecycleNotification(
                    title: 'Rider Remitted COD Cash',
                    message: "Rider {$rider->full_name} remitted ₱" . number_format((float) $locked->collected_amount, 2) . " for Order #{$locked->sellerOrder?->seller_order_number}. Please verify receipt.",
                    type: 'info',
                    url: route('logistics.finance.index'),
                    orderId: $locked->order_id,
                    sellerOrderId: $locked->seller_order_id,
                    shipmentId: $locked->shipment_id
                ));
            } catch (\Throwable $e) {
            }
        });

        return redirect()->route('rider.dashboard', ['tab' => 'remittance'])
            ->with('success', 'COD remittance submitted! Your Logistics hub will verify the cash handover.');
    }
}