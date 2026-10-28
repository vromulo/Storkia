<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRecord;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Models\SellerSettlement;
use App\Notifications\OrderLifecycleNotification;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFinanceController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected InventoryStockService $inventoryStockService
    ) {}

    public function commissions(Request $request)
    {
        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders();

        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = CommissionRecord::with([
            'sellerOrder.order',
            'sellerOrder.items',
            'sellerProfile',
            'logisticsProfile',
            'rider',
            'settlementItem.settlement',
        ]);

        if (in_array($status, ['recorded', 'settled'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('sellerOrder', fn ($sq) => $sq->where('seller_order_number', 'like', "%{$search}%"))
                  ->orWhereHas('sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('logisticsProfile', fn ($lp) => $lp->where('business_name', 'like', "%{$search}%"));
            });
        }

        $commissions = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(12)
            ->withQueryString();

        $settlements = SellerSettlement::with(['sellerProfile', 'logisticsProfile'])
            ->orderBy('settled_at', 'asc')
            ->orderBy('id', 'asc')
            ->take(8)
            ->get();

        $metrics = [
            'gross_product_volume'      => (float) CommissionRecord::sum('product_subtotal'),
            'gross_shipping_volume'     => (float) CommissionRecord::sum('shipping_fee'),
            'admin_10_commission_total' => (float) CommissionRecord::sum('admin_commission_amount'),
            'seller_90_net_total'       => (float) CommissionRecord::sum('seller_net_earning'),
            'logistics_10_commission'   => (float) CommissionRecord::sum('logistics_commission_amount'),
            'logistics_80_operational'  => (float) CommissionRecord::sum('logistics_operational_revenue'),
            'rider_10_commission'       => (float) CommissionRecord::sum('rider_commission_amount'),
            'settled_seller_payouts'    => (float) SellerSettlement::sum('net_settlement_amount'),
        ];

        return view('admin.finance.commissions', compact(
            'commissions',
            'settlements',
            'status',
            'search',
            'metrics'
        ));
    }

    public function disputes(Request $request)
    {
        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders();

        $status = $request->query('status', 'unresolved');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = SellerOrder::whereNotNull('dispute_reported_at');

        $counts = [
            'all'        => (clone $baseQuery)->count(),
            'unresolved' => (clone $baseQuery)->where('has_unresolved_dispute', true)->count(),
            'resolved'   => (clone $baseQuery)->where('has_unresolved_dispute', false)->count(),
        ];

        $query = SellerOrder::with([
            'order',
            'buyer',
            'sellerProfile.user',
            'logisticsProfile',
            'items',
            'shipment.latestDeliveryProof',
        ])->whereNotNull('dispute_reported_at');

        if ($status === 'unresolved') {
            $query->where('has_unresolved_dispute', true);
        } elseif ($status === 'resolved') {
            $query->where('has_unresolved_dispute', false);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('seller_order_number', 'like', "%{$search}%")
                  ->orWhereHas('sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $disputedOrders = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('admin.orders.disputes', compact(
            'disputedOrders',
            'status',
            'search',
            'counts'
        ));
    }

    public function resolveDispute(Request $request, SellerOrder $sellerOrder)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'resolution' => ['required', 'in:complete_order,return_and_cancel'],
            'remarks'    => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($sellerOrder, $admin, $validated) {
            $locked = SellerOrder::with(['order', 'payment', 'shipment'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

            if (! $locked->has_unresolved_dispute) {
                throw ValidationException::withMessages([
                    'dispute' => 'This delivery dispute has already been resolved.',
                ]);
            }

            $prevStatus = $locked->status;

            if ($validated['resolution'] === 'complete_order') {
                $locked->update([
                    'has_unresolved_dispute' => false,
                    'dispute_resolved_at'    => now(),
                    'status'                 => 'completed',
                    'completed_at'           => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => $prevStatus,
                    'to_status'       => 'completed',
                    'actor_type'      => 'Admin',
                    'actor_id'        => $admin?->id,
                    'remarks'         => 'Admin resolved dispute and completed order: ' . trim($validated['remarks']),
                ]);
            } else {
                $locked->update([
                    'has_unresolved_dispute' => false,
                    'dispute_resolved_at'    => now(),
                    'status'                 => 'returned_to_seller',
                    'payment_status'         => 'cancelled',
                    'cancelled_at'           => now(),
                ]);

                $locked->payment?->update([
                    'status' => 'cancelled',
                ]);

                $locked->shipment?->update([
                    'status'      => 'returned_to_seller',
                    'returned_at' => now(),
                ]);

                $this->inventoryStockService->restoreStockForSellerOrder($locked);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => $prevStatus,
                    'to_status'       => 'returned_to_seller',
                    'actor_type'      => 'Admin',
                    'actor_id'        => $admin?->id,
                    'remarks'         => 'Admin upheld buyer dispute, marked returned to seller, and restored stock: ' . trim($validated['remarks']),
                ]);
            }

            if ($locked->order) {
                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            }
        });

        try {
            $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                title: 'Delivery Dispute Resolved',
                message: "Admin resolved the dispute on Order #{$sellerOrder->seller_order_number}: {$validated['remarks']}",
                type: 'info',
                url: route('user.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return back()->with('success', "Dispute for Order #{$sellerOrder->seller_order_number} resolved.");
    }
}