<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuyerAccountSanction;
use App\Models\BuyerBanAppeal;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsProfile;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\SellerProfile;
use App\Models\SellerSettlement;
use App\Models\Shipment;
use App\Models\StorkVoucher;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerAbandonmentService;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFinanceController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected InventoryStockService $inventoryStockService,
        protected RoleWalletService $roleWalletService,
        protected BuyerAbandonmentService $buyerAbandonmentService,
        protected VoucherService $voucherService
    ) {}

    public function commissions(Request $request)
    {
        $this->orderCompletionService->runLifecycleMaintenance();
        $this->voucherService->expireOverdueVouchers();

        $completedOrders = SellerOrder::where('status', 'completed')->get();
        foreach ($completedOrders as $compOrder) {
            $this->roleWalletService->releaseCompletedOrderEarnings($compOrder);
        }

        $adminWallet = $this->roleWalletService->getOrCreateWallet(
            ownerType: 'Admin',
            ownerId: 1,
            ownerName: 'Storkia Platform Admin'
        );

        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = CommissionRecord::with([
            'sellerOrder.order',
            'sellerOrder.items',
            'sellerOrder.extra',
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

        $platformVouchers = StorkVoucher::withCount('claims')
            ->whereNull('seller_profile_id')
            ->whereIn('scope', ['platform', 'shipping'])
            ->orderBy('created_at', 'asc')
            ->get();

        $adminPlatformSubsidy = (float) SellerOrderExtra::whereHas('sellerOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->sum('platform_voucher_discount');

        $adminShippingSubsidy = (float) SellerOrderExtra::whereHas('sellerOrder', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))
            ->whereHas('shippingVoucher', fn ($vq) => $vq->whereNull('seller_profile_id'))
            ->sum('shipping_voucher_discount');

        $grossAdminComm = (float) CommissionRecord::sum('admin_commission_amount');

        $metrics = [
            'admin_wallet_available'    => (float) $adminWallet->available_balance,
            'admin_wallet_pending'      => (float) $adminWallet->pending_balance,
            'gross_product_volume'      => (float) CommissionRecord::sum('product_subtotal'),
            'gross_shipping_volume'     => (float) CommissionRecord::sum('shipping_fee'),
            'admin_10_commission_total' => $grossAdminComm,
            'admin_voucher_subsidies'   => round($adminPlatformSubsidy + $adminShippingSubsidy, 2),
            'admin_net_profit_after_sub'=> max(0.0, round($grossAdminComm - $adminPlatformSubsidy - $adminShippingSubsidy, 2)),
            'seller_90_net_total'       => (float) CommissionRecord::sum('seller_net_earning'),
            'logistics_10_commission'   => (float) CommissionRecord::sum('logistics_commission_amount'),
            'logistics_80_operational'  => (float) CommissionRecord::sum('logistics_operational_revenue'),
            'rider_10_commission'       => (float) CommissionRecord::sum('rider_commission_amount'),
            'settled_seller_payouts'    => (float) SellerSettlement::sum('net_settlement_amount'),
        ];

        return view('admin.finance.commissions', compact(
            'adminWallet',
            'commissions',
            'settlements',
            'platformVouchers',
            'status',
            'search',
            'metrics'
        ));
    }

    public function storePlatformVoucher(Request $request)
    {
        $normalizedCode = strtoupper(trim((string) $request->input('code')));
        $request->merge(['code' => $normalizedCode]);

        $validated = $request->validate([
            'code'              => ['required', 'string', 'min:4', 'max:15', 'regex:/^[A-Z0-9]+$/', 'unique:stork_vouchers,code'],
            'title'             => ['required', 'string', 'min:3', 'max:120'],
            'scope'             => ['required', 'in:platform,shipping'],
            'discount_type'     => ['required', 'in:fixed,percentage'],
            'discount_value'    => ['required', 'numeric', 'gt:0'],
            'max_discount_cap'  => ['nullable', 'numeric', 'gt:0'],
            'min_spend'         => ['required', 'numeric', 'min:1'],
            'total_usage_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_buyer_limit'   => ['required', 'integer', 'min:1', 'max:50'],
            'visibility'        => ['required', 'in:claimable,code_only'],
            'starts_at'         => ['required', 'date'],
            'ends_at'           => ['required', 'date', 'after:starts_at'],
        ], [
            'ends_at.after' => 'Expiration date must be after the start date.',
        ]);

        if ($validated['scope'] === 'shipping') {
            $validated['discount_type'] = 'fixed';
            $validated['max_discount_cap'] = $validated['discount_value'];
        }

        StorkVoucher::create([
            'seller_profile_id' => null,
            'created_by'        => null,
            'code'              => $validated['code'],
            'title'             => trim($validated['title']),
            'description'       => $validated['scope'] === 'shipping'
                ? 'Admin Free Shipping Voucher: Up to ₱' . number_format((float) $validated['discount_value'], 2) . ' off shipping (Min. spend ₱' . number_format((float) $validated['min_spend'], 2) . ').'
                : 'Storkia Platform Voucher: ' . ($validated['discount_type'] === 'percentage' ? $validated['discount_value'] . '% off' : '₱' . number_format((float) $validated['discount_value'], 2) . ' off') . ' (Min. spend ₱' . number_format((float) $validated['min_spend'], 2) . ').',
            'scope'             => $validated['scope'],
            'discount_type'     => $validated['discount_type'],
            'discount_value'    => $validated['discount_value'],
            'max_discount_cap'  => $validated['max_discount_cap'] ?? null,
            'min_spend'         => $validated['min_spend'],
            'total_usage_limit' => (int) $validated['total_usage_limit'],
            'used_count'        => 0,
            'per_buyer_limit'   => (int) $validated['per_buyer_limit'],
            'visibility'        => $validated['visibility'],
            'starts_at'         => $validated['starts_at'],
            'ends_at'           => $validated['ends_at'],
            'status'            => 'active',
        ]);

        return back()->with('success', "Admin Voucher \"{$validated['code']}\" created with expiration date " . Carbon::parse($validated['ends_at'])->format('M d, Y h:i A') . '!');
    }

    public function endPlatformVoucher(Request $request, StorkVoucher $voucher)
    {
        $voucher->update([
            'status'  => 'ended',
            'ends_at' => now(),
        ]);

        return back()->with('success', "Voucher \"{$voucher->code}\" has been ended immediately.");
    }

    public function salesReport(Request $request)
    {
        $this->orderCompletionService->runLifecycleMaintenance();

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $roleTab = $request->query('role_tab', 'sellers');

        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $periodCommissions = CommissionRecord::with(['sellerOrder.extra', 'sellerProfile', 'logisticsProfile', 'rider'])
            ->whereBetween('calculated_at', [$start, $end])
            ->orderBy('calculated_at', 'asc')
            ->get();

        $periodOrders = SellerOrder::with(['sellerProfile', 'logisticsProfile', 'extra'])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'asc')
            ->get();

        $adminPlatformSub = (float) $periodOrders->whereIn('status', ['delivered', 'completed'])
            ->sum(fn ($o) => (float) ($o->extra?->platform_voucher_discount ?? 0));

        $adminShippingSub = (float) $periodOrders->whereIn('status', ['delivered', 'completed'])
            ->sum(fn ($o) => (float) ($o->extra?->shipping_voucher_discount ?? 0));

        $grossAdmin10 = (float) $periodCommissions->sum('admin_commission_amount');

        $financialSummary = [
            'from_date'                  => $start->toDateString(),
            'to_date'                    => $end->toDateString(),
            'total_orders_in_period'     => $periodOrders->count(),
            'completed_orders_in_period' => $periodOrders->where('status', 'completed')->count(),
            'cancelled_rts_in_period'    => $periodOrders->whereIn('status', ['cancelled', 'rejected', 'returned_to_seller'])->count(),
            'gross_merchandise_volume'   => round((float) $periodCommissions->sum('product_subtotal'), 2),
            'gross_shipping_volume'      => round((float) $periodCommissions->sum('shipping_fee'), 2),
            'admin_10_gross_commission'  => round($grossAdmin10, 2),
            'admin_voucher_subsidies'    => round($adminPlatformSub + $adminShippingSub, 2),
            'admin_net_platform_profit'  => max(0.0, round($grossAdmin10 - $adminPlatformSub - $adminShippingSub, 2)),
            'seller_90_total_payout'     => round((float) $periodCommissions->sum('seller_net_earning'), 2),
            'logistics_90_total_share'   => round((float) $periodCommissions->sum('logistics_total_earning'), 2),
            'rider_10_total_share'       => round((float) $periodCommissions->sum('rider_commission_amount'), 2),
        ];

        $sellerPerformance = SellerProfile::with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (SellerProfile $sp) use ($periodOrders, $periodCommissions) {
                $spOrders = $periodOrders->where('seller_profile_id', $sp->id);
                $spComms = $periodCommissions->where('seller_profile_id', $sp->id);

                return [
                    'seller_profile'   => $sp,
                    'orders_count'     => $spOrders->count(),
                    'completed_count'  => $spOrders->where('status', 'completed')->count(),
                    'rts_cancel_count' => $spOrders->whereIn('status', ['cancelled', 'rejected', 'returned_to_seller'])->count(),
                    'gross_sales'      => round((float) $spComms->sum('product_subtotal'), 2),
                    'admin_10_cut'     => round((float) $spComms->sum('admin_commission_amount'), 2),
                    'seller_90_net'    => round((float) $spComms->sum('seller_net_earning'), 2),
                ];
            });

        $logisticsPerformance = LogisticsProfile::with('user')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (LogisticsProfile $lp) use ($start, $end, $periodCommissions) {
                $lpShipments = Shipment::where('logistics_profile_id', $lp->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                $lpComms = $periodCommissions->where('logistics_profile_id', $lp->id);

                return [
                    'logistics_profile' => $lp,
                    'shipments_total'   => $lpShipments->count(),
                    'delivered_count'   => $lpShipments->where('status', 'delivered')->count(),
                    'failed_rts_count'  => $lpShipments->whereIn('status', ['delivery_failed', 'returned_to_seller', 'returning_to_seller'])->count(),
                    'gross_shipping'    => round((float) $lpComms->sum('shipping_fee'), 2),
                    'hub_10_commission' => round((float) $lpComms->sum('logistics_commission_amount'), 2),
                    'hub_80_ops'        => round((float) $lpComms->sum('logistics_operational_revenue'), 2),
                    'hub_90_total'      => round((float) $lpComms->sum('logistics_total_earning'), 2),
                ];
            });

        $courierPerformance = Courier::with('logisticsProfile')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (Courier $c) use ($start, $end) {
                $pickups = PickupAssignment::with('shipment')
                    ->where('courier_id', $c->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->get();

                $completed = $pickups->where('status', 'completed');

                return [
                    'courier'             => $c,
                    'assigned_pickups'    => $pickups->count(),
                    'completed_dropoffs'  => $completed->count(),
                    'active_pickups'      => $pickups->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])->count(),
                    'total_weight_hauled' => round((float) $completed->sum(fn ($p) => (float) ($p->shipment?->total_weight ?? 0)), 2),
                ];
            });

        $riderPerformance = Rider::with('logisticsProfile')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (Rider $r) use ($start, $end, $periodCommissions) {
                $deliveries = DeliveryAssignment::where('rider_id', $r->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->get();
                $rComms = $periodCommissions->where('rider_id', $r->id);

                return [
                    'rider'                => $r,
                    'assigned_deliveries'  => $deliveries->count(),
                    'completed_deliveries' => $deliveries->where('status', 'delivered')->count(),
                    'failed_attempts'      => $deliveries->where('status', 'failed')->count(),
                    'rider_10_commission'  => round((float) $rComms->sum('rider_commission_amount'), 2),
                ];
            });

        return view('admin.reports.sales', compact(
            'fromDate',
            'toDate',
            'roleTab',
            'financialSummary',
            'sellerPerformance',
            'logisticsPerformance',
            'courierPerformance',
            'riderPerformance'
        ));
    }

    public function accountsIndex(Request $request)
    {
        $roleTab = $request->query('role', 'Buyer');
        $search = trim((string) $request->query('search', ''));

        $counts = [
            'Buyer'     => User::where('role', 'Buyer')->count(),
            'Seller'    => User::where('role', 'Seller')->count(),
            'Logistics' => User::where('role', 'Logistics')->count(),
            'Courier'   => Courier::count(),
            'Rider'     => Rider::count(),
            'Appeals'   => BuyerBanAppeal::where('status', 'pending')->count(),
        ];

        $users = collect();
        $couriers = collect();
        $riders = collect();
        $appeals = BuyerBanAppeal::with(['buyer', 'sanction'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->get();

        $sanctionsMap = BuyerAccountSanction::all()->keyBy('buyer_id');

        if (in_array($roleTab, ['Buyer', 'Seller', 'Logistics'], true)) {
            $uQuery = User::with(['sellerProfile', 'logisticsProfile', 'identityVerification'])
                ->where('role', $roleTab);

            if ($search !== '') {
                $uQuery->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('contact_no', 'like', "%{$search}%");
                });
            }

            $users = $uQuery->orderBy('created_at', 'asc')->orderBy('id', 'asc')->paginate(12)->withQueryString();
        } elseif ($roleTab === 'Courier') {
            $cQuery = Courier::with(['logisticsProfile', 'currentHubAssignment.hub']);
            if ($search !== '') {
                $cQuery->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('plate_number', 'like', "%{$search}%");
                });
            }
            $couriers = $cQuery->orderBy('created_at', 'asc')->orderBy('id', 'asc')->paginate(12)->withQueryString();
        } elseif ($roleTab === 'Rider') {
            $rQuery = Rider::with(['logisticsProfile', 'currentHubAssignment.hub', 'barangayCoverages']);
            if ($search !== '') {
                $rQuery->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('plate_number', 'like', "%{$search}%");
                });
            }
            $riders = $rQuery->orderBy('created_at', 'asc')->orderBy('id', 'asc')->paginate(12)->withQueryString();
        }

        return view('admin.accounts.index', compact(
            'roleTab',
            'search',
            'counts',
            'users',
            'couriers',
            'riders',
            'appeals',
            'sanctionsMap'
        ));
    }

    public function toggleAccountStatus(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'in:user,courier,rider'],
            'entity_id'   => ['required', 'integer'],
            'action'      => ['required', 'in:suspend_temp,suspend_perm,reactivate'],
            'reason'      => ['nullable', 'string', 'max:300'],
        ]);

        $reason = trim((string) ($validated['reason'] ?: 'Administrative account action by Storkia Admin.'));

        if ($validated['entity_type'] === 'user') {
            $targetUser = User::findOrFail($validated['entity_id']);
            $sanction = $this->buyerAbandonmentService->getOrCreateSanction((int) $targetUser->id);

            if ($validated['action'] === 'suspend_temp') {
                $sanction->update([
                    'ban_level'               => max(1, (int) $sanction->ban_level),
                    'account_status'          => 'temp_banned',
                    'temp_ban_started_at'     => now(),
                    'temp_ban_ends_at'        => now()->addDays(7),
                    'appeal_required'         => true,
                    'last_abandonment_reason' => $reason,
                ]);
                return back()->with('success', "Account {$targetUser->full_name} ({$targetUser->role}) suspended for 7 days with mandatory appeal.");
            }

            if ($validated['action'] === 'suspend_perm') {
                $sanction->update([
                    'ban_level'               => 2,
                    'account_status'          => 'permanently_banned',
                    'appeal_required'         => false,
                    'permanently_banned_at'   => now(),
                    'last_abandonment_reason' => $reason,
                ]);
                return back()->with('success', "Account {$targetUser->full_name} ({$targetUser->role}) permanently banned.");
            }

            $sanction->update([
                'account_status'  => 'active',
                'appeal_required' => false,
            ]);

            return back()->with('success', "Account {$targetUser->full_name} ({$targetUser->role}) reactivated.");
        }

        if ($validated['entity_type'] === 'courier') {
            $courier = Courier::findOrFail($validated['entity_id']);
            $newStatus = $validated['action'] === 'reactivate' ? 'active' : 'suspended';
            $courier->update(['status' => $newStatus]);

            return back()->with('success', "Courier {$courier->full_name} status updated to {$newStatus}.");
        }

        $rider = Rider::findOrFail($validated['entity_id']);
        $newStatus = $validated['action'] === 'reactivate' ? 'approved' : 'rejected';
        $rider->update([
            'status'      => $newStatus,
            'approved_at' => $newStatus === 'approved' ? now() : $rider->approved_at,
            'rejected_at' => $newStatus === 'rejected' ? now() : null,
        ]);

        return back()->with('success', "Rider {$rider->full_name} status updated to {$newStatus}.");
    }

    public function resolveBuyerAppeal(Request $request, BuyerBanAppeal $appeal)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'remarks'  => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->buyerAbandonmentService->resolveBuyerAppeal(
            appeal: $appeal,
            adminId: (int) ($admin?->id ?? 1),
            decision: $validated['decision'],
            remarks: $validated['remarks']
        );

        return back()->with(
            'success',
            $validated['decision'] === 'approve'
                ? 'Buyer appeal approved! Account has been reactivated on probation (a 2nd ban offense will trigger a Permanent Ban).'
                : 'Buyer appeal rejected. Account remains suspended.'
        );
    }

    public function disputes(Request $request)
    {
        $this->orderCompletionService->runLifecycleMaintenance();

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
            'extra',
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
            $locked = SellerOrder::with(['order', 'payment', 'shipment', 'extra'])->where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

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

                $this->roleWalletService->releaseCompletedOrderEarnings($locked);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => $prevStatus,
                    'to_status'       => 'completed',
                    'actor_type'      => 'Admin',
                    'actor_id'        => $admin?->id,
                    'remarks'         => 'Admin resolved dispute, completed order, and released E-Wallet commissions: ' . trim($validated['remarks']),
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

                $locked->extra?->update([
                    'refund_status'      => 'refund_released',
                    'refund_released_at' => now(),
                ]);

                $this->inventoryStockService->restoreStockForSellerOrder($locked);

                OrderStatusHistory::create([
                    'order_id'        => $locked->order_id,
                    'seller_order_id' => $locked->id,
                    'from_status'     => $prevStatus,
                    'to_status'       => 'returned_to_seller',
                    'actor_type'      => 'Admin',
                    'actor_id'        => $admin?->id,
                    'remarks'         => 'Admin upheld buyer dispute, approved refund, marked returned to seller, and restored stock: ' . trim($validated['remarks']),
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