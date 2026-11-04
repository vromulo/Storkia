<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\SellerRtsClaim;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComplaintController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService,
        protected InventoryStockService $inventoryStockService
    ) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'disputes');
        $status = $request->query('status', 'unresolved');
        if (! in_array($status, ['unresolved', 'resolved', 'all'], true)) {
            $status = 'unresolved';
        }
        $search = trim((string) $request->query('search', ''));

        $baseDisputesQuery = SellerOrder::with([
            'order',
            'buyer',
            'sellerProfile',
            'logisticsProfile',
            'items',
            'extra',
            'shipment.latestDeliveryProof',
            'payment',
        ])->where(function ($q) {
            $q->where('status', 'disputed')
              ->orWhere('has_unresolved_dispute', true)
              ->orWhereNotNull('dispute_reported_at')
              ->orWhereHas('extra', fn ($eq) => $eq->where('is_escalated_to_admin', true));
        });

        $unresolvedCount = (clone $baseDisputesQuery)->where('has_unresolved_dispute', true)->count();
        $resolvedCount = (clone $baseDisputesQuery)->where('has_unresolved_dispute', false)->whereNotNull('dispute_resolved_at')->count();
        $allDisputesCount = (clone $baseDisputesQuery)->count();

        $disputesQuery = clone $baseDisputesQuery;
        if ($status === 'unresolved') {
            $disputesQuery->where('has_unresolved_dispute', true);
        } elseif ($status === 'resolved') {
            $disputesQuery->where('has_unresolved_dispute', false)->whereNotNull('dispute_resolved_at');
        }

        if ($search !== '') {
            $disputesQuery->where('seller_order_number', 'like', "%{$search}%");
        }

        $disputes = $disputesQuery->orderByDesc('dispute_reported_at')->paginate(20, ['*'], 'disp_page')->withQueryString();
        $complaints = $disputes;
        $disputedOrders = $disputes;

        $rtsClaims = Schema::hasTable('seller_rts_claims')
            ? SellerRtsClaim::with(['sellerOrder', 'sellerProfile', 'logisticsProfile', 'buyer', 'shipment'])
                ->orderByRaw("CASE WHEN reimbursement_status = 'pending_admin_review' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'rts_page')
                ->withQueryString()
            : collect();

        $counts = [
            'unresolved' => $unresolvedCount,
            'resolved'   => $resolvedCount,
            'all'        => $allDisputesCount,
            'disputes'   => $unresolvedCount,
            'rts_claims' => Schema::hasTable('seller_rts_claims')
                ? SellerRtsClaim::where('reimbursement_status', 'pending_admin_review')->count()
                : 0,
        ];

        return view()->first(
            ['admin.complaints.index', 'admin.orders.disputes', 'admin.complaints'],
            compact(
                'tab',
                'status',
                'search',
                'disputes',
                'complaints',
                'disputedOrders',
                'rtsClaims',
                'counts'
            )
        );
    }

    public function resolveDispute(Request $request, SellerOrder $sellerOrder)
    {
        $rawChoice = $request->input('decision', $request->input('resolution'));
        $request->merge(['decision' => $rawChoice]);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve_refund,reject_dispute,approve,reject,return_and_cancel,complete_order'],
            'remarks'  => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $isRefundApproved = in_array($validated['decision'], ['approve_refund', 'approve', 'return_and_cancel'], true);
        $remarks = trim($validated['remarks']);
        $adminId = Auth::guard('admin')->id();

        $sellerOrder->loadMissing(['order', 'buyer', 'seller', 'payment', 'paymentSubmission', 'items']);

        if ($isRefundApproved) {
            DB::transaction(function () use ($sellerOrder, $remarks, $adminId) {
                $extra = SellerOrderExtra::firstOrCreate(
                    ['seller_order_id' => $sellerOrder->id],
                    ['order_id' => $sellerOrder->order_id]
                );

                $extra->update([
                    'seller_return_decision'        => 'approved',
                    'seller_return_Decision_reason' => "Resolved by Admin: {$remarks}",
                    'refund_status'                 => 'refund_released',
                    'refund_released_at'            => now(),
                ]);

                $sellerOrder->update([
                    'status'                 => 'returned_to_seller',
                    'payment_status'         => 'cancelled',
                    'has_unresolved_dispute' => false,
                    'dispute_resolved_at'    => now(),
                    'cancelled_at'           => now(),
                ]);

                $sellerOrder->payment?->update(['status' => 'cancelled']);

                if ($sellerOrder->paymentSubmission && $sellerOrder->effective_payment_method === 'GCASH') {
                    $sellerOrder->paymentSubmission->update([
                        'refund_status' => 'pending_admin_refund',
                        'admin_remarks' => "Dispute approved by Admin: {$remarks}",
                    ]);
                }

                $this->inventoryStockService->restoreStockForSellerOrder($sellerOrder);

                OrderStatusHistory::create([
                    'order_id'        => $sellerOrder->order_id,
                    'seller_order_id' => $sellerOrder->id,
                    'from_status'     => 'disputed',
                    'to_status'       => 'returned_to_seller',
                    'actor_type'      => 'Admin',
                    'actor_id'        => $adminId,
                    'remarks'         => "Admin approved Buyer return/refund dispute: {$remarks}",
                ]);

                if ($sellerOrder->order) {
                    $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
                }
            });

            try {
                $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Return & Refund Dispute Approved by Admin',
                    message: "Storkia Admin approved your return/refund request for Order #{$sellerOrder->seller_order_number}. Remarks: {$remarks}",
                    type: 'success',
                    url: route('user.orders.show', $sellerOrder->id),
                    orderId: $sellerOrder->order_id,
                    sellerOrderId: $sellerOrder->id
                ));
            } catch (\Throwable $e) {
            }
        } else {
            DB::transaction(function () use ($sellerOrder) {
                $sellerOrder->update([
                    'status'                 => 'delivered',
                    'has_unresolved_dispute' => false,
                    'dispute_resolved_at'    => now(),
                ]);

                $extra = SellerOrderExtra::where('seller_order_id', $sellerOrder->id)->first();
                $extra?->update([
                    'refund_status' => 'rejected_by_admin',
                ]);
            });

            $this->orderCompletionService->confirmSellerOrder(
                sellerOrder: $sellerOrder->fresh(),
                actorType: 'Admin',
                actorId: (int) $adminId,
                isAutoCompleted: false
            );

            try {
                $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                    title: 'Delivery Dispute Resolved — Order Completed',
                    message: "Storkia Admin reviewed your dispute for Order #{$sellerOrder->seller_order_number} and confirmed valid delivery. Remarks: {$remarks}",
                    type: 'info',
                    url: route('user.orders.show', $sellerOrder->id),
                    orderId: $sellerOrder->order_id,
                    sellerOrderId: $sellerOrder->id
                ));
            } catch (\Throwable $e) {
            }
        }

        AuditLoggerService::log(
            action: 'dispute_resolved_by_admin',
            targetType: 'SellerOrder',
            targetId: (int) $sellerOrder->id,
            targetReference: $sellerOrder->seller_order_number,
            newValues: ['decision' => $validated['decision']],
            reason: $remarks
        );

        return back()->with('success', "Dispute on Order #{$sellerOrder->seller_order_number} resolved.");
    }

    public function resolveRtsClaim(Request $request, SellerRtsClaim $claim)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,approved,rejected'],
            'remarks'  => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $isApproved = str_starts_with($validated['decision'], 'approve');
        $remarks = trim($validated['remarks']);
        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($claim, $isApproved, $remarks, $adminId) {
            $claim->update([
                'reimbursement_status'   => $isApproved ? 'approved' : 'rejected',
                'resolved_by_admin_id'   => $adminId,
                'admin_resolution_notes' => $remarks,
                'resolved_at'            => now(),
            ]);

            if ($isApproved && (float) $claim->claimed_product_expense > 0 && $claim->seller_profile_id) {
                $sellerName = $claim->sellerProfile?->business_name ?? "Seller #{$claim->seller_profile_id}";

                $this->roleWalletService->creditAvailableDirect(
                    ownerType: 'Seller',
                    ownerId: (int) $claim->seller_profile_id,
                    ownerName: $sellerName,
                    amount: (float) $claim->claimed_product_expense,
                    sellerOrderId: (int) $claim->seller_order_id,
                    shipmentId: $claim->shipment_id,
                    entryType: 'rts_damage_reimbursement',
                    paymentMethod: 'WALLET',
                    description: "RTS Damaged/Lost Parcel Reimbursement approved by Admin ({$remarks})"
                );
            }
        });

        AuditLoggerService::log(
            action: 'rts_claim_resolved_by_admin',
            targetType: 'SellerRtsClaim',
            targetId: (int) $claim->id,
            targetReference: 'Order #' . ($claim->sellerOrder?->seller_order_number ?? $claim->seller_order_id),
            newValues: ['reimbursement_status' => $claim->reimbursement_status],
            reason: $remarks
        );

        return back()->with('success', 'Seller RTS reimbursement claim resolved.');
    }
}