<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\Log;

/**
 * Buyer-facing order status / tracking notifications (BUY-CHAT-003).
 *
 * Seller, logistics and rider controllers call one method here right AFTER their
 * database transaction commits. Every method:
 *   - sends exactly one notification to the buyer of the given seller order,
 *   - links to that order (route 'user.orders.show'),
 *   - never throws: a notification problem must not undo or block the seller's,
 *     logistics' or rider's action (same behaviour as the existing try/catch
 *     notifications in the courier and rider controllers).
 *
 * Deliberately NOT here: anything about payments (GCash, COD collection),
 * refunds or returns. Statuses that already notified the buyer elsewhere
 * (order placed, picked up, out for delivery, delivered, auto-cancel, COD
 * abandonment) are not repeated either.
 */
class BuyerOrderNotificationService
{
    /** Seller accepted the order (status: preparing). */
    public function accepted(SellerOrder $sellerOrder): void
    {
        $order = $this->reload($sellerOrder);
        if (! $order) {
            return;
        }

        $this->send(
            $order,
            'Order Accepted',
            "{$this->storeName($order)} accepted your order #{$order->seller_order_number} and is now preparing your items.",
            'success'
        );
    }

    /** Seller rejected / cancelled the order before handing it to logistics. */
    public function rejected(SellerOrder $sellerOrder, ?string $reason = null): void
    {
        $order = $this->reload($sellerOrder);
        if (! $order) {
            return;
        }

        $reasonText = $reason ? " Reason: {$reason}" : '';

        $this->send(
            $order,
            'Order Rejected by Seller',
            "{$this->storeName($order)} could not fulfil your order #{$order->seller_order_number} and it was cancelled.{$reasonText}",
            'danger'
        );
    }

    /** Seller prepared the shipment (status: ready_for_pickup). */
    public function readyForPickup(SellerOrder $sellerOrder, ?Shipment $shipment = null, ?string $handoverMethod = null): void
    {
        $order = $this->reload($sellerOrder);
        if (! $order) {
            return;
        }

        $tracking = $shipment?->tracking_number ? " Tracking #{$shipment->tracking_number}." : '';

        $detail = $handoverMethod === 'self_drop_off'
            ? 'is dropping the parcel off at the logistics hub'
            : 'has requested a courier pickup';

        $this->send(
            $order,
            'Order Ready for Pickup',
            "{$this->storeName($order)} packed your order #{$order->seller_order_number} and {$detail}.{$tracking}",
            'info',
            $shipment?->id
        );
    }

    /** Parcel was received and verified at the logistics sorting hub. */
    public function arrivedAtHub(Shipment $shipment): void
    {
        $order = $this->orderOf($shipment);
        if (! $order) {
            return;
        }

        $hubName = data_get($shipment->hub_address_snapshot, 'hub_name');
        $at = $hubName ? "arrived at {$hubName}" : 'arrived at the logistics sorting hub';

        $this->send(
            $order,
            'Parcel Arrived at Logistics Hub',
            "Your package #{$shipment->tracking_number} (order #{$order->seller_order_number}) {$at} and is being sorted for delivery.",
            'info',
            $shipment->id
        );
    }

    /** A delivery attempt failed but the parcel is NOT being returned yet. */
    public function deliveryAttemptFailed(Shipment $shipment, int $attemptNumber, string $reason): void
    {
        $order = $this->orderOf($shipment);
        if (! $order) {
            return;
        }

        $this->send(
            $order,
            "Delivery Attempt #{$attemptNumber} of 3 Unsuccessful",
            "We couldn't deliver order #{$order->seller_order_number} ({$reason}). Logistics will arrange another attempt, so please keep your phone reachable and make sure someone can receive the parcel.",
            'danger',
            $shipment->id
        );
    }

    /** Logistics scheduled another delivery attempt after a failed one. */
    public function redeliveryScheduled(Shipment $shipment): void
    {
        $order = $this->orderOf($shipment);
        if (! $order) {
            return;
        }

        $next = (int) ($shipment->fresh()?->delivery_attempts_count ?? $shipment->delivery_attempts_count) + 1;

        $this->send(
            $order,
            'Redelivery Scheduled',
            "Another delivery attempt (#{$next} of 3) has been scheduled for order #{$order->seller_order_number}. Please make sure someone can receive the parcel.",
            'info',
            $shipment->id
        );
    }

    // ------------------------------------------------------------------ helpers

    protected function send(SellerOrder $sellerOrder, string $title, string $message, string $type = 'info', ?int $shipmentId = null): void
    {
        try {
            $buyer = $sellerOrder->buyer;

            if (! $buyer) {
                return;
            }

            $buyer->notify(new OrderLifecycleNotification(
                title: $title,
                message: $message,
                type: $type,
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id,
                shipmentId: $shipmentId
            ));
        } catch (\Throwable $e) {
            Log::warning('Buyer order notification failed', [
                'seller_order_id' => $sellerOrder->id,
                'title'           => $title,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /** Fresh copy of the order (the controller's instance is stale after its transaction). */
    protected function reload(SellerOrder $sellerOrder): ?SellerOrder
    {
        try {
            return $sellerOrder->fresh(['buyer', 'sellerProfile']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function orderOf(Shipment $shipment): ?SellerOrder
    {
        $sellerOrder = $shipment->sellerOrder;

        return $sellerOrder ? $this->reload($sellerOrder) : null;
    }

    protected function storeName(SellerOrder $sellerOrder): string
    {
        return $sellerOrder->sellerProfile?->business_name ?: 'The seller';
    }
}