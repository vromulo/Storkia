<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Models\User;
use App\Services\AddressService;
use App\Services\BuyerAbandonmentService;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\ShipmentVerificationService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected OrderCompletionService $orderCompletionService,
        protected ShipmentVerificationService $shipmentVerificationService,
        protected AddressService $addressService,
        protected BuyerAbandonmentService $buyerAbandonmentService,
        protected VoucherService $voucherService
    ) {}

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403, 'Your logistics hub must be approved to manage shipments.');
        }

        $profile->ensureMainHubExists();
        $this->orderCompletionService->runLifecycleMaintenance(logisticsProfileId: (int) $profile->id);

        $activeBranchHub = $user->isBranchLogisticsAccount() ? $user->branchLogisticsHub : null;

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        // Strict isolation: ONLY shipments explicitly assigned to THIS logistics_profile_id
        $baseQuery = Shipment::where('logistics_profile_id', $profile->id)
            ->whereHas('sellerOrder', fn ($q) => $q->where('logistics_profile_id', $profile->id));

        // If logged in as a Branch Hub account, strictly limit to packages routed to this exact Branch Hub ID
        if ($activeBranchHub) {
            $baseQuery->where(function ($q) use ($activeBranchHub) {
                $q->whereHas('packages', fn ($pq) => $pq->where('logistics_hub_id', $activeBranchHub->id))
                  ->orWhere('hub_address_snapshot->logistics_hub_id', $activeBranchHub->id);
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

        $query = (clone $baseQuery)->with([
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerProfile',
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

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhereHas('packages', fn ($pq) => $pq->where('package_reference', 'like', "%{$search}%"))
                  ->orWhereHas('sellerOrder', fn ($sq) => $sq->where('seller_order_number', 'like', "%{$search}%"))
                  ->orWhereHas('sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"));
            });
        }

        // Strict First-In, First-Out (FIFO) ordering
        $shipments = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('logistics.shipments.index', compact(
            'profile',
            'activeBranchHub',
            'shipments',
            'statusTab',
            'search',
            'counts'
        ));
    }

    public function show(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

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

        if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
            if ($targetHubId && (int) $targetHubId !== (int) $user->branchLogisticsHub->id) {
                abort(403, 'This shipment is assigned to another hub branch.');
            }
        }

        $pickupMun = $this->addressService->normalizeName(
            $primaryPackage?->logisticsHub?->municipality
            ?? ($shipment->hub_address_snapshot['municipality'] ?? ($shipment->pickup_address_snapshot['municipality'] ?? ''))
        );

        $deliveryMun = $this->addressService->normalizeName($shipment->delivery_address_snapshot['municipality'] ?? '');
        $deliveryBarangay = $this->addressService->normalizeName($shipment->delivery_address_snapshot['barangay'] ?? '');

        $allCouriers = Courier::with(['currentHubAssignment.hub'])
            ->where('logistics_profile_id', $profile->id)
            ->whereIn('status', ['active', 'on_delivery'])
            ->orderBy('first_name')
            ->get();

        // Strictly show ONLY Couriers registered to the designated Hub / Municipality (even for Main Hub!)
        $availableCouriers = $allCouriers->filter(function (Courier $c) use ($targetHubId, $pickupMun) {
            $assignedHub = $c->assigned_hub;
            if (! $assignedHub) {
                return false;
            }
            if ($targetHubId && (int) $assignedHub->id === (int) $targetHubId) {
                return true;
            }
            if ($pickupMun !== '' && $this->addressService->normalizeName($assignedHub->municipality) === $pickupMun) {
                return true;
            }
            return false;
        })->values();

        $allRiders = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->where('logistics_profile_id', $profile->id)
            ->where('status', 'approved')
            ->orderBy('first_name')
            ->get();

        // Strictly filter Riders registered to the target Municipality / Hub (even for Main Hub!)
        $municipalityRiders = $allRiders->filter(function (Rider $r) use ($targetHubId, $deliveryMun, $pickupMun) {
            $assignedHub = $r->assigned_hub;
            if (! $assignedHub) {
                return false;
            }
            $riderMun = $this->addressService->normalizeName($assignedHub->municipality);
            if ($deliveryMun !== '' && $riderMun === $deliveryMun) {
                return true;
            }
            if ($targetHubId && (int) $assignedHub->id === (int) $targetHubId) {
                return true;
            }
            if ($pickupMun !== '' && $riderMun === $pickupMun) {
                return true;
            }
            return false;
        })->values();

        $matchingBarangayRiders = $municipalityRiders->filter(function (Rider $r) use ($deliveryBarangay) {
            if ($deliveryBarangay === '') {
                return true;
            }
            return $r->barangayCoverages->contains(function ($cov) use ($deliveryBarangay) {
                return $this->addressService->normalizeName($cov->barangay_name) === $deliveryBarangay;
            });
        })->values();

        $availableRiders = $matchingBarangayRiders->isNotEmpty() ? $matchingBarangayRiders : $municipalityRiders;

        return view('logistics.shipments.show', compact(
            'profile',
            'shipment',
            'availableCouriers',
            'availableRiders',
            'matchingBarangayRiders'
        ));
    }

    public function verifyPackageLookup(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'This shipment is not assigned to your logistics company.');
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:150'],
            'verification_method' => ['required', 'in:qr,manual'],
        ], [
            'lookup_input.required' => 'Please scan the package QR Code or enter the Tracking / Package Reference.',
        ]);

        $stage = $shipment->status === 'awaiting_seller_drop_off'
            ? 'seller_drop_off'
            : 'logistics_receiving';

        $result = $this->shipmentVerificationService->lookupPackageForActor(
            rawInput: $validated['lookup_input'],
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

        $orderNo = $shipment->sellerOrder?->seller_order_number ?? 'N/A';

        return back()->with(
            'success',
            "QR/Package Identified: {$result['package']->package_reference} (Order #{$orderNo}, Tracking #{$shipment->tracking_number}) verified via " . strtoupper($validated['verification_method']) . ". Please review the manifest and click Confirm Physical Receipt at Sorting Hub."
        );
    }

    public function assignCourier(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'This shipment is not assigned to your logistics company.');
        }

        $shipment->loadMissing('packages.logisticsHub');
        $primaryPackage = $shipment->packages->first();
        $targetHubId = $primaryPackage?->logistics_hub_id ?? ($shipment->hub_address_snapshot['logistics_hub_id'] ?? null);
        $pickupMun = $this->addressService->normalizeName(
            $primaryPackage?->logisticsHub?->municipality
            ?? ($shipment->hub_address_snapshot['municipality'] ?? ($shipment->pickup_address_snapshot['municipality'] ?? ''))
        );

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
            ->whereIn('status', ['active', 'on_delivery'])
            ->findOrFail($validated['courier_id']);

        $courierHub = $courier->assigned_hub;
        $matchesHubOrMun = ($targetHubId && $courierHub && (int) $courierHub->id === (int) $targetHubId)
            || ($pickupMun !== '' && $courierHub && $this->addressService->normalizeName($courierHub->municipality) === $pickupMun);

        if (! $matchesHubOrMun) {
            return back()->withErrors([
                'courier_id' => "Courier {$courier->full_name} is registered to {$courierHub?->municipality} and cannot be assigned to a shipment routed to another hub municipality.",
            ]);
        }

        DB::transaction(function () use ($shipment, $courier, $profile, $user, $validated) {
            $locked = Shipment::with(['sellerOrder.order', 'packages'])->where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['pickup_requested', 'courier_assigned'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'Courier can only be assigned when the shipment is awaiting pickup.',
                ]);
            }

            // Mark other couriers' pending assignments on this shipment as reassigned
            PickupAssignment::where('shipment_id', $locked->id)
                ->where('courier_id', '!=', $courier->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->update(['status' => 'reassigned']);

            $scheduledNote = ! empty($validated['scheduled_pickup_by'])
                ? '[Scheduled Pickup By: ' . \Carbon\Carbon::parse($validated['scheduled_pickup_by'])->format('M d, Y h:i A') . '] '
                : '[SLA: Pickup within 2 hours] ';

            // Single-record upsert per (shipment_id, courier_id) so duplicate cards never appear!
            PickupAssignment::updateOrCreate(
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

        return back()->with('success', "Courier {$courier->full_name} has been assigned to pick up Shipment #{$shipment->tracking_number}.");
    }

    public function verifyHubArrival(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        DB::transaction(function () use ($shipment, $profile, $user) {
            $locked = Shipment::with([
                'sellerOrder.order',
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

                $pkg->update([
                    'hub_verified_at'         => now(),
                    'hub_verified_by'         => $user->id,
                    'hub_verification_method' => $verifiedRecord?->verification_method ?? ($pkg->hub_verification_method ?: 'manual'),
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
            }

            if ($locked->currentCourier) {
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

            $receivingHub = $user->activeLogisticsHub();
            $hubName = $receivingHub?->hub_name ?? $profile->business_name;
            $hubLocation = ($receivingHub?->municipality ?? $profile->municipality) . ', ' . ($receivingHub?->province ?? $profile->province);

            if ($isSelfDropOff) {
                ShipmentTracking::create([
                    'shipment_id'     => $locked->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'received_at_hub',
                    'title'           => 'Seller Dropped Off Package — Verified & Received at Hub',
                    'description'     => "Seller personally dropped off the package at {$hubName}. Logistics verified package identification and confirmed physical receipt.",
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
                    'description'     => "Package verified and received at {$hubName} from Courier {$locked->currentCourier?->full_name}.",
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
                    ? "Seller drop-off verified and received at {$hubName}."
                    : "Courier handover verified and received at {$hubName}.",
            ]);
        });

        return back()->with('success', "Shipment #{$shipment->tracking_number} physical receipt verified at sorting hub. You can now assign a last-mile Rider.");
    }

    public function assignRider(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

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

        $shipment->loadMissing('packages.logisticsHub');
        $primaryPackage = $shipment->packages->first();
        $targetHubId = $primaryPackage?->logistics_hub_id ?? ($shipment->hub_address_snapshot['logistics_hub_id'] ?? null);
        $deliveryMun = $this->addressService->normalizeName($shipment->delivery_address_snapshot['municipality'] ?? '');
        $pickupMun = $this->addressService->normalizeName(
            $primaryPackage?->logisticsHub?->municipality
            ?? ($shipment->hub_address_snapshot['municipality'] ?? ($shipment->pickup_address_snapshot['municipality'] ?? ''))
        );

        $riderHub = $rider->assigned_hub;
        $riderMun = $this->addressService->normalizeName($riderHub?->municipality);

        $matchesMunicipalityOrHub = ($deliveryMun !== '' && $riderMun === $deliveryMun)
            || ($targetHubId && $riderHub && (int) $riderHub->id === (int) $targetHubId)
            || ($pickupMun !== '' && $riderMun === $pickupMun);

        if (! $matchesMunicipalityOrHub) {
            return back()->withErrors([
                'rider_id' => "Rider {$rider->full_name} is registered to {$riderHub?->municipality} and cannot be assigned to a shipment outside their registered hub municipality.",
            ]);
        }

        DB::transaction(function () use ($shipment, $rider, $profile, $user, $validated) {
            $locked = Shipment::with(['sellerOrder', 'packages'])->where('id', $shipment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['received_at_hub', 'rider_assigned', 'scheduled_redelivery'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => 'Rider can only be assigned when the package has been verified as received at the sorting hub or scheduled for redelivery.',
                ]);
            }

            // Mark other riders' pending assignments on this shipment as reassigned
            DeliveryAssignment::where('shipment_id', $locked->id)
                ->where('rider_id', '!=', $rider->id)
                ->whereIn('status', ['assigned', 'accepted', 'collected'])
                ->update(['status' => 'reassigned']);

            $nextAttemptNumber = (int) $locked->delivery_attempts_count + 1;
            $scheduledNote = ! empty($validated['scheduled_delivery_by'])
                ? '[Scheduled Delivery By: ' . \Carbon\Carbon::parse($validated['scheduled_delivery_by'])->format('M d, Y h:i A') . '] '
                : '[SLA: Dispatch within 2 hours] ';

            // Single-record upsert per (shipment_id, rider_id) so duplicate cards never appear on Rider dashboard!
            DeliveryAssignment::updateOrCreate(
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

        return back()->with('success', "Rider {$rider->full_name} has been assigned to deliver Shipment #{$shipment->tracking_number}.");
    }

    public function resolveFailedDelivery(Request $request, Shipment $shipment)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

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
            $newRider = Rider::where('logistics_profile_id', $profile->id)
                ->where('status', 'approved')
                ->findOrFail($validated['rider_id']);
        }

        $abandonmentShipment = null;
        $abandonmentOrder = null;
        $abandonmentReasonText = '';
        $abandonmentAttempts = 1;

        DB::transaction(function () use (
            $shipment,
            $profile,
            $user,
            $validated,
            $newRider,
            &$abandonmentShipment,
            &$abandonmentOrder,
            &$abandonmentReasonText,
            &$abandonmentAttempts
        ) {
            $locked = Shipment::with(['sellerOrder.order', 'sellerOrder.payment', 'deliveryAttempts', 'packages'])
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

                    DeliveryAssignment::updateOrCreate(
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

                $locked->sellerOrder->update([
                    'status'         => 'returned_to_seller',
                    'payment_status' => 'cancelled',
                    'cancelled_at'   => now(),
                ]);

                $locked->sellerOrder->payment?->update([
                    'status' => 'cancelled',
                ]);

                $this->inventoryStockService->restoreStockForSellerOrder($locked->sellerOrder);
                $this->voucherService->restoreVouchersForSellerOrder($locked->sellerOrder);

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
                    'remarks'         => "Failed delivery resolved as Return to Seller (RTS): {$failureReason}. Reserved stock restored and Buyer COD abandonment strike logged.",
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

        return back()->with('success', 'Failed delivery report resolved successfully.');
    }

    public function ridersIndex(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $filter = $request->query('filter', 'all');
        $search = trim((string) $request->query('search', ''));

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

        // Branch Hub only sees its own registered riders; Main Hub sees all riders across its branches
        if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
            $branchId = (int) $user->branchLogisticsHub->id;
            $query->whereHas('currentHubAssignment', fn ($q) => $q->where('logistics_hub_id', $branchId)->where('is_current', true));
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

        return view('logistics.riders.active-riders', compact('profile', 'riders', 'filter', 'search'));
    }
}