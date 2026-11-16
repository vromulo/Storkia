<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\PickupController as WebPickupController;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Models\User;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AddressService;
use App\Services\BuyerAbandonmentService;
use App\Services\BuyerOrderNotificationService;
use App\Services\BuyerRewardService;
use App\Services\CourierMobileSchemaService;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\RealtimeBroadcastService;
use App\Services\RoleWalletService;
use App\Services\ShipmentVerificationService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected OrderCompletionService $orderCompletionService,
        protected ShipmentVerificationService $shipmentVerificationService,
        protected AddressService $addressService,
        protected BuyerAbandonmentService $buyerAbandonmentService,
        protected VoucherService $voucherService,
        protected BuyerOrderNotificationService $buyerNotifications,
        protected RoleWalletService $roleWalletService,
        protected ?BuyerRewardService $buyerRewardService = null
    ) {
        $this->buyerRewardService = $buyerRewardService ?? app(BuyerRewardService::class);
    }

    protected function resolveLogisticsProfile(?User $user): ?LogisticsProfile
    {
        if (! $user) {
            return null;
        }

        if ($user->logisticsProfile) {
            return $user->logisticsProfile;
        }

        if (method_exists($user, 'isBranchLogisticsAccount') && $user->isBranchLogisticsAccount()) {
            return $user->branchLogisticsHub?->parentLogisticsProfile;
        }

        return null;
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile) {
            abort(403, 'Your logistics hub must be approved to manage shipments.');
        }

        $profile->ensureMainHubExists();
        $this->orderCompletionService->runLifecycleMaintenance(logisticsProfileId: (int) $profile->id);
        $this->backfillShipmentDistances((int) $profile->id);

        $activeBranchHub = $user->isBranchLogisticsAccount() ? $user->branchLogisticsHub : null;

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $queueSort = Shipment::normalizeQueueSort($request->query('sort'));
        $tierFilter = trim((string) $request->query('tier', ''));
        $municipalityFilter = trim((string) $request->query('municipality', ''));
        $hubFilter = (int) $request->query('hub_id', 0);

        $baseQuery = Shipment::where('logistics_profile_id', $profile->id)
            ->whereHas('sellerOrder', fn ($q) => $q->where('logistics_profile_id', $profile->id));

        if ($activeBranchHub) {
            $baseQuery->where(function ($q) use ($activeBranchHub) {
                $q->whereHas('packages', fn ($pq) => $pq->where('logistics_hub_id', $activeBranchHub->id))
                  ->orWhere('hub_address_snapshot->logistics_hub_id', $activeBranchHub->id)
                  ->when(Schema::hasColumn('shipments', 'destination_logistics_hub_id'), function ($dq) use ($activeBranchHub) {
                      $dq->orWhere('destination_logistics_hub_id', $activeBranchHub->id);
                  });
            });
        }

        $counts = [
            'all'              => (clone $baseQuery)->count(),
            'to_pickup'        => (clone $baseQuery)->whereIn('status', ['pickup_requested', 'awaiting_seller_drop_off', 'courier_assigned'])->count(),
            'in_transit_hub'   => (clone $baseQuery)->whereIn('status', ['picked_up_from_seller', 'in_transit_to_hub'])->count(),
            'at_hub'           => (clone $baseQuery)->whereIn('status', ['received_at_hub', 'rider_assigned', 'scheduled_redelivery'])->count(),
            'out_for_delivery' => (clone $baseQuery)->where('status', 'out_for_delivery')->count(),
            'delivered'        => (clone $baseQuery)->where('status', 'delivered')->count(),
            'failed_returned'  => (clone $baseQuery)->whereIn('status', ['delivery_failed', 'returning_to_seller', 'returned_to_seller'])->count(),
        ];

        $companyHubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality')
            ->get();

        $availableMunicipalities = (clone $baseQuery)
            ->select(['pickup_address_snapshot', 'delivery_address_snapshot'])
            ->get()
            ->flatMap(function (Shipment $s) {
                $pMun = trim((string) ($s->pickup_address_snapshot['municipality'] ?? ''));
                $dMun = trim((string) ($s->delivery_address_snapshot['municipality'] ?? ''));
                return array_filter([$pMun, $dMun]);
            })
            ->unique()
            ->sort()
            ->values();

        $query = (clone $baseQuery)->with([
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerProfile',
            'destinationLogisticsHub',
            'currentCourier',
            'currentRider',
            'packages.logisticsHub',
            'codCollection',
        ]);

        match ($statusTab) {
            'to_pickup'        => $query->whereIn('status', ['pickup_requested', 'awaiting_seller_drop_off', 'courier_assigned']),
            'in_transit_hub'   => $query->whereIn('status', ['picked_up_from_seller', 'in_transit_to_hub']),
            'at_hub'           => $query->whereIn('status', ['received_at_hub', 'rider_assigned', 'scheduled_redelivery']),
            'out_for_delivery' => $query->where('status', 'out_for_delivery'),
            'delivered'        => $query->where('status', 'delivered'),
            'failed_returned'  => $query->whereIn('status', ['delivery_failed', 'returning_to_seller', 'returned_to_seller']),
            default            => null,
        };

        if ($tierFilter !== '') {
            $query->whereHas('sellerOrder', fn ($sq) => $sq->where('shipping_tier', $tierFilter));
        }

        if ($municipalityFilter !== '') {
            $query->where(function ($q) use ($municipalityFilter) {
                $q->where('delivery_address_snapshot->municipality', $municipalityFilter)
                  ->orWhere('pickup_address_snapshot->municipality', $municipalityFilter);
            });
        }

        if ($hubFilter > 0 && ! $activeBranchHub) {
            $query->where(function ($q) use ($hubFilter) {
                $q->whereHas('packages', fn ($pq) => $pq->where('logistics_hub_id', $hubFilter))
                  ->orWhere('hub_address_snapshot->logistics_hub_id', $hubFilter)
                  ->when(Schema::hasColumn('shipments', 'destination_logistics_hub_id'), function ($dq) use ($hubFilter) {
                      $dq->orWhere('destination_logistics_hub_id', $hubFilter);
                  });
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhereHas('packages', fn ($pq) => $pq->where('package_reference', 'like', "%{$search}%"))
                  ->orWhereHas('sellerOrder', fn ($sq) => $sq->where('seller_order_number', 'like', "%{$search}%"))
                  ->orWhereHas('sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"));
            });
        }

        $shipments = $query
            ->orderByOperationalQueue($queueSort)
            ->paginate(10)
            ->withQueryString();

        return view('logistics.shipments.index', compact(
            'profile',
            'activeBranchHub',
            'shipments',
            'statusTab',
            'search',
            'counts',
            'queueSort',
            'tierFilter',
            'municipalityFilter',
            'hubFilter',
            'companyHubs',
            'availableMunicipalities'
        ));
    }

    protected function backfillShipmentDistances(int $logisticsProfileId): void
    {
        if (! Schema::hasColumn('shipments', 'hub_to_buyer_distance_km')) {
            return;
        }

        $missing = Shipment::where('logistics_profile_id', $logisticsProfileId)
            ->whereNull('hub_to_buyer_distance_km')
            ->limit(40)
            ->get();

        foreach ($missing as $shp) {
            $origDist = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
                (array) ($shp->pickup_address_snapshot ?? []),
                (array) ($shp->hub_address_snapshot ?? [])
            );
            $destHubSnap = (array) ($shp->hub_address_snapshot['destination_hub'] ?? $shp->hub_address_snapshot ?? []);
            $destDist = CourierMobileSchemaService::calculateDistanceKmBetweenLocations(
                $destHubSnap,
                (array) ($shp->delivery_address_snapshot ?? [])
            );
            $shp->update([
                'origin_to_hub_distance_km' => $origDist,
                'hub_to_buyer_distance_km'  => $destDist,
            ]);
        }
    }

    public function show(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (
            ! $profile
            || (int) $shipment->logistics_profile_id !== (int) $profile->id
            || (int) $shipment->sellerOrder?->logistics_profile_id !== (int) $profile->id
        ) {
            abort(403, 'This shipment is not assigned to your logistics company.');
        }

        $this->orderCompletionService->runLifecycleMaintenance(logisticsProfileId: (int) $profile->id);

        $shipment->loadMissing('sellerOrder.items');
        $this->shipmentVerificationService->ensurePackagesForShipment(
            shipment: $shipment,
            sellerOrder: $shipment->sellerOrder
        );

        $shipment->load([
            'sellerOrder.order',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.payment',
            'sellerProfile',
            'destinationLogisticsHub',
            'currentCourier.currentHubAssignment.hub',
            'currentRider.currentHubAssignment.hub',
            'currentRider.barangayCoverages',
            'packages.logisticsHub',
            'packages.verifications',
            'verifications',
            'trackingLogs',
            'pickupAssignments.courier',
            'deliveryAssignments.rider',
            'deliveryAttempts.rider',
            'deliveryProofs',
            'codCollection',
        ]);

        $primaryPackage = $shipment->packages->first();
        $targetHubId = $primaryPackage?->logistics_hub_id ?? ($shipment->hub_address_snapshot['logistics_hub_id'] ?? null);
        $destHubId = $shipment->destination_logistics_hub_id ?? ($shipment->hub_address_snapshot['destination_logistics_hub_id'] ?? $targetHubId);

        if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
            $branchId = (int) $user->branchLogisticsHub->id;
            if (
                $targetHubId && (int) $targetHubId !== $branchId
                && (! $destHubId || (int) $destHubId !== $branchId)
            ) {
                abort(403, 'This shipment is assigned to another hub branch.');
            }
        }

        $pickupSnap = (array) ($shipment->pickup_address_snapshot ?? []);
        $deliverySnap = (array) ($shipment->delivery_address_snapshot ?? []);
        $shipmentWeightKg = (float) ($shipment->total_weight ?: 0.5);

        $courierSort = $request->query('courier_sort', 'nearest');
        $riderSort = $request->query('rider_sort', 'nearest');

        $allCouriers = Courier::with(['currentHubAssignment.hub'])
            ->where('logistics_profile_id', $profile->id)
            ->whereIn('status', ['active', 'Active', 'on_delivery'])
            ->orderBy('first_name')
            ->get();

        $evaluatedCouriers = $allCouriers->map(function (Courier $c) use ($pickupSnap, $shipmentWeightKg) {
            $eval = $c->evaluatePickupEligibilityForSnapshot($pickupSnap, $shipmentWeightKg);
            $c->setAttribute('pickup_eval', $eval);
            return $c;
        });

        $eligibleCouriers = $evaluatedCouriers->filter(fn (Courier $c) => (bool) ($c->getAttribute('pickup_eval')['is_eligible'] ?? false));

        $availableCouriers = ($courierSort === 'farthest'
            ? $eligibleCouriers->sortByDesc(fn (Courier $c) => (float) ($c->getAttribute('pickup_eval')['distance_from_courier_km'] ?? 0))
            : ($courierSort === 'capacity'
                ? $eligibleCouriers->sortByDesc(fn (Courier $c) => (float) ($c->getAttribute('pickup_eval')['remaining_capacity_kg'] ?? 0))
                : $eligibleCouriers->sortBy(fn (Courier $c) => (float) ($c->getAttribute('pickup_eval')['distance_from_courier_km'] ?? 9999))
            ))->values();

        $outOfRangeCouriers = $evaluatedCouriers->reject(fn (Courier $c) => (bool) ($c->getAttribute('pickup_eval')['is_eligible'] ?? false))->values();

        $allRiders = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->withCount([
                'deliveryAssignments as active_deliveries_count' => function ($q) {
                    $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']);
                },
            ])
            ->where('logistics_profile_id', $profile->id)
            ->where('status', 'approved')
            ->orderBy('first_name')
            ->get();

        $evaluatedRiders = $allRiders->map(function (Rider $r) use ($deliverySnap) {
            $eval = $r->evaluateDeliveryEligibilityForSnapshot($deliverySnap);
            $r->setAttribute('delivery_eval', $eval);
            return $r;
        });

        $eligibleRiders = $evaluatedRiders->filter(fn (Rider $r) => (bool) ($r->getAttribute('delivery_eval')['is_eligible'] ?? false));

        $matchingBarangayRiders = $eligibleRiders->filter(fn (Rider $r) => (bool) ($r->getAttribute('delivery_eval')['exact_barangay_match'] ?? false))->values();

        $availableRiders = ($riderSort === 'farthest'
            ? $eligibleRiders->sortByDesc(fn (Rider $r) => (float) ($r->getAttribute('delivery_eval')['distance_from_rider_km'] ?? 0))
            : ($riderSort === 'workload'
                ? $eligibleRiders->sortBy(fn (Rider $r) => (int) ($r->active_deliveries_count ?? 0))
                : $eligibleRiders->sortBy(function (Rider $r) {
                    $eval = $r->getAttribute('delivery_eval');
                    $brgyBonus = ! empty($eval['exact_barangay_match']) ? 0 : 50;
                    return $brgyBonus + (float) ($eval['distance_from_rider_km'] ?? 9999);
                })
            ))->values();

        $outOfRangeRiders = $evaluatedRiders->reject(fn (Rider $r) => (bool) ($r->getAttribute('delivery_eval')['is_eligible'] ?? false))->values();

        return view('logistics.shipments.show', compact(
            'profile',
            'shipment',
            'availableCouriers',
            'outOfRangeCouriers',
            'availableRiders',
            'outOfRangeRiders',
            'matchingBarangayRiders',
            'courierSort',
            'riderSort'
        ));
    }

    public function verifyPackageLookup(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'This shipment is not assigned to your logistics company.');
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:150'],
            'verification_method' => ['required', 'in:qr,manual'],
        ], [
            'lookup_input.required' => 'Please scan the package QR Code or enter the Tracking / Package Reference.',
        ]);

        $shipment->loadMissing('packages');
        $firstPackage = $shipment->packages->first();
        $isSelfDropOff = $shipment->status === 'awaiting_seller_drop_off'
            || ($firstPackage?->handover_method === 'self_drop_off' && $shipment->status === 'pickup_requested');

        $stage = $isSelfDropOff ? 'seller_drop_off' : 'logistics_receiving';

        $result = $this->shipmentVerificationService->lookupPackageForActor(
            rawInput: trim($validated['lookup_input']),
            verificationMethod: $validated['verification_method'],
            stage: $stage,
            actorType: 'Logistics',
            actor: $user
        );

        if ((int) $result['shipment']->id !== (int) $shipment->id) {
            throw ValidationException::withMessages([
                'lookup_input' => 'The scanned/entered package reference belongs to a different shipment.',
            ]);
        }

        $result['verification']->update([
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $result['package']->update([
            'hub_verification_method' => $validated['verification_method'],
        ]);

        $shipment->load('packages.verifications');
        $allHubPackagesVerified = $shipment->packages->isNotEmpty() && $shipment->packages->every(function ($pkg) {
            if ($pkg->hub_verified_at !== null) {
                return true;
            }

            return $pkg->verifications
                ->whereIn('stage', ['logistics_receiving', 'seller_drop_off'])
                ->where('actor_type', 'Logistics')
                ->where('is_confirmed', true)
                ->isNotEmpty();
        });

        $canAutoConfirmArrival = $isSelfDropOff || in_array($shipment->status, ['picked_up_from_seller', 'in_transit_to_hub'], true);

        if ($allHubPackagesVerified && $canAutoConfirmArrival) {
            return $this->verifyHubArrival($request, $shipment);
        }

        RealtimeBroadcastService::forShipment($shipment->fresh(), 'shipment.hub_package_verified');

        $orderNo = $shipment->sellerOrder?->seller_order_number ?? 'N/A';

        return back()->with(
            'success',
            "QR/Package Identified: {$result['package']->package_reference} (Order #{$orderNo}, Tracking #{$shipment->tracking_number}) verified via " . strtoupper($validated['verification_method']) . ". Please scan/verify the remaining package(s) to confirm physical receipt at the Sorting Hub."
        );
    }

    public function assignCourier(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'This shipment is not assigned to your logistics company.');
        }

        $shipment->loadMissing('packages.logisticsHub');
        $primaryPackage = $shipment->packages->first();

        if ($shipment->status === 'awaiting_seller_drop_off' || ($primaryPackage && $primaryPackage->handover_method === 'self_drop_off')) {
            return back()->withErrors([
                'shipment' => 'This shipment uses Seller Self Drop-Off at the hub and does not use a Courier pickup assignment.',
            ]);
        }

        $validated = $request->validate([
            'courier_id'          => ['required', 'integer', 'exists:couriers,id'],
            'scheduled_pickup_by' => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Courier $courier */
        $courier = Courier::with('currentHubAssignment.hub')
            ->where('logistics_profile_id', $profile->id)
            ->whereIn('status', ['active', 'Active', 'on_delivery'])
            ->findOrFail($validated['courier_id']);

        $pickupEval = $courier->evaluatePickupEligibilityForSnapshot(
            (array) ($shipment->pickup_address_snapshot ?? []),
            (float) ($shipment->total_weight ?: 0.5)
        );

        if (! $pickupEval['is_eligible']) {
            return back()->withErrors([
                'courier_id' => $pickupEval['reason'] ?: "Courier {$courier->full_name} is not eligible to pick up this shipment.",
            ]);
        }

        $previousCourierId = $shipment->current_courier_id ? (int) $shipment->current_courier_id : null;
        $createdAssignment = null;

        DB::transaction(function () use ($shipment, $courier, $profile, $user, $validated, &$createdAssignment) {
            $locked = Shipment::with(['sellerOrder.order', 'packages'])->where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['pickup_requested', 'courier_assigned'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'Courier can only be assigned when the shipment is awaiting pickup.',
                ]);
            }

            PickupAssignment::where('shipment_id', $locked->id)
                ->where('courier_id', '!=', $courier->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->update(['status' => 'reassigned']);

            $scheduledNote = ! empty($validated['scheduled_pickup_by'])
                ? '[Scheduled Pickup By: ' . \Carbon\Carbon::parse($validated['scheduled_pickup_by'])->format('M d, Y h:i A') . '] '
                : '[SLA: Pickup within 2 hours] ';

            $createdAssignment = PickupAssignment::updateOrCreate(
                [
                    'shipment_id' => $locked->id,
                    'courier_id'  => $courier->id,
                ],
                [
                    'seller_order_id'      => $locked->seller_order_id,
                    'logistics_profile_id' => $profile->id,
                    'assigned_by'          => $user->id,
                    'status'               => 'assigned',
                    'assigned_at'          => now(),
                    'accepted_at'          => null,
                    'picked_up_at'         => null,
                    'handed_over_at'       => null,
                    'is_delayed'           => false,
                    'delay_reason'         => null,
                    'notes'                => trim($scheduledNote . ($validated['notes'] ?? '')),
                ]
            );

            foreach ($locked->packages as $pkg) {
                $pkg->update([
                    'courier_verified_at'         => null,
                    'courier_verification_method' => null,
                ]);
            }

            $locked->update([
                'current_courier_id'  => $courier->id,
                'status'              => 'courier_assigned',
                'courier_assigned_at' => now(),
            ]);

            $prevOrderStatus = $locked->sellerOrder->status;
            $locked->sellerOrder->update([
                'status' => 'pickup_assigned',
            ]);

            $courier->update(['status' => 'on_delivery']);

            $hubLabel = $courier->assigned_hub?->hub_name ?? $profile->business_name;

            ShipmentTracking::create([
                'shipment_id'     => $locked->id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'courier_assigned',
                'title'           => 'First-Mile Courier Assigned',
                'description'     => "Courier {$courier->full_name} ({$courier->vehicle_type} - {$courier->plate_number}) from {$hubLabel} assigned to pick up package from seller. {$scheduledNote}",
                'location'        => $hubLabel,
                'actor_type'      => 'Logistics',
                'actor_id'        => $user->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->seller_order_id,
                'from_status'     => $prevOrderStatus,
                'to_status'       => 'pickup_assigned',
                'actor_type'      => 'Logistics',
                'actor_id'        => $user->id,
                'remarks'         => "Courier {$courier->full_name} assigned for package pickup.",
            ]);
        });

        try {
            $sellerName = $shipment->pickup_address_snapshot['business_name']
                ?? $shipment->sellerProfile?->business_name
                ?? 'Seller Store';
            $payInfo = WebPickupController::calculateFirstMileCourierPay($shipment);
            $peso = "\u{20B1}";

            $courier->notify(new OrderLifecycleNotification(
                title: "New Pickup Assignment (#{$shipment->tracking_number})",
                message: "Collect package from {$sellerName} and transport to " . ($courier->assigned_hub?->hub_name ?? $profile->business_name) . ". Haulage Pay: {$peso}" . number_format((float) $payInfo['total_pay'], 2) . '.',
                type: 'info',
                url: '/courier/dashboard',
                orderId: $shipment->order_id,
                sellerOrderId: $shipment->seller_order_id,
                shipmentId: $shipment->id
            ));
        } catch (\Throwable $e) {
        }

        $freshShipment = $shipment->fresh(['sellerOrder']);
        if ($createdAssignment) {
            RealtimeBroadcastService::forPickupAssignment($createdAssignment->fresh(), 'pickup.assigned');
        }
        if ($freshShipment) {
            RealtimeBroadcastService::forShipment($freshShipment, 'shipment.courier_assigned');
            if ($freshShipment->sellerOrder) {
                RealtimeBroadcastService::forSellerOrder($freshShipment->sellerOrder, 'order.pickup_assigned');
            }
        }
        if ($previousCourierId && $previousCourierId !== (int) $courier->id) {
            RealtimeBroadcastService::send(
                channels: ['courier.' . $previousCourierId],
                scope: 'pickup',
                action: 'pickup.reassigned',
                payload: [
                    'shipment_id'     => (int) $shipment->id,
                    'tracking_number' => (string) $shipment->tracking_number,
                ]
            );
        }

        return back()->with('success', "Courier {$courier->full_name} has been assigned to pick up Shipment #{$shipment->tracking_number}.");
    }

    public function verifyHubArrival(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $notifiableCourier = null;
        $creditedPayInfo = null;
        $completedPickupAssignment = null;
        $isCod = true;
        $verificationMethodUsed = strtoupper((string) $request->input('verification_method', 'MANUAL'));

        DB::transaction(function () use ($shipment, $profile, $user, &$notifiableCourier, &$creditedPayInfo, &$completedPickupAssignment, &$isCod, &$verificationMethodUsed) {
            $locked = Shipment::with([
                'sellerOrder.order',
                'sellerOrder.payment',
                'latestPickupAssignment',
                'currentCourier',
                'packages.logisticsHub',
            ])
                ->where('id', $shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isSelfDropOff = $locked->status === 'awaiting_seller_drop_off'
                || ($locked->packages->first()?->handover_method === 'self_drop_off' && $locked->status === 'pickup_requested');

            if (! $isSelfDropOff && ! in_array($locked->status, ['picked_up_from_seller', 'in_transit_to_hub'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'Package must either be picked up by the assigned Courier or awaiting Seller Self Drop-Off before verifying hub receipt.',
                ]);
            }

            foreach ($locked->packages as $pkg) {
                $verifiedRecord = ShipmentVerification::where('shipment_package_id', $pkg->id)
                    ->whereIn('stage', ['logistics_receiving', 'seller_drop_off'])
                    ->where('actor_type', 'Logistics')
                    ->where('is_confirmed', true)
                    ->latest('id')
                    ->first();

                if (! $verifiedRecord && $pkg->hub_verified_at === null) {
                    throw ValidationException::withMessages([
                        'shipment' => "Package \"{$pkg->package_reference}\" must first be scanned or verified via QR Code / Manual lookup before confirming physical receipt at the hub.",
                    ]);
                }

                $methodForPkg = $verifiedRecord?->verification_method ?? ($pkg->hub_verification_method ?: 'manual');
                $verificationMethodUsed = strtoupper((string) $methodForPkg);

                $pkg->update([
                    'hub_verified_at'         => now(),
                    'hub_verified_by'         => $user->id,
                    'hub_verification_method' => $methodForPkg,
                    'seller_dropped_off_at'   => $isSelfDropOff ? now() : $pkg->seller_dropped_off_at,
                ]);
            }

            $locked->update([
                'status'            => 'received_at_hub',
                'arrived_at_hub_at' => now(),
                'hub_verified_by'   => $user->id,
            ]);

            if ($locked->latestPickupAssignment) {
                $locked->latestPickupAssignment->update([
                    'status'          => 'completed',
                    'handed_over_at'  => $locked->latestPickupAssignment->handed_over_at ?? now(),
                    'hub_verified_by' => $user->id,
                    'hub_verified_at' => now(),
                ]);
                $completedPickupAssignment = $locked->latestPickupAssignment->fresh();
            }

            if ($locked->currentCourier) {
                $notifiableCourier = $locked->currentCourier;
                $creditedPayInfo = WebPickupController::calculateFirstMileCourierPay($locked);
                $isCod = strtoupper((string) ($locked->sellerOrder?->effective_payment_method ?? 'COD')) === 'COD';

                $this->roleWalletService->recordFirstMileCourierTaskCredit($locked, $locked->currentCourier, $creditedPayInfo);

                $remainingActive = PickupAssignment::where('courier_id', $locked->currentCourier->id)
                    ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])
                    ->exists();

                if (! $remainingActive && $locked->currentCourier->status === 'on_delivery') {
                    $locked->currentCourier->update(['status' => 'active']);
                }
            }

            $prevOrderStatus = $locked->sellerOrder->status;
            $locked->sellerOrder->update([
                'status' => 'at_logistics_hub',
            ]);

            $receivingHub = method_exists($user, 'activeLogisticsHub') ? $user->activeLogisticsHub() : null;
            $hubName = $receivingHub?->hub_name ?? $profile->business_name;
            $hubLocation = ($receivingHub?->municipality ?? $profile->municipality) . ', ' . ($receivingHub?->province ?? $profile->province);

            if ($isSelfDropOff) {
                ShipmentTracking::create([
                    'shipment_id'     => $locked->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'received_at_hub',
                    'title'           => 'Seller Dropped Off Package — Verified & Received at Hub',
                    'description'     => "Seller personally dropped off the package at {$hubName}. Logistics verified package identification ({$verificationMethodUsed}) and confirmed physical receipt.",
                    'location'        => $hubLocation,
                    'actor_type'      => 'Logistics',
                    'actor_id'        => $user->id,
                    'occurred_at'     => now(),
                ]);
            } else {
                ShipmentTracking::create([
                    'shipment_id'     => $locked->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'received_at_hub',
                    'title'           => 'Arrived at Logistics Sorting Facility',
                    'description'     => "Package verified ({$verificationMethodUsed}) and received at {$hubName} from Courier {$locked->currentCourier?->full_name}.",
                    'location'        => $hubLocation,
                    'actor_type'      => 'Logistics',
                    'actor_id'        => $user->id,
                    'occurred_at'     => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->seller_order_id,
                'from_status'     => $prevOrderStatus,
                'to_status'       => 'at_logistics_hub',
                'actor_type'      => 'Logistics',
                'actor_id'        => $user->id,
                'remarks'         => $isSelfDropOff
                    ? "Seller drop-off verified ({$verificationMethodUsed}) and received at {$hubName}."
                    : "Courier handover verified ({$verificationMethodUsed}) and received at {$hubName}.",
            ]);
        });

        if ($notifiableCourier && $creditedPayInfo) {
            try {
                $peso = "\u{20B1}";
                $notifiableCourier->notify(new OrderLifecycleNotification(
                    title: "Hub Handover Completed (#{$shipment->tracking_number})",
                    message: $isCod
                        ? "Sorting Hub verified receipt of Shipment #{$shipment->tracking_number}. {$peso}" . number_format((float) $creditedPayInfo['total_pay'], 2) . ' recorded in your Pending Escrow (releases upon Admin COD Remittance verification).'
                        : "Sorting Hub verified receipt of Shipment #{$shipment->tracking_number}. {$peso}" . number_format((float) $creditedPayInfo['total_pay'], 2) . ' earned in your Courier E-Wallet.',
                    type: 'success',
                    url: '/courier/dashboard',
                    orderId: $shipment->order_id,
                    sellerOrderId: $shipment->seller_order_id,
                    shipmentId: $shipment->id
                ));
            } catch (\Throwable $e) {
            }
        }

        $this->buyerNotifications->arrivedAtHub($shipment);

        $freshShipment = $shipment->fresh(['sellerOrder']);
        if ($completedPickupAssignment) {
            RealtimeBroadcastService::forPickupAssignment($completedPickupAssignment, 'pickup.completed');
        }
        if ($freshShipment) {
            RealtimeBroadcastService::forShipment($freshShipment, 'shipment.received_at_hub');
            if ($freshShipment->sellerOrder) {
                RealtimeBroadcastService::forSellerOrder($freshShipment->sellerOrder, 'order.at_logistics_hub');
            }
        }
        if ($notifiableCourier && $creditedPayInfo) {
            RealtimeBroadcastService::forWalletUpdate('Courier', (int) $notifiableCourier->id, 'first_mile_pickup_pay.credited', [
                'shipment_id' => (int) $shipment->id,
                'total_pay'   => round((float) ($creditedPayInfo['total_pay'] ?? 0), 2),
                'is_cod'      => $isCod,
            ]);
        }

        return back()->with('success', "Shipment #{$shipment->tracking_number} verified ({$verificationMethodUsed}) and physical receipt confirmed at sorting hub. You can now assign a last-mile Rider.");
    }

    public function assignRider(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'This shipment is not assigned to your logistics company.');
        }

        $validated = $request->validate([
            'rider_id'              => ['required', 'integer', 'exists:riders,id'],
            'scheduled_delivery_by' => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Rider $rider */
        $rider = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->where('logistics_profile_id', $profile->id)
            ->where('status', 'approved')
            ->findOrFail($validated['rider_id']);

        $deliveryEval = $rider->evaluateDeliveryEligibilityForSnapshot((array) ($shipment->delivery_address_snapshot ?? []));

        if (! $deliveryEval['is_eligible']) {
            return back()->withErrors([
                'rider_id' => $deliveryEval['reason'] ?: "Rider {$rider->full_name} is registered to {$rider->assigned_hub?->municipality} and cannot be assigned to a buyer outside their last-mile hub radius.",
            ]);
        }

        $previousRiderId = $shipment->current_rider_id ? (int) $shipment->current_rider_id : null;
        $createdDeliveryAssignment = null;

        DB::transaction(function () use ($shipment, $rider, $profile, $user, $validated, &$createdDeliveryAssignment) {
            $locked = Shipment::with(['sellerOrder', 'packages'])->where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['received_at_hub', 'rider_assigned', 'scheduled_redelivery'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'Rider can only be assigned when the package has been verified as received at the sorting hub or scheduled for redelivery.',
                ]);
            }

            DeliveryAssignment::where('shipment_id', $locked->id)
                ->where('rider_id', '!=', $rider->id)
                ->whereIn('status', ['assigned', 'accepted', 'collected'])
                ->update(['status' => 'reassigned']);

            $nextAttemptNumber = (int) $locked->delivery_attempts_count + 1;

            $scheduledNote = ! empty($validated['scheduled_delivery_by'])
                ? '[Scheduled Delivery By: ' . \Carbon\Carbon::parse($validated['scheduled_delivery_by'])->format('M d, Y h:i A') . '] '
                : '[SLA: Dispatch within 2 hours] ';

            $createdDeliveryAssignment = DeliveryAssignment::updateOrCreate(
                [
                    'shipment_id' => $locked->id,
                    'rider_id'    => $rider->id,
                ],
                [
                    'seller_order_id'      => $locked->seller_order_id,
                    'logistics_profile_id' => $profile->id,
                    'assigned_by'          => $user->id,
                    'attempt_number'       => $nextAttemptNumber,
                    'status'               => 'assigned',
                    'assigned_at'          => now(),
                    'accepted_at'          => null,
                    'out_for_delivery_at'  => null,
                    'completed_at'         => null,
                    'notes'                => trim($scheduledNote . ($validated['notes'] ?? '')),
                ]
            );

            foreach ($locked->packages as $pkg) {
                $pkg->update([
                    'rider_accepted_at'         => null,
                    'rider_verified_at'         => null,
                    'rider_verification_method' => null,
                    'rider_collected_at'        => null,
                ]);
            }

            $locked->update([
                'current_rider_id'  => $rider->id,
                'status'            => 'rider_assigned',
                'rider_assigned_at' => now(),
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $locked->id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'rider_assigned',
                'title'           => "Last-Mile Delivery Rider Assigned (Attempt #{$nextAttemptNumber})",
                'description'     => "Rider {$rider->full_name} ({$rider->vehicle_type} - {$rider->plate_number}) assigned for delivery. {$scheduledNote}",
                'location'        => $rider->assigned_hub?->hub_name ?? $profile->business_name,
                'actor_type'      => 'Logistics',
                'actor_id'        => $user->id,
                'occurred_at'     => now(),
            ]);
        });

        if ($createdDeliveryAssignment) {
            RealtimeBroadcastService::forDeliveryAssignment($createdDeliveryAssignment->fresh(), 'delivery.assigned');
        }
        $freshShipment = $shipment->fresh(['sellerOrder']);
        if ($freshShipment) {
            RealtimeBroadcastService::forShipment($freshShipment, 'shipment.rider_assigned');
        }
        if ($previousRiderId && $previousRiderId !== (int) $rider->id) {
            RealtimeBroadcastService::send(
                channels: ['rider.' . $previousRiderId],
                scope: 'delivery',
                action: 'delivery.reassigned',
                payload: [
                    'shipment_id'     => (int) $shipment->id,
                    'tracking_number' => (string) $shipment->tracking_number,
                ]
            );
        }

        return back()->with('success', "Rider {$rider->full_name} has been assigned to deliver Shipment #{$shipment->tracking_number}.");
    }

    public function resolveFailedDelivery(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $validated = $request->validate([
            'resolution' => ['required', 'in:schedule_redelivery,return_to_seller'],
            'rider_id'   => ['nullable', 'integer', 'exists:riders,id'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $newRider = null;
        if ($validated['resolution'] === 'schedule_redelivery' && ! empty($validated['rider_id'])) {
            $newRider = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
                ->where('logistics_profile_id', $profile->id)
                ->where('status', 'approved')
                ->findOrFail($validated['rider_id']);

            $redelivEval = $newRider->evaluateDeliveryEligibilityForSnapshot((array) ($shipment->delivery_address_snapshot ?? []));
            if (! $redelivEval['is_eligible']) {
                return back()->withErrors([
                    'rider_id' => $redelivEval['reason'] ?: "Rider {$newRider->full_name} is outside the Last-Mile delivery range for this buyer.",
                ]);
            }
        }

        $abandonmentShipment = null;
        $abandonmentOrder = null;
        $abandonmentReasonText = '';
        $abandonmentAttempts = 1;
        $createdRedeliveryAssignment = null;

        DB::transaction(function () use (
            $shipment,
            $profile,
            $user,
            $validated,
            $newRider,
            &$abandonmentShipment,
            &$abandonmentOrder,
            &$abandonmentReasonText,
            &$abandonmentAttempts,
            &$createdRedeliveryAssignment
        ) {
            $locked = Shipment::with(['sellerOrder.order', 'sellerOrder.payment', 'sellerOrder.extra', 'deliveryAttempts', 'packages'])
                ->where('id', $shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['delivery_failed', 'returning_to_seller'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'Only shipments with a failed delivery attempt can be resolved here.',
                ]);
            }

            $latestAttempt = $locked->deliveryAttempts->first();
            if ($latestAttempt) {
                $latestAttempt->update([
                    'logistics_resolution' => $validated['resolution'],
                    'reviewed_by'          => $user->id,
                    'reviewed_at'          => now(),
                ]);
            }

            if ($validated['resolution'] === 'schedule_redelivery') {
                if ((int) $locked->delivery_attempts_count >= 3) {
                    throw ValidationException::withMessages([
                        'shipment' => 'This parcel has already failed 3 delivery attempts and must be returned to the seller (RTS).',
                    ]);
                }

                foreach ($locked->packages as $pkg) {
                    $pkg->update([
                        'rider_accepted_at'         => null,
                        'rider_verified_at'         => null,
                        'rider_verification_method' => null,
                        'rider_collected_at'        => null,
                    ]);
                }

                if ($newRider) {
                    $nextAttemptNumber = (int) $locked->delivery_attempts_count + 1;

                    DeliveryAssignment::where('shipment_id', $locked->id)
                        ->where('rider_id', '!=', $newRider->id)
                        ->whereIn('status', ['assigned', 'accepted', 'collected'])
                        ->update(['status' => 'reassigned']);

                    $createdRedeliveryAssignment = DeliveryAssignment::updateOrCreate(
                        [
                            'shipment_id' => $locked->id,
                            'rider_id'    => $newRider->id,
                        ],
                        [
                            'seller_order_id'      => $locked->seller_order_id,
                            'logistics_profile_id' => $profile->id,
                            'assigned_by'          => $user->id,
                            'attempt_number'       => $nextAttemptNumber,
                            'status'               => 'assigned',
                            'assigned_at'          => now(),
                            'accepted_at'          => null,
                            'out_for_delivery_at'  => null,
                            'completed_at'         => null,
                            'notes'                => $validated['notes'] ?? 'Scheduled for redelivery.',
                        ]
                    );

                    $locked->update([
                        'current_rider_id'  => $newRider->id,
                        'status'            => 'rider_assigned',
                        'rider_assigned_at' => now(),
                    ]);

                    $desc = "Logistics scheduled redelivery (Attempt #{$nextAttemptNumber} of 3) and assigned Rider {$newRider->full_name}.";
                } else {
                    $locked->update([
                        'current_rider_id' => null,
                        'status'           => 'scheduled_redelivery',
                    ]);

                    $desc = 'Logistics reviewed failed attempt and scheduled package for redelivery.';
                }

                $locked->sellerOrder->update([
                    'status' => 'at_logistics_hub',
                ]);

                ShipmentTracking::create([
                    'shipment_id'     => $locked->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => $locked->status,
                    'title'           => 'Scheduled for Redelivery',
                    'description'     => $desc,
                    'location'        => $profile->business_name,
                    'actor_type'      => 'Logistics',
                    'actor_id'        => $user->id,
                    'occurred_at'     => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->seller_order_id,
                    'from_status'     => 'delivery_failed',
                    'to_status'       => 'at_logistics_hub',
                    'actor_type'      => 'Logistics',
                    'actor_id'        => $user->id,
                    'remarks'         => $desc,
                ]);
            } else {
                $failureReason = $latestAttempt?->failure_reason ?: 'Buyer abandoned or failed to receive COD parcel';
                if (! empty($validated['notes'])) {
                    $failureReason .= ' (' . trim($validated['notes']) . ')';
                }

                $locked->update([
                    'status'      => 'returned_to_seller',
                    'returned_at' => now(),
                ]);

                $wasWalletPaid = strtoupper((string) $locked->sellerOrder->effective_payment_method) === 'WALLET'
                    || (float) ($locked->sellerOrder->extra?->wallet_paid_amount ?? 0) > 0;

                $locked->sellerOrder->update([
                    'status'         => 'returned_to_seller',
                    'payment_status' => 'cancelled',
                    'cancelled_at'   => now(),
                ]);

                $locked->sellerOrder->payment?->update([
                    'status' => 'cancelled',
                ]);

                if ($wasWalletPaid) {
                    $refundAmt = (float) ($locked->sellerOrder->extra?->wallet_paid_amount > 0 ? $locked->sellerOrder->extra->wallet_paid_amount : $locked->sellerOrder->total_amount);
                    $this->roleWalletService->refundBuyerWalletForOrder(
                        $locked->sellerOrder,
                        $refundAmt,
                        'Order Returned to Seller (RTS) — E-Wallet Refund'
                    );
                }

                $this->inventoryStockService->restoreStockForSellerOrder($locked->sellerOrder);
                $this->voucherService->restoreVouchersForSellerOrder($locked->sellerOrder);
                $this->buyerRewardService?->restoreCoinsForSellerOrder($locked->sellerOrder);

                ShipmentTracking::create([
                    'shipment_id'     => $locked->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'returned_to_seller',
                    'title'           => 'Package Returned to Seller (RTS — Buyer Abandonment Logged)',
                    'description'     => "Following unsuccessful delivery ({$failureReason}), Logistics initiated Return to Seller (RTS), restored reserved stock, and recorded a COD abandonment strike against the buyer.",
                    'location'        => $profile->business_name,
                    'actor_type'      => 'Logistics',
                    'actor_id'        => $user->id,
                    'occurred_at'     => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->seller_order_id,
                    'from_status'     => 'delivery_failed',
                    'to_status'       => 'returned_to_seller',
                    'actor_type'      => 'Logistics',
                    'actor_id'        => $user->id,
                    'remarks'         => "Failed delivery resolved as Return to Seller (RTS): {$failureReason}. Reserved stock, vouchers, and coins restored; Buyer COD abandonment strike logged.",
                ]);

                $this->orderCompletionService->syncParentOrderStatus($locked->sellerOrder->order);

                $abandonmentShipment = $locked;
                $abandonmentOrder = $locked->sellerOrder;
                $abandonmentReasonText = $failureReason;
                $abandonmentAttempts = max(1, (int) $locked->delivery_attempts_count);
            }
        });

        if ($abandonmentShipment && $abandonmentOrder) {
            $this->buyerAbandonmentService->recordCodParcelAbandonment(
                shipment: $abandonmentShipment,
                sellerOrder: $abandonmentOrder,
                abandonmentReason: $abandonmentReasonText,
                failedAttemptsCount: $abandonmentAttempts
            );
        }

        if ($validated['resolution'] === 'schedule_redelivery') {
            $this->buyerNotifications->redeliveryScheduled($shipment);
        }

        if ($createdRedeliveryAssignment) {
            RealtimeBroadcastService::forDeliveryAssignment($createdRedeliveryAssignment->fresh(), 'delivery.assigned');
        }
        $freshShipment = $shipment->fresh(['sellerOrder']);
        if ($freshShipment) {
            RealtimeBroadcastService::forShipment($freshShipment, 'shipment.failed_resolved');
            if ($freshShipment->sellerOrder) {
                RealtimeBroadcastService::forSellerOrder($freshShipment->sellerOrder, 'order.failed_resolved');
            }
        }

        return back()->with('success', 'Failed delivery report resolved successfully.');
    }

    public function ridersIndex(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($user);

        if (! $profile) {
            abort(403);
        }

        $filter = $request->query('filter', 'all');
        $search = trim((string) $request->query('search', ''));
        $hubFilter = (int) $request->query('hub_id', 0);

        $query = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->withCount([
                'deliveryAssignments as active_deliveries_count' => function ($q) {
                    $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']);
                },
                'deliveryAssignments as completed_deliveries_count' => function ($q) {
                    $q->where('status', 'delivered');
                },
            ])
            ->where('logistics_profile_id', $profile->id)
            ->where('status', 'approved');

        if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
            $branchId = (int) $user->branchLogisticsHub->id;
            $query->whereHas('currentHubAssignment', fn ($q) => $q->where('logistics_hub_id', $branchId)->where('is_current', true));
        } elseif ($hubFilter > 0) {
            $query->whereHas('currentHubAssignment', fn ($q) => $q->where('logistics_hub_id', $hubFilter)->where('is_current', true));
        }

        if ($filter === 'available') {
            $query->whereDoesntHave('deliveryAssignments', function ($q) {
                $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']);
            });
        } elseif ($filter === 'on_delivery') {
            $query->whereHas('deliveryAssignments', function ($q) {
                $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']);
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        $riders = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('logistics.riders.active-riders', compact('profile', 'riders', 'filter', 'search', 'hubFilter'));
    }
}