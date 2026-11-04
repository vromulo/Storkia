<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\DeliveryController as WebRiderDeliveryController;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerAbandonmentService;
use App\Services\BuyerOrderNotificationService;
use App\Services\OperationalSlaGuardService;
use App\Services\OrderCompletionService;
use App\Services\RiderMobileSchemaService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class RiderDeliveryApiController extends Controller
{
    public const MAX_CONCURRENT_ACTIVE_JOBS = 3;
    public const MAX_DELIVERY_ATTEMPTS = 3;

    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected BuyerAbandonmentService $buyerAbandonmentService,
        protected BuyerOrderNotificationService $buyerOrderNotificationService,
        protected RoleWalletService $roleWalletService,
        protected OperationalSlaGuardService $slaGuardService,
        protected AuthApiController $authApiController
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        if ($rider->logistics_profile_id) {
            $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $rider->logistics_profile_id);
        }

        if ($rider->is_online && $rider->last_seen_at && $rider->last_seen_at->lt(now()->subMinutes(5))) {
            $rider->update(['is_online' => false]);
        }

        $rider->update(['last_seen_at' => now()]);
        $rider->loadMissing(['logisticsProfile', 'currentHubAssignment.hub', 'barangayCoverages']);

        $activeAssignments = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.order.userAddress',
        ])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $unremittedCollections = CodCollection::where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->get();

        $riderWallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        $completedDeliveriesCount = DeliveryAssignment::where('rider_id', $rider->id)
            ->where('status', 'delivered')
            ->count();

        $failedDeliveriesCount = DeliveryAssignment::where('rider_id', $rider->id)
            ->where('status', 'failed')
            ->count();

        $unremittedCodTotal = round((float) $unremittedCollections->sum('collected_amount'), 2);
        $pendingRiderComm = round((float) $unremittedCollections->sum(fn ($c) => round((float) $c->shipping_fee * 0.10, 2)), 2);

        $verifiedRiderEarnings = max(
            round((float) $riderWallet->total_earned, 2),
            Schema::hasTable('cod_remittances')
                ? round((float) CodRemittance::where('rider_id', $rider->id)->where('status', 'verified')->sum('total_rider_commission'), 2)
                : 0.00
        );

        $badges = $this->buildPendingActionBadges($rider, $activeAssignments, $unremittedCollections->count());

        return response()->json([
            'status' => 'success',
            'data'   => [
                'rider'              => $this->authApiController->formatRiderProfile($rider),
                'metrics'            => [
                    'active_deliveries'       => $activeAssignments->count(),
                    'completed_deliveries'    => $completedDeliveriesCount,
                    'failed_deliveries'       => $failedDeliveriesCount,
                    'cod_on_hand'             => $unremittedCodTotal,
                    'unremitted_cod_count'    => $unremittedCollections->count(),
                    'pending_rider_comm'      => max($pendingRiderComm, round((float) $riderWallet->pending_balance, 2)),
                    'verified_rider_earnings' => $verifiedRiderEarnings,
                    'wallet_available'        => round((float) $riderWallet->available_balance, 2),
                    'wallet_pending'          => round((float) $riderWallet->pending_balance, 2),
                    'wallet_total_earned'     => round((float) $riderWallet->total_earned, 2),
                    'wallet_total_withdrawn'  => round((float) $riderWallet->total_withdrawn, 2),
                ],
                'badges'             => $badges,
                'active_assignments' => $activeAssignments->map(fn (DeliveryAssignment $a) => $this->formatDeliveryAssignment($a, $rider, false))->values()->all(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        if ($rider->logistics_profile_id) {
            $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $rider->logistics_profile_id);
        }

        $filter = strtolower(trim((string) $request->query('filter', 'active')));
        $search = trim((string) $request->query('search', ''));

        $query = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.latestDeliveryProof',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.order.userAddress',
        ])->where('rider_id', $rider->id);

        if ($filter === 'assigned') {
            $query->where('status', 'assigned');
        } elseif ($filter === 'to_collect' || $filter === 'accepted') {
            $query->whereIn('status', ['accepted', 'collected']);
        } elseif ($filter === 'out_for_delivery') {
            $query->where('status', 'out_for_delivery');
        } elseif ($filter === 'history') {
            $query->whereIn('status', ['delivered', 'failed', 'reassigned']);
            if ($request->filled('from_date')) {
                $query->where('updated_at', '>=', Carbon::parse($request->query('from_date'))->startOfDay());
            }
            if ($request->filled('to_date')) {
                $query->where('updated_at', '<=', Carbon::parse($request->query('to_date'))->endOfDay());
            }
        } else {
            $query->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('shipment', function ($sq) use ($search) {
                    $sq->where('tracking_number', 'LIKE', "%{$search}%")
                       ->orWhere('delivery_address_snapshot', 'LIKE', "%{$search}%");
                })->orWhereHas('sellerOrder', function ($oq) use ($search) {
                    $oq->where('seller_order_number', 'LIKE', "%{$search}%");
                });
            });
        }

        if ($filter === 'history') {
            $assignments = $query->orderByDesc('updated_at')->orderByDesc('id')->limit(100)->get();
        } else {
            $assignments = $query->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();
        }

        $activeAll = DeliveryAssignment::with('shipment.packages.verifications')
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->get();

        $unremittedCount = CodCollection::where('rider_id', $rider->id)->where('status', 'collected')->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'filter'      => $filter,
                'badges'      => $this->buildPendingActionBadges($rider, $activeAll, $unremittedCount),
                'assignments' => $assignments->map(fn (DeliveryAssignment $a) => $this->formatDeliveryAssignment($a, $rider, false))->values()->all(),
            ],
        ]);
    }

    public function show(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.verifications',
            'shipment.packages.logisticsHub',
            'shipment.trackingLogs',
            'shipment.latestDeliveryProof',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.order.userAddress',
            'attempts',
            'proof',
        ])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You do not have permission to view or access this delivery assignment.',
            ], 403);
        }

        if ($assignment->shipment && $assignment->shipment->packages->isEmpty() && $assignment->sellerOrder) {
            StorkSchemaBootstrapService::backfillMainHubsAndPackages();
            $assignment->load('shipment.packages.verifications');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function accept(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $allowedStatuses = (array) config('security.allowed_statuses.rider', ['approved', 'active']);
        if (! in_array($rider->status, $allowedStatuses, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Suspended or inactive Rider accounts cannot accept delivery assignments.',
            ], 403);
        }

        $assignment = DeliveryAssignment::with('shipment')->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: This delivery assignment belongs to another rider or company.',
            ], 403);
        }

        if (in_array($assignment->status, ['accepted', 'collected', 'out_for_delivery'], true)) {
            $assignment->load(['shipment.packages.verifications', 'sellerOrder.items', 'sellerOrder.payment']);
            return response()->json([
                'status'  => 'success',
                'message' => 'Delivery assignment is already accepted.',
                'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
            ]);
        }

        if ($assignment->status !== 'assigned') {
            return response()->json([
                'status'  => 'error',
                'message' => 'This delivery offer has expired or was already reassigned by Logistics.',
            ], 409);
        }

        $activeAcceptedCount = DeliveryAssignment::where('rider_id', $rider->id)
            ->where('id', '!=', $assignment->id)
            ->whereIn('status', ['accepted', 'collected', 'out_for_delivery'])
            ->count();

        if ($activeAcceptedCount >= self::MAX_CONCURRENT_ACTIVE_JOBS) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Concurrent active job limit reached (maximum ' . self::MAX_CONCURRENT_ACTIVE_JOBS . ' active jobs at a time). Complete an existing delivery first.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($assignment, $rider) {
                /** @var DeliveryAssignment $locked */
                $locked = DeliveryAssignment::with('shipment.packages')
                    ->where('id', $assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $locked->rider_id !== (int) $rider->id || $locked->status !== 'assigned') {
                    throw ValidationException::withMessages([
                        'assignment' => 'This job was just reassigned or is no longer available.',
                    ]);
                }

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
                        'location'        => $rider->assigned_hub?->hub_name ?? ($rider->logisticsProfile->business_name ?? 'Sorting Hub'),
                        'actor_type'      => 'Rider',
                        'actor_id'        => $rider->id,
                        'occurred_at'     => now(),
                    ]);
                }
            });
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => collect($e->errors())->flatten()->first() ?? 'Job could not be accepted.',
                'errors'  => $e->errors(),
            ], 409);
        }

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Delivery assignment accepted! Proceed to Step 2: Scan the package QR code or enter the Tracking / Package Reference at the hub.',
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function decline(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with('shipment')->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You cannot decline another rider\'s assignment.',
            ], 403);
        }

        if ($assignment->status !== 'assigned') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only unaccepted assignments can be declined. Packages already accepted or collected cannot be declined.',
            ], 422);
        }

        $reason = trim((string) $request->input('reason', 'Declined by Rider on mobile app.'));

        DB::transaction(function () use ($assignment, $rider, $reason) {
            $locked = DeliveryAssignment::with('shipment')
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'status' => 'reassigned',
                'notes'  => trim(($locked->notes ? $locked->notes . ' | ' : '') . "Declined by Rider {$rider->full_name}: {$reason}"),
            ]);

            if ($locked->shipment && (int) $locked->shipment->current_rider_id === (int) $rider->id) {
                $locked->shipment->update([
                    'current_rider_id' => null,
                    'status'           => 'received_at_hub',
                ]);

                ShipmentTracking::create([
                    'shipment_id'     => $locked->shipment->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'received_at_hub',
                    'title'           => 'Returned to Unassigned Hub Queue (Declined by Rider)',
                    'description'     => "Rider {$rider->full_name} declined the delivery offer ({$reason}). Returned to hub dispatch queue.",
                    'location'        => $rider->assigned_hub?->hub_name ?? 'Sorting Hub',
                    'actor_type'      => 'Rider',
                    'actor_id'        => $rider->id,
                    'occurred_at'     => now(),
                ]);
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Delivery assignment declined and returned to the hub queue.',
        ]);
    }

    public function verifyPackage(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with(['shipment.packages.verifications', 'sellerOrder.items'])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You are not assigned to this delivery.',
            ], 403);
        }

        if ($assignment->status === 'assigned') {
            return response()->json([
                'status'  => 'error',
                'message' => 'You must first accept the delivery assignment (Step 1) before scanning or verifying the package.',
            ], 422);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:180'],
            'verification_method' => ['nullable', 'in:qr,manual'],
            'stage'               => ['nullable', 'in:rider_collection,delivery_confirmation'],
        ]);

        $rawInput = trim((string) $validated['lookup_input']);
        if (str_contains($rawInput, 'qr_token=')) {
            parse_str((string) parse_url($rawInput, PHP_URL_QUERY), $queryParts);
            if (! empty($queryParts['qr_token'])) {
                $rawInput = trim((string) $queryParts['qr_token']);
            }
        } elseif (str_starts_with($rawInput, 'STORK-QR|')) {
            $parts = explode('|', $rawInput);
            $rawInput = trim((string) (end($parts) ?: $rawInput));
        }

        $method = $validated['verification_method'] ?? (str_starts_with(strtoupper($rawInput), 'STORK-QR-') ? 'qr' : 'manual');
        $stage = $validated['stage'] ?? ($assignment->status === 'out_for_delivery' ? 'delivery_confirmation' : 'rider_collection');

        $shipment = $assignment->shipment;
        if (! $shipment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Shipment record not found.',
            ], 404);
        }

        if ($shipment->packages->isEmpty() && $assignment->sellerOrder) {
            StorkSchemaBootstrapService::backfillMainHubsAndPackages();
            $shipment->load('packages.verifications');
        }

        $matchedPkg = $shipment->packages->first(function ($pkg) use ($rawInput, $shipment) {
            return strcasecmp((string) $pkg->qr_token, $rawInput) === 0
                || strcasecmp((string) $pkg->package_reference, $rawInput) === 0
                || strcasecmp((string) $shipment->tracking_number, $rawInput) === 0;
        });

        if (! $matchedPkg) {
            return response()->json([
                'status'  => 'error',
                'message' => "Tracking number or package reference \"{$rawInput}\" does not match Shipment #{$shipment->tracking_number}.",
                'errors'  => [
                    'lookup_input' => ["Tracking number does not match this job (#{$shipment->tracking_number})."],
                ],
            ], 422);
        }

        $alreadyVerified = $stage === 'delivery_confirmation'
            ? ($matchedPkg->delivery_verified_at !== null)
            : ($matchedPkg->rider_verified_at !== null || $matchedPkg->rider_collected_at !== null);

        if (! $alreadyVerified) {
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
                    'lookup_token_or_number' => substr($rawInput, 0, 100),
                    'actor_type'             => 'Rider',
                    'actor_id'               => $rider->id,
                    'is_confirmed'           => true,
                    'confirmed_at'           => now(),
                    'remarks'                => "Verified by Rider {$rider->full_name} via " . strtoupper($method) . " ({$stage})",
                ]);
            });
        }

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
        ]);

        return response()->json([
            'status'           => 'success',
            'already_verified' => $alreadyVerified,
            'message'          => $alreadyVerified
                ? "Package {$matchedPkg->package_reference} was already verified."
                : "Package {$matchedPkg->package_reference} verified via " . strtoupper($method) . "! Please review the manifest and confirm collection.",
            'data'             => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function startDelivery(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with([
            'shipment.packages.verifications',
            'shipment.sellerOrder.buyer',
            'shipment.sellerOrder.order',
        ])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You are not assigned to this delivery.',
            ], 403);
        }

        if ($assignment->status === 'out_for_delivery') {
            return response()->json([
                'status'  => 'success',
                'message' => 'Package is already marked Out for Delivery.',
                'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
            ]);
        }

        if ($assignment->status === 'assigned') {
            return response()->json([
                'status'  => 'error',
                'message' => 'You must accept the delivery assignment before collecting the package.',
            ], 422);
        }

        if (! in_array($assignment->status, ['accepted', 'collected'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => "Cannot start delivery from current status ({$assignment->status}).",
            ], 422);
        }

        $shipment = $assignment->shipment;
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
            return response()->json([
                'status'  => 'error',
                'message' => 'Please scan the QR code or verify all package references at the sorting hub before starting Out for Delivery.',
                'errors'  => [
                    'verification' => ['Package verification (Step 2) is required before confirming hub collection.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($assignment, $shipment, $rider) {
            $assignment->update([
                'status'              => 'out_for_delivery',
                'accepted_at'         => $assignment->accepted_at ?? now(),
                'picked_up_at'        => now(),
                'collected_at'        => now(),
                'out_for_delivery_at' => now(),
            ]);

            $shipment->packages()->update([
                'rider_collected_at' => now(),
            ]);

            $shipmentUpdates = [
                'status'              => 'out_for_delivery',
                'out_for_delivery_at' => now(),
            ];
            if (Schema::hasColumn('shipments', 'delivery_otp') && empty($shipment->delivery_otp)) {
                $shipmentUpdates['delivery_otp'] = (string) random_int(100000, 999999);
            }

            $shipment->update($shipmentUpdates);

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
                'description'     => "Rider {$rider->full_name} ({$rider->contact_no}) collected the parcel from the sorting hub and is on the way to the buyer's address.",
                'location'        => $shipment->delivery_address_snapshot['municipality'] ?? 'Local Delivery Area',
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

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Shipment #{$shipment->tracking_number} is now Out for Delivery!",
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function completeDelivery(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with([
            'shipment.sellerOrder.order',
            'shipment.sellerOrder.buyer',
            'shipment.sellerOrder.seller',
            'shipment.sellerOrder.payment',
            'shipment.packages',
        ])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You cannot complete another rider\'s delivery assignment.',
            ], 403);
        }

        if ($assignment->status === 'delivered') {
            return response()->json([
                'status'  => 'success',
                'message' => 'This delivery has already been completed.',
                'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
            ]);
        }

        if ($assignment->status !== 'out_for_delivery' || $assignment->shipment?->status !== 'out_for_delivery') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid status transition: Package must be Out for Delivery before marking as Delivered.',
                'errors'  => [
                    'status' => ['You cannot skip directly to Delivered before accepting, verifying, and starting Out for Delivery.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'proof_photo'        => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'recipient_name'     => ['nullable', 'string', 'max:120'],
            'recipient_relation' => ['nullable', 'string', 'max:80'],
            'collected_amount'   => ['nullable', 'numeric', 'min:0'],
            'delivery_otp'       => ['nullable', 'string', 'max:10'],
            'notes'              => ['nullable', 'string', 'max:500'],
            'latitude'           => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'           => ['nullable', 'numeric', 'min:0'],
        ], [
            'proof_photo.required'  => 'A live Proof of Delivery photo (JPG, PNG, or WEBP, max 5 MB) is strictly required.',
            'proof_photo.mimetypes' => 'Proof of Delivery must be a valid JPG, PNG, or WEBP image.',
            'proof_photo.mimes'     => 'Proof of Delivery must be a JPG, PNG, or WEBP file.',
            'proof_photo.max'       => 'Proof of Delivery image must not exceed 5 MB.',
        ]);

        $shipment = $assignment->shipment;
        $sellerOrder = $shipment->sellerOrder;

        if ($request->filled('delivery_otp') && Schema::hasColumn('shipments', 'delivery_otp') && ! empty($shipment->delivery_otp)) {
            if ($shipment->delivery_otp_locked_until && Carbon::parse($shipment->delivery_otp_locked_until)->isFuture()) {
                $mins = max(1, (int) ceil(now()->diffInSeconds(Carbon::parse($shipment->delivery_otp_locked_until)) / 60));
                return response()->json([
                    'status'  => 'error',
                    'message' => "Delivery OTP entry is locked due to 5 incorrect attempts. Please wait {$mins} minute(s).",
                    'errors'  => [
                        'delivery_otp' => ["OTP entry is locked for 15 minutes after 5 failed attempts ({$mins}m remaining)."],
                    ],
                ], 422);
            }

            $inputOtp = trim((string) $validated['delivery_otp']);
            if (! preg_match('/^\d{6}$/', $inputOtp)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Delivery OTP must be exactly 6 digits.',
                    'errors'  => ['delivery_otp' => ['Delivery OTP must be a 6-digit code.']],
                ], 422);
            }

            if ($inputOtp !== (string) $shipment->delivery_otp) {
                $attempts = (int) $shipment->delivery_otp_attempts + 1;
                $lockUntil = $attempts >= 5 ? now()->addMinutes(15) : null;
                $shipment->update([
                    'delivery_otp_attempts'     => $attempts,
                    'delivery_otp_locked_until' => $lockUntil,
                ]);

                $msg = $attempts >= 5
                    ? '5 wrong OTP entries. Delivery OTP entry on this job is now locked for 15 minutes.'
                    : 'Invalid Delivery OTP. ' . (5 - $attempts) . ' attempt(s) remaining.';

                return response()->json([
                    'status'  => 'error',
                    'message' => $msg,
                    'errors'  => ['delivery_otp' => [$msg]],
                ], 422);
            }
        }

        $expectedCod = round((float) $shipment->cod_amount, 2);
        $collectedCod = isset($validated['collected_amount'])
            ? round((float) $validated['collected_amount'], 2)
            : $expectedCod;

        if ($expectedCod > 0 && abs($collectedCod - $expectedCod) >= 0.01) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Collected COD amount (₱' . number_format($collectedCod, 2) . ') must match the exact payable COD amount of ₱' . number_format($expectedCod, 2) . '.',
                'errors'  => [
                    'collected_amount' => ['Collected COD amount must exactly match ₱' . number_format($expectedCod, 2) . '.'],
                ],
            ], 422);
        }

        if ($expectedCod <= 0) {
            $collectedCod = 0.00;
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

        $payInfo = WebRiderDeliveryController::calculateDistanceScaledRiderPay($shipment);

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

            $attempt = DeliveryAttempt::create([
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $assignment->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => max(1, (int) $assignment->attempt_number),
                'outcome'                => 'delivered',
                'notes'                  => $validated['notes'] ?? "Delivered to {$recipientName} ({$recipientRelation})",
                'attempted_at'           => now(),
            ]);

            $proofData = [
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $assignment->id,
                'delivery_attempt_id'    => $attempt->id,
                'rider_id'               => $rider->id,
                'photo_path'             => $filename,
                'recipient_name'         => $recipientName,
                'recipient_relation'     => $recipientRelation,
                'cod_collected_amount'   => $collectedCod,
                'notes'                  => $validated['notes'] ?? null,
                'captured_at'            => now(),
            ];

            if (isset($validated['latitude'], $validated['longitude']) && Schema::hasColumn('delivery_proofs', 'latitude')) {
                $proofData['latitude'] = round((float) $validated['latitude'], 7);
                $proofData['longitude'] = round((float) $validated['longitude'], 7);
                $proofData['location_accuracy'] = isset($validated['accuracy']) ? round((float) $validated['accuracy'], 2) : null;

                if (Schema::hasTable('rider_locations')) {
                    RiderLocation::create([
                        'rider_id'               => $rider->id,
                        'delivery_assignment_id' => $assignment->id,
                        'latitude'               => $proofData['latitude'],
                        'longitude'              => $proofData['longitude'],
                        'accuracy'               => $proofData['location_accuracy'],
                        'recorded_at'            => now(),
                    ]);
                }
            }

            DeliveryProof::create($proofData);

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

            $this->roleWalletService->recordPendingEscrowOnDelivery($sellerOrder->fresh(['extra', 'payment', 'shipment', 'sellerProfile', 'logisticsProfile']));

            if ($sellerOrder->order) {
                $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
            }

            try {
                $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Parcel Delivered! Please Confirm Receipt Within 72 Hours',
                    message: "Order #{$sellerOrder->seller_order_number} was delivered to {$recipientName}. Please inspect your items and confirm receipt within 3 days.",
                    type: 'success',
                    url: route('user.orders.show', $sellerOrder->id),
                    orderId: $sellerOrder->order_id,
                    sellerOrderId: $sellerOrder->id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        });

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'shipment.latestDeliveryProof',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Delivery completed! Your distance-scaled Rider Pay of ₱" . number_format((float) $payInfo['total_pay'], 2) . " ({$payInfo['distance_tier']}) has been credited to your Pending Escrow Wallet.",
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function failDelivery(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with([
            'shipment.sellerOrder.order',
            'shipment.sellerOrder.buyer',
            'shipment.sellerOrder.seller',
            'shipment.sellerOrder.payment',
        ])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You cannot update another rider\'s delivery assignment.',
            ], 403);
        }

        if ($assignment->status !== 'out_for_delivery') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Only Out for Delivery assignments can be marked as Failed Delivery.',
            ], 422);
        }

        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'min:3', 'max:255'],
            'failure_notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'failure_reason.required' => 'Please select a reason for the failed delivery attempt.',
        ]);

        $reasonLabel = trim((string) $validated['failure_reason']);
        $notesText = trim((string) ($validated['failure_notes'] ?? ''));
        $fullReason = $notesText !== '' ? "{$reasonLabel} — {$notesText}" : $reasonLabel;

        $shipment = $assignment->shipment;
        $sellerOrder = $shipment->sellerOrder;
        $autoRtsTriggered = false;
        $attemptNo = 1;

        DB::transaction(function () use ($assignment, $shipment, $sellerOrder, $rider, $reasonLabel, $notesText, $fullReason, &$autoRtsTriggered, &$attemptNo) {
            $prevAttempts = max(
                (int) ($shipment->failed_attempts_count ?? 0),
                (int) ($shipment->delivery_attempts_count ?? 0),
                (int) DeliveryAttempt::where('shipment_id', $shipment->id)->where('outcome', 'failed')->count()
            );
            $attemptNo = $prevAttempts + 1;

            $assignment->update([
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => $fullReason,
                'completed_at'   => now(),
            ]);

            DeliveryAttempt::create([
                'shipment_id'            => $shipment->id,
                'seller_order_id'        => $sellerOrder->id,
                'delivery_assignment_id' => $assignment->id,
                'rider_id'               => $rider->id,
                'attempt_number'         => $attemptNo,
                'outcome'                => 'failed',
                'failure_reason'         => $reasonLabel,
                'failure_notes'          => $notesText !== '' ? $notesText : null,
                'notes'                  => "Failed Attempt #{$attemptNo} (Rider Pay: ₱0.00)",
                'attempted_at'           => now(),
            ]);

            $isBuyerRefusal = stripos($reasonLabel, 'refused') !== false || stripos($reasonLabel, 'reject') !== false;
            $autoRtsTriggered = $attemptNo >= self::MAX_DELIVERY_ATTEMPTS || $isBuyerRefusal;

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
                'remarks'         => "Failed Delivery Attempt #{$attemptNo}: {$fullReason}" . ($autoRtsTriggered ? ' (Return to Seller triggered).' : ''),
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => $shipment->status,
                'title'           => $autoRtsTriggered
                    ? "Delivery Failed (Attempt #{$attemptNo}) — Returning to Seller (RTS)"
                    : "Delivery Attempt #{$attemptNo} of " . self::MAX_DELIVERY_ATTEMPTS . " Unsuccessful",
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

        if (! $autoRtsTriggered) {
            $this->buyerOrderNotificationService->deliveryAttemptFailed($shipment, $attemptNo, $fullReason);
        }

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => $autoRtsTriggered
                ? "Failed delivery recorded (Attempt #{$attemptNo}). Return to Seller (RTS) has been initiated (Rider Pay: ₱0.00)."
                : "Failed delivery attempt #{$attemptNo} of " . self::MAX_DELIVERY_ATTEMPTS . " recorded (Rider Pay: ₱0.00).",
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function buildPendingActionBadges(Rider $rider, $activeAssignments, int $unremittedCodCount): array
    {
        $unacceptedCount = 0;
        $toCollectCount = 0;
        $outForDeliveryCount = 0;

        foreach ($activeAssignments as $a) {
            if ($a->status === 'assigned') {
                $unacceptedCount++;
            } elseif (in_array($a->status, ['accepted', 'collected'], true)) {
                $toCollectCount++;
            } elseif ($a->status === 'out_for_delivery') {
                $outForDeliveryCount++;
            }
        }

        $unreadNotifications = $rider->unreadNotifications()->count();

        return [
            'unaccepted_assignments'     => $unacceptedCount,
            'pending_collections'        => $toCollectCount,
            'out_for_delivery'           => $outForDeliveryCount,
            'total_deliveries_badge'     => $unacceptedCount + $toCollectCount + $outForDeliveryCount,
            'pending_cod_remittances'    => $unremittedCodCount,
            'unread_notifications_count' => $unreadNotifications,
        ];
    }

    public function formatDeliveryAssignment(DeliveryAssignment $assignment, Rider $rider, bool $detailed = false): array
    {
        $shipment = $assignment->shipment;
        $sellerOrder = $assignment->sellerOrder;
        $delivSnap = $shipment?->delivery_address_snapshot ?? ($sellerOrder?->delivery_address_snapshot ?? []);
        $pickupSnap = $shipment?->pickup_address_snapshot ?? ($sellerOrder?->pickup_address_snapshot ?? []);
        $hubSnap = $shipment?->hub_address_snapshot ?? [];

        $packages = $shipment?->packages ?? collect();
        $primaryPackage = $packages->first();

        $isTaskAccepted = in_array($assignment->status, ['accepted', 'collected', 'out_for_delivery', 'delivered'], true)
            || ($primaryPackage && $primaryPackage->rider_accepted_at !== null);

        $allCollectionVerified = $packages->isNotEmpty() && $packages->every(function ($pkg) use ($rider) {
            if ($pkg->rider_verified_at !== null || $pkg->rider_collected_at !== null) {
                return true;
            }
            return $pkg->verifications
                ->where('stage', 'rider_collection')
                ->where('actor_type', 'Rider')
                ->where('actor_id', $rider->id)
                ->where('is_confirmed', true)
                ->isNotEmpty();
        });

        $isOutForDelivery = $assignment->status === 'out_for_delivery' && $shipment?->status === 'out_for_delivery';

        $allDoorstepVerified = $packages->isNotEmpty() && $packages->every(function ($pkg) use ($rider) {
            if ($pkg->delivery_verified_at !== null) {
                return true;
            }
            return $pkg->verifications
                ->where('stage', 'delivery_confirmation')
                ->where('actor_type', 'Rider')
                ->where('actor_id', $rider->id)
                ->where('is_confirmed', true)
                ->isNotEmpty();
        });

        $payBreakdown = $shipment
            ? WebRiderDeliveryController::calculateDistanceScaledRiderPay($shipment)
            : [
                'base_pay'       => 50.00,
                'distance_tier'  => 'Standard',
                'distance_bonus' => 0.00,
                'weight_bonus'   => 0.00,
                'total_pay'      => 50.00,
            ];

        if ($assignment->status === 'failed') {
            $payBreakdown['total_pay'] = 0.00;
        }

        $buyerCoords = $this->resolveCoordinatesForAddress($delivSnap, $sellerOrder?->order?->userAddress);
        $hubCoords = $this->resolveCoordinatesForAddress($hubSnap, null, $rider->assigned_hub);

        $payMethod = $sellerOrder?->effective_payment_method ?? ((float) ($shipment?->cod_amount ?? 0) > 0 ? 'COD' : 'GCASH');
        $codAmount = round((float) ($shipment?->cod_amount ?? 0), 2);

        $slaDeadlineAt = $assignment->assigned_at
            ? $assignment->assigned_at->copy()->addHours(OperationalSlaGuardService::DISPATCH_SLA_HOURS)
            : null;

        $payload = [
            'id'                      => (int) $assignment->id,
            'shipment_id'             => (int) $assignment->shipment_id,
            'seller_order_id'         => (int) $assignment->seller_order_id,
            'tracking_number'         => (string) ($shipment?->tracking_number ?? 'N/A'),
            'seller_order_number'     => (string) ($sellerOrder?->seller_order_number ?? 'N/A'),
            'attempt_number'          => max(1, (int) $assignment->attempt_number),
            'max_attempts'            => self::MAX_DELIVERY_ATTEMPTS,
            'status'                  => (string) $assignment->status,
            'status_label'            => strtoupper(str_replace('_', ' ', (string) $assignment->status)),
            'shipment_status'         => (string) ($shipment?->status ?? ''),
            'shipment_status_label'   => (string) ($shipment?->status_label ?? ''),
            'payment_method'          => $payMethod,
            'is_cod'                  => $codAmount > 0,
            'cod_amount'              => $codAmount,
            'shipping_fee'            => round((float) ($shipment?->shipping_fee ?? 0), 2),
            'total_weight'            => round((float) ($shipment?->total_weight ?? 0), 3),
            'rider_pay'               => $payBreakdown,
            'workflow_state'          => [
                'step_1_accepted'            => $isTaskAccepted,
                'step_2_collection_verified' => $allCollectionVerified,
                'step_3_out_for_delivery'    => $isOutForDelivery || $assignment->status === 'delivered',
                'step_4_doorstep_verified'   => $allDoorstepVerified || $assignment->status === 'delivered',
                'is_completed'               => $assignment->status === 'delivered',
                'is_failed'                  => $assignment->status === 'failed',
            ],
            'assigned_at'             => $assignment->assigned_at?->toIso8601String(),
            'accepted_at'             => $assignment->accepted_at?->toIso8601String(),
            'out_for_delivery_at'     => $assignment->out_for_delivery_at?->toIso8601String(),
            'completed_at'            => $assignment->completed_at?->toIso8601String(),
            'sla_deadline_at'         => $slaDeadlineAt?->toIso8601String(),
            'failure_reason'          => $assignment->failure_reason ?? $shipment?->last_failure_reason,
            'seller'                  => [
                'business_name' => $shipment?->sellerProfile?->business_name ?? ($pickupSnap['business_name'] ?? 'Seller Store'),
                'contact_no'    => $pickupSnap['contact_no'] ?? ($shipment?->sellerProfile?->contact_no ?? ''),
                'barangay'      => $pickupSnap['barangay'] ?? '',
                'municipality'  => $pickupSnap['municipality'] ?? '',
                'province'      => $pickupSnap['province'] ?? '',
                'full_address'  => $pickupSnap['full_address'] ?? '',
            ],
            'hub'                     => [
                'hub_name'     => $rider->assigned_hub?->hub_name ?? ($hubSnap['hub_name'] ?? ($shipment?->logisticsProfile?->business_name ?? 'Sorting Hub')),
                'contact_no'   => $rider->assigned_hub?->contact_no ?? ($hubSnap['contact_no'] ?? ''),
                'barangay'     => $rider->assigned_hub?->barangay ?? ($hubSnap['barangay'] ?? ''),
                'municipality' => $rider->assigned_hub?->municipality ?? ($hubSnap['municipality'] ?? ''),
                'province'     => $rider->assigned_hub?->province ?? ($hubSnap['province'] ?? ''),
                'full_address' => $rider->assigned_hub?->full_address ?? ($hubSnap['full_address'] ?? ''),
                'latitude'     => $hubCoords['latitude'],
                'longitude'    => $hubCoords['longitude'],
                'is_exact_pin' => $hubCoords['is_exact'],
            ],
            'buyer'                   => [
                'recipient_name'   => $delivSnap['recipient_name'] ?? ($sellerOrder?->buyer?->full_name ?? 'Buyer'),
                'phone_number'     => $delivSnap['phone_number'] ?? ($sellerOrder?->buyer?->contact_no ?? ''),
                'house_details'    => $delivSnap['building_details'] ?? ($delivSnap['house_details'] ?? ''),
                'street'           => $delivSnap['street_address'] ?? ($delivSnap['street'] ?? ''),
                'barangay'         => $delivSnap['barangay'] ?? '',
                'municipality'     => $delivSnap['municipality'] ?? '',
                'province'         => $delivSnap['province'] ?? '',
                'postcode'         => $delivSnap['postcode'] ?? '',
                'full_address'     => $delivSnap['full_address'] ?? collect([
                    $delivSnap['building_details'] ?? null,
                    $delivSnap['street_address'] ?? ($delivSnap['street'] ?? null),
                    $delivSnap['barangay'] ?? null,
                    $delivSnap['municipality'] ?? null,
                    $delivSnap['province'] ?? null,
                ])->filter()->implode(', '),
                'latitude'         => $buyerCoords['latitude'],
                'longitude'        => $buyerCoords['longitude'],
                'is_exact_pin'     => $buyerCoords['is_exact'],
                'coordinate_label' => $buyerCoords['source_label'],
            ],
            'packages'                => $packages->map(function ($pkg) use ($isOutForDelivery, $allCollectionVerified, $allDoorstepVerified) {
                return [
                    'id'                          => (int) $pkg->id,
                    'package_reference'           => (string) $pkg->package_reference,
                    'qr_token'                    => (string) $pkg->qr_token,
                    'package_number'              => (int) $pkg->package_number,
                    'total_packages'              => (int) $pkg->total_packages,
                    'package_weight'              => (float) $pkg->package_weight,
                    'total_items_count'           => (int) $pkg->total_items_count,
                    'is_collection_verified'      => $pkg->rider_verified_at !== null || $allCollectionVerified,
                    'is_doorstep_verified'        => $pkg->delivery_verified_at !== null || $allDoorstepVerified,
                    'rider_verification_method'   => $pkg->rider_verification_method,
                    'delivery_verification_method'=> $pkg->delivery_verification_method,
                    'manifest'                    => is_array($pkg->manifest_snapshot) ? $pkg->manifest_snapshot : [],
                ];
            })->values()->all(),
        ];

        if ($detailed) {
            $proof = $assignment->proof ?? $shipment?->latestDeliveryProof;
            $payload['proof'] = $proof ? [
                'id'                   => (int) $proof->id,
                'photo_url'            => asset('storage/' . ltrim($proof->photo_path, '/')),
                'recipient_name'       => (string) ($proof->recipient_name ?? ''),
                'recipient_relation'   => (string) ($proof->recipient_relation ?? ''),
                'cod_collected_amount' => (float) ($proof->cod_collected_amount ?? 0),
                'latitude'             => $proof->latitude !== null ? (float) $proof->latitude : null,
                'longitude'            => $proof->longitude !== null ? (float) $proof->longitude : null,
                'notes'                => $proof->notes,
                'captured_at'          => $proof->captured_at?->toIso8601String(),
            ] : null;

            $payload['items'] = ($sellerOrder?->items ?? collect())->map(fn ($item) => [
                'id'                    => (int) $item->id,
                'product_id'            => (int) $item->product_id,
                'product_name'          => (string) $item->product_name,
                'variant_name'          => $item->variant_name,
                'sub_variant_name'      => $item->sub_variant_name,
                'quantity'              => (int) $item->quantity,
                'discounted_unit_price' => (float) $item->discounted_unit_price,
                'line_total'            => (float) $item->line_total,
                'line_weight'           => (float) $item->line_weight,
                'image_url'             => $item->product_image ? asset('storage/' . ltrim($item->product_image, '/')) : null,
            ])->values()->all();

            $payload['tracking_logs'] = ($shipment?->trackingLogs ?? collect())->map(fn ($log) => [
                'id'          => (int) $log->id,
                'status'      => (string) $log->status,
                'title'       => (string) $log->title,
                'description' => (string) ($log->description ?? ''),
                'location'    => (string) ($log->location ?? ''),
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])->values()->all();
        }

        return $payload;
    }

    protected function resolveCoordinatesForAddress(array $snapshot, $userAddress = null, $hub = null): array
    {
        if (isset($snapshot['latitude'], $snapshot['longitude']) && is_numeric($snapshot['latitude']) && is_numeric($snapshot['longitude'])) {
            return [
                'latitude'     => round((float) $snapshot['latitude'], 7),
                'longitude'    => round((float) $snapshot['longitude'], 7),
                'is_exact'     => true,
                'source_label' => 'Saved GPS Pin',
            ];
        }

        if ($userAddress && $userAddress->latitude !== null && $userAddress->longitude !== null) {
            return [
                'latitude'     => round((float) $userAddress->latitude, 7),
                'longitude'    => round((float) $userAddress->longitude, 7),
                'is_exact'     => true,
                'source_label' => 'Buyer Saved Address Coordinates',
            ];
        }

        if ($hub && $hub->latitude !== null && $hub->longitude !== null) {
            return [
                'latitude'     => round((float) $hub->latitude, 7),
                'longitude'    => round((float) $hub->longitude, 7),
                'is_exact'     => true,
                'source_label' => 'Sorting Hub Coordinates',
            ];
        }

        $barangay = trim((string) ($snapshot['barangay'] ?? ($hub?->barangay ?? '')));
        $municipality = trim((string) ($snapshot['municipality'] ?? ($hub?->municipality ?? '')));
        $province = trim((string) ($snapshot['province'] ?? ($hub?->province ?? '')));

        $knownDirectory = [
            'santa cruz|laguna'  => [14.2786, 121.4162],
            'sta. rosa|laguna'   => [14.3122, 121.1114],
            'santa rosa|laguna'  => [14.3122, 121.1114],
            'calamba|laguna'     => [14.2118, 121.1653],
            'san pablo|laguna'   => [14.0693, 121.3266],
            'pagsanjan|laguna'   => [14.2733, 121.4542],
            'pila|laguna'        => [14.2339, 121.3636],
            'los banos|laguna'   => [14.1700, 121.2414],
            'binan|laguna'       => [14.3364, 121.0842],
            'cabuyao|laguna'     => [14.2725, 121.1239],
            'quezon city'        => [14.6760, 121.0437],
            'makati'             => [14.5547, 121.0244],
            'pasig'              => [14.5764, 121.0851],
            'manila'             => [14.5995, 120.9842],
            'taguig'             => [14.5176, 121.0509],
            'mandaluyong'        => [14.5794, 121.0359],
            'cebu city'          => [10.3157, 123.8854],
            'davao city'         => [7.1907, 125.4553],
            'imus|cavite'        => [14.4297, 120.9367],
        ];

        $normMun = strtolower(preg_replace('/\b(city of|municipality of|city)\b/', '', strtolower($municipality)));
        $normMun = trim((string) preg_replace('/\s+/', ' ', $normMun));
        $normProv = strtolower(trim($province));

        $queryKey = strtolower(trim("{$barangay}|{$normMun}|{$normProv}"));

        if ($normMun !== '' || $normProv !== '') {
            $cached = Cache::remember("stork_geo_v1_" . md5($queryKey), now()->addDays(7), function () use ($barangay, $municipality, $province) {
                try {
                    $q = collect([$barangay, $municipality, $province, 'Philippines'])->filter()->implode(', ');
                    $resp = Http::withoutVerifying()
                        ->withHeaders(['User-Agent' => 'StorkiaEcommerceLogistics/1.0'])
                        ->timeout(4)
                        ->get('https://nominatim.openstreetmap.org/search', [
                            'q'              => $q,
                            'format'         => 'json',
                            'limit'          => 1,
                            'countrycodes'   => 'ph',
                        ]);

                    if ($resp->successful() && is_array($resp->json()) && ! empty($resp->json()[0]['lat'])) {
                        return [
                            'latitude'  => round((float) $resp->json()[0]['lat'], 7),
                            'longitude' => round((float) $resp->json()[0]['lon'], 7),
                        ];
                    }
                } catch (\Throwable $e) {
                }

                return null;
            });

            if (is_array($cached) && isset($cached['latitude'], $cached['longitude'])) {
                if ($userAddress && $userAddress->latitude === null) {
                    try {
                        $userAddress->update([
                            'latitude'  => $cached['latitude'],
                            'longitude' => $cached['longitude'],
                        ]);
                    } catch (\Throwable $e) {
                    }
                }

                return [
                    'latitude'     => $cached['latitude'],
                    'longitude'    => $cached['longitude'],
                    'is_exact'     => false,
                    'source_label' => 'Geocoded Barangay / Municipality Pin',
                ];
            }
        }

        foreach ($knownDirectory as $pattern => $coords) {
            $parts = explode('|', $pattern);
            if (str_contains($normMun, $parts[0])) {
                return [
                    'latitude'     => $coords[0],
                    'longitude'    => $coords[1],
                    'is_exact'     => false,
                    'source_label' => 'Municipality Center Fallback',
                ];
            }
        }

        return [
            'latitude'     => 14.2786,
            'longitude'    => 121.4162,
            'is_exact'     => false,
            'source_label' => 'Regional Hub Center Fallback',
        ];
    }
}