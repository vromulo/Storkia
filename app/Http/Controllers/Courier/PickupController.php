<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\ShipmentVerification;
use App\Notifications\OrderLifecycleNotification;
use App\Services\OrderCompletionService;
use App\Services\ShipmentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PickupController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected ShipmentVerificationService $shipmentVerificationService
    ) {}

    public function index(Request $request)
    {
        /** @var Courier $courier */
        $courier = Auth::guard('courier')->user();
        $courier->loadMissing('logisticsProfile');

        $statusFilter = $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = PickupAssignment::where('courier_id', $courier->id);

        $counts = [
            'active'    => (clone $baseQuery)->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'all'       => (clone $baseQuery)->count(),
        ];

        $query = PickupAssignment::with([
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.packages.verifications',
            'sellerOrder.items',
        ])->where('courier_id', $courier->id);

        if ($statusFilter === 'active') {
            $query->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub']);
        } elseif ($statusFilter === 'completed') {
            $query->where('status', 'completed');
        }

        if ($search !== '') {
            $query->whereHas('shipment', function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhereHas('sellerOrder', fn ($sq) => $sq->where('seller_order_number', 'like', "%{$search}%"))
                  ->orWhereHas('sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"));
            });
        }

        $assignments = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('courier.dashboard', compact(
            'courier',
            'assignments',
            'statusFilter',
            'search',
            'counts'
        ));
    }

    public function acceptAssignment(Request $request, PickupAssignment $assignment)
    {
        /** @var Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        DB::transaction(function () use ($assignment, $courier) {
            $locked = PickupAssignment::with('shipment')->where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'assigned') {
                throw ValidationException::withMessages([
                    'pickup' => 'This pickup assignment has already been accepted or processed.',
                ]);
            }

            $locked->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $locked->shipment_id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'courier_assigned',
                'title'           => 'Courier En Route to Seller',
                'description'     => "Courier {$courier->full_name} accepted the pickup assignment and is traveling to the seller's facility.",
                'location'        => $courier->home_hub,
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);
        });

        return back()->with('success', 'Pickup accepted. Please travel to the Seller facility to scan/verify package QR codes and collect the parcel.');
    }

    public function verifyPackageLookup(Request $request, PickupAssignment $assignment)
    {
        /** @var Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        $validated = $request->validate([
            'lookup_input'        => ['required', 'string', 'max:150'],
            'verification_method' => ['required', 'in:qr,manual'],
        ], [
            'lookup_input.required' => 'Please scan the package QR Code or enter the Tracking / Package Reference.',
        ]);

        $shipment = $assignment->shipment;
        if (! $shipment) {
            abort(404);
        }

        try {
            $result = $this->shipmentVerificationService->lookupPackageForActor(
                rawInput: $validated['lookup_input'],
                verificationMethod: $validated['verification_method'],
                stage: 'courier_pickup',
                actorType: 'Courier',
                actor: $courier
            );

            if ((int) $result['shipment']->id !== (int) $shipment->id) {
                throw ValidationException::withMessages([
                    'lookup_input' => 'Scanned package does not belong to this active pickup assignment.',
                ]);
            }

            $result['verification']->update([
                'is_confirmed' => true,
                'confirmed_at' => now(),
            ]);

            $result['package']->update([
                'courier_verified_at'         => now(),
                'courier_verification_method' => $validated['verification_method'],
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Package verified successfully! You can now confirm pickup collection.');
    }

    public function confirmPickup(Request $request, PickupAssignment $assignment)
    {
        /** @var Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $notifications = [];

        DB::transaction(function () use ($assignment, $courier, $validated, &$notifications) {
            $locked = PickupAssignment::with([
                'shipment.sellerOrder.order',
                'shipment.sellerOrder.buyer',
                'shipment.sellerOrder.seller',
                'shipment.logisticsProfile.user',
                'shipment.packages',
            ])
                ->where('id', $assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, ['assigned', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'pickup' => 'Package pickup has already been confirmed.',
                ]);
            }

            /** @var Shipment $shipment */
            $shipment = $locked->shipment;
            if ((int) $shipment->current_courier_id !== (int) $courier->id) {
                throw ValidationException::withMessages([
                    'pickup' => 'You are no longer the active courier assigned to this shipment.',
                ]);
            }

            foreach ($shipment->packages as $pkg) {
                if ($pkg->courier_verified_at === null) {
                    $verifiedBefore = ShipmentVerification::where('shipment_package_id', $pkg->id)
                        ->where('stage', 'courier_pickup')
                        ->where('actor_type', 'Courier')
                        ->where('actor_id', $courier->id)
                        ->where('is_confirmed', true)
                        ->exists();

                    if (! $verifiedBefore) {
                        throw ValidationException::withMessages([
                            'pickup' => "Package \"{$pkg->package_reference}\" must be scanned or verified via QR/manual lookup before confirming pickup handover.",
                        ]);
                    }
                }
            }

            $locked->update([
                'status'       => 'picked_up',
                'accepted_at'  => $locked->accepted_at ?? now(),
                'picked_up_at' => now(),
                'notes'        => $validated['notes'] ?? $locked->notes,
            ]);

            $shipment->update([
                'status'       => 'picked_up_from_seller',
                'picked_up_at' => now(),
            ]);

            $sellerOrder = $shipment->sellerOrder;
            $prevStatus = $sellerOrder->status;

            $sellerOrder->update([
                'status' => 'picked_up',
            ]);

            $pickupLocation = ($shipment->pickup_address_snapshot['municipality'] ?? '') . ', ' . ($shipment->pickup_address_snapshot['province'] ?? '');

            ShipmentTracking::create([
                'shipment_id'     => $shipment->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => 'picked_up_from_seller',
                'title'           => 'Package Picked Up by Courier',
                'description'     => "Courier {$courier->full_name} verified package QR/references and collected the parcel from {$shipment->pickup_address_snapshot['business_name']}.",
                'location'        => trim($pickupLocation, ', '),
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $prevStatus,
                'to_status'       => 'picked_up',
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'remarks'         => "Courier {$courier->full_name} confirmed first-mile pickup with verified package identification.",
            ]);

            $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);

            if ($sellerOrder->buyer) {
                $notifications[] = [
                    'recipient' => $sellerOrder->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Order Picked Up by Courier',
                        message: "Your package #{$shipment->tracking_number} has been verified and picked up from the seller.",
                        type: 'info',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            if ($shipment->logisticsProfile?->user) {
                $notifications[] = [
                    'recipient' => $shipment->logisticsProfile->user,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Courier Picked Up Parcel',
                        message: "Courier {$courier->full_name} collected Shipment #{$shipment->tracking_number} and is transporting it to your hub.",
                        type: 'info',
                        url: route('logistics.shipments.show', $shipment->id),
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

        return back()->with('success', 'Package pickup confirmed! Please transport the package to your assigned Logistics sorting hub.');
    }

    public function confirmTransportToHub(Request $request, PickupAssignment $assignment)
    {
        /** @var Courier $courier */
        $courier = Auth::guard('courier')->user();

        if ((int) $assignment->courier_id !== (int) $courier->id) {
            abort(403);
        }

        DB::transaction(function () use ($assignment, $courier) {
            $locked = PickupAssignment::with(['shipment.logisticsProfile'])->where('id', $assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'picked_up') {
                throw ValidationException::withMessages([
                    'pickup' => 'Package must be picked up from Seller before handing over to the hub.',
                ]);
            }

            $locked->update([
                'status'         => 'in_transit_to_hub',
                'handed_over_at' => now(),
            ]);

            $locked->shipment->update([
                'status' => 'in_transit_to_hub',
            ]);

            ShipmentTracking::create([
                'shipment_id'     => $locked->shipment_id,
                'seller_order_id' => $locked->seller_order_id,
                'status'          => 'in_transit_to_hub',
                'title'           => 'Arrived at Hub Drop-off (Pending Hub Verification)',
                'description'     => "Courier {$courier->full_name} transported the package to {$locked->shipment->logisticsProfile?->business_name} for facility intake verification.",
                'location'        => $locked->shipment->logisticsProfile?->municipality . ', ' . $locked->shipment->logisticsProfile?->province,
                'actor_type'      => 'Courier',
                'actor_id'        => $courier->id,
                'occurred_at'     => now(),
            ]);
        });

        return back()->with('success', 'Package handed over at the Logistics facility. Awaiting Logistics hub arrival verification.');
    }

    public function logout(Request $request)
    {
        Auth::guard('courier')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('logistics.login');
    }
}