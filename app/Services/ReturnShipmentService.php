<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\PickupAssignment;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReturnShipmentService
{
    public function __construct(
        protected ReturnRefundService $returnRefundService,
        protected ReturnRefundLedgerService $ledger,
        protected AddressService $addressService
    ) {}

    public function eligibleRiders(ReturnShipment $returnShipment, Collection $riders): Collection
    {
        $pickupSnap = (array) ($returnShipment->pickup_address_snapshot ?? []);

        return $riders
            ->filter(fn (Rider $rider) => $this->riderCoversReturn($rider, $returnShipment))
            ->sortBy(function (Rider $rider) use ($pickupSnap) {
                $eval = $rider->evaluateDeliveryEligibilityForSnapshot($pickupSnap);
                $priority = $eval['exact_barangay_match'] ? 0 : ($eval['same_municipality'] ? 100 : 200);
                return $priority + ($eval['distance_from_rider_km'] ?? 0);
            })
            ->values();
    }

    public function eligibleCouriers(ReturnShipment $returnShipment, Collection $couriers): Collection
    {
        $returnSnap = (array) ($returnShipment->return_address_snapshot ?? []);

        return $couriers
            ->filter(fn (Courier $courier) => $this->courierServesHub($courier, $returnShipment))
            ->sortBy(fn (Courier $courier) => $courier->distanceToSnapshotKm($returnSnap))
            ->values();
    }

    public function assignRider(ReturnShipment $returnShipment, int $riderId, int $logisticsProfileId, int $assignedByUserId, ?string $notes = null): ReturnShipment
    {
        $pending = [];
        $previousRiderId = $returnShipment->current_rider_id ? (int) $returnShipment->current_rider_id : null;

        DB::transaction(function () use ($returnShipment, $riderId, $logisticsProfileId, $assignedByUserId, $notes, &$pending) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertOwnedBy($locked, $logisticsProfileId);
            $this->assertRequestInTransit($locked);

            if (! in_array($locked->status, [
                ReturnShipment::STATUS_AWAITING_RIDER,
                ReturnShipment::STATUS_PICKUP_FAILED,
                ReturnShipment::STATUS_RIDER_ASSIGNED,
            ], true)) {
                $this->fail('A rider can only be assigned before the parcel is collected from the buyer.');
            }

            /** @var Rider|null $rider */
            $rider = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
                ->where('logistics_profile_id', $logisticsProfileId)
                ->where('status', 'approved')
                ->find($riderId);

            if (! $rider) {
                $this->fail('Choose an approved rider from your logistics company.');
            }

            if (! $this->riderCoversReturn($rider, $locked)) {
                $eval = $rider->evaluateDeliveryEligibilityForSnapshot((array) ($locked->pickup_address_snapshot ?? []));
                $this->fail($eval['reason'] ?: ("Rider {$rider->full_name} is registered to " . ($rider->assigned_hub?->municipality ?? 'no hub') . ' and cannot collect a parcel outside their last-mile hub area.'));
            }

            $locked->update([
                'current_rider_id'      => $rider->id,
                'status'                => ReturnShipment::STATUS_RIDER_ASSIGNED,
                'rider_assigned_at'     => now(),
                'pickup_failure_reason' => null,
            ]);

            $note = $notes !== null && trim($notes) !== '' ? ' Note: ' . trim($notes) : '';
            $locked->recordEvent(
                'Rider Assigned for Return Pickup',
                "Rider {$rider->full_name} ({$rider->vehicle_type} - {$rider->plate_number}) will collect the parcel from the buyer.{$note}",
                $this->place($locked->pickup_address_snapshot),
                'Logistics',
                $assignedByUserId,
                ReturnShipment::STATUS_RIDER_ASSIGNED
            );

            $order = $locked->sellerOrder;

            $this->queue(
                $pending,
                $rider,
                'New Return Pickup Assignment',
                "Collect the return parcel {$locked->tracking_number} from the buyer at " . $this->place($locked->pickup_address_snapshot)
                    . ' and take it to the sorting hub. Ask the buyer for the item and everything it came with, and take a clear photo as proof.',
                'info',
                $this->url('rider.returns.index'),
                $locked
            );

            $this->queue(
                $pending,
                $order?->buyer,
                'Rider Assigned to Collect Your Return',
                "A rider was assigned to collect your return for Order #{$order?->seller_order_number} ({$locked->tracking_number}). Please keep the parcel packed and be available at your delivery address.",
                'info',
                $this->url('user.orders.show', $order?->id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.rider_assigned');
            if ($fresh->returnRequest) {
                RealtimeBroadcastService::forReturnRequest($fresh->returnRequest, 'return.rider_assigned');
            }
            if ($previousRiderId && $previousRiderId !== (int) $fresh->current_rider_id) {
                RealtimeBroadcastService::send(
                    channels: ['rider.' . $previousRiderId],
                    scope: 'return_shipment',
                    action: 'return_shipment.reassigned',
                    payload: [
                        'return_shipment_id' => (int) $fresh->id,
                        'tracking_number'    => (string) $fresh->tracking_number,
                    ]
                );
            }
        }

        return $fresh ?? $returnShipment;
    }

    public function receiveAtHub(ReturnShipment $returnShipment, int $logisticsProfileId, int $verifiedByUserId, ?string $notes = null): ReturnShipment
    {
        $pending = [];

        DB::transaction(function () use ($returnShipment, $logisticsProfileId, $verifiedByUserId, $notes, &$pending) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertOwnedBy($locked, $logisticsProfileId);
            $this->assertRequestInTransit($locked);

            if ($locked->status !== ReturnShipment::STATUS_PICKED_UP_FROM_BUYER) {
                $this->fail('Only a parcel that the rider already collected from the buyer can be received at the hub.');
            }

            $locked->update([
                'status'            => ReturnShipment::STATUS_RECEIVED_AT_HUB,
                'arrived_at_hub_at' => now(),
                'hub_verified_by'   => $verifiedByUserId,
            ]);

            $this->ledger->creditReturnLegPay($locked, 'rider');

            $hubName = $locked->hub_address_snapshot['hub_name'] ?? ($locked->hub_address_snapshot['name'] ?? 'the sorting hub');
            $note = $notes !== null && trim($notes) !== '' ? ' Note: ' . trim($notes) : '';

            $locked->recordEvent(
                'Received at Sorting Hub',
                "The returned parcel was checked and received at {$hubName}.{$note}",
                $this->place($locked->hub_address_snapshot),
                'Logistics',
                $verifiedByUserId,
                ReturnShipment::STATUS_RECEIVED_AT_HUB
            );

            $order = $locked->sellerOrder;

            $this->queue(
                $pending,
                $order?->buyer,
                'Your Return Reached the Sorting Hub',
                "Your returned parcel for Order #{$order?->seller_order_number} arrived at the sorting hub. It is now being sent back to the seller.",
                'info',
                $this->url('user.orders.show', $order?->id),
                $locked
            );

            $this->queue(
                $pending,
                $order?->seller,
                'Returned Parcel Reached the Sorting Hub',
                "The parcel returned for Order #{$order?->seller_order_number} ({$locked->tracking_number}) arrived at the sorting hub and will be delivered to you soon.",
                'info',
                $this->url('seller.returns.show', $locked->return_request_id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.received_at_hub');
            if ($fresh->current_rider_id) {
                RealtimeBroadcastService::forWalletUpdate('Rider', (int) $fresh->current_rider_id, 'return_pickup_pay.credited', [
                    'return_shipment_id' => (int) $fresh->id,
                    'rider_pay'          => (float) ($fresh->rider_pay ?? 0),
                ]);
            }
        }

        return $fresh ?? $returnShipment;
    }

    public function assignCourier(ReturnShipment $returnShipment, int $courierId, int $logisticsProfileId, int $assignedByUserId, ?string $notes = null): ReturnShipment
    {
        $pending = [];

        DB::transaction(function () use ($returnShipment, $courierId, $logisticsProfileId, $assignedByUserId, $notes, &$pending) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertOwnedBy($locked, $logisticsProfileId);
            $this->assertRequestInTransit($locked);

            if (! in_array($locked->status, [
                ReturnShipment::STATUS_RECEIVED_AT_HUB,
                ReturnShipment::STATUS_DELIVERY_FAILED,
                ReturnShipment::STATUS_COURIER_ASSIGNED,
            ], true)) {
                $this->fail('A courier can only be assigned after the parcel was received at the hub, and before it leaves for the seller.');
            }

            /** @var Courier|null $courier */
            $courier = Courier::with('currentHubAssignment.hub')
                ->where('logistics_profile_id', $logisticsProfileId)
                ->whereIn('status', ['active', 'Active', 'on_delivery'])
                ->find($courierId);

            if (! $courier) {
                $this->fail('Choose an active courier from your logistics company.');
            }

            if (! $this->courierServesHub($courier, $locked)) {
                $this->fail("Courier {$courier->full_name} is registered to " . ($courier->assigned_hub?->municipality ?? 'no hub')
                    . ' and cannot carry a parcel routed to a seller outside their hub province.');
            }

            $previousCourierId = $locked->current_courier_id ? (int) $locked->current_courier_id : null;

            $updates = [
                'current_courier_id'  => $courier->id,
                'status'              => ReturnShipment::STATUS_COURIER_ASSIGNED,
                'courier_assigned_at' => now(),
            ];
            if (Schema::hasColumn('return_shipments', 'delivery_failure_reason')) {
                $updates['delivery_failure_reason'] = null;
            }
            $locked->update($updates);

            $courier->update(['status' => 'on_delivery']);

            if ($previousCourierId && $previousCourierId !== (int) $courier->id) {
                $this->releaseCourier($previousCourierId, (int) $locked->id);
            }

            $note = $notes !== null && trim($notes) !== '' ? ' Note: ' . trim($notes) : '';
            $locked->recordEvent(
                'Courier Assigned for Return Delivery',
                "Courier {$courier->full_name} ({$courier->vehicle_type} - {$courier->plate_number}) will bring the parcel back to the seller.{$note}",
                $this->place($locked->hub_address_snapshot),
                'Logistics',
                $assignedByUserId,
                ReturnShipment::STATUS_COURIER_ASSIGNED
            );

            $order = $locked->sellerOrder;

            $this->queue(
                $pending,
                $courier,
                'New Return Delivery Assignment',
                "Collect the return parcel {$locked->tracking_number} at the sorting hub and deliver it to the seller at " . $this->place($locked->return_address_snapshot)
                    . '. Take a clear photo as proof of delivery.',
                'info',
                $this->url('courier.returns.index'),
                $locked
            );

            $this->queue(
                $pending,
                $order?->seller,
                'Courier Assigned to Bring Back Your Returned Parcel',
                "A courier was assigned to deliver the parcel returned for Order #{$order?->seller_order_number} ({$locked->tracking_number}) to you.",
                'info',
                $this->url('seller.returns.show', $locked->return_request_id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.courier_assigned');
        }

        return $fresh ?? $returnShipment;
    }

    public function riderConfirmPickup(ReturnShipment $returnShipment, Rider $rider, string $photoPath, ?string $notes = null): ReturnShipment
    {
        $pending = [];

        DB::transaction(function () use ($returnShipment, $rider, $photoPath, $notes, &$pending) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertRider($locked, $rider);
            $this->assertRequestInTransit($locked);

            if ($locked->status !== ReturnShipment::STATUS_RIDER_ASSIGNED) {
                $this->fail('This return is not waiting to be collected from the buyer.');
            }

            $locked->update([
                'status'                  => ReturnShipment::STATUS_PICKED_UP_FROM_BUYER,
                'picked_up_from_buyer_at' => now(),
                'buyer_pickup_photo'      => $photoPath,
            ]);

            $note = $notes !== null && trim($notes) !== '' ? ' Note: ' . trim($notes) : '';
            $locked->recordEvent(
                'Picked Up From Buyer',
                "Rider {$rider->full_name} collected the parcel from the buyer and is heading to the sorting hub.{$note}",
                $this->place($locked->pickup_address_snapshot),
                'Rider',
                (int) $rider->id,
                ReturnShipment::STATUS_PICKED_UP_FROM_BUYER
            );

            $order = $locked->sellerOrder;

            $this->queue(
                $pending,
                $order?->buyer,
                'Your Return Was Picked Up',
                "The rider collected your return for Order #{$order?->seller_order_number}. It is on its way to the sorting hub ({$locked->tracking_number}).",
                'success',
                $this->url('user.orders.show', $order?->id),
                $locked
            );

            $this->queue(
                $pending,
                $order?->seller,
                'Buyer\'s Return Was Picked Up',
                "The parcel for Order #{$order?->seller_order_number} was collected from the buyer and is on its way back to you ({$locked->tracking_number}).",
                'info',
                $this->url('seller.returns.show', $locked->return_request_id),
                $locked
            );

            $this->queue(
                $pending,
                $order?->logisticsProfile?->user,
                'Return Parcel On Its Way to Your Hub',
                "Rider {$rider->full_name} collected {$locked->tracking_number}. Check it and confirm receipt once it arrives at the hub.",
                'info',
                $this->url('logistics.returns.show', $locked->id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.picked_up_from_buyer');
        }

        return $fresh ?? $returnShipment;
    }

    public function riderReportPickupFailed(ReturnShipment $returnShipment, Rider $rider, string $reason, ?string $notes = null): ReturnShipment
    {
        $pending = [];
        $closeMessage = null;
        $requestId = null;
        $previousRiderId = (int) $rider->id;

        DB::transaction(function () use ($returnShipment, $rider, $reason, $notes, &$pending, &$closeMessage, &$requestId) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertRider($locked, $rider);
            $this->assertRequestInTransit($locked);

            if ($locked->status !== ReturnShipment::STATUS_RIDER_ASSIGNED) {
                $this->fail('This return is not waiting to be collected from the buyer.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                $this->fail('Please tell us why the parcel could not be collected (at least 5 characters).');
            }

            $attempts = (int) $locked->pickup_attempts_count + 1;
            $full = trim($reason . ($notes !== null && trim($notes) !== '' ? ' - ' . trim($notes) : ''));

            $locked->update([
                'status'                => ReturnShipment::STATUS_PICKUP_FAILED,
                'pickup_attempts_count' => $attempts,
                'pickup_failure_reason' => mb_substr($full, 0, 120),
                'failed_at'             => now(),
                'current_rider_id'      => null,
            ]);

            $max = ReturnShipment::MAX_PICKUP_ATTEMPTS;
            $locked->recordEvent(
                "Return Pickup Failed (Attempt {$attempts} of {$max})",
                "Rider {$rider->full_name} could not collect the parcel: {$full}",
                $this->place($locked->pickup_address_snapshot),
                'Rider',
                (int) $rider->id,
                ReturnShipment::STATUS_PICKUP_FAILED
            );

            $order = $locked->sellerOrder;
            $requestId = (int) $locked->return_request_id;

            if ($attempts >= $max) {
                $closeMessage = "The rider could not collect the returned parcel after {$max} attempts, so the return request was closed without a refund.";
                return;
            }

            $this->queue(
                $pending,
                $order?->buyer,
                'Return Pickup Attempt Failed',
                "The rider could not collect your return for Order #{$order?->seller_order_number} ({$full}). We will try again. Please keep the parcel ready and be available. After {$max} failed attempts the request is closed without a refund.",
                'danger',
                $this->url('user.orders.show', $order?->id),
                $locked
            );

            $this->queue(
                $pending,
                $order?->logisticsProfile?->user,
                'Return Pickup Failed: Assign a Rider Again',
                "{$locked->tracking_number} could not be collected (attempt {$attempts} of {$max}): {$full}. Please assign a rider for another attempt.",
                'danger',
                $this->url('logistics.returns.show', $locked->id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        if ($closeMessage !== null && $requestId !== null) {
            $request = ReturnRequest::find($requestId);
            if ($request) {
                $this->returnRefundService->closeWithoutRefund(
                    $request,
                    ReturnRequest::STATUS_CLOSED,
                    'pickup_failed',
                    $closeMessage,
                    'System'
                );
            }
        }

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.pickup_failed');
            if ($previousRiderId > 0) {
                RealtimeBroadcastService::send(
                    channels: ['rider.' . $previousRiderId],
                    scope: 'return_shipment',
                    action: 'return_shipment.pickup_failed',
                    payload: [
                        'return_shipment_id' => (int) $fresh->id,
                        'tracking_number'    => (string) $fresh->tracking_number,
                        'status'             => (string) $fresh->status,
                    ]
                );
            }
        }

        return $fresh ?? $returnShipment;
    }

    public function courierStartDelivery(ReturnShipment $returnShipment, Courier $courier): ReturnShipment
    {
        $pending = [];

        DB::transaction(function () use ($returnShipment, $courier, &$pending) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertCourier($locked, $courier);
            $this->assertRequestInTransit($locked);

            if ($locked->status !== ReturnShipment::STATUS_COURIER_ASSIGNED) {
                $this->fail('This return is not waiting to be collected from the sorting hub.');
            }

            $locked->update([
                'status'                => ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
                'picked_up_from_hub_at' => now(),
            ]);

            $locked->recordEvent(
                'On the Way Back to Seller',
                "Courier {$courier->full_name} collected the parcel from the sorting hub and is heading to the seller.",
                $this->place($locked->hub_address_snapshot),
                'Courier',
                (int) $courier->id,
                ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY
            );

            $order = $locked->sellerOrder;

            $this->queue(
                $pending,
                $order?->seller,
                'Returned Parcel Is On Its Way',
                "The parcel returned for Order #{$order?->seller_order_number} ({$locked->tracking_number}) is out for delivery to you. Please be ready to receive it.",
                'info',
                $this->url('seller.returns.show', $locked->return_request_id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.out_for_return_delivery');
        }

        return $fresh ?? $returnShipment;
    }

    public function courierReportDeliveryFailed(ReturnShipment $returnShipment, Courier $courier, string $reason, ?string $notes = null): ReturnShipment
    {
        $pending = [];

        DB::transaction(function () use ($returnShipment, $courier, $reason, $notes, &$pending) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertCourier($locked, $courier);
            $this->assertRequestInTransit($locked);

            if ($locked->status !== ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY) {
                $this->fail('Only a return parcel currently out for delivery to the seller can be marked as failed.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 5) {
                $this->fail('Please explain why the parcel could not be handed over to the seller (at least 5 characters).');
            }

            $attempts = (int) ($locked->delivery_attempts_count ?? 0) + 1;
            $full = trim($reason . ($notes !== null && trim($notes) !== '' ? ' - ' . trim($notes) : ''));

            $updates = [
                'status'             => ReturnShipment::STATUS_DELIVERY_FAILED,
                'current_courier_id' => null,
            ];
            if (Schema::hasColumn('return_shipments', 'delivery_attempts_count')) {
                $updates['delivery_attempts_count'] = $attempts;
                $updates['delivery_failure_reason'] = mb_substr($full, 0, 150);
                $updates['delivery_failed_at'] = now();
            }

            $locked->update($updates);

            $this->releaseCourier((int) $courier->id, (int) $locked->id);

            $max = ReturnShipment::MAX_DELIVERY_ATTEMPTS;
            $locked->recordEvent(
                "Return Delivery to Seller Unsuccessful (Attempt {$attempts} of {$max})",
                "Courier {$courier->full_name} could not hand over the returned parcel to the seller ({$full}). Parcel returned to the sorting hub for re-dispatch.",
                $this->place($locked->return_address_snapshot),
                'Courier',
                (int) $courier->id,
                ReturnShipment::STATUS_DELIVERY_FAILED
            );

            $order = $locked->sellerOrder;

            $this->queue(
                $pending,
                $order?->seller,
                'Return Delivery Attempt Unsuccessful',
                "Our courier attempted to deliver the returned parcel for Order #{$order?->seller_order_number} ({$locked->tracking_number}), but could not complete the handover ({$full}). Logistics will schedule another delivery attempt.",
                'warning',
                $this->url('seller.returns.show', $locked->return_request_id),
                $locked
            );

            $this->queue(
                $pending,
                $order?->logisticsProfile?->user,
                'Return Delivery to Seller Failed: Reassign Courier',
                "Courier {$courier->full_name} could not deliver return {$locked->tracking_number} to the seller ({$full}). Please assign a courier for redelivery.",
                'danger',
                $this->url('logistics.returns.show', $locked->id),
                $locked
            );
        });

        $this->runAfterCommit($pending);

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.delivery_failed');
        }

        return $fresh ?? $returnShipment;
    }

    public function courierConfirmDelivered(ReturnShipment $returnShipment, Courier $courier, string $photoPath, ?string $notes = null): ReturnShipment
    {
        DB::transaction(function () use ($returnShipment, $courier, $photoPath, $notes) {
            $locked = $this->lockShipment($returnShipment->id);
            $this->assertCourier($locked, $courier);

            if ($locked->status === ReturnShipment::STATUS_DELIVERED_TO_SELLER) {
                return;
            }

            $this->assertRequestInTransit($locked);

            if ($locked->status !== ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY) {
                $this->fail('Start the delivery first, then confirm once the parcel is handed to the seller.');
            }

            $locked->update([
                'status'                 => ReturnShipment::STATUS_DELIVERED_TO_SELLER,
                'delivered_to_seller_at' => now(),
                'seller_delivery_photo'  => $photoPath,
            ]);

            $this->ledger->creditReturnLegPay($locked, 'courier');
            $this->releaseCourier((int) $courier->id, (int) $locked->id);

            $note = $notes !== null && trim($notes) !== '' ? ' Note: ' . trim($notes) : '';
            $locked->recordEvent(
                'Delivered to Seller',
                "Courier {$courier->full_name} handed the returned parcel to the seller.{$note}",
                $this->place($locked->return_address_snapshot),
                'Courier',
                (int) $courier->id,
                ReturnShipment::STATUS_DELIVERED_TO_SELLER
            );
        });

        $request = ReturnRequest::find($returnShipment->return_request_id);
        if ($request) {
            $this->returnRefundService->markReturnDelivered($request);
        }

        $fresh = $returnShipment->fresh(['rider', 'courier', 'returnRequest']);
        if ($fresh) {
            RealtimeBroadcastService::forReturnShipment($fresh, 'return_shipment.delivered_to_seller');
            RealtimeBroadcastService::forWalletUpdate('Courier', (int) $courier->id, 'return_delivery_pay.credited', [
                'return_shipment_id' => (int) $fresh->id,
                'courier_pay'        => (float) ($fresh->courier_pay ?? 0),
            ]);
        }

        return $fresh ?? $returnShipment;
    }

    protected function lockShipment(int $id): ReturnShipment
    {
        return ReturnShipment::with([
            'returnRequest',
            'sellerOrder.order',
            'sellerOrder.buyer',
            'sellerOrder.seller',
            'sellerOrder.logisticsProfile.user',
        ])->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    protected function assertOwnedBy(ReturnShipment $locked, int $logisticsProfileId): void
    {
        if ((int) $locked->logistics_profile_id !== $logisticsProfileId) {
            $this->fail('This return belongs to another logistics company.');
        }
    }

    protected function assertRider(ReturnShipment $locked, Rider $rider): void
    {
        if ((int) $locked->current_rider_id !== (int) $rider->id) {
            $this->fail('This return is not assigned to you.');
        }
    }

    protected function assertCourier(ReturnShipment $locked, Courier $courier): void
    {
        if ((int) $locked->current_courier_id !== (int) $courier->id) {
            $this->fail('This return is not assigned to you.');
        }
    }

    protected function assertRequestInTransit(ReturnShipment $locked): void
    {
        if ($locked->returnRequest?->status !== ReturnRequest::STATUS_RETURN_IN_TRANSIT) {
            $this->fail('This return request is no longer active, so the parcel does not need to move.');
        }
    }

    protected function riderCoversReturn(Rider $rider, ReturnShipment $returnShipment): bool
    {
        $pickupSnap = (array) ($returnShipment->pickup_address_snapshot ?? []);
        $eval = $rider->evaluateDeliveryEligibilityForSnapshot($pickupSnap);

        return (bool) $eval['is_eligible'];
    }

    protected function courierServesHub(Courier $courier, ReturnShipment $returnShipment): bool
    {
        $returnSnap = (array) ($returnShipment->return_address_snapshot ?? []);
        $eval = $courier->evaluatePickupEligibilityForSnapshot($returnSnap, (float) ($returnShipment->total_weight ?? 0));

        if ($eval['within_range']) {
            return true;
        }

        $hub = $courier->assigned_hub;
        if (! $hub) {
            return false;
        }

        $hubMunicipality = $this->addressService->normalizeName($returnShipment->hub_address_snapshot['municipality'] ?? '');
        $targetHubId = $returnShipment->hub_address_snapshot['logistics_hub_id'] ?? null;

        return ($targetHubId && (int) $hub->id === (int) $targetHubId)
            || ($hubMunicipality !== '' && $this->addressService->normalizeName($hub->municipality) === $hubMunicipality);
    }

    protected function releaseCourier(int $courierId, int $exceptReturnShipmentId): void
    {
        $courier = Courier::find($courierId);
        if (! $courier || $courier->status !== 'on_delivery') {
            return;
        }

        $busyWithPickup = PickupAssignment::where('courier_id', $courierId)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        $busyWithReturn = ReturnShipment::where('current_courier_id', $courierId)
            ->where('id', '!=', $exceptReturnShipmentId)
            ->whereIn('status', [
                ReturnShipment::STATUS_COURIER_ASSIGNED,
                ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
            ])
            ->exists();

        if (! $busyWithPickup && ! $busyWithReturn) {
            $courier->update(['status' => 'active']);
        }
    }

    protected function place(?array $snapshot): string
    {
        $snapshot = (array) $snapshot;
        $parts = array_filter([
            $snapshot['barangay'] ?? null,
            $snapshot['municipality'] ?? null,
            $snapshot['province'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : (string) ($snapshot['full_address'] ?? 'Location on file');
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['return' => $message]);
    }

    protected function queue(array &$pending, ?object $recipient, string $title, string $message, string $type, ?string $url, ReturnShipment $returnShipment): void
    {
        if (! $recipient) {
            return;
        }

        $order = $returnShipment->sellerOrder;

        $pending[] = [
            $recipient,
            new OrderLifecycleNotification(
                $title,
                $message,
                $type,
                $url,
                $order ? (int) $order->order_id : null,
                (int) $returnShipment->seller_order_id,
                $returnShipment->shipment_id ? (int) $returnShipment->shipment_id : null
            ),
        ];
    }

    protected function runAfterCommit(array $pending): void
    {
        foreach ($pending as [$recipient, $notification]) {
            try {
                $recipient->notify($notification);
            } catch (\Throwable $e) {
            }
        }
    }

    protected function url(string $routeName, mixed $parameter = null): ?string
    {
        try {
            return $parameter === null ? route($routeName) : route($routeName, $parameter);
        } catch (\Throwable $e) {
            return null;
        }
    }
}