<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\DeliveryController as WebRiderDeliveryController;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryChatMessage;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerAbandonmentService;
use App\Services\BuyerOrderNotificationService;
use App\Services\BuyerRewardService;
use App\Services\CourierMobileSchemaService;
use App\Services\OperationalSlaGuardService;
use App\Services\OrderCompletionService;
use App\Services\RealtimeBroadcastService;
use App\Services\RiderMobileSchemaService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use App\Services\VoucherService;
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
        protected AuthApiController $authApiController,
        protected ?BuyerRewardService $buyerRewardService = null,
        protected ?VoucherService $voucherService = null
    ) {
        $this->buyerRewardService = $buyerRewardService ?? app(BuyerRewardService::class);
        $this->voucherService = $voucherService ?? app(VoucherService::class);
    }

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

        $sort = strtolower(trim((string) $request->query('sort', 'nearest')));
        if (! in_array($sort, ['nearest', 'farthest', 'oldest', 'newest', 'cod_high'], true)) {
            $sort = 'nearest';
        }
        $barangayFilter = trim((string) $request->query('barangay', ''));

        $rawActiveAssignments = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.extra',
            'sellerOrder.order.userAddress',
        ])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
            ->orderBy('id', 'asc')
            ->get();

        $availableBarangays = $rawActiveAssignments
            ->map(fn (DeliveryAssignment $a) => trim((string) ($a->shipment?->delivery_address_snapshot['barangay'] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $decoratedActive = $rawActiveAssignments->map(function (DeliveryAssignment $a) use ($rider) {
            $delivSnap = (array) ($a->shipment?->delivery_address_snapshot ?? []);
            $distKm = $rider->distanceToSnapshotKm($delivSnap);
            $a->setAttribute('rider_distance_km', $distKm);
            return $a;
        });

        if ($barangayFilter !== '') {
            $decoratedActive = $decoratedActive->filter(function (DeliveryAssignment $a) use ($barangayFilter) {
                $brgy = trim((string) ($a->shipment?->delivery_address_snapshot['barangay'] ?? ''));
                return strcasecmp($brgy, $barangayFilter) === 0;
            });
        }

        $activeAssignments = match ($sort) {
            'farthest' => $decoratedActive->sortByDesc(fn (DeliveryAssignment $a) => (float) ($a->getAttribute('rider_distance_km') ?? 0))->values(),
            'newest'   => $decoratedActive->sortByDesc(fn (DeliveryAssignment $a) => ($a->assigned_at ?? $a->created_at)?->timestamp ?? 0)->values(),
            'oldest'   => $decoratedActive->sortBy(fn (DeliveryAssignment $a) => ($a->assigned_at ?? $a->created_at)?->timestamp ?? 0)->values(),
            'cod_high' => $decoratedActive->sortByDesc(fn (DeliveryAssignment $a) => (float) ($a->shipment?->cod_amount ?? 0))->values(),
            default    => $decoratedActive->sortBy(fn (DeliveryAssignment $a) => (float) ($a->getAttribute('rider_distance_km') ?? 9999))->values(),
        };

        $recentCompletedAssignments = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.extra',
            'sellerOrder.order.userAddress',
        ])
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['delivered', 'failed'])
            ->orderByRaw('COALESCE(completed_at, updated_at) DESC')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $unremittedCollections = CodCollection::with(['shipment.sellerOrder.extra', 'commissionRecord'])
            ->where('rider_id', $rider->id)
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
        $pendingRiderComm = round((float) $unremittedCollections->sum(fn (CodCollection $c) => WebRiderDeliveryController::resolveCollectionRiderCommission($c)), 2);

        $verifiedRiderEarnings = max(
            round((float) $riderWallet->total_earned, 2),
            Schema::hasTable('cod_remittances')
                ? round((float) CodRemittance::where('rider_id', $rider->id)->where('status', 'verified')->sum('total_rider_commission'), 2)
                : 0.00
        );

        $badges = $this->buildPendingActionBadges($rider, $rawActiveAssignments, $unremittedCollections->count());

        return response()->json([
            'status' => 'success',
            'data'   => [
                'rider'                 => $this->authApiController->formatRiderProfile($rider),
                'sort'                  => $sort,
                'barangay_filter'       => $barangayFilter,
                'available_barangays'   => $availableBarangays,
                'metrics'               => [
                    'active_deliveries'       => $rawActiveAssignments->count(),
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
                'badges'                => $badges,
                'active_assignments'    => $activeAssignments->map(fn (DeliveryAssignment $a) => $this->formatDeliveryAssignment($a, $rider, false))->values()->all(),
                'completed_assignments' => $recentCompletedAssignments->map(fn (DeliveryAssignment $a) => $this->formatDeliveryAssignment($a, $rider, false))->values()->all(),
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
        $sort = strtolower(trim((string) $request->query('sort', '')));
        $barangayFilter = trim((string) $request->query('barangay', ''));
        $municipalityFilter = trim((string) $request->query('municipality', ''));

        $query = DeliveryAssignment::with([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.latestDeliveryProof',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.extra',
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
            $query->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery', 'delivered', 'failed']);
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
            $assignments = $query
                ->orderByRaw('COALESCE(completed_at, updated_at) DESC')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        } elseif (in_array($filter, ['assigned', 'to_collect', 'accepted', 'out_for_delivery'], true)) {
            $assignments = $query
                ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $activeItems = (clone $query)
                ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
                ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
                ->orderBy('id', 'asc')
                ->get();

            $completedItems = (clone $query)
                ->whereIn('status', ['delivered', 'failed'])
                ->orderByRaw('COALESCE(completed_at, updated_at) DESC')
                ->orderByDesc('id')
                ->limit(25)
                ->get();

            $assignments = $activeItems->concat($completedItems);
        }

        $availableBarangays = $assignments
            ->map(fn (DeliveryAssignment $a) => trim((string) ($a->shipment?->delivery_address_snapshot['barangay'] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $availableMunicipalities = $assignments
            ->map(fn (DeliveryAssignment $a) => trim((string) ($a->shipment?->delivery_address_snapshot['municipality'] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $assignments = $assignments->map(function (DeliveryAssignment $a) use ($rider) {
            $delivSnap = (array) ($a->shipment?->delivery_address_snapshot ?? []);
            $a->setAttribute('rider_distance_km', $rider->distanceToSnapshotKm($delivSnap));
            return $a;
        });

        if ($barangayFilter !== '') {
            $assignments = $assignments->filter(function (DeliveryAssignment $a) use ($barangayFilter) {
                $brgy = trim((string) ($a->shipment?->delivery_address_snapshot['barangay'] ?? ''));
                return strcasecmp($brgy, $barangayFilter) === 0;
            })->values();
        }

        if ($municipalityFilter !== '') {
            $assignments = $assignments->filter(function (DeliveryAssignment $a) use ($municipalityFilter) {
                $mun = trim((string) ($a->shipment?->delivery_address_snapshot['municipality'] ?? ''));
                return strcasecmp($mun, $municipalityFilter) === 0;
            })->values();
        }

        if (in_array($sort, ['nearest', 'farthest', 'oldest', 'newest', 'cod_high'], true)) {
            $assignments = match ($sort) {
                'nearest'  => $assignments->sortBy(fn (DeliveryAssignment $a) => (float) ($a->getAttribute('rider_distance_km') ?? 9999))->values(),
                'farthest' => $assignments->sortByDesc(fn (DeliveryAssignment $a) => (float) ($a->getAttribute('rider_distance_km') ?? 0))->values(),
                'newest'   => $assignments->sortByDesc(fn (DeliveryAssignment $a) => ($a->assigned_at ?? $a->created_at)?->timestamp ?? 0)->values(),
                'oldest'   => $assignments->sortBy(fn (DeliveryAssignment $a) => ($a->assigned_at ?? $a->created_at)?->timestamp ?? 0)->values(),
                'cod_high' => $assignments->sortByDesc(fn (DeliveryAssignment $a) => (float) ($a->shipment?->cod_amount ?? 0))->values(),
            };
        }

        $activeAll = DeliveryAssignment::with('shipment.packages.verifications')
            ->where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->get();

        $unremittedCount = CodCollection::where('rider_id', $rider->id)->where('status', 'collected')->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'filter'                   => $filter,
                'sort'                     => $sort !== '' ? $sort : 'oldest',
                'barangay_filter'          => $barangayFilter,
                'municipality_filter'      => $municipalityFilter,
                'available_barangays'      => $availableBarangays,
                'available_municipalities' => $availableMunicipalities,
                'badges'                   => $this->buildPendingActionBadges($rider, $activeAll, $unremittedCount),
                'assignments'              => $assignments->map(fn (DeliveryAssignment $a) => $this->formatDeliveryAssignment($a, $rider, false))->values()->all(),
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
            'sellerOrder.extra',
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
            $assignment->load(['shipment.packages.verifications', 'sellerOrder.items', 'sellerOrder.payment', 'sellerOrder.extra']);

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
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.accepted');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.rider_accepted');
        }

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

            $updates = [
                'status' => 'reassigned',
                'notes'  => trim(($locked->notes ? $locked->notes . ' | ' : '') . "Declined by Rider {$rider->full_name}: {$reason}"),
            ];
            if (Schema::hasColumn('delivery_assignments', 'declined_at')) {
                $updates['declined_at'] = now();
                $updates['decline_reason'] = substr($reason, 0, 300);
            }

            $locked->update($updates);

            if ($locked->shipment && (int) $locked->shipment->current_rider_id === (int) $rider->id) {
                $restoredStatus = $locked->shipment->needsInterHubTransfer()
                    ? 'received_at_destination_hub'
                    : 'received_at_hub';

                $locked->shipment->update([
                    'current_rider_id' => null,
                    'status'           => $restoredStatus,
                ]);

                ShipmentTracking::create([
                    'shipment_id'     => $locked->shipment->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => $restoredStatus,
                    'title'           => 'Returned to Unassigned Hub Queue (Declined by Rider)',
                    'description'     => "Rider {$rider->full_name} declined the delivery offer ({$reason}). Returned to hub dispatch queue.",
                    'location'        => $rider->assigned_hub?->hub_name ?? 'Sorting Hub',
                    'actor_type'      => 'Rider',
                    'actor_id'        => $rider->id,
                    'occurred_at'     => now(),
                ]);
            }
        });

        $assignment->refresh()->load('shipment');
        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.declined');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.rider_declined');
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Delivery assignment declined and returned to the hub queue.',
        ]);
    }

    public function reportDelay(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with(['shipment', 'sellerOrder.buyer'])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'reason'               => ['required', 'string', 'min:3', 'max:150'],
            'notes'                => ['nullable', 'string', 'max:300'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'            => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $eta = ! empty($validated['estimated_arrival_at']) ? Carbon::parse($validated['estimated_arrival_at']) : null;

        DB::transaction(function () use ($assignment, $rider, $validated, $eta) {
            if (Schema::hasColumn('delivery_assignments', 'is_delayed')) {
                $assignment->update([
                    'is_delayed'           => true,
                    'delay_reason'         => trim($validated['reason']),
                    'estimated_arrival_at' => $eta,
                ]);
            }

            if (Schema::hasTable('rider_trip_incidents')) {
                DB::table('rider_trip_incidents')->insert([
                    'delivery_assignment_id' => $assignment->id,
                    'shipment_id'            => $assignment->shipment_id,
                    'rider_id'               => $rider->id,
                    'logistics_profile_id'   => $assignment->logistics_profile_id,
                    'incident_type'          => 'delay',
                    'reason'                 => trim($validated['reason']),
                    'notes'                  => ! empty($validated['notes']) ? trim($validated['notes']) : null,
                    'estimated_arrival_at'   => $eta,
                    'latitude'               => isset($validated['latitude']) ? round((float) $validated['latitude'], 7) : null,
                    'longitude'              => isset($validated['longitude']) ? round((float) $validated['longitude'], 7) : null,
                    'status'                 => 'reported',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }

            if ($assignment->shipment) {
                ShipmentTracking::create([
                    'shipment_id'     => $assignment->shipment_id,
                    'seller_order_id' => $assignment->seller_order_id,
                    'status'          => $assignment->shipment->status,
                    'title'           => 'Delivery Delay Reported by Rider',
                    'description'     => "Rider {$rider->full_name} reported a route delay: {$validated['reason']}" . ($eta ? ' (New ETA: ' . $eta->format('h:i A') . ')' : ''),
                    'location'        => $assignment->shipment->delivery_address_snapshot['municipality'] ?? 'En Route',
                    'actor_type'      => 'Rider',
                    'actor_id'        => $rider->id,
                    'occurred_at'     => now(),
                ]);
            }
        });

        try {
            $assignment->sellerOrder?->buyer?->notify(new OrderLifecycleNotification(
                title: 'Delivery Delay Update (Order #' . ($assignment->sellerOrder->seller_order_number ?? '') . ')',
                message: "Your rider {$rider->full_name} reported a slight delay ({$validated['reason']}). Thank you for your patience!",
                type: 'warning',
                url: route('user.orders.show', $assignment->seller_order_id),
                orderId: $assignment->sellerOrder->order_id ?? null,
                sellerOrderId: $assignment->seller_order_id,
                shipmentId: $assignment->shipment_id
            ));
        } catch (\Throwable $e) {
        }

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.delayed');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.delayed');
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Delivery delay logged and buyer/hub notified.',
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function reportBreakdown(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with(['shipment.logisticsProfile.user', 'sellerOrder.buyer'])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'reason'    => ['required', 'string', 'min:3', 'max:150'],
            'notes'     => ['nullable', 'string', 'max:300'],
            'photo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('rider_incidents', 'public')
            : null;

        DB::transaction(function () use ($assignment, $rider, $validated, $photoPath) {
            if (Schema::hasColumn('delivery_assignments', 'is_delayed')) {
                $assignment->update([
                    'is_delayed'   => true,
                    'delay_reason' => 'Vehicle Breakdown / Emergency: ' . trim($validated['reason']),
                ]);
            }

            if (Schema::hasTable('rider_trip_incidents')) {
                DB::table('rider_trip_incidents')->insert([
                    'delivery_assignment_id' => $assignment->id,
                    'shipment_id'            => $assignment->shipment_id,
                    'rider_id'               => $rider->id,
                    'logistics_profile_id'   => $assignment->logistics_profile_id,
                    'incident_type'          => 'breakdown',
                    'reason'                 => trim($validated['reason']),
                    'notes'                  => ! empty($validated['notes']) ? trim($validated['notes']) : null,
                    'photo_path'             => $photoPath,
                    'latitude'               => isset($validated['latitude']) ? round((float) $validated['latitude'], 7) : null,
                    'longitude'              => isset($validated['longitude']) ? round((float) $validated['longitude'], 7) : null,
                    'status'                 => 'reported',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }

            if ($assignment->shipment) {
                ShipmentTracking::create([
                    'shipment_id'     => $assignment->shipment_id,
                    'seller_order_id' => $assignment->seller_order_id,
                    'status'          => $assignment->shipment->status,
                    'title'           => 'Rider Vehicle Breakdown / Emergency Reported',
                    'description'     => "Rider {$rider->full_name} reported an emergency/breakdown: {$validated['reason']}. Logistics hub notified for assistance.",
                    'location'        => $assignment->shipment->delivery_address_snapshot['municipality'] ?? 'En Route',
                    'actor_type'      => 'Rider',
                    'actor_id'        => $rider->id,
                    'occurred_at'     => now(),
                ]);
            }
        });

        try {
            $assignment->shipment?->logisticsProfile?->user?->notify(new OrderLifecycleNotification(
                title: "URGENT: Rider Breakdown on Shipment #{$assignment->shipment->tracking_number}",
                message: "Rider {$rider->full_name} reported a breakdown ({$validated['reason']}). Immediate hub assistance required.",
                type: 'danger',
                url: route('logistics.shipments.show', $assignment->shipment_id),
                orderId: $assignment->sellerOrder->order_id ?? null,
                sellerOrderId: $assignment->seller_order_id,
                shipmentId: $assignment->shipment_id
            ));
        } catch (\Throwable $e) {
        }

        $assignment->refresh()->load([
            'shipment.sellerProfile',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.payment',
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.breakdown');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.breakdown');
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Emergency / breakdown incident reported to your Logistics Sorting Hub.',
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function chatMessages(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with('sellerOrder.buyer')->find($assignmentId);
        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        if (! Schema::hasTable('delivery_chat_messages')) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'can_send' => false,
                    'messages' => [],
                ],
            ]);
        }

        $markedCount = DeliveryChatMessage::where('seller_order_id', $assignment->seller_order_id)
            ->where('sender_type', 'buyer')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if ($markedCount > 0 && $assignment->sellerOrder) {
            RealtimeBroadcastService::send(
                channels: [
                    'user.' . (int) $assignment->sellerOrder->buyer_id,
                    'rider.' . (int) $rider->id,
                    'order.' . (int) $assignment->seller_order_id,
                    'shipment.' . (int) $assignment->shipment_id,
                ],
                scope: 'delivery_chat',
                action: 'delivery_chat.read',
                payload: [
                    'shipment_id'            => (int) $assignment->shipment_id,
                    'seller_order_id'        => (int) $assignment->seller_order_id,
                    'delivery_assignment_id' => (int) $assignment->id,
                    'reader_type'            => 'rider',
                ]
            );
        }

        $messages = DeliveryChatMessage::where('seller_order_id', $assignment->seller_order_id)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (DeliveryChatMessage $m) => [
                'id'             => (int) $m->id,
                'sender_type'    => (string) $m->sender_type,
                'message'        => (string) ($m->message ?? ''),
                'attachment_url' => $m->attachment_url ?? ($m->attachment_path ? asset('storage/' . ltrim($m->attachment_path, '/')) : null),
                'is_read'        => (bool) $m->is_read,
                'created_at'     => $m->created_at?->toIso8601String(),
            ]);

        $canSend = in_array($assignment->status, ['accepted', 'collected', 'out_for_delivery'], true);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'can_send' => $canSend,
                'buyer'    => [
                    'name'         => $assignment->sellerOrder?->delivery_address_snapshot['recipient_name'] ?? ($assignment->sellerOrder?->buyer?->full_name ?? 'Buyer'),
                    'phone_number' => $assignment->sellerOrder?->delivery_address_snapshot['phone_number'] ?? '',
                ],
                'messages' => $messages,
            ],
        ]);
    }

    public function markChatRead(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with('sellerOrder')->find($assignmentId);
        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        $markedCount = 0;
        if (Schema::hasTable('delivery_chat_messages')) {
            $markedCount = DeliveryChatMessage::where('seller_order_id', $assignment->seller_order_id)
                ->where('sender_type', 'buyer')
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        }

        if ($markedCount > 0 && $assignment->sellerOrder) {
            RealtimeBroadcastService::send(
                channels: [
                    'user.' . (int) $assignment->sellerOrder->buyer_id,
                    'rider.' . (int) $rider->id,
                    'order.' . (int) $assignment->seller_order_id,
                    'shipment.' . (int) $assignment->shipment_id,
                ],
                scope: 'delivery_chat',
                action: 'delivery_chat.read',
                payload: [
                    'shipment_id'            => (int) $assignment->shipment_id,
                    'seller_order_id'        => (int) $assignment->seller_order_id,
                    'delivery_assignment_id' => (int) $assignment->id,
                    'reader_type'            => 'rider',
                ]
            );
        }

        return response()->json([
            'status'            => 'success',
            'marked_read_count' => $markedCount,
            'unread_chat_count' => 0,
        ]);
    }

    public function sendChatMessage(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with('sellerOrder.buyer')->find($assignmentId);
        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden.'], 403);
        }

        if (! in_array($assignment->status, ['accepted', 'collected', 'out_for_delivery'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Chat with Buyer is only available for active accepted/out-for-delivery assignments.',
            ], 422);
        }

        $validated = $request->validate([
            'message'    => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $cleanMessage = trim((string) ($validated['message'] ?? ''));
        if ($cleanMessage === '' && ! $request->hasFile('attachment')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please enter a message or attach a photo.',
            ], 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('delivery_chat', 'public');
        }

        $chatMsg = DeliveryChatMessage::create([
            'shipment_id'            => $assignment->shipment_id,
            'seller_order_id'        => $assignment->seller_order_id,
            'delivery_assignment_id' => $assignment->id,
            'buyer_id'               => $assignment->sellerOrder->buyer_id,
            'rider_id'               => $rider->id,
            'sender_type'            => 'rider',
            'message'                => $cleanMessage !== '' ? $cleanMessage : '[Photo Attachment]',
            'attachment_path'        => $attachmentPath,
            'is_read'                => false,
        ]);

        $messagePayload = [
            'id'             => (int) $chatMsg->id,
            'sender_type'    => (string) $chatMsg->sender_type,
            'message'        => (string) $chatMsg->message,
            'attachment_url' => $attachmentPath ? asset('storage/' . ltrim($attachmentPath, '/')) : null,
            'is_read'        => false,
            'created_at'     => $chatMsg->created_at?->toIso8601String(),
        ];

        RealtimeBroadcastService::forDeliveryChat(
            shipmentId: (int) $assignment->shipment_id,
            sellerOrderId: (int) $assignment->seller_order_id,
            buyerId: (int) $assignment->sellerOrder->buyer_id,
            riderId: (int) $rider->id,
            messagePayload: $messagePayload
        );

        try {
            $assignment->sellerOrder?->buyer?->notify(new OrderLifecycleNotification(
                title: "Message from Delivery Rider {$rider->full_name}",
                message: '"' . substr($chatMsg->message, 0, 120) . '"',
                type: 'info',
                url: route('user.orders.show', $assignment->seller_order_id),
                orderId: $assignment->sellerOrder->order_id ?? null,
                sellerOrderId: $assignment->seller_order_id,
                shipmentId: $assignment->shipment_id
            ));
        } catch (\Throwable $e) {
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Message sent to Buyer.',
            'data'    => $messagePayload,
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
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.package_verified');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.package_verified');
        }

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
            'shipment.sellerOrder.extra',
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
                $otpNotice = ! empty($shipment->delivery_otp)
                    ? " Your 6-digit Delivery OTP is {$shipment->delivery_otp}."
                    : '';
                $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Your Parcel is Out for Delivery!',
                    message: "Order #{$sellerOrder->seller_order_number} (Tracking #{$shipment->tracking_number}) is out for delivery with Rider {$rider->full_name}.{$otpNotice}",
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
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.out_for_delivery');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.out_for_delivery');
        }
        if ($assignment->sellerOrder) {
            RealtimeBroadcastService::forSellerOrder($assignment->sellerOrder, 'order.out_for_delivery');
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Shipment #{$shipment->tracking_number} is now Out for Delivery!",
            'data'    => $this->formatDeliveryAssignment($assignment, $rider, true),
        ]);
    }

    public function resendDeliveryOtp(Request $request, int $assignmentId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $assignment = DeliveryAssignment::with(['shipment.sellerOrder.buyer'])->find($assignmentId);

        if (! $assignment || (int) $assignment->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You are not assigned to this delivery.',
            ], 403);
        }

        if ($assignment->status !== 'out_for_delivery') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Delivery OTP can only be resent while the parcel is Out for Delivery.',
            ], 422);
        }

        $shipment = $assignment->shipment;
        $sellerOrder = $shipment?->sellerOrder;

        if (! $shipment || ! $sellerOrder) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Shipment or order record not found.',
            ], 404);
        }

        $otpCode = ! empty($shipment->delivery_otp)
            ? (string) $shipment->delivery_otp
            : (string) random_int(100000, 999999);

        if (Schema::hasColumn('shipments', 'delivery_otp')) {
            $updates = ['delivery_otp' => $otpCode];
            if (
                Schema::hasColumn('shipments', 'delivery_otp_locked_until')
                && $shipment->delivery_otp_locked_until
                && Carbon::parse($shipment->delivery_otp_locked_until)->isPast()
            ) {
                $updates['delivery_otp_attempts'] = 0;
                $updates['delivery_otp_locked_until'] = null;
            }
            $shipment->update($updates);
        }

        try {
            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: "6-Digit Delivery OTP for Order #{$sellerOrder->seller_order_number}",
                message: "Rider {$rider->full_name} is at your location for Shipment #{$shipment->tracking_number}. Your 6-digit Delivery OTP is: {$otpCode}.",
                type: 'info',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id,
                shipmentId: $shipment->id
            ));
        } catch (\Throwable $e) {
        }

        if ($sellerOrder->buyer) {
            RealtimeBroadcastService::forNotification($sellerOrder->buyer, [
                'title'           => "6-Digit Delivery OTP: {$otpCode}",
                'message'         => "Provide code {$otpCode} to Rider {$rider->full_name} for Order #{$sellerOrder->seller_order_number}.",
                'type'            => 'info',
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'shipment_id'     => $shipment->id,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'The 6-digit Delivery OTP has been resent to the Buyer\'s notifications and order screen.',
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
            'shipment.sellerOrder.extra',
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
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.completed');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.delivered');
        }
        if ($assignment->sellerOrder) {
            RealtimeBroadcastService::forSellerOrder($assignment->sellerOrder, 'order.delivered');
        }
        RealtimeBroadcastService::forWalletUpdate('Rider', (int) $rider->id, 'wallet.escrow_credited', [
            'seller_order_id' => (int) $sellerOrder->id,
            'shipment_id'     => (int) $shipment->id,
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
            'shipment.sellerOrder.extra',
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

            if ($autoRtsTriggered) {
                $sellerOrder->payment?->update([
                    'status' => 'cancelled',
                ]);

                $wasWalletPaid = strtoupper((string) $sellerOrder->effective_payment_method) === 'WALLET'
                    || (float) ($sellerOrder->extra?->wallet_paid_amount ?? 0) > 0;
                if ($wasWalletPaid) {
                    $refundAmt = (float) ($sellerOrder->extra?->wallet_paid_amount > 0 ? $sellerOrder->extra->wallet_paid_amount : $sellerOrder->total_amount);
                    $this->roleWalletService->refundBuyerWalletForOrder(
                        $sellerOrder,
                        $refundAmt,
                        'Order Returned to Seller (RTS) — E-Wallet Refund'
                    );
                }

                $this->voucherService?->restoreVouchersForSellerOrder($sellerOrder);
                $this->buyerRewardService?->restoreCoinsForSellerOrder($sellerOrder);
            }

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
            'sellerOrder.extra',
        ]);

        RealtimeBroadcastService::forDeliveryAssignment($assignment, 'delivery.failed');
        if ($assignment->shipment) {
            RealtimeBroadcastService::forShipment($assignment->shipment, 'shipment.failed');
        }
        if ($assignment->sellerOrder) {
            RealtimeBroadcastService::forSellerOrder($assignment->sellerOrder, 'order.delivery_failed');
        }

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

        $pendingReturnPickups = Schema::hasTable('return_shipments')
            ? ReturnShipment::where('current_rider_id', $rider->id)
                ->whereIn('status', [
                    ReturnShipment::STATUS_RIDER_ASSIGNED,
                    ReturnShipment::STATUS_PICKED_UP_FROM_BUYER,
                ])
                ->count()
            : 0;

        $unreadNotifications = $rider->unreadNotifications()->count();

        return [
            'unaccepted_assignments'     => $unacceptedCount,
            'pending_collections'        => $toCollectCount,
            'out_for_delivery'           => $outForDeliveryCount,
            'pending_return_pickups'     => $pendingReturnPickups,
            'total_deliveries_badge'     => $unacceptedCount + $toCollectCount + $outForDeliveryCount + $pendingReturnPickups,
            'pending_cod_remittances'    => $unremittedCodCount,
            'unread_notifications_count' => $unreadNotifications,
        ];
    }

    public function formatDeliveryAssignment(DeliveryAssignment $assignment, Rider $rider, bool $detailed = false): array
    {
        $shipment = $assignment->shipment;
        $sellerOrder = $assignment->sellerOrder;
        $delivSnap = (array) ($shipment?->delivery_address_snapshot ?? ($sellerOrder?->delivery_address_snapshot ?? []));
        $pickupSnap = (array) ($shipment?->pickup_address_snapshot ?? ($sellerOrder?->pickup_address_snapshot ?? []));
        $hubSnap = (array) ($shipment?->hub_address_snapshot ?? []);

        $packages = $shipment?->packages ?? collect();
        $primaryPackage = $packages->first();

        $isTaskAccepted = in_array($assignment->status, ['accepted', 'collected', 'out_for_delivery', 'delivered'], true)
            || ($primaryPackage && $primaryPackage->rider_accepted_at !== null);

        $allCollectionVerified = $packages->isNotEmpty() && $packages->every(function ($pkg) use ($rider) {
            if ($pkg->rider_verified_at !== null || $pkg->rider_collected_at !== null) {
                return true;
            }
            return $pVerif = $pkg->verifications
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
                'base_pay'       => 15.75,
                'distance_tier'  => 'Same Barangay (45% Share)',
                'distance_bonus' => 0.00,
                'weight_bonus'   => 0.00,
                'total_pay'      => 15.75,
            ];

        if ($assignment->status === 'failed') {
            $payBreakdown['total_pay'] = 0.00;
        }

        $buyerCoords = $this->resolveCoordinatesForAddress($delivSnap, $sellerOrder?->order?->userAddress);
        $hubCoords = $this->resolveCoordinatesForAddress($hubSnap, null, $rider->assigned_hub);

        $riderEval = $rider->evaluateDeliveryEligibilityForSnapshot($delivSnap);
        $distFromRiderKm = round((float) ($assignment->getAttribute('rider_distance_km') ?? $riderEval['distance_from_rider_km'] ?? 0), 2);
        $distFromHubKm = round((float) ($riderEval['distance_from_hub_km'] ?? $shipment?->calculated_buyer_distance_km ?? 0), 2);

        $payMethod = $sellerOrder?->effective_payment_method ?? ((float) ($shipment?->cod_amount ?? 0) > 0 ? 'COD' : 'WALLET');
        $codAmount = round((float) ($shipment?->cod_amount ?? 0), 2);

        $slaDeadlineAt = $assignment->assigned_at
            ? $assignment->assigned_at->copy()->addHours(OperationalSlaGuardService::DISPATCH_SLA_HOURS)
            : null;

        $unreadChatCount = Schema::hasTable('delivery_chat_messages') && $assignment->seller_order_id
            ? DeliveryChatMessage::where('seller_order_id', $assignment->seller_order_id)
                ->where('sender_type', 'buyer')
                ->where('is_read', false)
                ->count()
            : 0;

        $etaFormatted = null;
        if (! empty($assignment->estimated_arrival_at)) {
            try {
                $etaFormatted = Carbon::parse($assignment->estimated_arrival_at)->toIso8601String();
            } catch (\Throwable $e) {
            }
        }

        $hasDeliveryOtp = Schema::hasColumn('shipments', 'delivery_otp') && ! empty($shipment?->delivery_otp);
        $otpLockedUntil = null;
        if (Schema::hasColumn('shipments', 'delivery_otp_locked_until') && ! empty($shipment?->delivery_otp_locked_until)) {
            try {
                $parsedLock = Carbon::parse($shipment->delivery_otp_locked_until);
                if ($parsedLock->isFuture()) {
                    $otpLockedUntil = $parsedLock->toIso8601String();
                }
            } catch (\Throwable $e) {
            }
        }

        $payload = [
            'id'                        => (int) $assignment->id,
            'shipment_id'               => (int) $assignment->shipment_id,
            'seller_order_id'           => (int) $assignment->seller_order_id,
            'tracking_number'           => (string) ($shipment?->tracking_number ?? 'N/A'),
            'seller_order_number'       => (string) ($sellerOrder?->seller_order_number ?? 'N/A'),
            'attempt_number'            => max(1, (int) $assignment->attempt_number),
            'max_attempts'              => self::MAX_DELIVERY_ATTEMPTS,
            'status'                    => (string) $assignment->status,
            'status_label'              => strtoupper(str_replace('_', ' ', (string) $assignment->status)),
            'shipment_status'           => (string) ($shipment?->status ?? ''),
            'shipment_status_label'     => (string) ($shipment?->status_label ?? ''),
            'payment_method'            => $payMethod,
            'is_cod'                    => $codAmount > 0,
            'cod_amount'                => $codAmount,
            'shipping_fee'              => round((float) ($shipment?->shipping_fee ?? 0), 2),
            'total_weight'              => round((float) ($shipment?->total_weight ?? 0), 3),
            'distance_from_rider_km'    => $distFromRiderKm,
            'distance_from_hub_km'      => $distFromHubKm,
            'distance_label'            => '~' . number_format($distFromRiderKm, 1) . ' km',
            'is_within_rider_radius'    => (bool) ($riderEval['is_eligible'] ?? true),
            'exact_barangay_match'      => (bool) ($riderEval['exact_barangay_match'] ?? false),
            'max_rider_radius_km'       => (float) ($riderEval['max_radius_km'] ?? 20.0),
            'rider_pay'                 => $payBreakdown,
            'is_delayed'                => (bool) ($assignment->is_delayed ?? false),
            'delay_reason'              => $assignment->delay_reason ?? null,
            'estimated_arrival_at'      => $etaFormatted,
            'unread_chat_count'         => $unreadChatCount,
            'has_delivery_otp'          => $hasDeliveryOtp,
            'delivery_otp_locked_until' => $otpLockedUntil,
            'workflow_state'            => [
                'step_1_accepted'            => $isTaskAccepted,
                'step_2_collection_verified' => $allCollectionVerified,
                'step_3_out_for_delivery'    => $isOutForDelivery || $assignment->status === 'delivered',
                'step_4_doorstep_verified'   => $allDoorstepVerified || $assignment->status === 'delivered',
                'is_completed'               => $assignment->status === 'delivered',
                'is_failed'                  => $assignment->status === 'failed',
            ],
            'assigned_at'               => $assignment->assigned_at?->toIso8601String(),
            'accepted_at'               => $assignment->accepted_at?->toIso8601String(),
            'out_for_delivery_at'       => $assignment->out_for_delivery_at?->toIso8601String(),
            'completed_at'              => $assignment->completed_at?->toIso8601String(),
            'sla_deadline_at'           => $slaDeadlineAt?->toIso8601String(),
            'failure_reason'            => $assignment->failure_reason ?? $shipment?->last_failure_reason,
            'seller'                    => [
                'business_name' => $shipment?->sellerProfile?->business_name ?? ($pickupSnap['business_name'] ?? 'Seller Store'),
                'contact_no'    => $pickupSnap['contact_no'] ?? ($shipment?->sellerProfile?->contact_no ?? ''),
                'barangay'      => $pickupSnap['barangay'] ?? '',
                'municipality'  => $pickupSnap['municipality'] ?? '',
                'province'      => $pickupSnap['province'] ?? '',
                'full_address'  => $pickupSnap['full_address'] ?? '',
            ],
            'hub'                       => [
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
            'buyer'                     => [
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
            'packages'                  => $packages->map(function ($pkg) use ($allCollectionVerified, $allDoorstepVerified) {
                return [
                    'id'                           => (int) $pkg->id,
                    'package_reference'            => (string) $pkg->package_reference,
                    'qr_token'                     => (string) $pkg->qr_token,
                    'package_number'               => (int) $pkg->package_number,
                    'total_packages'               => (int) $pkg->total_packages,
                    'package_weight'               => (float) $pkg->package_weight,
                    'total_items_count'            => (int) $pkg->total_items_count,
                    'is_collection_verified'       => $pkg->rider_verified_at !== null || $allCollectionVerified,
                    'is_doorstep_verified'         => $pkg->delivery_verified_at !== null || $allDoorstepVerified,
                    'rider_verification_method'    => $pkg->rider_verification_method,
                    'delivery_verification_method' => $pkg->delivery_verification_method,
                    'manifest'                     => is_array($pkg->manifest_snapshot) ? $pkg->manifest_snapshot : [],
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
        if (isset($snapshot['latitude'], $snapshot['longitude']) && is_numeric($snapshot['latitude']) && is_numeric($snapshot['longitude']) && (float) $snapshot['latitude'] != 0.0) {
            return [
                'latitude'     => round((float) $snapshot['latitude'], 7),
                'longitude'    => round((float) $snapshot['longitude'], 7),
                'is_exact'     => true,
                'source_label' => 'Saved GPS Pin',
            ];
        }

        if ($userAddress && $userAddress->latitude !== null && $userAddress->longitude !== null && (float) $userAddress->latitude != 0.0) {
            return [
                'latitude'     => round((float) $userAddress->latitude, 7),
                'longitude'    => round((float) $userAddress->longitude, 7),
                'is_exact'     => true,
                'source_label' => 'Buyer Saved Address Coordinates',
            ];
        }

        if ($hub && $hub->latitude !== null && $hub->longitude !== null && (float) $hub->latitude != 0.0) {
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

        $normMun = strtolower(preg_replace('/\b(city of|municipality of|city)\b/', '', strtolower($municipality)));
        $normMun = trim((string) preg_replace('/\s+/', ' ', $normMun));
        $normProv = strtolower(trim($province));

        $queryKey = strtolower(trim("{$barangay}|{$normMun}|{$normProv}"));

        if ($normMun !== '' || $normProv !== '') {
            $cached = Cache::remember('stork_geo_v1_' . md5($queryKey), now()->addDays(7), function () use ($barangay, $municipality, $province) {
                try {
                    $q = collect([$barangay, $municipality, $province, 'Philippines'])->filter()->implode(', ');
                    $resp = Http::withoutVerifying()
                        ->withHeaders(['User-Agent' => 'StorkiaEcommerceLogistics/1.0'])
                        ->timeout(4)
                        ->get('https://nominatim.openstreetmap.org/search', [
                            'q'            => $q,
                            'format'       => 'json',
                            'limit'        => 1,
                            'countrycodes' => 'ph',
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

        $resolvedFallback = CourierMobileSchemaService::resolveCoordinatesForAddress($snapshot, $userAddress ?? $hub);

        return [
            'latitude'     => $resolvedFallback['latitude'],
            'longitude'    => $resolvedFallback['longitude'],
            'is_exact'     => ! $resolvedFallback['is_approximate'],
            'source_label' => 'Provincial / Municipality Centroid',
        ];
    }
}