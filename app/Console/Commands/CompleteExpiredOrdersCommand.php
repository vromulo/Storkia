<?php

namespace App\Console\Commands;

use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Notifications\OrderLifecycleNotification;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteExpiredOrdersCommand extends Command
{
    protected $signature = 'orders:auto-complete';

    protected $description = 'Automatically complete delivered seller orders after 72 hours without dispute and expire overdue 24-hour seller acceptance orders.';

    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected InventoryStockService $inventoryStockService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $completedCount = 0;
        $expiredCount = 0;

        $eligibleForCompletion = SellerOrder::where('status', 'delivered')
            ->where('has_unresolved_dispute', false)
            ->whereNull('completed_at')
            ->whereNotNull('confirmation_deadline_at')
            ->where('confirmation_deadline_at', '<=', now())
            ->get();

        foreach ($eligibleForCompletion as $sellerOrder) {
            try {
                $this->orderCompletionService->confirmSellerOrder(
                    sellerOrder: $sellerOrder,
                    actorType: 'System',
                    actorId: null,
                    isAutoCompleted: true
                );
                $completedCount++;
            } catch (\Throwable $e) {
                $this->error("Failed auto-completing SellerOrder ID {$sellerOrder->id}: {$e->getMessage()}");
            }
        }

        $overdueAcceptances = SellerOrder::with(['order', 'payment', 'buyer'])
            ->where('status', 'pending_seller_acceptance')
            ->whereNotNull('acceptance_deadline_at')
            ->where('acceptance_deadline_at', '<=', now())
            ->get();

        foreach ($overdueAcceptances as $overdue) {
            try {
                DB::transaction(function () use ($overdue, &$expiredCount) {
                    $locked = SellerOrder::with(['order', 'payment'])->where('id', $overdue->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== 'pending_seller_acceptance') {
                        return;
                    }

                    $locked->update([
                        'status'           => 'cancelled',
                        'payment_status'   => 'cancelled',
                        'cancelled_at'     => now(),
                        'rejection_reason' => 'Automatically cancelled because the 24-hour seller acceptance deadline expired.',
                    ]);

                    $locked->payment?->update([
                        'status' => 'cancelled',
                    ]);

                    $this->inventoryStockService->restoreStockForSellerOrder($locked);

                    OrderStatusHistory::create([
                        'order_id'        => $locked->order_id,
                        'seller_order_id' => $locked->id,
                        'from_status'     => 'pending_seller_acceptance',
                        'to_status'       => 'cancelled',
                        'actor_type'      => 'System',
                        'actor_id'        => null,
                        'remarks'         => 'Order automatically cancelled after the 24-hour seller acceptance deadline expired.',
                    ]);

                    if ($locked->order) {
                        $this->orderCompletionService->syncParentOrderStatus($locked->order);
                    }

                    $expiredCount++;
                });

                $overdue->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Order Cancelled (Acceptance Deadline Expired)',
                    message: "Order #{$overdue->seller_order_number} was automatically cancelled because the seller did not respond within 24 hours.",
                    type: 'warning',
                    url: route('user.orders.show', $overdue->id),
                    orderId: $overdue->order_id,
                    sellerOrderId: $overdue->id
                ));
            } catch (\Throwable $e) {
                $this->error("Failed expiring SellerOrder ID {$overdue->id}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-completed {$completedCount} order(s); expired {$expiredCount} overdue order(s).");

        return self::SUCCESS;
    }
}