<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CodRemittanceItem;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\OrderServiceRating;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Models\WalletTransaction;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\BuyerAbandonmentService;
use App\Services\OperationalSlaGuardService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class DeliveryController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected BuyerAbandonmentService $buyerAbandonmentService,
        protected RoleWalletService $roleWalletService,
        protected OperationalSlaGuardService $slaGuardService
    ) {}

    public static function calculateDistanceScaledRiderPay(Shipment $shipment): array
    {
        $pickup = $shipment->pickup_address_snapshot ?? [];
        $deliv = $shipment->delivery_address_snapshot ?? [];

        $originProv = strtolower(trim((string) ($pickup['province'] ?? '')));
        $originMuni = strtolower(trim((string) ($pickup['municipality'] ?? '')));
        $originBrgy = strtolower(trim((string) ($pickup['barangay'] ?? '')));

        $destProv = strtolower(trim((string) ($deliv['province'] ?? '')));
        $destMuni = strtolower(trim((string) ($deliv['municipality'] ?? '')));
        $destBrgy = strtolower(trim((string) ($deliv['barangay'] ?? '')));

        if ($originProv !== '' && $originProv === $destProv && $originMuni !== '' && $originMuni === $destMuni) {
            if ($originBrgy !== '' && $originBrgy === $destBrgy) {
                $distanceTier = 'Same Barangay';
                $distanceBonus = 0.00;
            } else {
                $distanceTier = 'Same Municipality';
                $distanceBonus = 15.00;
            }
        } elseif ($originProv !== '' && $originProv === $destProv) {
            $distanceTier = 'Same Province (Cross-Municipality)';
            $distanceBonus = 35.00;
        } else {
            $distanceTier = 'Inter-Provincial / Regional';
            $distanceBonus = 60.00;
        }

        $basePay = max(50.00, round(((float) $shipment->shipping_fee) * 0.10, 2));
        $weightBonus = max(0, ceil((float) $shipment->total_weight - 1)) * 5.00;
        $totalPay = round($basePay + $distanceBonus + $weightBonus, 2);

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

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ($rider->logistics_profile_id) {
            $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $rider->logistics_profile_id);
        }

        $tab = $request->query('tab', $request->query('status', 'deliveries'));
        if (! in_array($tab, ['deliveries', 'remittance', 'history'], true)) {
            $tab = 'deliveries';
        }

        $activeAssignments = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
        ])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $historyAssignments = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'sellerOrder.buyer',
        ])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['delivered', 'failed', 'reassigned'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $unremittedCollections = CodCollection::with(['shipment', 'sellerOrder'])
            ->where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->orderBy('collected_at', 'asc')
            ->get();

        $remittances = Schema::hasTable('cod_remittances')
            ? CodRemittance::where('rider_id', $rider->id)
                ->orderByDesc('submitted_at')
                ->limit(30)
                ->get()
            : collect();

        $riderWallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        $completedDeliveriesCount = DeliveryAssignment::where('rider_id', $rider->id)
            ->where('status', 'delivered')
            ->count();

        $unremittedCodTotal = round((float) $unremittedCollections->sum('collected_amount'), 2);
        $pendingRiderComm = round((float) $unremittedCollections->sum(fn ($c) => round((float) $c->shipping_fee * 0.10, 2)), 2);

        $verifiedRiderEarnings = max(
            round((float) $riderWallet->total_earned, 2),
            Schema::hasTable('cod_remittances')
                ? round((float) CodRemittance::where('rider_id', $rider->id)->where('status', 'verified')->sum('total_rider_commission'), 2)
                : 0.00
        );

        $metrics = [
            'active_deliveries'       => $activeAssignments->count(),
            'completed_deliveries'    => $completedDeliveriesCount,
            'cod_on_hand'             => $unremittedCodTotal,
            'pending_rider_comm'      => max($pendingRiderComm, round((float) $riderWallet->pending_balance, 2)),
            'verified_rider_earnings' => $verifiedRiderEarnings,
            'wallet_available'        => round((float) $riderWallet->available_balance, 2),
            'wallet_pending'          => round((float) $riderWallet->pending_balance, 2),
        ];

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? collect(
                DB::table('withdrawal_requests')
                    ->where('requester_type', 'Rider')
                    ->where('requester_id', (int) $rider->id)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get()
            )
            : collect();

        return view('rider.dashboard', compact(
            'rider',
            'tab',
            'activeAssignments',
            'historyAssignments',
            'unremittedCollections',
            'remittances',
            'metrics',
            'riderWallet',
            'withdrawalRequests'
        ));
    }

    public function acceptAssignment(DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        if ($assignment->status !== 'assigned') {
            return back()->withErrors(['assignment' => 'This delivery assignment has already been accepted or processed.']);
        }

        DB::transaction(function () use ($assignment, $rider) {
            $locked = DeliveryAssignment::with('shipment.packages')
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            if ($locked->shipment) {
                $locked->shipment->packages()->update([
                    'rider_accepted_at' => now(),
                ]);

                ShipmentTracking::create([
                    'shipment_id'     => $locked->shipment_id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => $locked->shipment->status,
                    'title'           => 'Rider Accepted Delivery Dispatch',
                    'description'     => "Rider {$rider->full_name} accepted the last-mile delivery assignment and is proceeding to verify packages at the hub.",
                    'location'        => $rider->logisticsProfile->business_name ?? 'Sorting Hub',
                    'actor_type'      => 'Rider',
                    'actor_id'        => $rider->id,
                    'occurred_at'     => now(),
                ]);
            }
        });

        return back()->with('success', 'Delivery assignment accepted! Please scan the package QR code or enter the Package Reference at the hub.');
    }

    public function verifyPackageLookup(Request $request, DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:120'],
            'verification_method' => ['nullable', 'in:qr,manual'],
            'stage'               => ['nullable', 'in:rider_collection,delivery_confirmation'],
        ]);

        $rawInput = trim($validated['lookup_input']);
        if (str_starts_with($rawInput, 'STORK-QR|')) {
            $parts = explode('|', $rawInput);
            $rawInput = end($parts) ?: $rawInput;
        }

        $method = $validated['verification_method'] ?? 'manual';
        $stage = $validated['stage'] ?? ($assignment->status === 'out_for_delivery' ? 'delivery_confirmation' : 'rider_collection');

        $shipment = $assignment->shipment()->with('packages')->firstOrFail();

        $matchedPkg = $shipment->packages->first(function ($pkg) use ($rawInput, $shipment) {
            return strcasecmp($pkg->qr_token, $rawInput) === 0
                || strcasecmp($pkg->package_reference, $rawInput) === 0
                || strcasecmp($shipment->tracking_number, $rawInput) === 0;
        });

        if (! $matchedPkg) {
            throw ValidationException::withMessages([
                'lookup_input' => "No package in Shipment #{$shipment->tracking_number} matched \"{$rawInput}\".",
            ]);
        }

        DB::transaction(function () use ($matchedPkg, $shipment, $rider, $method, $stage, $rawInput) {
            if ($stage === 'delivery_confirmation') {
                $matchedPkg->update([
                    'delivery_verified_at'         => now(),
                    'delivery_verification_method' => $method,
                ]);
            } else {
                $matchedPkg->update([
                    'rider_verified_at'         => now(),
                    'rider_verification_method' => $method,
                ]);
            }

            ShipmentVerification::create([
                'shipment_id'            => $shipment->id,
                'shipment_package_id'    => $matchedPkg->id,
                'seller_order_id'        => $shipment->seller_order_id,
                'logistics_profile_id'   => $shipment->logistics_profile_id,
                'logistics_hub_id'       => $matchedPkg->logistics_hub_id,
                'stage'                  => $stage,
                'verification_method'    => $method,
                'lookup_token_or_number' => $rawInput,
                'actor_type'             => 'Rider',
                'actor_id'               => $rider->id,
                'is_confirmed'           => true,
                'confirmed_at'           => now(),
                'remarks'                => "Verified by Rider {$rider->full_name} ({$stage})",
            ]);
        });

        return back()->with('success', "Package {$matchedPkg->package_reference} verified via " . strtoupper($method) . '!');
    }

    public function startDelivery(DeliveryAssignment $assignment)
    {
        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $shipment = $assignment->shipment()->with(['packages.verifications', 'sellerOrder.buyer', 'sellerOrder.order'])->firstOrFail();

        $allVerified = $shipment->packages->isEmpty() || $shipment->packages->every(function ($p) use ($rider) {
            if ($p->rider_verified_at !== null || $p->rider_collected_at !== null) {
                return true;
            }
            return $p->verifications
                ->where('stage', 'rider_collection')
                ->where('actor_type', 'Rider')
                ->where('actor_id', $rider->id)
                ->where('is_confirmed', true)
                ->isNotEmpty();
        });

        if (! $allVerified) {
            throw ValidationException::withMessages([
                'verification' => 'Please scan or verify all package references at the sorting hub before starting Out for Delivery.',
            ]);
        }

        DB::transaction(function () use ($assignment, $shipment, $rider) {
            $UpdatePayload = [
                'status'              => 'out_for_delivery',
                'collected_at'        => $assignment->collected_at ?? now(),
                'out_for_delivery_at' => now(),
            ];
            if (Schema::hasColumn('delivery_assignments', 'accepted_at')) {
                $UpdatePayload['accepted_at'] = $assignment->accepted_at ?? now();
            }
            if (Schema::hasColumn('delivery_assignments', 'picked_up_at')) {
                $UpdatePayload['picked_up_at'] = now();
            }
            $assignment->update($UpdatePayload);

            $shipment->packages()->update([
                'rider_collected_at' => now(),
            ]);

            $shipment->update([
                'status'              => 'out_for_delivery',
                'out_for_delivery_at' => now(),
            ]);

            $sellerOrder = $shipment->sellerOrder;
            $oldStatus = $sellerOrder->status;
            $sellerOrder->update([
                'status' => 'out_for_delivery',
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $oldStatus,
                'to_status'       => 'out_for_delivery',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Rider {$rider->full_name} collected the package from the hub and is Out for Delivery.",
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'out_for_delivery',
                'title'           => 'Out for Delivery',
                'description'     => "Rider {$rider->full_name} ({$rider->contact_no}) is on the way to the buyer's address.",
                'location'        => $shipment->delivery_address_snapshot['municipality'] ?? 'Local Area',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            if ($sellerOrder->order) {
                $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
            }

            try {
                $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Your Parcel is Out for Delivery!',
                    message: "Order #{$sellerOrder->seller_order_number} (Tracking #{$shipment->tracking_number}) is out for delivery with Rider {$rider->full_name}.",
                    type: 'info',
                    url: route('user.orders.show', $sellerOrder->id),
                    orderId: $sellerOrder->order_id,
                    sellerOrderId: $sellerOrder->id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        });

        return back()->with('success', "Shipment #{$shipment->tracking_number} is now Out for Delivery!");
    }

    public function completeDelivery(Request $request, DeliveryAssignment $assignment)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $assignment->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'proof_photo'        => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'recipient_name'     => ['nullable', 'string', 'max:120'],
            'recipient_relation' => ['nullable', 'string', 'max:80'],
            'collected_amount'   => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ], [
            'proof_photo.required'  => 'A clear Proof of Delivery photo (JPG, PNG, or WEBP, max 5 MB) is strictly required.',
            'proof_photo.mimetypes' => 'Proof of Delivery must be a valid JPG, PNG, or WEBP image.',
            'proof_photo.max'       => 'Proof of Delivery image must not exceed 5 MB.',
        ]);

        $shipment = $assignment->shipment()->with(['sellerOrder.order', 'sellerOrder.buyer', 'sellerOrder.seller', 'sellerOrder.payment', 'packages'])->firstOrFail();
        $sellerOrder = $shipment->sellerOrder;
        $expectedCod = (float) $shipment->cod_amount;
        $collectedCod = isset($validated['collected_amount']) ? round((float) $validated['collected_amount'], 2) : $expectedCod;

        if ($expectedCod > 0 && abs($collectedCod - $expectedCod) > 0.01) {
            throw ValidationException::withMessages([
                'collected_amount' => 'Collected COD amount must match the exact payable COD amount of ₱' . number_format($expectedCod, 2) . '.',
            ]);
        }

        $delivSnapshot = $shipment->delivery_address_snapshot ?? [];
        $recipientName = trim((string) ($validated['recipient_name'] ?? '')) ?: ($delivSnapshot['recipient_name'] ?? ($sellerOrder->buyer?->full_name ?? 'Recipient'));
        $recipientRelation = trim((string) ($validated['recipient_relation'] ?? '')) ?: 'Recipient';

        $file = $request->file('proof_photo');
        $filename = 'delivery_proofs/pod_' . $shipment->id . '_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1600, 1600)->toWebp(82);
            Storage::disk('public')->put($filename, (string) $encoded);
        } catch (\Throwable $e) {
            $filename = $file->store('delivery_proofs', 'public');
        }

        $payInfo = self::calculateDistanceScaledRiderPay($shipment);

        DB::transaction(function () use (
            $assignment,
            $shipment,
            $sellerOrder,
            $rider,
            $filename,
            $recipientName,
            $recipientRelation,
            $expectedCod,
            $collectedCod,
            $validated
        ) {
            $assignment->update([
                'status'       => 'delivered',
                'completed_at' => now(),
            ]);

            $shipment->packages()->update([
                'delivery_verified_at'         => now(),
                'delivery_verification_method' => 'qr',
            ]);

            $shipment->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            $attemptPayload = [
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $assignment->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => max(1, (int) $assignment->attempt_number),
                'outcome'                => 'delivered',
                'attempted_at'           => now(),
            ];
            if (Schema::hasColumn('delivery_attempts', 'notes')) {
                $attemptPayload['notes'] = $validated['notes'] ?? "Delivered to {$recipientName} ({$recipientRelation})";
            }
            $attempt = DeliveryAttempt::create($attemptPayload);

            $proofPayload = [
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $assignment->id,
                'delivery_attempt_id'    => $attempt->id,
                'rider_id'               => $rider->id,
                'photo_path'             => $filename,
                'recipient_name'         => $recipientName,
                'notes'                  => $validated['notes'] ?? null,
                'captured_at'            => now(),
            ];
            if (Schema::hasColumn('delivery_proofs', 'recipient_relation')) {
                $proofPayload['recipient_relation'] = $recipientRelation;
                $proofPayload['cod_collected_amount'] = $collectedCod;
            }
            DeliveryProof::create($proofPayload);

            if ($expectedCod > 0) {
                CodCollection::updateOrCreate(
                    [
                        'shipment_id'     => $shipment->id,
                        'seller_order_id' => $sellerOrder->id,
                    ],
                    [
                        'collection_reference'   => 'COD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                        'delivery_assignment_id' => $assignment->id,
                        'logistics_profile_id'   => $shipment->logistics_profile_id,
                        'rider_id'               => $rider->id,
                        'expected_amount'        => $expectedCod,
                        'collected_amount'       => $collectedCod,
                        'remitted_amount'        => null,
                        'product_subtotal'       => (float) $sellerOrder->subtotal_amount,
                        'shipping_fee'           => (float) $shipment->shipping_fee,
                        'status'                 => 'collected',
                        'collected_at'           => now(),
                    ]
                );
            }

            $oldOrderStatus = $sellerOrder->status;
            $sellerOrder->update([
                'status'                   => 'delivered',
                'payment_status'           => 'paid',
                'delivered_at'             => now(),
                'confirmation_deadline_at' => now()->addHours(72),
            ]);

            if ($sellerOrder->payment) {
                $sellerOrder->payment->update([
                    'status'       => 'paid',
                    'collected_at' => $sellerOrder->payment->collected_at ?? now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $oldOrderStatus,
                'to_status'       => 'delivered',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Delivered to {$recipientName} ({$recipientRelation}). Proof of Delivery uploaded.",
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'delivered',
                'title'           => 'Parcel Delivered — Proof of Delivery Uploaded',
                'description'     => "Received by {$recipientName} ({$recipientRelation}). Rider: {$rider->full_name}.",
                'location'        => $shipment->delivery_address_snapshot['municipality'] ?? 'Buyer Address',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            $this->orderCompletionService->recordDeliveredPendingWalletCredits($sellerOrder->fresh(['extra', 'payment', 'shipment', 'sellerProfile', 'logisticsProfile']));

            if ($sellerOrder->order) {
                $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
            }

            try {
                $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Parcel Delivered! Please Confirm Receipt Within 72 Hours',
                    message: "Order #{$sellerOrder->seller_order_number} was delivered to {$recipientName}. Please inspect your items and click 'Confirm Order Received' or request a Return/Refund within 3 days.",
                    type: 'success',
                    url: route('user.orders.show', $sellerOrder->id),
                    orderId: $sellerOrder->order_id,
                    sellerOrderId: $sellerOrder->id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        });

        return back()->with(
            'success',
            "Delivery completed! Your distance-scaled Rider Pay of ₱" . number_format($payInfo['total_pay'], 2) . " ({$payInfo['distance_tier']}) has been credited to your Pending Escrow Wallet."
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
            'failure_reason' => ['required', 'string', 'min:5', 'max:255'],
            'failure_notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $fullReason = trim($validated['failure_reason'] . (! empty($validated['failure_notes']) ? ' — ' . $validated['failure_notes'] : ''));

        $shipment = $assignment->shipment()->with(['sellerOrder.order', 'sellerOrder.buyer', 'sellerOrder.seller', 'sellerOrder.payment'])->firstOrFail();
        $sellerOrder = $shipment->sellerOrder;

        DB::transaction(function () use ($assignment, $shipment, $sellerOrder, $rider, $fullReason) {
            $currentAttempts = (int) ($shipment->failed_attempts_count ?? $shipment->delivery_attempts_count ?? 0);
            $attemptNo = max(1, $currentAttempts + 1);

            $assignmentUpdates = [
                'status'       => 'failed',
                'failed_at'    => now(),
                'completed_at' => now(),
            ];
            if (Schema::hasColumn('delivery_assignments', 'failure_reason')) {
                $assignmentUpdates['failure_reason'] = $fullReason;
            }
            $assignment->update($assignmentUpdates);

            $attemptPayload = [
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $assignment->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => $attemptNo,
                'outcome'                => 'failed',
                'failure_reason'         => substr($fullReason, 0, 120),
                'failure_notes'          => "Failed Attempt #{$attemptNo} (Rider Pay: ₱0.00)",
                'attempted_at'           => now(),
            ];
            if (Schema::hasColumn('delivery_attempts', 'notes')) {
                $attemptPayload['notes'] = "Failed Attempt #{$attemptNo} (Rider Pay: ₱0.00)";
            }
            DeliveryAttempt::create($attemptPayload);

            $isBuyerRefusal = stripos($fullReason, 'refused') !== false || stripos($fullReason, 'reject') !== false;
            $autoRtsTriggered = $attemptNo >= 2 || $isBuyerRefusal;

            $shipmentUpdates = [
                'status'                  => $autoRtsTriggered ? 'returned_to_seller' : 'delivery_failed',
                'delivery_attempts_count' => $attemptNo,
                'failed_at'               => now(),
            ];
            if (Schema::hasColumn('shipments', 'failed_attempts_count')) {
                $shipmentUpdates['failed_attempts_count'] = $attemptNo;
                $shipmentUpdates['last_failure_reason'] = $fullReason;
            }
            $shipment->update($shipmentUpdates);

            $oldOrderStatus = $sellerOrder->status;
            $sellerOrder->update([
                'status'         => $autoRtsTriggered ? 'returned_to_seller' : 'delivery_failed',
                'payment_status' => $autoRtsTriggered ? 'cancelled' : $sellerOrder->payment_status,
                'cancelled_at'   => $autoRtsTriggered ? now() : $sellerOrder->cancelled_at,
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $oldOrderStatus,
                'to_status'       => $sellerOrder->status,
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'remarks'         => "Failed Delivery Attempt #{$attemptNo}: {$fullReason}" . ($autoRtsTriggered ? ' (Auto-RTS triggered).' : ''),
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => $shipment->status,
                'title'           => $autoRtsTriggered
                    ? "Delivery Failed (#{$attemptNo}) — Returning to Seller (RTS)"
                    : "Delivery Attempt #{$attemptNo} Unsuccessful",
                'description'     => "Reason: {$fullReason}. Rider Pay for failed attempt: ₱0.00.",
                'location'        => $shipment->delivery_address_snapshot['municipality'] ?? 'Buyer Area',
                'actor_type'      => 'Rider',
                'actor_id'        => $rider->id,
                'occurred_at'     => now(),
            ]);

            if ($autoRtsTriggered) {
                $this->buyerAbandonmentService->recordCodParcelAbandonment(
                    shipment: $shipment,
                    sellerOrder: $sellerOrder,
                    abandonmentReason: $fullReason,
                    failedAttemptsCount: $attemptNo
                );
            }

            if ($sellerOrder->order) {
                $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
            }
        });

        return back()->with('success', 'Failed delivery attempt recorded (Failed attempt Rider Pay: ₱0.00).');
    }

    public function remitAllCod(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        $collections = CodCollection::where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->orderBy('collected_at', 'asc')
            ->get();

        if ($collections->isEmpty()) {
            return back()->withErrors(['remittance' => 'You have no unremitted COD collections to remit.']);
        }

        $remittanceRef = 'REM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

        DB::transaction(function () use ($rider, $collections, $remittanceRef) {
            $totalCollected = round((float) $collections->sum('collected_amount'), 2);
            $totalRiderComm = round((float) $collections->sum(fn ($c) => round((float) $c->shipping_fee * 0.10, 2)), 2);
            $netRemitted = round(max(0, $totalCollected - $totalRiderComm), 2);

            $remittance = CodRemittance::create([
                'remittance_reference'   => $remittanceRef,
                'logistics_profile_id'   => $rider->logistics_profile_id,
                'rider_id'               => $rider->id,
                'total_collected_amount' => $totalCollected,
                'total_rider_commission' => $totalRiderComm,
                'net_remitted_amount'    => $netRemitted,
                'status'                 => 'submitted',
                'submitted_at'           => now(),
                'notes'                  => "Submitted by Rider {$rider->full_name} ({$collections->count()} COD collection(s)).",
            ]);

            foreach ($collections as $col) {
                $col->update([
                    'remitted_amount'      => $col->collected_amount,
                    'status'               => 'remitted',
                    'remittance_reference' => $remittanceRef,
                    'remitted_at'          => now(),
                ]);

                $itemPayload = [
                    'cod_remittance_id'       => $remittance->id,
                    'seller_order_id'         => $col->seller_order_id,
                    'collected_amount'        => $col->collected_amount,
                    'rider_commission_amount' => round((float) $col->shipping_fee * 0.10, 2),
                ];
                if (Schema::hasColumn('cod_remittance_items', 'amount')) {
                    $itemPayload['amount'] = $col->collected_amount;
                }

                CodRemittanceItem::updateOrCreate(
                    ['cod_collection_id' => $col->id],
                    $itemPayload
                );
            }
        });

        return back()->with('success', "COD Remittance Batch #{$remittanceRef} submitted to your Logistics Hub for verification!");
    }

    public function remitCod(Request $request, CodCollection $codCollection)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        if ((int) $codCollection->rider_id !== (int) $rider->id) {
            abort(403);
        }

        $validated = $request->validate([
            'remitted_amount'      => ['nullable', 'numeric', 'min:0.01'],
            'remittance_reference' => ['nullable', 'string', 'max:80'],
            'shortfall_reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $expected = (float) $codCollection->expected_amount;
        $remitted = isset($validated['remitted_amount'])
            ? round((float) $validated['remitted_amount'], 2)
            : round((float) $codCollection->collected_amount, 2);

        $isShort = $remitted < $expected;
        if ($isShort && empty(trim((string) ($validated['shortfall_reason'] ?? '')))) {
            throw ValidationException::withMessages([
                'shortfall_reason' => 'Remitted amount (₱' . number_format($remitted, 2) . ') is less than expected COD (₱' . number_format($expected, 2) . '). Please provide an explanation for the shortfall.',
            ]);
        }

        $remittanceRef = ! empty($validated['remittance_reference'])
            ? strtoupper(trim($validated['remittance_reference']))
            : ('REM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)));

        DB::transaction(function () use ($codCollection, $rider, $remitted, $expected, $isShort, $validated, $remittanceRef) {
            $codCollection->update([
                'remitted_amount'      => $remitted,
                'status'               => $isShort ? 'short_remitted' : 'remitted',
                'remittance_reference' => $remittanceRef,
                'notes'                => $validated['shortfall_reason'] ?? null,
                'remitted_at'          => now(),
            ]);

            $riderComm = round((float) $codCollection->shipping_fee * 0.10, 2);
            $netRemitted = round(max(0, $remitted - $riderComm), 2);

            $remittance = CodRemittance::create([
                'remittance_reference'   => $remittanceRef,
                'logistics_profile_id'   => $codCollection->logistics_profile_id,
                'rider_id'               => $rider->id,
                'total_collected_amount' => $remitted,
                'total_rider_commission' => $riderComm,
                'net_remitted_amount'    => $netRemitted,
                'status'                 => 'submitted',
                'submitted_at'           => now(),
                'notes'                  => $validated['shortfall_reason'] ?? "Single COD collection remittance ({$codCollection->collection_reference}).",
            ]);

            $itemPayload = [
                'cod_remittance_id'       => $remittance->id,
                'seller_order_id'         => $codCollection->seller_order_id,
                'collected_amount'        => $remitted,
                'rider_commission_amount' => $riderComm,
            ];
            if (Schema::hasColumn('cod_remittance_items', 'amount')) {
                $itemPayload['amount'] = $remitted;
            }

            CodRemittanceItem::updateOrCreate(
                ['cod_collection_id' => $codCollection->id],
                $itemPayload
            );

            if ($isShort) {
                AuditLoggerService::log(
                    action: 'short_cod_remittance_flagged',
                    targetType: 'CodRemittance',
                    targetId: (int) $codCollection->id,
                    targetReference: 'COD-' . $codCollection->seller_order_id,
                    oldValues: ['expected_amount' => $expected],
                    newValues: ['remitted_amount' => $remitted, 'shortfall' => round($expected - $remitted, 2)],
                    reason: $validated['shortfall_reason'],
                    actorType: 'Rider',
                    actorId: (int) $rider->id,
                    actorName: $rider->full_name
                );
            }
        });

        return back()->with(
            'success',
            $isShort
                ? 'Short COD remittance recorded and flagged for Logistics & Admin reconciliation.'
                : 'Full COD remittance submitted to Logistics Hub for verification.'
        );
    }

    public function requestWithdrawal(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:100', 'max:500000'],
            'payout_method'    => ['nullable', 'in:GCASH,BANK'],
            'account_name'     => ['required', 'string', 'min:2', 'max:120'],
            'account_number'   => ['required', 'string', 'min:8', 'max:50'],
            'idempotency_key'  => ['nullable', 'string', 'max:64'],
        ]);

        $withdrawal = $this->roleWalletService->submitWithdrawalRequest(
            ownerType: 'Rider',
            ownerId: (int) $rider->id,
            ownerName: $rider->full_name,
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

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        $ratings = Schema::hasTable('order_service_ratings')
            ? OrderServiceRating::with(['buyer', 'sellerOrder', 'sellerProfile', 'logisticsProfile'])
                ->where('rider_id', $rider->id)
                ->orderByDesc('created_at')
                ->paginate(20)
            : collect();

        $avgRating = Schema::hasTable('order_service_ratings')
            ? round((float) OrderServiceRating::where('rider_id', $rider->id)->avg('rider_delivery_rating'), 2)
            : 0.00;

        $totalRatings = Schema::hasTable('order_service_ratings')
            ? OrderServiceRating::where('rider_id', $rider->id)->count()
            : 0;

        return view('rider.ratings', compact('rider', 'ratings', 'avgRating', 'totalRatings'));
    }

    public function report(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var \App\Models\Rider $rider */
        $rider = Auth::guard('rider')->user();

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        $riderWallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        $walletTransactions = WalletTransaction::with(['sellerOrder', 'shipment'])
            ->where('owner_type', 'Rider')
            ->where('owner_id', $rider->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $attempts = DeliveryAttempt::with(['shipment', 'sellerOrder'])
            ->where('rider_id', $rider->id)
            ->whereBetween('attempted_at', [$from, $to])
            ->orderBy('attempted_at', 'asc')
            ->get();

        $collections = CodCollection::with(['shipment', 'sellerOrder'])
            ->where('rider_id', $rider->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $assignmentsInPeriod = DeliveryAssignment::where('rider_id', $rider->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $deliveredCount = $attempts->where('outcome', 'delivered')->count();
        $failedCount = $attempts->where('outcome', 'failed')->count();
        $totalAssigned = max($assignmentsInPeriod->count(), $deliveredCount + $failedCount);

        $periodRealizedIncome = round((float) $walletTransactions
            ->where('entry_type', 'commission_released')
            ->sum('amount'), 2);

        $periodCodCollected = round((float) $collections->sum('collected_amount'), 2);

        $summary = [
            'total_assigned'         => $totalAssigned,
            'delivered_count'        => $deliveredCount,
            'failed_count'           => $failedCount,
            'period_realized_income' => $periodRealizedIncome,
            'period_cod_collected'   => $periodCodCollected,
            'cod_collected'          => $periodCodCollected,
            'cod_remitted'           => round((float) $collections->sum(fn ($c) => (float) ($c->remitted_amount ?? 0)), 2),
            'cod_shortfall'          => round((float) $collections->sum(fn ($c) => max(0, (float) $c->expected_amount - (float) ($c->remitted_amount ?? $c->collected_amount))), 2),
            'wallet_available'       => (float) $riderWallet->available_balance,
            'wallet_pending'         => (float) $riderWallet->pending_balance,
            'total_earned'           => (float) $riderWallet->total_earned,
        ];

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? collect(
                DB::table('withdrawal_requests')
                    ->where('requester_type', 'Rider')
                    ->where('requester_id', (int) $rider->id)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get()
            )
            : collect();

        return view('rider.report', compact(
            'rider',
            'fromDate',
            'toDate',
            'riderWallet',
            'walletTransactions',
            'attempts',
            'collections',
            'summary',
            'withdrawalRequests'
        ));
    }
}