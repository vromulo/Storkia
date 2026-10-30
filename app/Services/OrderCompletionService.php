<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCompletionService
{
    public const SELLER_RETURN_RESPONSE_DAYS = 3;

    public function __construct(
        protected RoleWalletService $roleWalletService,
        protected InventoryStockService $inventoryStockService,
        protected VoucherService $voucherService
    ) {}

    public function runLifecycleMaintenance(?int $buyerId = null, ?int $sellerId = null, ?int $logisticsProfileId = null): void
    {
        $this->expireOverdueSellerAcceptanceOrders($buyerId, $sellerId);
        $this->autoApproveOverdueReturnRequests($buyerId, $sellerId);
        $this->autoCompleteExpiredDeliveredOrders($buyerId, $sellerId, $logisticsProfileId);
    }

    public function autoApproveOverdueReturnRequests(?int $buyerId = null, ?int $sellerId = null): int
    {
        $query = SellerOrder::with(['order', 'payment', 'extra', 'buyer', 'seller'])
            ->where('status', 'disputed')
            ->where('has_unresolved_dispute', true)
            ->whereNotNull('dispute_reported_at')
            ->where('dispute_reported_at', '<=', now()->subDays(self::SELLER_RETURN_RESPONSE_DAYS));

        if ($buyerId !== null) {
            $query->where('buyer_id', $buyerId);
        }

        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        $overdueDisputes = $query->limit(50)->get();
        $approvedCount = 0;

        foreach ($overdueDisputes as $disputedOrder) {
            $extra = $disputedOrder->extra;
            if ($extra && ($extra->seller_return_decision !== null || $extra->is_escalated_to_admin)) {
                continue;
            }

            $notifications = [];

            try {
                DB::transaction(function () use ($disputedOrder, &$notifications, &$approvedCount) {
                    $locked = SellerOrder::with(['order', 'payment', 'extra', 'buyer', 'seller'])
                        ->where('id', $disputedOrder->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $locked || $locked->status !== 'disputed' || ! $locked->has_unresolved_dispute) {
                        return;
                    }

                    $lockedExtra = $locked->extra ?: SellerOrderExtra::create([
                        'order_id'        => $locked->order_id,
                        'seller_order_id' => $locked->id,
                    ]);

                    if ($lockedExtra->seller_return_decision !== null) {
                        return;
                    }

                    $lockedExtra->update([
                        'seller_return_decision'        => 'approved',
                        'seller_return_Decision_reason' => 'Automatically approved because the Seller did not respond within the 3-day return response window.',
                        'seller_return_responded_at'    => now(),
                        'refund_status'                 => 'refund_released',
                        'refund_released_at'            => now(),
                    ]);

                    $locked->update([
                        'status'                 => 'returned_to_seller',
                        'payment_status'         => 'cancelled',
                        'has_unresolved_dispute' => false,
                        'dispute_resolved_at'    => now(),
                        'cancelled_at'           => now(),
                    ]);

                    $locked->payment?->update([
                        'status' => 'cancelled',
                    ]);

                    OrderStatusHistory::create([
                        'order_id'        => $locked->order_id,
                        'seller_order_id' => $locked->id,
                        'from_status'     => 'disputed',
                        'to_status'       => 'returned_to_seller',
                        'actor_type'      => 'System',
                        'actor_id'        => null,
                        'remarks'         => 'Return and refund request automatically approved after Seller failed to respond within 3 days (72 hours).',
                    ]);

                    if ($locked->order) {
                        $this->syncParentOrderStatus($locked->order);
                    }

                    if ($locked->buyer) {
                        $notifications[] = [
                            'recipient'    => $locked->buyer,
                            'notification' => new OrderLifecycleNotification(
                                title: 'Return & Refund Auto-Approved (3-Day Seller Timeout)',
                                message: "Your return and refund request for Order #{$locked->seller_order_number} was automatically approved because the seller did not respond within 3 days.",
                                type: 'info',
                                url: route('user.orders.show', $locked->id),
                                orderId: $locked->order_id,
                                sellerOrderId: $locked->id
                            ),
                        ];
                    }

                    if ($locked->seller) {
                        $notifications[] = [
                            'recipient'    => $locked->seller,
                            'notification' => new OrderLifecycleNotification(
                                title: 'Return Request Auto-Approved (3-Day Timeout)',
                                message: "Return request on Order #{$locked->seller_order_number} was auto-approved after 3 days without a response.",
                                type: 'warning',
                                url: route('seller.orders.show', $locked->id),
                                orderId: $locked->order_id,
                                sellerOrderId: $locked->id
                            ),
                        ];
                    }

                    $approvedCount++;
                });

                foreach ($notifications as $entry) {
                    try {
                        $entry['recipient']->notify($entry['notification']);
                    } catch (\Throwable $e) {
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return $approvedCount;
    }

    public function expireOverdueSellerAcceptanceOrders(?int $buyerId = null, ?int $sellerId = null): int
    {
        $query = SellerOrder::with(['order', 'payment', 'paymentSubmission', 'buyer', 'seller.sellerProfile'])
            ->where('status', 'pending_seller_acceptance')
            ->whereNotNull('acceptance_deadline_at')
            ->where('acceptance_deadline_at', '<=', now());

        if ($buyerId !== null) {
            $query->where('buyer_id', $buyerId);
        }

        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        $overdueOrders = $query->limit(50)->get();
        $expiredCount = 0;

        foreach ($overdueOrders as $overdue) {
            $notifications = [];

            try {
                DB::transaction(function () use ($overdue, &$notifications, &$expiredCount) {
                    $locked = SellerOrder::with(['order', 'payment', 'paymentSubmission', 'buyer', 'seller.sellerProfile'])
                        ->where('id', $overdue->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $locked || $locked->status !== 'pending_seller_acceptance') {
                        return;
                    }

                    $wasPrepaidGcash = $locked->effective_payment_method === 'GCASH';

                    $locked->update([
                        'status'           => 'cancelled',
                        'payment_status'   => 'cancelled',
                        'cancelled_at'     => now(),
                        'rejection_reason' => 'Automatically cancelled because the Seller did not respond within the 24-hour acceptance deadline.',
                    ]);

                    $locked->payment?->update([
                        'status' => 'cancelled',
                    ]);

                    $locked->paymentSubmission?->update([
                        'status' => 'refund_required_seller_timeout',
                    ]);

                    $this->inventoryStockService->restoreStockForSellerOrder($locked);
                    $this->voucherService->restoreVouchersForSellerOrder($locked);

                    OrderStatusHistory::create([
                        'order_id'        => $locked->order_id,
                        'seller_order_id' => $locked->id,
                        'from_status'     => 'pending_seller_acceptance',
                        'to_status'       => 'cancelled',
                        'actor_type'      => 'System',
                        'actor_id'        => null,
                        'remarks'         => 'Order automatically cancelled after the 24-hour seller acceptance deadline expired without response.'
                            . ($wasPrepaidGcash ? ' Buyer GCash payment flagged for full refund.' : ''),
                    ]);

                    if ($locked->order) {
                        $this->syncParentOrderStatus($locked->order);
                    }

                    if ($locked->buyer) {
                        $notifications[] = [
                            'recipient'    => $locked->buyer,
                            'notification' => new OrderLifecycleNotification(
                                title: 'Order Auto-Cancelled (Seller 24h Timeout)',
                                message: "Order #{$locked->seller_order_number} was automatically cancelled because the seller did not respond within 24 hours. Any applied vouchers have been restored to your wallet.",
                                type: 'warning',
                                url: route('user.orders.show', $locked->id),
                                orderId: $locked->order_id,
                                sellerOrderId: $locked->id
                            ),
                        ];
                    }

                    if ($locked->seller) {
                        $notifications[] = [
                            'recipient'    => $locked->seller,
                            'notification' => new OrderLifecycleNotification(
                                title: 'Order Expired (24-Hour Acceptance Missed)',
                                message: "Order #{$locked->seller_order_number} was automatically cancelled because the 24-hour acceptance window expired.",
                                type: 'danger',
                                url: route('seller.orders.show', $locked->id),
                                orderId: $locked->order_id,
                                sellerOrderId: $locked->id
                            ),
                        ];
                    }

                    $expiredCount++;
                });

                foreach ($notifications as $entry) {
                    try {
                        $entry['recipient']->notify($entry['notification']);
                    } catch (\Throwable $e) {
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        return $expiredCount;
    }

    public function autoCompleteExpiredDeliveredOrders(?int $buyerId = null, ?int $sellerId = null, ?int $logisticsProfileId = null): int
    {
        $this->expireOverdueSellerAcceptanceOrders($buyerId, $sellerId);
        $this->autoApproveOverdueReturnRequests($buyerId, $sellerId);

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
            $locked = SellerOrder::with(['order', 'seller', 'buyer', 'logisticsProfile.user', 'shipment.currentRider', 'payment', 'extra'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'completed' || $locked->completed_at !== null) {
                $this->roleWalletService->releaseCompletedOrderEarnings($locked);
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

            $commissionRecord = $this->roleWalletService->releaseCompletedOrderEarnings($locked);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $previousStatus,
                'to_status'       => 'completed',
                'actor_type'      => $actorType,
                'actor_id'        => $actorId,
                'remarks'         => $isAutoCompleted
                    ? 'Order automatically completed after the 72-hour buyer confirmation window expired without dispute. Real-money commissions credited to Role E-Wallets.'
                    : 'Buyer manually confirmed order receipt. Real-money commissions credited to Role E-Wallets.',
            ]);

            if ($locked->order) {
                $this->syncParentOrderStatus($locked->order);
            }

            if ($locked->seller) {
                $notifications[] = [
                    'recipient' => $locked->seller,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Order Completed & E-Wallet Updated',
                        message: "Order #{$locked->seller_order_number} is now completed! Your 90% net earning (₱" . number_format((float) $commissionRecord->seller_net_earning, 2) . ") has been settled.",
                        type: 'info',
                        url: route('seller.settlements.index'),
                        orderId: $locked->order_id,
                        sellerOrderId: $locked->id
                    ),
                ];
            }

            if ($locked->logisticsProfile?->user) {
                $notifications[] = [
                    'recipient' => $locked->logisticsProfile->user,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Shipping Revenue Credited to Hub E-Wallet',
                        message: "Order #{$locked->seller_order_number} completed. ₱" . number_format((float) $commissionRecord->logistics_total_earning, 2) . " has been credited to your Logistics E-Wallet.",
                        type: 'info',
                        url: route('logistics.finance.index'),
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
                'remarks'         => 'Buyer reported a delivery problem: ' . trim($reason) . ' (Seller has 3 days to respond before auto-approval).',
            ]);

            if ($locked->order) {
                $this->syncParentOrderStatus($locked->order);
            }

            if ($locked->seller) {
                $notifications[] = [
                    'recipient' => $locked->seller,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Delivery Return/Refund Request (3-Day Response Deadline)',
                        message: "Buyer reported an issue for Order #{$locked->seller_order_number}. Please respond within 3 days or the return/refund will be auto-approved.",
                        type: 'danger',
                        url: route('seller.orders.show', $locked->id),
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
        $allPaidOrSettled = $activeOrders->every(fn (SellerOrder $so) => in_array($so->payment_status, ['paid', 'collected', 'remitted', 'settled'], true));

        $overallPaymentStatus = 'unpaid';
        if ($activeOrders->every(fn (SellerOrder $so) => $so->payment_status === 'settled')) {
            $overallPaymentStatus = 'settled';
        } elseif ($allPaidOrSettled) {
            $overallPaymentStatus = 'collected';
        } elseif ($activeOrders->contains(fn (SellerOrder $so) => in_array($so->payment_status, ['paid', 'submitted', 'collected', 'remitted', 'settled'], true))) {
            $overallPaymentStatus = 'partially_collected';
        }

        if ($allCompleted) {
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