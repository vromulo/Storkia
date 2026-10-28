<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCompletionService
{
    public function autoCompleteExpiredDeliveredOrders(?int $buyerId = null, ?int $sellerId = null, ?int $logisticsProfileId = null): int
    {
        $query = SellerOrder::query()
            ->where('status', 'delivered')
            ->where('has_unresolved_dispute', false)
            ->whereNotNull('confirmation_deadline_at')
            ->where('confirmation_deadline_at', '<=', now());

        if ($buyerId !== null) {
            $query->where('buyer_id', $buyerId);
        }

        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        if ($logisticsProfileId !== null) {
            $query->where('logistics_profile_id', $logisticsProfileId);
        }

        $expiredOrders = $query->limit(50)->get();
        $completedCount = 0;

        foreach ($expiredOrders as $expiredOrder) {
            try {
                $this->confirmSellerOrder(
                    sellerOrder: $expiredOrder,
                    actorType: 'System',
                    actorId: null,
                    isAutoCompleted: true
                );
                $completedCount++;
            } catch (\Throwable $e) {
            }
        }

        return $completedCount;
    }

    public function confirmSellerOrder(SellerOrder $sellerOrder, string $actorType = 'Buyer', ?int $actorId = null, bool $isAutoCompleted = false): SellerOrder
    {
        $notifications = [];

        $updatedSellerOrder = DB::transaction(function () use ($sellerOrder, $actorType, $actorId, $isAutoCompleted, &$notifications) {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::with(['order', 'seller', 'buyer', 'logisticsProfile.user'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'completed' || $locked->completed_at !== null) {
                return $locked;
            }

            if ($locked->has_unresolved_dispute || $locked->status === 'disputed') {
                throw ValidationException::withMessages([
                    'order' => 'This order cannot be completed while an unresolved delivery dispute exists.',
                ]);
            }

            if ($locked->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order' => 'Only delivered orders can be confirmed as completed.',
                ]);
            }

            $previousStatus = $locked->status;

            $locked->update([
                'status'             => 'completed',
                'buyer_confirmed_at' => $isAutoCompleted ? $locked->buyer_confirmed_at : now(),
                'completed_at'       => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $previousStatus,
                'to_status'       => 'completed',
                'actor_type'      => $actorType,
                'actor_id'        => $actorId,
                'remarks'         => $isAutoCompleted
                    ? 'Order automatically completed after the 72-hour buyer confirmation window expired without dispute.'
                    : 'Buyer manually confirmed order receipt.',
            ]);

            $this->syncParentOrderStatus($locked->order);

            if ($locked->seller) {
                $notifications[] = [
                    'recipient' => $locked->seller,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Order Completed',
                        message: "Seller Order #{$locked->seller_order_number} has been marked as completed.",
                        type: 'info',
                        url: route('seller.orders.show', $locked->id),
                        orderId: $locked->order_id,
                        sellerOrderId: $locked->id
                    ),
                ];
            }

            if ($isAutoCompleted && $locked->buyer) {
                $notifications[] = [
                    'recipient' => $locked->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Order Automatically Completed',
                        message: "Order #{$locked->seller_order_number} was automatically completed after the 72-hour confirmation period.",
                        type: 'info',
                        url: route('user.orders.show', $locked->id),
                        orderId: $locked->order_id,
                        sellerOrderId: $locked->id
                    ),
                ];
            }

            return $locked;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $updatedSellerOrder;
    }

    public function reportDeliveryProblem(SellerOrder $sellerOrder, int $buyerId, string $reason): SellerOrder
    {
        $notifications = [];

        $updatedSellerOrder = DB::transaction(function () use ($sellerOrder, $buyerId, $reason, &$notifications) {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::with(['order', 'seller', 'logisticsProfile.user'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'completed') {
                throw ValidationException::withMessages([
                    'dispute' => 'Completed orders can no longer be disputed.',
                ]);
            }

            if ($locked->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'dispute' => 'Delivery problems can only be reported after a package is marked delivered.',
                ]);
            }

            if ($locked->confirmation_deadline_at && $locked->confirmation_deadline_at->isPast()) {
                throw ValidationException::withMessages([
                    'dispute' => 'The 72-hour buyer confirmation period has already expired.',
                ]);
            }

            $previousStatus = $locked->status;

            $locked->update([
                'status'                 => 'disputed',
                'has_unresolved_dispute' => true,
                'dispute_reason'         => trim($reason),
                'dispute_reported_at'    => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $previousStatus,
                'to_status'       => 'disputed',
                'actor_type'      => 'Buyer',
                'actor_id'        => $buyerId,
                'remarks'         => 'Buyer reported a delivery problem: ' . trim($reason),
            ]);

            $this->syncParentOrderStatus($locked->order);

            if ($locked->seller) {
                $notifications[] = [
                    'recipient' => $locked->seller,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Delivery Issue Reported',
                        message: "Buyer reported an issue for Order #{$locked->seller_order_number}. Automatic completion is paused.",
                        type: 'danger',
                        url: route('seller.orders.show', $locked->id),
                        orderId: $locked->order_id,
                        sellerOrderId: $locked->id
                    ),
                ];
            }

            if ($locked->logisticsProfile?->user) {
                $notifications[] = [
                    'recipient' => $locked->logisticsProfile->user,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Delivery Dispute Alert',
                        message: "Buyer reported a delivery issue on Order #{$locked->seller_order_number}.",
                        type: 'danger',
                        url: route('logistics.shipments.index'),
                        orderId: $locked->order_id,
                        sellerOrderId: $locked->id
                    ),
                ];
            }

            return $locked;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $updatedSellerOrder;
    }

    public function syncParentOrderStatus(Order $order): void
    {
        $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
        if (! $lockedOrder) {
            return;
        }

        $sellerOrders = SellerOrder::where('order_id', $lockedOrder->id)->get();
        if ($sellerOrders->isEmpty()) {
            return;
        }

        $terminalCancelled = ['rejected', 'cancelled', 'returned_to_seller'];
        $activeOrders = $sellerOrders->reject(fn (SellerOrder $so) => in_array($so->status, $terminalCancelled, true));

        if ($activeOrders->isEmpty()) {
            $lockedOrder->update([
                'status'         => 'cancelled',
                'payment_status' => 'cancelled',
            ]);
            return;
        }

        $allCompleted = $activeOrders->every(fn (SellerOrder $so) => $so->status === 'completed');
        $allPaidOrSettled = $activeOrders->every(fn (SellerOrder $so) => in_array($so->payment_status, ['collected', 'remitted', 'settled'], true));

        $overallPaymentStatus = 'unpaid';
        if ($activeOrders->every(fn (SellerOrder $so) => $so->payment_status === 'settled')) {
            $overallPaymentStatus = 'settled';
        } elseif ($allPaidOrSettled) {
            $overallPaymentStatus = 'collected';
        } elseif ($activeOrders->contains(fn (SellerOrder $so) => in_array($so->payment_status, ['collected', 'remitted', 'settled'], true))) {
            $overallPaymentStatus = 'partially_collected';
        }

        if ($allCompleted && $allPaidOrSettled) {
            $lockedOrder->update([
                'status'         => 'completed',
                'payment_status' => $overallPaymentStatus,
                'completed_at'   => $lockedOrder->completed_at ?? now(),
            ]);
            return;
        }

        if ($activeOrders->contains(fn (SellerOrder $so) => $so->status === 'disputed')) {
            $overallStatus = 'disputed';
        } elseif ($activeOrders->contains(fn (SellerOrder $so) => in_array($so->status, ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivered'], true))) {
            $overallStatus = 'in_progress';
        } elseif ($activeOrders->contains(fn (SellerOrder $so) => in_array($so->status, ['accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned'], true))) {
            $overallStatus = 'processing';
        } else {
            $overallStatus = 'pending';
        }

        $lockedOrder->update([
            'status'         => $overallStatus,
            'payment_status' => $overallPaymentStatus,
        ]);
    }
}