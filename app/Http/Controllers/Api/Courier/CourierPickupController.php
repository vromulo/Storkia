<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\PickupController as WebPickupController;
use App\Models\Courier;
use App\Models\LogisticsHub;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\ReturnShipment;
use App\Models\Shipment;
use App\Models\ShipmentPackage;
use App\Models\ShipmentTracking;
use App\Models\WalletTransaction;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\CourierMobileSchemaService;
use App\Services\OperationalSlaGuardService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\ShipmentVerificationService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourierPickupController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService,
        protected ShipmentVerificationService $shipmentVerificationService,
        protected OperationalSlaGuardService $slaGuardService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        if ($courier->logistics_profile_id) {
            $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $courier->logistics_profile_id);
        }

        $courier->forceFill(['last_seen_at' => now()])->save();

        $activeAssignments = PickupAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.logisticsHub',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.extra',
        ])
            ->where('courier_id', $courier->id)
            ->where('logistics_profile_id', $courier->logistics_profile_id)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->whereHas('shipment', function ($q) {
                $q->where('status', '!=', 'awaiting_seller_drop_off');
            })
            ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
            ->orderBy('id', 'asc')
            ->get();

        $recentCompletedAssignments = PickupAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.logisticsHub',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.extra',
        ])
            ->where('courier_id', $courier->id)
            ->where('logistics_profile_id', $courier->logistics_profile_id)
            ->whereIn('status', ['in_transit_to_hub', 'completed'])
            ->orderByRaw('COALESCE(handed_over_at, updated_at) DESC')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $unacceptedCount = $activeAssignments->where('status', 'assigned')->count();
        $acceptedCount = $activeAssignments->where('status', 'accepted')->count();
        $inTransitCount = PickupAssignment::where('courier_id', $courier->id)
            ->where('logistics_profile_id', $courier->logistics_profile_id)
            ->whereIn('status', ['picked_up', 'in_transit_to_hub'])
            ->count();

        $completedCount = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['in_transit_to_hub', 'completed'])
            ->count();

        $pendingReturnDeliveries = Schema::hasTable('return_shipments')
            ? ReturnShipment::where('current_courier_id', $courier->id)
                ->whereIn('status', [
                    ReturnShipment::STATUS_COURIER_ASSIGNED,
                    ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
                ])
                ->count()
            : 0;

        $wallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        $todayStart = now()->timezone('Asia/Manila')->startOfDay();
        $todayEnd = now()->timezone('Asia/Manila')->endOfDay();

        $todayEarnings = (float) WalletTransaction::where('owner_type', 'Courier')
            ->where('owner_id', $courier->id)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');

        $unreadNotificationsCount = $courier->unreadNotifications()->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'metrics' => [
                    'active_pickups'             => $activeAssignments->count(),
                    'unaccepted_pickups'         => $unacceptedCount,
                    'accepted_pickups'           => $acceptedCount,
                    'in_transit_pickups'         => $inTransitCount,
                    'completed_pickups'          => $completedCount,
                    'pending_return_deliveries'  => $pendingReturnDeliveries,
                    'wallet_available'           => number_format((float) $wallet->available_balance, 2, '.', ''),
                    'wallet_available_raw'       => round((float) $wallet->available_balance, 2),
                    'wallet_pending'             => number_format((float) $wallet->pending_balance, 2, '.', ''),
                    'wallet_pending_raw'         => round((float) $wallet->pending_balance, 2),
                    'total_earned'               => number_format((float) $wallet->total_earned, 2, '.', ''),
                    'total_earned_raw'           => round((float) $wallet->total_earned, 2),
                    'today_earnings'             => number_format($todayEarnings, 2, '.', ''),
                    'today_earnings_raw'         => round($todayEarnings, 2),
                    'unread_notifications_count' => $unreadNotificationsCount,
                ],
                'active_assignments'    => $activeAssignments
                    ->map(fn (PickupAssignment $a) => $this->formatAssignmentPayload($a, false))
                    ->values()
                    ->all(),
                'completed_assignments' => $recentCompletedAssignments
                    ->map(fn (PickupAssignment $a) => $this->formatAssignmentPayload($a, false))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        if ($courier->logistics_profile_id) {
            $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $courier->logistics_profile_id);
        }

        $filter = strtolower(trim((string) $request->query('filter', 'active')));
        $search = trim((string) $request->query('search', ''));
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        if ($fromDate && $toDate) {
            $parsedFrom = Carbon::parse($fromDate)->startOfDay();
            $parsedTo = Carbon::parse($toDate)->endOfDay();
            if ($parsedTo->lt($parsedFrom)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'End date cannot be earlier than the start date.',
                    'errors'  => [
                        'to_date' => ['End date must be on or after the start date.'],
                    ],
                ], 422);
            }
        }

        $query = PickupAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.logisticsHub',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.extra',
        ])
            ->where('courier_id', $courier->id)
            ->where('logistics_profile_id', $courier->logistics_profile_id)
            ->whereHas('shipment', function ($q) {
                $q->where('status', '!=', 'awaiting_seller_drop_off');
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('shipment', function ($sq) use ($search) {
                    $sq->where('tracking_number', 'like', "%{$search}%")
                        ->orWhere('pickup_address_snapshot', 'like', "%{$search}%");
                })
                ->orWhereHas('sellerOrder', function ($soq) use ($search) {
                    $soq->where('seller_order_number', 'like', "%{$search}%");
                })
                ->orWhereHas('shipment.sellerProfile', function ($spq) use ($search) {
                    $spq->where('business_name', 'like', "%{$search}%")
                        ->orWhere('municipality', 'like', "%{$search}%");
                });
            });
        }

        if ($fromDate) {
            $start = Carbon::parse($fromDate)->startOfDay();
            $query->where(function ($q) use ($start) {
                $q->where('updated_at', '>=', $start)
                    ->orWhere('assigned_at', '>=', $start);
            });
        }

        if ($toDate) {
            $end = Carbon::parse($toDate)->endOfDay();
            $query->where(function ($q) use ($end) {
                $q->where('updated_at', '<=', $end)
                    ->orWhere('assigned_at', '<=', $end);
            });
        }

        if ($filter === 'assigned') {
            $assignments = $query->where('status', 'assigned')
                ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
                ->orderBy('id', 'asc')
                ->limit(100)
                ->get();
        } elseif ($filter === 'accepted') {
            $assignments = $query->where('status', 'accepted')
                ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
                ->orderBy('id', 'asc')
                ->limit(100)
                ->get();
        } elseif (in_array($filter, ['en_route_to_hub', 'in_transit'], true)) {
            $assignments = $query->whereIn('status', ['picked_up', 'in_transit_to_hub'])
                ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
                ->orderBy('id', 'asc')
                ->limit(100)
                ->get();
        } elseif (in_array($filter, ['history', 'completed'], true)) {
            $assignments = $query->whereIn('status', ['completed', 'in_transit_to_hub', 'declined', 'reassigned', 'cancelled'])
                ->orderByRaw('COALESCE(handed_over_at, updated_at) DESC')
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        } else {
            $activeItems = (clone $query)
                ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
                ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
                ->orderBy('id', 'asc')
                ->get();

            $completedItems = (clone $query)
                ->whereIn('status', ['in_transit_to_hub', 'completed'])
                ->orderByRaw('COALESCE(handed_over_at, updated_at) DESC')
                ->orderByDesc('id')
                ->limit(25)
                ->get();

            $assignments = $activeItems->concat($completedItems);
        }

        $countsBase = PickupAssignment::where('courier_id', $courier->id)
            ->where('logistics_profile_id', $courier->logistics_profile_id)
            ->whereHas('shipment', function ($q) {
                $q->where('status', '!=', 'awaiting_seller_drop_off');
            });

        $pendingReturnDeliveries = Schema::hasTable('return_shipments')
            ? ReturnShipment::where('current_courier_id', $courier->id)
                ->whereIn('status', [
                    ReturnShipment::STATUS_COURIER_ASSIGNED,
                    ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
                ])
                ->count()
            : 0;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'filter'      => $filter,
                'counts'      => [
                    'active'          => (clone $countsBase)->whereIn('status', ['assigned', 'accepted', 'picked_up'])->count(),
                    'assigned'        => (clone $countsBase)->where('status', 'assigned')->count(),
                    'accepted'        => (clone $countsBase)->where('status', 'accepted')->count(),
                    'en_route_to_hub' => (clone $countsBase)->whereIn('status', ['picked_up', 'in_transit_to_hub'])->count(),
                    'history'         => (clone $countsBase)->whereIn('status', ['completed', 'in_transit_to_hub', 'declined', 'reassigned'])->count(),
                    'returns'         => $pendingReturnDeliveries,
                ],
                'assignments' => $assignments
                    ->map(fn (PickupAssignment $a) => $this->formatAssignmentPayload($a, false))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.logisticsHub',
            'shipment.packages.verifications',
            'shipment.trackingLogs',
            'sellerOrder.order',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.payment',
            'sellerOrder.extra',
        ])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: You do not have permission to view this pickup assignment.',
            ], 403);
        }

        if ($assignment->shipment && $assignment->shipment->packages->isEmpty() && $assignment->sellerOrder) {
            $this->shipmentVerificationService->ensurePackagesForShipment(
                shipment: $assignment->shipment,
                sellerOrder: $assignment->sellerOrder
            );
            $assignment->load('shipment.packages.logisticsHub');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatAssignmentPayload($assignment, true),
        ]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        if (strtolower((string) $courier->status) === 'suspended') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your Courier account is suspended and cannot accept pickup assignments.',
            ], 403);
        }

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with(['shipment.sellerOrder', 'shipment.packages'])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: This pickup assignment belongs to another Courier or Logistics company.',
            ], 403);
        }

        return DB::transaction(function () use ($assignment, $courier) {
            /** @var PickupAssignment $locked */
            $locked = PickupAssignment::with(['shipment.sellerOrder', 'shipment.packages'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shipment = $locked->shipment;

            if (! $shipment || (int) $shipment->current_courier_id !== (int) $courier->id || $locked->status === 'reassigned') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This pickup task has already been reassigned to another Courier by Logistics.',
                ], 409);
            }

            if ($locked->status === 'accepted') {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Pickup assignment is already accepted.',
                    'data'    => $this->formatAssignmentPayload($locked->fresh(['shipment.sellerProfile', 'shipment.logisticsProfile', 'shipment.packages.logisticsHub', 'sellerOrder.items']), true),
                ]);
            }

            if ($locked->status !== 'assigned') {
                return response()->json([
                    'status'  => 'error',
                    'message' => "This pickup assignment cannot be accepted because its status is \"{$locked->status}\".",
                ], 422);
            }

            $locked->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            $courier->forceFill([
                'status'       => 'on_delivery',
                'is_online'    => true,
                'last_seen_at' => now(),
            ])->save();

            ShipmentTracking::create([
                'shipment_id'     => $locked->shipment_id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'courier_assigned',
                'title'           => 'First-Mile Courier En Route to Seller',
                'description'     => "Courier {$courier->full_name} accepted the pickup assignment and is traveling to the Seller's pickup address.",
                'location'        => $courier->assigned_hub?->hub_name ?? $courier->home_hub,
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            $fresh = $locked->fresh([
                'shipment.sellerProfile',
                'shipment.logisticsProfile',
                'shipment.packages.logisticsHub',
                'shipment.trackingLogs',
                'sellerOrder.items',
                'sellerOrder.extra',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pickup assignment accepted! Navigate to the Seller and scan the package QR code or verify the tracking number.',
                'data'    => $this->formatAssignmentPayload($fresh, true),
            ]);
        });
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with(['shipment.logisticsProfile.user'])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: You cannot decline another Courier\'s pickup assignment.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'notes'  => ['nullable', 'string', 'max:300'],
        ], [
            'reason.required' => 'Please select or enter a reason for declining this pickup assignment.',
            'reason.min'      => 'Decline reason must be at least 3 characters.',
        ]);

        $fullReason = trim($validated['reason'] . (! empty($validated['notes']) ? ' - ' . trim($validated['notes']) : ''));

        return DB::transaction(function () use ($assignment, $courier, $fullReason) {
            /** @var PickupAssignment $locked */
            $locked = PickupAssignment::with(['shipment.sellerOrder', 'shipment.logisticsProfile.user'])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'assigned') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Only unaccepted pickup assignments in Assigned status can be declined.',
                ], 422);
            }

            $shipment = $locked->shipment;

            $updateData = [
                'status' => 'declined',
                'notes'  => trim(($locked->notes ? $locked->notes . ' | ' : '') . "Declined by Courier {$courier->full_name}: {$fullReason}"),
            ];

            if (Schema::hasColumn('pickup_assignments', 'declined_at')) {
                $updateData['declined_at'] = now();
                $updateData['decline_reason'] = substr($fullReason, 0, 300);
            }

            $locked->update($updateData);

            if ($shipment && (int) $shipment->current_courier_id === (int) $courier->id) {
                $shipment->update([
                    'current_courier_id' => null,
                    'status'             => 'pickup_requested',
                ]);

                $shipment->sellerOrder?->update([
                    'status' => 'ready_for_pickup',
                ]);

                ShipmentTracking::create([
                    'shipment_id'     => $shipment->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'pickup_requested',
                    'title'           => 'Pickup Assignment Declined — Returned to Unassigned Queue',
                    'description'     => "Courier {$courier->full_name} declined the pickup offer (Reason: {$fullReason}). Shipment returned to Logistics Unassigned queue.",
                    'location'        => $courier->assigned_hub?->hub_name ?? $courier->home_hub,
                    'actor_type'      => 'Courier',
                    'actor_id'        => $courier->id,
                    'occurred_at'     => now(),
                ]);
            }

            $remainingActive = PickupAssignment::where('courier_id', $courier->id)
                ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])
                ->exists();

            if (! $remainingActive && $courier->status === 'on_delivery') {
                $courier->update(['status' => 'active']);
            }

            if ($shipment?->logisticsProfile?->user) {
                try {
                    $shipment->logisticsProfile->user->notify(new OrderLifecycleNotification(
                        title: "Pickup Declined by Courier ({$shipment->tracking_number})",
                        message: "Courier {$courier->full_name} declined Shipment #{$shipment->tracking_number}. Reason: {$fullReason}. Please reassign.",
                        type: 'warning',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $shipment->order_id,
                        sellerOrderId: $shipment->seller_order_id,
                        shipmentId: $shipment->id
                    ));
                } catch (\Throwable $e) {
                }
            }

            AuditLoggerService::log(
                action: 'courier_pickup_declined',
                targetType: 'PickupAssignment',
                targetId: (int) $locked->id,
                targetReference: $shipment?->tracking_number ?? "PKP-#{$locked->id}",
                oldValues: ['status' => 'assigned', 'courier_id' => $courier->id],
                newValues: ['status' => 'declined', 'current_courier_id' => null, 'pay' => 0.00],
                reason: $fullReason,
                actorType: 'Courier',
                actorId: (int) $courier->id,
                actorName: $courier->full_name
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Pickup assignment declined and returned to Logistics for reassignment (Pay: ₱0.00).',
                'data'    => [
                    'id'     => $locked->id,
                    'status' => 'declined',
                    'reason' => $fullReason,
                    'pay'    => 0.00,
                ],
            ]);
        });
    }

    public function confirmCollection(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with([
            'shipment.packages',
            'shipment.sellerOrder.order',
            'shipment.sellerOrder.buyer',
            'shipment.sellerOrder.seller',
            'shipment.logisticsProfile.user',
        ])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: You are not assigned to this pickup task.',
            ], 403);
        }

        $validated = $request->validate([
            'seal_number' => ['nullable', 'string', 'max:30'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'notes'       => ['nullable', 'string', 'max:300'],
        ]);

        if (! empty($validated['seal_number'])) {
            $cleanSeal = strtoupper(trim($validated['seal_number']));
            if (! preg_match('/^BH-\d{7}$/', $cleanSeal)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Invalid seal number format. Seal number must be BH- followed by exactly 7 digits (e.g., BH-0004521).',
                    'errors'  => [
                        'seal_number' => ['Seal number must follow the format BH- followed by 7 digits (e.g., BH-0004521).'],
                    ],
                ], 422);
            }
            $validated['seal_number'] = $cleanSeal;
        }

        return DB::transaction(function () use ($assignment, $courier, $validated) {
            /** @var PickupAssignment $locked */
            $locked = PickupAssignment::with([
                'shipment.packages',
                'shipment.sellerOrder.order',
                'shipment.sellerOrder.buyer',
                'shipment.sellerOrder.seller',
                'shipment.logisticsProfile.user',
            ])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, ['picked_up', 'in_transit_to_hub', 'completed'], true)) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Package collection has already been confirmed.',
                    'data'    => $this->formatAssignmentPayload($locked->fresh(['shipment.sellerProfile', 'shipment.logisticsProfile', 'shipment.packages.logisticsHub', 'sellerOrder.items']), true),
                ]);
            }

            if ($locked->status === 'assigned') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You must first accept the pickup assignment before confirming package collection.',
                ], 422);
            }

            if ($locked->status !== 'accepted') {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Cannot confirm collection because assignment status is \"{$locked->status}\".",
                ], 422);
            }

            $shipment = $locked->shipment;
            if (! $shipment) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Shipment record not found for this assignment.',
                ], 404);
            }

            if ($shipment->packages->isEmpty() && $shipment->sellerOrder) {
                $this->shipmentVerificationService->ensurePackagesForShipment($shipment, $shipment->sellerOrder);
                $shipment->load('packages');
            }

            $unverifiedPackages = $shipment->packages->filter(fn (ShipmentPackage $pkg) => $pkg->courier_verified_at === null);
            if ($unverifiedPackages->isNotEmpty()) {
                $missingRefs = $unverifiedPackages->pluck('package_reference')->implode(', ');

                return response()->json([
                    'status'              => 'error',
                    'message'             => "All packages must be scanned or verified before confirming collection. Unverified package(s): {$missingRefs}.",
                    'unverified_packages' => $unverifiedPackages->pluck('package_reference')->values()->all(),
                ], 422);
            }

            $sellerOrder = $shipment->sellerOrder;
            $oldOrderStatus = $sellerOrder?->status ?? 'pickup_assigned';

            $updatePayload = [
                'status'       => 'picked_up',
                'accepted_at'  => $locked->accepted_at ?? now(),
                'picked_up_at' => now(),
            ];

            if (! empty($validated['seal_number']) && Schema::hasColumn('pickup_assignments', 'seal_number')) {
                $updatePayload['seal_number'] = $validated['seal_number'];
            }

            $locked->update($updatePayload);

            $shipment->update([
                'status'       => 'picked_up_from_seller',
                'picked_up_at' => now(),
            ]);

            if ($sellerOrder) {
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

                if ($sellerOrder->order) {
                    $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
                }
            }

            $sealSuffix = ! empty($validated['seal_number']) ? " Seal #{$validated['seal_number']}." : '';

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $shipment->seller_order_id,
                'status'          => 'picked_up_from_seller',
                'title'           => 'Picked Up from Seller',
                'description'     => "First-Mile Courier verified and collected the package from " . ($shipment->pickup_address_snapshot['business_name'] ?? 'Seller') . " and is transporting it to " . ($shipment->hub_address_snapshot['hub_name'] ?? 'Sorting Hub') . ".{$sealSuffix}",
                'location'        => ($shipment->pickup_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->pickup_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            if ($sellerOrder?->buyer) {
                try {
                    $sellerOrder->buyer->notify(new OrderLifecycleNotification(
                        title: 'Package Picked Up from Seller',
                        message: "Order #{$sellerOrder->seller_order_number} (Tracking #{$shipment->tracking_number}) has been picked up from the seller and is heading to the sorting hub.",
                        type: 'info',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ));
                } catch (\Throwable $e) {
                }
            }

            $fresh = $locked->fresh([
                'shipment.sellerProfile',
                'shipment.logisticsProfile',
                'shipment.packages.logisticsHub',
                'shipment.trackingLogs',
                'sellerOrder.items',
                'sellerOrder.extra',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Package collection confirmed! Proceed to transport the parcel to the assigned Logistics Sorting Hub.',
                'data'    => $this->formatAssignmentPayload($fresh, true),
            ]);
        });
    }

    public function handoverToHub(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with([
            'shipment.sellerOrder.payment',
            'shipment.sellerOrder.extra',
            'shipment.logisticsProfile.user',
            'shipment.packages.logisticsHub',
        ])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: You are not assigned to this pickup task.',
            ], 403);
        }

        $validated = $request->validate([
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'enforce_proximity' => ['nullable', 'boolean'],
            'notes'             => ['nullable', 'string', 'max:300'],
        ]);

        if ($request->boolean('enforce_proximity') && isset($validated['latitude'], $validated['longitude'])) {
            $shipment = $assignment->shipment;
            $hubModel = $shipment?->packages->first()?->logisticsHub ?? $courier->assigned_hub;
            $hubCoords = CourierMobileSchemaService::resolveCoordinatesForAddress(
                $shipment?->hub_address_snapshot ?? [],
                $hubModel
            );

            $distanceMeters = CourierMobileSchemaService::calculateDistanceMeters(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $hubCoords['latitude'],
                (float) $hubCoords['longitude']
            );

            if ($distanceMeters > 500.0) {
                $hubName = $hubModel?->hub_name ?? ($shipment?->hub_address_snapshot['hub_name'] ?? 'the assigned Sorting Hub');

                return response()->json([
                    'status'          => 'error',
                    'message'         => "You must be within 500 meters of {$hubName} to mark Arrived at Hub (Current distance: " . number_format($distanceMeters / 1000, 2) . ' km).',
                    'distance_meters' => $distanceMeters,
                ], 422);
            }
        }

        return DB::transaction(function () use ($assignment, $courier) {
            /** @var PickupAssignment $locked */
            $locked = PickupAssignment::with([
                'shipment.sellerOrder.payment',
                'shipment.sellerOrder.extra',
                'shipment.logisticsProfile.user',
                'shipment.packages.logisticsHub',
            ])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, ['in_transit_to_hub', 'completed'], true)) {
                $payInfo = WebPickupController::calculateFirstMileCourierPay($locked->shipment);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Arrival and handover at Sorting Hub is already recorded.',
                    'data'    => $this->formatAssignmentPayload($locked->fresh(['shipment.sellerProfile', 'shipment.logisticsProfile', 'shipment.packages.logisticsHub', 'sellerOrder.items']), true),
                    'pay'     => $payInfo,
                ]);
            }

            if ($locked->status !== 'picked_up') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You must first verify and confirm package collection from the Seller before handing over at the Sorting Hub.',
                ], 422);
            }

            $shipment = $locked->shipment;
            $sellerOrder = $shipment->sellerOrder;
            $payInfo = WebPickupController::calculateFirstMileCourierPay($shipment);
            $isCod = strtoupper((string) ($sellerOrder?->effective_payment_method ?? 'COD')) === 'COD';

            $locked->update([
                'status'         => 'in_transit_to_hub',
                'handed_over_at' => now(),
            ]);

            $shipment->update([
                'status' => 'in_transit_to_hub',
            ]);

            $hubName = $shipment->packages->first()?->logisticsHub?->hub_name
                ?? ($shipment->hub_address_snapshot['hub_name'] ?? ($shipment->hub_address_snapshot['business_name'] ?? 'Sorting Hub'));

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'in_transit_to_hub',
                'title'           => 'Arrived at Sorting Facility — Pending Hub Verification',
                'description'     => "Courier {$courier->full_name} arrived and handed over the package at {$hubName} for hub staff verification.",
                'location'        => ($shipment->hub_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->hub_address_snapshot['province'] ?? ''),
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            $this->roleWalletService->recordFirstMileCourierTaskCredit($shipment, $courier, $payInfo);

            if ($shipment->logisticsProfile?->user) {
                try {
                    $shipment->logisticsProfile->user->notify(new OrderLifecycleNotification(
                        title: 'Courier Handover Ready for Hub Verification',
                        message: "Courier {$courier->full_name} delivered Shipment #{$shipment->tracking_number} to {$hubName}. Please scan and verify physical receipt.",
                        type: 'info',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ));
                } catch (\Throwable $e) {
                }
            }

            $fresh = $locked->fresh([
                'shipment.sellerProfile',
                'shipment.logisticsProfile',
                'shipment.packages.logisticsHub',
                'shipment.trackingLogs',
                'sellerOrder.items',
                'sellerOrder.extra',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => $isCod
                    ? "Handover recorded at {$hubName}! ₱" . number_format((float) $payInfo['total_pay'], 2) . " First-Mile Pickup Task Pay (25% of Shipping/Ops Fee) credited to your Pending Escrow and will release to Available E-Wallet upon Admin COD Remittance verification."
                    : "Handover recorded at {$hubName}! ₱" . number_format((float) $payInfo['total_pay'], 2) . " First-Mile Pickup Task Pay (25% of Shipping/Ops Fee) credited to your Courier E-Wallet.",
                'data'    => $this->formatAssignmentPayload($fresh, true),
            ]);
        });
    }

    public function reportDelay(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with(['shipment.logisticsProfile.user'])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: You cannot report a delay on another Courier\'s assignment.',
            ], 403);
        }

        if (! in_array($assignment->status, ['accepted', 'picked_up', 'in_transit_to_hub'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Delay reports can only be submitted for active accepted or in-transit pickups.',
            ], 422);
        }

        $validated = $request->validate([
            'reason'               => ['required', 'string', 'in:Heavy traffic,Vehicle breakdown,Weather,Road closure,Other'],
            'estimated_arrival_at' => ['required', 'date'],
            'notes'                => ['nullable', 'string', 'max:300'],
            'latitude'             => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'            => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'reason.required'               => 'Please select a delay reason (Heavy traffic, Vehicle breakdown, Weather, Road closure, or Other).',
            'reason.in'                     => 'Please choose a valid delay reason from the list.',
            'estimated_arrival_at.required' => 'Please specify the new estimated arrival time (ETA).',
        ]);

        $eta = Carbon::parse($validated['estimated_arrival_at']);
        if ($eta->lte(now())) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The new estimated arrival time (ETA) must be later than the current time.',
                'errors'  => [
                    'estimated_arrival_at' => ['New ETA must be in the future (later than the current time).'],
                ],
            ], 422);
        }

        $shipment = $assignment->shipment;
        $notes = trim((string) ($validated['notes'] ?? ''));
        $reasonLabel = $validated['reason'] . ($notes !== '' ? " ({$notes})" : '');

        DB::transaction(function () use ($assignment, $shipment, $courier, $validated, $eta, $notes, $reasonLabel) {
            if (Schema::hasColumn('pickup_assignments', 'is_delayed')) {
                $assignment->update([
                    'is_delayed'           => true,
                    'delay_reason'         => substr($reasonLabel, 0, 300),
                    'estimated_arrival_at' => $eta,
                ]);
            }

            if (Schema::hasTable('courier_trip_incidents')) {
                DB::table('courier_trip_incidents')->insert([
                    'pickup_assignment_id' => $assignment->id,
                    'shipment_id'          => $shipment->id,
                    'courier_id'           => $courier->id,
                    'logistics_profile_id' => $courier->logistics_profile_id,
                    'incident_type'        => 'delay',
                    'reason'               => $validated['reason'],
                    'notes'                => $notes !== '' ? $notes : null,
                    'estimated_arrival_at' => $eta,
                    'latitude'             => isset($validated['latitude']) ? round((float) $validated['latitude'], 7) : null,
                    'longitude'            => isset($validated['longitude']) ? round((float) $validated['longitude'], 7) : null,
                    'status'               => 'reported',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $assignment->seller_order_id,
                'status'          => $shipment->status,
                'title'           => "Transit Delay Reported ({$validated['reason']})",
                'description'     => "Courier {$courier->full_name} reported a delay: {$reasonLabel}. Updated ETA: " . $eta->timezone('Asia/Manila')->format('M d, Y h:i A') . '.',
                'location'        => $courier->assigned_municipality,
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);
        });

        if ($shipment?->logisticsProfile?->user) {
            try {
                $shipment->logisticsProfile->user->notify(new OrderLifecycleNotification(
                    title: "Courier Delay Reported — Shipment #{$shipment->tracking_number}",
                    message: "Courier {$courier->full_name} reported a delay ({$reasonLabel}). New ETA: " . $eta->timezone('Asia/Manila')->format('M d, Y h:i A') . '.',
                    type: 'warning',
                    url: route('logistics.shipments.show', $shipment->id),
                    orderId: $shipment->order_id,
                    sellerOrderId: $shipment->seller_order_id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Delay report submitted and Logistics has been notified with your updated ETA.',
            'data'    => $this->formatAssignmentPayload($assignment->fresh(['shipment.sellerProfile', 'shipment.logisticsProfile', 'shipment.packages.logisticsHub', 'sellerOrder.items']), true),
        ]);
    }

    public function reportBreakdown(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with(['shipment.logisticsProfile.user'])->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: You cannot report a breakdown on another Courier\'s assignment.',
            ], 403);
        }

        $validated = $request->validate([
            'notes'          => ['required', 'string', 'min:10', 'max:300'],
            'incident_photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'notes.required'          => 'Please provide a description of the vehicle breakdown (10 to 300 characters).',
            'notes.min'               => 'Breakdown description must be at least 10 characters.',
            'incident_photo.required' => 'A photo of the vehicle breakdown / incident (JPG or PNG, max 5 MB) is required.',
        ]);

        $photoPath = $request->file('incident_photo')->store('courier_incidents', 'public');
        $shipment = $assignment->shipment;

        DB::transaction(function () use ($assignment, $shipment, $courier, $validated, $photoPath) {
            if (Schema::hasColumn('pickup_assignments', 'is_delayed')) {
                $assignment->update([
                    'is_delayed'   => true,
                    'delay_reason' => 'Vehicle breakdown: ' . trim($validated['notes']),
                ]);
            }

            if (Schema::hasTable('courier_trip_incidents')) {
                DB::table('courier_trip_incidents')->insert([
                    'pickup_assignment_id' => $assignment->id,
                    'shipment_id'          => $shipment->id,
                    'courier_id'           => $courier->id,
                    'logistics_profile_id' => $courier->logistics_profile_id,
                    'incident_type'        => 'vehicle_breakdown',
                    'reason'               => 'Vehicle breakdown',
                    'notes'                => trim($validated['notes']),
                    'photo_path'           => $photoPath,
                    'latitude'             => isset($validated['latitude']) ? round((float) $validated['latitude'], 7) : null,
                    'longitude'            => isset($validated['longitude']) ? round((float) $validated['longitude'], 7) : null,
                    'status'               => 'awaiting_reassignment',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $assignment->seller_order_id,
                'status'          => $shipment->status,
                'title'           => 'Vehicle Breakdown Reported — Awaiting Logistics Reassignment',
                'description'     => "Courier {$courier->full_name} reported a vehicle breakdown with photo evidence: " . trim($validated['notes']),
                'location'        => $courier->assigned_municipality,
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            AuditLoggerService::log(
                action: 'courier_vehicle_breakdown_reported',
                targetType: 'PickupAssignment',
                targetId: (int) $assignment->id,
                targetReference: $shipment->tracking_number,
                oldValues: ['status' => $assignment->status],
                newValues: ['incident_type' => 'vehicle_breakdown', 'photo_path' => $photoPath],
                reason: trim($validated['notes']),
                actorType: 'Courier',
                actorId: (int) $courier->id,
                actorName: $courier->full_name
            );
        });

        if ($shipment?->logisticsProfile?->user) {
            try {
                $shipment->logisticsProfile->user->notify(new OrderLifecycleNotification(
                    title: "URGENT: Courier Vehicle Breakdown (#{$shipment->tracking_number})",
                    message: "Courier {$courier->full_name} ({$courier->plate_number}) reported a vehicle breakdown: \"{$validated['notes']}\". Please reassign the shipment.",
                    type: 'danger',
                    url: route('logistics.shipments.show', $shipment->id),
                    orderId: $shipment->order_id,
                    sellerOrderId: $shipment->seller_order_id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Vehicle breakdown report and photo submitted to Logistics for immediate reassignment.',
            'data'    => $this->formatAssignmentPayload($assignment->fresh(['shipment.sellerProfile', 'shipment.logisticsProfile', 'shipment.packages.logisticsHub', 'sellerOrder.items']), true),
        ]);
    }

    public function submitDiscrepancyNote(Request $request, int $id): JsonResponse
    {
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        /** @var PickupAssignment|null $assignment */
        $assignment = PickupAssignment::with('shipment')->find($id);

        if (
            ! $assignment
            || (int) $assignment->courier_id !== (int) $courier->id
            || (int) $assignment->logistics_profile_id !== (int) $courier->logistics_profile_id
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'discrepancy_notes' => ['required', 'string', 'min:10', 'max:300'],
        ], [
            'discrepancy_notes.required' => 'Please enter a discrepancy note between 10 and 300 characters.',
            'discrepancy_notes.min'      => 'Discrepancy note must be at least 10 characters.',
        ]);

        $assignment->update([
            'has_discrepancy'   => true,
            'discrepancy_notes' => trim($validated['discrepancy_notes']),
        ]);

        AuditLoggerService::log(
            action: 'courier_discrepancy_note_submitted',
            targetType: 'PickupAssignment',
            targetId: (int) $assignment->id,
            targetReference: $assignment->shipment?->tracking_number ?? "PKP-#{$assignment->id}",
            oldValues: null,
            newValues: ['discrepancy_notes' => trim($validated['discrepancy_notes'])],
            reason: trim($validated['discrepancy_notes']),
            actorType: 'Courier',
            actorId: (int) $courier->id,
            actorName: $courier->full_name
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Discrepancy note saved and recorded in the audit log.',
            'data'    => $this->formatAssignmentPayload($assignment->fresh(['shipment.sellerProfile', 'shipment.logisticsProfile', 'shipment.packages.logisticsHub', 'sellerOrder.items']), true),
        ]);
    }

    public function formatAssignmentPayload(PickupAssignment $assignment, bool $detailed = false): array
    {
        $shipment = $assignment->shipment;
        $sellerOrder = $assignment->sellerOrder ?? $shipment?->sellerOrder;
        $sellerProfile = $shipment?->sellerProfile ?? $sellerOrder?->sellerProfile;
        $logisticsProfile = $shipment?->logisticsProfile ?? $assignment->logisticsProfile;

        $pickupSnap = $shipment?->pickup_address_snapshot ?? ($sellerOrder?->pickup_address_snapshot ?? []);
        $hubSnap = $shipment?->hub_address_snapshot ?? [];
        $deliverySnap = $shipment?->delivery_address_snapshot ?? ($sellerOrder?->delivery_address_snapshot ?? []);

        $packages = $shipment?->packages ?? collect();
        $primaryPackage = $packages->first();
        /** @var LogisticsHub|null $assignedHubModel */
        $assignedHubModel = $primaryPackage?->logisticsHub;

        $sellerCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($pickupSnap, $sellerProfile);
        $hubCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($hubSnap, $assignedHubModel ?? $logisticsProfile);

        $sellerFullAddress = $pickupSnap['full_address']
            ?? $sellerProfile?->full_address
            ?? collect([
                $pickupSnap['house_details'] ?? $sellerProfile?->house_details,
                $pickupSnap['street'] ?? $sellerProfile?->street,
                $pickupSnap['barangay'] ?? $sellerProfile?->barangay,
                $pickupSnap['municipality'] ?? $sellerProfile?->municipality,
                $pickupSnap['province'] ?? $sellerProfile?->province,
            ])->filter()->implode(', ');

        $hubName = $assignedHubModel?->hub_name
            ?? ($hubSnap['hub_name'] ?? ($logisticsProfile?->business_name ?? 'Sorting Hub'));

        $hubFullAddress = $assignedHubModel?->full_address
            ?? ($hubSnap['full_address'] ?? ($logisticsProfile?->full_address ?? ''));

        $payInfo = $shipment
            ? WebPickupController::calculateFirstMileCourierPay($shipment)
            : [
                'base_pay'       => 8.75,
                'distance_tier'  => 'Same Municipality (25% Share)',
                'distance_bonus' => 2.50,
                'weight_bonus'   => 0.00,
                'total_pay'      => 11.25,
            ];

        if (in_array($assignment->status, ['declined', 'reassigned', 'cancelled'], true)) {
            $payInfo = [
                'base_pay'       => 0.00,
                'distance_tier'  => ucfirst($assignment->status),
                'distance_bonus' => 0.00,
                'weight_bonus'   => 0.00,
                'total_pay'      => 0.00,
            ];
        }

        $formattedPackages = $packages->map(function (ShipmentPackage $pkg) {
            return [
                'id'                          => (int) $pkg->id,
                'package_reference'           => (string) $pkg->package_reference,
                'qr_token'                    => (string) $pkg->qr_token,
                'package_number'              => (int) $pkg->package_number,
                'total_packages'              => (int) $pkg->total_packages,
                'handover_method'             => (string) $pkg->handover_method,
                'package_weight'              => (float) $pkg->package_weight,
                'package_weight_formatted'    => number_format((float) $pkg->package_weight, 2) . ' kg',
                'cod_amount'                  => number_format((float) $pkg->cod_amount, 2, '.', ''),
                'total_items_count'           => (int) $pkg->total_items_count,
                'is_verified'                 => $pkg->courier_verified_at !== null,
                'courier_verified_at'         => $pkg->courier_verified_at?->toIso8601String(),
                'courier_verification_method' => $pkg->courier_verification_method,
                'hub_verified_at'             => $pkg->hub_verified_at?->toIso8601String(),
                'manifest'                    => is_array($pkg->manifest_snapshot) ? $pkg->manifest_snapshot : [],
            ];
        })->values()->all();

        $totalPackagesCount = max(1, count($formattedPackages));
        $verifiedPackagesCount = collect($formattedPackages)->where('is_verified', true)->count();
        $allPackagesVerified = count($formattedPackages) > 0 && $verifiedPackagesCount === count($formattedPackages);

        $itemsList = ($sellerOrder?->items ?? collect())->map(function ($item) {
            $variantLabel = implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name]));
            $imgUrl = $item->product_image ? asset('storage/' . ltrim($item->product_image, '/')) : null;

            return [
                'id'           => (int) $item->id,
                'product_id'   => (int) $item->product_id,
                'product_name' => (string) $item->product_name,
                'variant_name' => $variantLabel !== '' ? $variantLabel : 'Standard',
                'quantity'     => (int) $item->quantity,
                'unit_weight'  => (float) $item->unit_weight,
                'line_weight'  => (float) $item->line_weight,
                'unit_price'   => number_format((float) $item->discounted_unit_price, 2, '.', ''),
                'line_total'   => number_format((float) $item->line_total, 2, '.', ''),
                'image_url'    => $imgUrl,
            ];
        })->values()->all();

        $statusLabel = match ($assignment->status) {
            'assigned'          => 'PENDING ACCEPTANCE',
            'accepted'          => $allPackagesVerified ? 'VERIFIED — READY TO COLLECT' : 'ACCEPTED — EN ROUTE TO SELLER',
            'picked_up'         => 'PICKED UP — IN TRANSIT TO HUB',
            'in_transit_to_hub' => 'ARRIVED AT HUB — PENDING RECEIPT',
            'completed'         => $assignment->has_discrepancy ? 'COMPLETED WITH DISCREPANCY' : 'COMPLETED (RECEIVED AT HUB)',
            'declined'          => 'DECLINED',
            'reassigned'        => 'REASSIGNED',
            default             => strtoupper(str_replace('_', ' ', (string) $assignment->status)),
        };

        $payload = [
            'id'                      => (int) $assignment->id,
            'assignment_id'           => (int) $assignment->id,
            'shipment_id'             => (int) ($shipment?->id ?? $assignment->shipment_id),
            'seller_order_id'         => (int) ($sellerOrder?->id ?? $assignment->seller_order_id),
            'tracking_number'         => (string) ($shipment?->tracking_number ?? 'TRK-N/A'),
            'tracking_code'           => (string) ($shipment?->tracking_number ?? 'TRK-N/A'),
            'seller_order_number'     => (string) ($sellerOrder?->seller_order_number ?? 'SO-N/A'),
            'status'                  => (string) $assignment->status,
            'status_label'            => $statusLabel,
            'shipment_status'         => (string) ($shipment?->status ?? ''),
            'shipment_status_label'   => (string) ($shipment?->status_label ?? ''),
            'total_weight'            => round((float) ($shipment?->total_weight ?? $sellerOrder?->total_weight ?? 0), 3),
            'total_weight_formatted'  => number_format((float) ($shipment?->total_weight ?? $sellerOrder?->total_weight ?? 0), 2) . ' kg',
            'cod_amount'              => number_format((float) ($shipment?->cod_amount ?? 0), 2, '.', ''),
            'payment_method'          => $sellerOrder?->effective_payment_method ?? 'COD',
            'verified_packages_count' => $verifiedPackagesCount,
            'total_packages_count'    => $totalPackagesCount,
            'all_packages_verified'   => $allPackagesVerified,
            'seal_number'             => $assignment->seal_number ?? null,
            'is_delayed'              => (bool) ($assignment->is_delayed ?? false),
            'delay_reason'            => $assignment->delay_reason ?? null,
            'estimated_arrival_at'    => $assignment->estimated_arrival_at
                ? Carbon::parse($assignment->estimated_arrival_at)->toIso8601String()
                : null,
            'has_discrepancy'         => (bool) ($assignment->has_discrepancy ?? false),
            'discrepancy_notes'       => $assignment->discrepancy_notes ?? null,
            'decline_reason'          => $assignment->decline_reason ?? null,
            'notes'                   => $assignment->notes,
            'assigned_at'             => $assignment->assigned_at?->toIso8601String(),
            'accepted_at'             => $assignment->accepted_at?->toIso8601String(),
            'picked_up_at'            => $assignment->picked_up_at?->toIso8601String(),
            'handed_over_at'          => $assignment->handed_over_at?->toIso8601String(),
            'hub_verified_at'         => $assignment->hub_verified_at?->toIso8601String(),
            'seller'                  => [
                'id'             => (int) ($sellerProfile?->id ?? 0),
                'business_name'  => (string) ($sellerProfile?->business_name ?? ($pickupSnap['business_name'] ?? 'Seller Store')),
                'contact_no'     => (string) ($pickupSnap['contact_no'] ?? ($sellerProfile?->contact_no ?? '')),
                'province'       => (string) ($pickupSnap['province'] ?? ($sellerProfile?->province ?? '')),
                'municipality'   => (string) ($pickupSnap['municipality'] ?? ($sellerProfile?->municipality ?? '')),
                'barangay'       => (string) ($pickupSnap['barangay'] ?? ($sellerProfile?->barangay ?? '')),
                'street'         => (string) ($pickupSnap['street'] ?? ($sellerProfile?->street ?? '')),
                'house_details'  => (string) ($pickupSnap['house_details'] ?? ($sellerProfile?->house_details ?? '')),
                'full_address'   => $sellerFullAddress ?: 'Seller Pickup Facility',
                'latitude'       => $sellerCoords['latitude'],
                'longitude'      => $sellerCoords['longitude'],
                'is_approximate' => $sellerCoords['is_approximate'],
            ],
            'destination_hub'         => [
                'hub_id'         => (int) ($assignedHubModel?->id ?? ($hubSnap['logistics_hub_id'] ?? 0)),
                'hub_code'       => (string) ($assignedHubModel?->hub_code ?? ($hubSnap['hub_code'] ?? 'HUB-MAIN')),
                'hub_name'       => $hubName,
                'company_name'   => (string) ($logisticsProfile?->business_name ?? ($hubSnap['business_name'] ?? 'Storkia Logistics')),
                'is_main_hub'    => (bool) ($assignedHubModel?->is_main_hub ?? ($hubSnap['is_main_hub'] ?? true)),
                'contact_no'     => (string) ($assignedHubModel?->contact_no ?? ($hubSnap['contact_no'] ?? ($logisticsProfile?->contact_no ?? ''))),
                'province'       => (string) ($assignedHubModel?->province ?? ($hubSnap['province'] ?? ($logisticsProfile?->province ?? ''))),
                'municipality'   => (string) ($assignedHubModel?->municipality ?? ($hubSnap['municipality'] ?? ($logisticsProfile?->municipality ?? ''))),
                'barangay'       => (string) ($assignedHubModel?->barangay ?? ($hubSnap['barangay'] ?? ($logisticsProfile?->barangay ?? ''))),
                'full_address'   => $hubFullAddress ?: $hubName,
                'latitude'       => $hubCoords['latitude'],
                'longitude'      => $hubCoords['longitude'],
                'is_approximate' => $hubCoords['is_approximate'],
            ],
            'recipient_summary'       => [
                'municipality' => (string) ($deliverySnap['municipality'] ?? ''),
                'province'     => (string) ($deliverySnap['province'] ?? ''),
                'barangay'     => (string) ($deliverySnap['barangay'] ?? ''),
            ],
            'courier_pay'             => [
                'base_pay'       => number_format((float) $payInfo['base_pay'], 2, '.', ''),
                'distance_tier'  => (string) $payInfo['distance_tier'],
                'distance_bonus' => number_format((float) $payInfo['distance_bonus'], 2, '.', ''),
                'weight_bonus'   => number_format((float) $payInfo['weight_bonus'], 2, '.', ''),
                'total_pay'      => number_format((float) $payInfo['total_pay'], 2, '.', ''),
                'total_pay_raw'  => round((float) $payInfo['total_pay'], 2),
            ],
            'packages'                => $formattedPackages,
            'items'                   => $itemsList,
        ];

        if ($detailed && $shipment) {
            $payload['tracking_logs'] = ($shipment->trackingLogs ?? collect())->map(function (ShipmentTracking $log) {
                return [
                    'id'          => (int) $log->id,
                    'status'      => (string) $log->status,
                    'title'       => (string) $log->title,
                    'description' => (string) ($log->description ?? ''),
                    'location'    => (string) ($log->location ?? ''),
                    'actor_type'  => (string) $log->actor_type,
                    'occurred_at' => $log->occurred_at?->toIso8601String(),
                ];
            })->values()->all();

            if (Schema::hasTable('courier_trip_incidents')) {
                $payload['incidents'] = DB::table('courier_trip_incidents')
                    ->where('pickup_assignment_id', $assignment->id)
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn ($inc) => [
                        'id'                   => (int) $inc->id,
                        'incident_type'        => (string) $inc->incident_type,
                        'reason'               => (string) $inc->reason,
                        'notes'                => $inc->notes,
                        'estimated_arrival_at' => $inc->estimated_arrival_at,
                        'photo_url'            => $inc->photo_path ? asset('storage/' . $inc->photo_path) : null,
                        'status'               => (string) $inc->status,
                        'created_at'           => $inc->created_at,
                    ])
                    ->values()
                    ->all();
            }
        }

        return $payload;
    }
}