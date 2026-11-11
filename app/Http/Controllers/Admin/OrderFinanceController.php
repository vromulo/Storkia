<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\PickupController;
use App\Http\Controllers\Rider\DeliveryController;
use App\Models\BuyerWalletTopup;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsAdminRemittance;
use App\Models\LogisticsProfile;
use App\Models\OrderPaymentSubmission;
use App\Models\OrderServiceRating;
use App\Models\OrderStatusHistory;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\StorkVoucher;
use App\Models\WithdrawalRequest;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderFinanceController extends Controller
{
    public function __construct(
        protected RoleWalletService $roleWalletService
    ) {}

    protected function sumAdminSubsidyForOrderIds($orderIds): float
    {
        if (! Schema::hasTable('seller_order_extras') || empty($orderIds) || count($orderIds) === 0) {
            return 0.00;
        }

        $rows = DB::table('seller_order_extras')
            ->whereIn('seller_order_id', $orderIds)
            ->get([
                'platform_voucher_code',
                'platform_voucher_discount',
                'shipping_voucher_discount',
                'coins_discount_amount',
            ]);

        $total = 0.00;
        foreach ($rows as $row) {
            $platDisc = (float) ($row->platform_voucher_discount ?? 0);
            $coinsDisc = (float) ($row->coins_discount_amount ?? 0);
            $shipDisc = (float) ($row->shipping_voucher_discount ?? 0);

            if (empty($row->platform_voucher_code) && abs($platDisc - $coinsDisc) < 0.009) {
                $platDisc = 0.00;
            }

            $total = round($total + $platDisc + $coinsDisc + $shipDisc, 2);
        }

        return $total;
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $tab = $request->query('tab', 'commissions');
        $roleFilter = $request->query('role_filter', 'all');
        $statusFilter = $request->query('status_filter', 'all');
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $commQuery = CommissionRecord::with(['sellerOrder.buyer', 'sellerProfile', 'logisticsProfile', 'shipment', 'courier', 'rider'])
            ->orderByDesc('created_at');

        if ($status !== 'all' && in_array($status, ['recorded', 'pending_settlement', 'settled'], true)) {
            if ($status === 'recorded') {
                $commQuery->whereIn('status', ['recorded', 'pending_settlement']);
            } else {
                $commQuery->where('status', $status);
            }
        }

        if ($search !== '') {
            $commQuery->where(function ($q) use ($search) {
                $q->whereHas('sellerOrder', fn ($oq) => $oq->where('seller_order_number', 'like', "%{$search}%"))
                  ->orWhereHas('sellerProfile', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                  ->orWhereHas('logisticsProfile', fn ($lq) => $lq->where('business_name', 'like', "%{$search}%"));
            });
        }

        $commissionRecords = $commQuery->paginate(20, ['*'], 'comm_page')->withQueryString();
        $commissions = $commissionRecords;

        $platformVouchers = StorkVoucher::whereIn('scope', ['platform', 'shipping'])
            ->withCount('claims')
            ->orderByDesc('created_at')
            ->get();

        $withdrawalsQuery = WithdrawalRequest::with(['wallet', 'histories'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'asc');

        if ($roleFilter !== 'all' && in_array($roleFilter, ['Seller', 'Logistics', 'Rider', 'Courier'], true)) {
            $withdrawalsQuery->where('requester_type', $roleFilter);
        }
        if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'completed', 'rejected'], true)) {
            $withdrawalsQuery->where('status', $statusFilter);
        }

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? $withdrawalsQuery->paginate(20, ['*'], 'wdr_page')->withQueryString()
            : collect();
        $payoutRequests = $withdrawalRequests;

        $logisticsCodRemittances = Schema::hasTable('logistics_admin_remittances')
            ? LogisticsAdminRemittance::with([
                'logisticsProfile.user',
                'items.sellerOrder.sellerProfile',
                'items.shipment',
                'reviewedByAdmin',
            ])
                ->orderByRaw("CASE WHEN status = 'pending_verification' THEN 0 ELSE 1 END")
                ->orderByDesc('submitted_at')
                ->paginate(20, ['*'], 'lar_page')
                ->withQueryString()
            : collect();

        $buyerWalletTopups = Schema::hasTable('buyer_wallet_topups')
            ? BuyerWalletTopup::with(['buyer', 'wallet', 'reviewedByAdmin'])
                ->orderByRaw("CASE WHEN status = 'pending_verification' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'topup_page')
                ->withQueryString()
            : collect();

        $arrearsRemittances = Schema::hasTable('seller_commission_remittances')
            ? collect(
                DB::table('seller_commission_remittances')
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'asc')
                    ->get()
            )->map(function ($row) {
                $row->sellerProfile = SellerProfile::find($row->seller_profile_id);
                return $row;
            })
            : collect();

        $gcashSubmissions = Schema::hasTable('order_payment_submissions')
            ? OrderPaymentSubmission::with(['sellerOrder.buyer', 'sellerOrder.extra', 'sellerProfile', 'order'])
                ->orderByRaw("CASE WHEN status IN ('submitted', 'refund_required_seller_timeout') OR refund_status = 'pending_admin_refund' THEN 0 WHEN is_suspicious = 1 THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'gcash_page')
                ->withQueryString()
            : collect();

        $allCommissions = CommissionRecord::all();
        $grossMerchandiseSales = round((float) $allCommissions->sum('product_subtotal'), 2);
        $grossAdminCommission10 = round((float) $allCommissions->sum('admin_commission_amount'), 2);
        $grossShippingFees = round((float) $allCommissions->sum('shipping_fee'), 2);
        $sellerNet90 = round((float) $allCommissions->sum(fn ($c) => (float) ($c->seller_net_earning ?? $c->seller_net_amount ?? 0)), 2);
        $logisticsComm10 = round((float) $allCommissions->sum('logistics_commission_amount'), 2);
        $logisticsOps80 = round((float) $allCommissions->sum(fn ($c) => (float) ($c->logistics_operational_revenue ?? $c->logistics_operational_amount ?? 0)), 2);
        $logisticsNetShare = round((float) $allCommissions->sum(fn ($c) => (float) ($c->logistics_total_earning ?: ((float) $c->logistics_commission_amount + (float) ($c->logistics_operational_revenue ?? $c->logistics_operational_amount ?? 0)))), 2);
        $courierNetTotal = round((float) $allCommissions->sum(fn ($c) => (float) ($c->courier_commission_amount ?? 0)), 2);
        $riderNet10 = round((float) $allCommissions->sum('rider_commission_amount'), 2);

        $completedOrderIds = $allCommissions->pluck('seller_order_id')->unique()->values()->all();
        $adminVoucherSubsidy = $this->sumAdminSubsidyForOrderIds($completedOrderIds);

        $netAdminProfit = round($grossAdminCommission10 - $adminVoucherSubsidy, 2);

        $pendingWithdrawalsCount = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::where('status', 'pending')->count()
            : 0;
        $pendingWithdrawalsAmount = Schema::hasTable('withdrawal_requests')
            ? round((float) WithdrawalRequest::where('status', 'pending')->sum('requested_amount'), 2)
            : 0.00;
        $completedWithdrawalsAmount = Schema::hasTable('withdrawal_requests')
            ? round((float) WithdrawalRequest::where('status', 'completed')->sum('requested_amount'), 2)
            : 0.00;

        $pendingLogisticsRemittancesCount = Schema::hasTable('logistics_admin_remittances')
            ? LogisticsAdminRemittance::where('status', 'pending_verification')->count()
            : 0;
        $pendingLogisticsRemittancesAmount = Schema::hasTable('logistics_admin_remittances')
            ? round((float) LogisticsAdminRemittance::where('status', 'pending_verification')->sum('total_cod_amount'), 2)
            : 0.00;

        $pendingWalletTopupsCount = Schema::hasTable('buyer_wallet_topups')
            ? BuyerWalletTopup::where('status', 'pending_verification')->count()
            : 0;
        $approvedWalletTopupsAmount = Schema::hasTable('buyer_wallet_topups')
            ? round((float) BuyerWalletTopup::where('status', 'approved')->sum('approved_amount'), 2)
            : 0.00;

        $pendingGcashVerificationsCount = Schema::hasTable('order_payment_submissions')
            ? OrderPaymentSubmission::whereIn('status', ['submitted', 'refund_required_seller_timeout'])
                ->orWhere('refund_status', 'pending_admin_refund')
                ->count()
            : 0;

        $metrics = [
            'gross_merchandise_sales'              => $grossMerchandiseSales,
            'gross_admin_commission'               => $grossAdminCommission10,
            'admin_voucher_subsidy'                => $adminVoucherSubsidy,
            'net_admin_profit'                     => $netAdminProfit,
            'gross_shipping_fees'                  => $grossShippingFees,
            'seller_net_90'                        => $sellerNet90,
            'logistics_net_90'                     => $logisticsNetShare,
            'courier_net_total'                    => $courierNetTotal,
            'rider_net_10'                         => $riderNet10,
            'pending_withdrawals_count'            => $pendingWithdrawalsCount,
            'pending_withdrawals_amount'           => $pendingWithdrawalsAmount,
            'completed_withdrawals_amount'         => $completedWithdrawalsAmount,
            'pending_logistics_remittances_count'  => $pendingLogisticsRemittancesCount,
            'pending_logistics_remittances_amount' => $pendingLogisticsRemittancesAmount,
            'pending_wallet_topups_count'          => $pendingWalletTopupsCount,
            'approved_wallet_topups_amount'        => $approvedWalletTopupsAmount,
            'pending_gcash_verifications'          => $pendingGcashVerificationsCount,
            'admin_wallet_available'               => $netAdminProfit,
            'admin_wallet_pending'                 => round((float) $allCommissions->where('status', '!=', 'settled')->sum('admin_commission_amount'), 2),
            'admin_10_commission_total'            => $grossAdminCommission10,
            'admin_net_profit_after_sub'           => $netAdminProfit,
            'seller_90_net_total'                  => $sellerNet90,
            'settled_seller_payouts'               => $completedWithdrawalsAmount,
            'logistics_10_commission'              => $logisticsComm10,
            'logistics_80_operational'             => $logisticsOps80,
            'rider_10_commission'                  => $riderNet10,
            'gross_shipping_volume'                => $grossShippingFees,
        ];

        return view()->first(
            ['admin.commissions.index', 'admin.finance.commissions'],
            compact(
                'tab',
                'status',
                'search',
                'roleFilter',
                'statusFilter',
                'commissionRecords',
                'commissions',
                'platformVouchers',
                'withdrawalRequests',
                'payoutRequests',
                'logisticsCodRemittances',
                'buyerWalletTopups',
                'arrearsRemittances',
                'gcashSubmissions',
                'metrics'
            )
        );
    }

    public function decideBuyerWalletTopup(Request $request, int $topup)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'decision'        => ['required', 'in:approve,reject,approved,rejected'],
            'approved_amount' => ['required_if:decision,approve,approved', 'nullable', 'numeric', 'min:1', 'max:500000'],
            'remarks'         => ['nullable', 'string', 'max:500'],
        ], [
            'approved_amount.required_if' => 'Please enter the exact verified Peso amount shown on the buyer\'s GCash receipt screenshot.',
        ]);

        $processed = $this->roleWalletService->processBuyerWalletTopupDecision(
            topupId: $topup,
            adminId: (int) ($admin?->id ?? 1),
            decision: $validated['decision'],
            approvedAmount: isset($validated['approved_amount']) ? (float) $validated['approved_amount'] : null,
            remarks: $validated['remarks'] ?? null
        );

        AuditLoggerService::log(
            action: $processed->status === 'approved' ? 'buyer_wallet_topup_approved' : 'buyer_wallet_topup_rejected',
            targetType: 'BuyerWalletTopup',
            targetId: (int) $processed->id,
            targetReference: $processed->topup_reference,
            oldValues: ['status' => 'pending_verification'],
            newValues: [
                'status'          => $processed->status,
                'approved_amount' => $processed->approved_amount ? (float) $processed->approved_amount : null,
            ],
            reason: $validated['remarks'] ?? ($processed->status === 'approved' ? 'Verified and credited to Buyer E-Wallet' : 'Rejected by Admin')
        );

        $msg = $processed->status === 'approved'
            ? "Buyer E-Wallet Top-Up #{$processed->topup_reference} (Ref #{$processed->reference_number}) approved! ₱" . number_format((float) $processed->approved_amount, 2) . " has been credited to {$processed->buyer?->full_name}'s Storkia E-Wallet."
            : "Buyer E-Wallet Top-Up #{$processed->topup_reference} declined.";

        return redirect()
            ->route('admin.commissions.index', ['tab' => 'wallet_topups'])
            ->with('success', $msg);
    }

    public function decideLogisticsCodRemittance(Request $request, int $remittance)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,verify,verified,rejected'],
            'remarks'  => ['nullable', 'string', 'max:500'],
        ]);

        $batch = $this->roleWalletService->processLogisticsAdminRemittanceDecision(
            remittanceId: $remittance,
            adminId: (int) ($admin?->id ?? 1),
            decision: $validated['decision'],
            remarks: $validated['remarks'] ?? null
        );

        AuditLoggerService::log(
            action: $batch->status === 'verified' ? 'logistics_admin_cod_remittance_verified' : 'logistics_admin_cod_remittance_rejected',
            targetType: 'LogisticsAdminRemittance',
            targetId: (int) $batch->id,
            targetReference: $batch->remittance_reference,
            oldValues: ['status' => 'pending_verification'],
            newValues: [
                'status'           => $batch->status,
                'total_cod_amount' => (float) $batch->total_cod_amount,
            ],
            reason: $validated['remarks'] ?? ($batch->status === 'verified' ? 'Verified by Admin' : 'Rejected by Admin')
        );

        $msg = $batch->status === 'verified'
            ? "Logistics COD Remittance #{$batch->remittance_reference} (₱" . number_format((float) $batch->total_cod_amount, 2) . ") verified! Courier, Rider, Logistics Hub, and Seller commissions have been automatically distributed to Available E-Wallets."
            : "Logistics COD Remittance #{$batch->remittance_reference} rejected and returned to the Logistics Hub queue.";

        return redirect()
            ->route('admin.commissions.index', ['tab' => 'logistics_remittances'])
            ->with('success', $msg);
    }

    public function storePlatformVoucher(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('code', '')));
        $request->merge([
            'code'          => $code,
            'discount_type' => $request->input('discount_type', 'fixed'),
        ]);

        $validated = $request->validate([
            'code'              => ['required', 'string', 'min:4', 'max:20', 'unique:stork_vouchers,code'],
            'title'             => ['required', 'string', 'min:3', 'max:120'],
            'description'       => ['nullable', 'string', 'max:255'],
            'scope'             => ['required', 'in:platform,shipping'],
            'discount_type'     => ['required', 'in:fixed,percentage'],
            'discount_value'    => ['required', 'numeric', 'min:1', 'max:100000'],
            'max_discount_cap'  => ['nullable', 'numeric', 'min:1', 'max:100000'],
            'min_spend'         => ['required', 'numeric', 'min:0', 'max:500000'],
            'total_usage_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'per_buyer_limit'   => ['required', 'integer', 'min:1', 'max:100'],
            'visibility'        => ['required', 'in:claimable,code_only'],
            'starts_at'         => ['required', 'date'],
            'ends_at'           => ['required', 'date', 'after:starts_at'],
        ]);

        if ($validated['discount_type'] === 'percentage' && (float) $validated['discount_value'] > 99) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discount must be between 1% and 99%.',
            ]);
        }

        StorkVoucher::create([
            'seller_profile_id' => null,
            'created_by'        => null,
            'code'              => $validated['code'],
            'title'             => trim($validated['title']),
            'description'       => $validated['description'] ?? null,
            'scope'             => $validated['scope'],
            'discount_type'     => $validated['discount_type'],
            'discount_value'    => round((float) $validated['discount_value'], 2),
            'max_discount_cap'  => ! empty($validated['max_discount_cap']) ? round((float) $validated['max_discount_cap'], 2) : null,
            'min_spend'         => round((float) $validated['min_spend'], 2),
            'total_usage_limit' => (int) $validated['total_usage_limit'],
            'used_count'        => 0,
            'per_buyer_limit'   => (int) $validated['per_buyer_limit'],
            'visibility'        => $validated['visibility'],
            'starts_at'         => Carbon::parse($validated['starts_at']),
            'ends_at'           => Carbon::parse($validated['ends_at']),
            'status'            => 'active',
        ]);

        return back()->with('success', "Platform voucher {$validated['code']} created and published!");
    }

    public function updatePlatformVoucher(Request $request, StorkVoucher $voucher)
    {
        $validated = $request->validate([
            'total_usage_limit' => ['required', 'integer', 'min:' . max(1, (int) $voucher->used_count)],
            'ends_at'           => ['required', 'date', 'after:now'],
        ]);

        $voucher->update([
            'total_usage_limit' => (int) $validated['total_usage_limit'],
            'ends_at'           => Carbon::parse($validated['ends_at']),
        ]);

        return back()->with('success', "Voucher {$voucher->code} limits updated.");
    }

    public function endPlatformVoucher(StorkVoucher $voucher)
    {
        $voucher->update([
            'status'  => 'ended',
            'ends_at' => now(),
        ]);

        return back()->with('success', "Voucher {$voucher->code} ended immediately.");
    }

    public function decideWithdrawalRequest(Request $request, int $id)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'decision'               => ['required', 'in:approve,reject,completed,rejected'],
            'remarks'                => ['nullable', 'string', 'max:500'],
            'payout_proof_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $withdrawal = $this->roleWalletService->processWithdrawalDecision(
            withdrawalRequestId: $id,
            adminId: (int) ($admin?->id ?? 1),
            decision: $validated['decision'],
            remarks: $validated['remarks'] ?? null,
            proofReference: $validated['payout_proof_reference'] ?? null
        );

        $actionWord = $withdrawal->status === 'completed' ? 'approved and marked as disbursed' : 'rejected and refunded to wallet';

        return back()->with('success', "Withdrawal Request #{$withdrawal->withdrawal_reference} ({$withdrawal->requester_type} - {$withdrawal->requester_name}) {$actionWord}.");
    }

    public function processSellerPayout(Request $request, int $id)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'decision'               => ['nullable', 'in:approve,reject,completed,rejected'],
            'status'                 => ['nullable', 'in:completed,rejected'],
            'remarks'                => ['nullable', 'string', 'max:500'],
            'admin_remarks'          => ['nullable', 'string', 'max:500'],
            'payout_proof_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $decision = $validated['decision'] ?? $validated['status'] ?? 'approve';
        $remarks = $validated['remarks'] ?? $validated['admin_remarks'] ?? null;

        $withdrawal = WithdrawalRequest::find($id);
        if (! $withdrawal && Schema::hasTable('seller_payout_requests')) {
            $legacy = DB::table('seller_payout_requests')->where('id', $id)->first();
            if ($legacy) {
                $withdrawal = WithdrawalRequest::where('withdrawal_reference', $legacy->payout_reference)->first();
            }
        }

        if (! $withdrawal) {
            abort(404, 'Withdrawal request not found.');
        }

        $processed = $this->roleWalletService->processWithdrawalDecision(
            withdrawalRequestId: (int) $withdrawal->id,
            adminId: (int) ($admin?->id ?? 1),
            decision: $decision,
            remarks: $remarks,
            proofReference: $validated['payout_proof_reference'] ?? null
        );

        return back()->with('success', "Withdrawal Request #{$processed->withdrawal_reference} processed ({$processed->status}).");
    }

    public function verifyArrearsRemittance(Request $request, int $id)
    {
        $admin = Auth::guard('admin')->user();
        $remittance = DB::table('seller_commission_remittances')->where('id', $id)->first();
        if (! $remittance) {
            abort(404);
        }

        $validated = $request->validate([
            'decision'      => ['required', 'in:approve,reject'],
            'admin_remarks' => ['nullable', 'string', 'max:300'],
        ]);

        DB::transaction(function () use ($remittance, $validated, $admin) {
            if ($validated['decision'] === 'approve') {
                DB::table('seller_commission_remittances')
                    ->where('id', $remittance->id)
                    ->update([
                        'status'               => 'verified',
                        'verified_by_admin_id' => $admin?->id,
                        'admin_remarks'        => $validated['admin_remarks'] ?? 'Verified by Admin',
                        'verified_at'          => now(),
                        'updated_at'           => now(),
                    ]);

                $wallet = RoleWallet::where('id', $remittance->role_wallet_id)->lockForUpdate()->first();
                if ($wallet) {
                    $remittedAmt = round((float) $remittance->amount_remitted, 2);
                    $newArrears = max(0.00, round((float) $wallet->platform_commission_arrears - $remittedAmt, 2));
                    $newAvailable = round((float) $wallet->available_balance + $remittedAmt, 2);

                    $wallet->update([
                        'platform_commission_arrears' => $newArrears,
                        'available_balance'           => $newAvailable,
                    ]);

                    if ($newArrears < RoleWalletService::GCASH_ARREARS_CAP) {
                        SellerProfile::where('id', $remittance->seller_profile_id)->update([
                            'gcash_blocked_for_arrears' => false,
                        ]);
                    }
                }
            } else {
                DB::table('seller_commission_remittances')
                    ->where('id', $remittance->id)
                    ->update([
                        'status'               => 'rejected',
                        'verified_by_admin_id' => $admin?->id,
                        'admin_remarks'        => $validated['admin_remarks'] ?? 'Rejected by Admin',
                        'verified_at'          => now(),
                        'updated_at'           => now(),
                    ]);
            }
        });

        return back()->with('success', 'Seller arrears remittance updated.');
    }

    public function verifyBuyerGcashPayment(Request $request, OrderPaymentSubmission $submission)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'decision'      => ['required', 'in:verify,flag_suspicious'],
            'admin_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($submission, $validated, $admin) {
            $lockedSub = OrderPaymentSubmission::with(['sellerOrder.buyer', 'sellerOrder.seller', 'sellerOrder.payment'])
                ->where('id', $submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sellerOrder = $lockedSub->sellerOrder;

            if ($validated['decision'] === 'verify') {
                $lockedSub->update([
                    'status'               => 'verified',
                    'is_suspicious'        => false,
                    'verified_by_admin_id' => $admin?->id,
                    'admin_verified_at'    => now(),
                    'seller_verified_at'   => $lockedSub->seller_verified_at ?? now(),
                    'admin_remarks'        => $validated['admin_remarks'] ?? 'Verified by Storkia Admin Escrow',
                ]);

                if ($sellerOrder) {
                    $sellerOrder->update([
                        'payment_status' => 'paid',
                    ]);
                    $sellerOrder->payment?->update([
                        'status'       => 'paid',
                        'collected_at' => $lockedSub->paid_at ?? now(),
                    ]);

                    try {
                        $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                            title: 'GCash Payment Verified by Storkia Admin!',
                            message: "Your GCash payment (Ref #{$lockedSub->reference_number}) for Order #{$sellerOrder->seller_order_number} has been verified and secured in Storkia Escrow.",
                            type: 'success',
                            url: route('user.orders.show', $sellerOrder->id),
                            orderId: $sellerOrder->order_id,
                            sellerOrderId: $sellerOrder->id
                        ));

                        $sellerOrder->seller?->notify(new OrderLifecycleNotification(
                            title: 'Buyer GCash Payment Verified by Admin Escrow',
                            message: "Storkia Admin verified the buyer's GCash payment (₱" . number_format((float) $lockedSub->submitted_amount, 2) . ") for Order #{$sellerOrder->seller_order_number}. You may safely accept and prepare the order.",
                            type: 'success',
                            url: route('seller.orders.show', $sellerOrder->id),
                            orderId: $sellerOrder->order_id,
                            sellerOrderId: $sellerOrder->id
                        ));
                    } catch (\Throwable $e) {
                    }
                }
            } else {
                $reason = trim((string) ($validated['admin_remarks'] ?? '')) ?: 'GCash reference number or receipt screenshot could not be verified by Storkia Admin.';

                $lockedSub->update([
                    'status'                 => 'flagged_suspicious',
                    'is_suspicious'          => true,
                    'suspicious_reason'      => $reason,
                    'suspicious_reported_at' => now(),
                    'verified_by_admin_id'   => $admin?->id,
                    'admin_remarks'          => $reason,
                ]);

                if ($sellerOrder && in_array($sellerOrder->status, ['pending_seller_acceptance', 'accepted', 'preparing'], true)) {
                    $oldStatus = $sellerOrder->status;
                    $sellerOrder->update([
                        'status' => 'pending_buyer_response',
                    ]);

                    OrderStatusHistory::create([
                        'order_id'        => $sellerOrder->order_id,
                        'seller_order_id' => $sellerOrder->id,
                        'from_status'     => $oldStatus,
                        'to_status'       => 'pending_buyer_response',
                        'actor_type'      => 'Admin',
                        'actor_id'        => $admin?->id,
                        'remarks'         => "Admin flagged GCash receipt as unverifiable: {$reason}",
                    ]);

                    try {
                        $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                            title: 'Action Required: GCash Payment Flagged for Verification',
                            message: "Order #{$sellerOrder->seller_order_number} GCash payment was flagged ({$reason}). Please switch to COD or cancel the order in My Orders.",
                            type: 'warning',
                            url: route('user.orders.show', $sellerOrder->id),
                            orderId: $sellerOrder->order_id,
                            sellerOrderId: $sellerOrder->id
                        ));
                    } catch (\Throwable $e) {
                    }
                }
            }
        });

        return back()->with('success', 'GCash payment submission status updated.');
    }

    public function refundBuyerGcashPayment(Request $request, OrderPaymentSubmission $submission)
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'refund_reference' => ['required', 'string', 'min:4', 'max:80'],
            'admin_remarks'    => ['nullable', 'string', 'max:500'],
        ]);

        $refundRef = strtoupper(trim($validated['refund_reference']));
        $remarks = trim((string) ($validated['admin_remarks'] ?? "Refunded via GCash (Ref: {$refundRef})"));

        DB::transaction(function () use ($submission, $refundRef, $remarks, $admin) {
            $lockedSub = OrderPaymentSubmission::with(['sellerOrder.buyer', 'sellerOrder.extra'])
                ->where('id', $submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSub->update([
                'status'               => 'refunded',
                'refund_status'        => 'refunded',
                'refund_reference'     => $refundRef,
                'refunded_at'          => now(),
                'verified_by_admin_id' => $admin?->id,
                'admin_remarks'        => $remarks,
            ]);

            $sellerOrder = $lockedSub->sellerOrder;
            if ($sellerOrder) {
                $sellerOrder->extra?->update([
                    'refund_status'      => 'refund_released',
                    'refund_released_at' => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $sellerOrder->order_id,
                    'seller_order_id' => $sellerOrder->id,
                    'from_status'     => $sellerOrder->status,
                    'to_status'       => $sellerOrder->status,
                    'actor_type'      => 'Admin',
                    'actor_id'        => $admin?->id,
                    'remarks'         => "Admin disbursed Buyer GCash refund of ₱" . number_format((float) $lockedSub->submitted_amount, 2) . " (Ref #{$refundRef}).",
                ]);

                try {
                    $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                        title: 'GCash Refund Disbursed!',
                        message: "Your GCash refund of ₱" . number_format((float) $lockedSub->submitted_amount, 2) . " for Order #{$sellerOrder->seller_order_number} has been sent (GCash Ref #{$refundRef}).",
                        type: 'success',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id
                    ));
                } catch (\Throwable $e) {
                }
            }
        });

        AuditLoggerService::log(
            action: 'buyer_gcash_refund_disbursed',
            targetType: 'OrderPaymentSubmission',
            targetId: (int) $submission->id,
            targetReference: $refundRef,
            newValues: ['refund_status' => 'refunded', 'refund_reference' => $refundRef],
            reason: $remarks
        );

        return back()->with('success', "Buyer GCash refund recorded (Ref #{$refundRef}).");
    }

    public function salesReport(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $roleTab = $request->query('role_tab', 'sellers');
        if (! in_array($roleTab, ['sellers', 'logistics', 'couriers', 'riders'], true)) {
            $roleTab = 'sellers';
        }

        $preset = $request->query('preset', 'this_month');
        $now = now();

        if ($preset === 'today') {
            $from = $now->copy()->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'last_7_days') {
            $from = $now->copy()->subDays(6)->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'last_30_days') {
            $from = $now->copy()->subDays(29)->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'this_quarter') {
            $from = $now->copy()->firstOfQuarter()->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'this_year') {
            $from = $now->copy()->startOfYear()->startOfDay();
            $to = $now->copy()->endOfDay();
        } elseif ($preset === 'custom' && $request->filled('from_date') && $request->filled('to_date')) {
            $from = Carbon::parse($request->query('from_date'))->startOfDay();
            $to = Carbon::parse($request->query('to_date'))->endOfDay();
            if ($to->lt($from)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
        } else {
            $preset = 'this_month';
            $from = $now->copy()->startOfMonth()->startOfDay();
            $to = $now->copy()->endOfDay();
        }

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $search = trim((string) $request->query('search', ''));

        $periodCommissions = CommissionRecord::with(['sellerOrder', 'sellerProfile', 'logisticsProfile', 'shipment'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $periodCompletedOrdersCount = $periodCommissions->count();
        $periodGrossMerchandise = round((float) $periodCommissions->sum('product_subtotal'), 2);
        $periodAdminComm10 = round((float) $periodCommissions->sum('admin_commission_amount'), 2);
        $periodSellerNet90 = round((float) $periodCommissions->sum(fn ($c) => (float) ($c->seller_net_earning ?? $c->seller_net_amount ?? 0)), 2);
        $periodGrossShipping = round((float) $periodCommissions->sum('shipping_fee'), 2);
        $periodLogisticsNet90 = round((float) $periodCommissions->sum(fn ($c) => (float) ($c->logistics_total_earning ?: ((float) $c->logistics_commission_amount + (float) ($c->logistics_operational_revenue ?? $c->logistics_operational_amount ?? 0)))), 2);
        $periodCourierComm = round((float) $periodCommissions->sum(fn ($c) => (float) ($c->courier_commission_amount ?? 0)), 2);
        $periodRiderComm10 = round((float) $periodCommissions->sum('rider_commission_amount'), 2);

        $periodOrderIds = $periodCommissions->pluck('seller_order_id')->unique()->values()->all();
        $periodVoucherSubsidy = $this->sumAdminSubsidyForOrderIds($periodOrderIds);

        $periodNetAdminProfit = round($periodAdminComm10 - $periodVoucherSubsidy, 2);

        $platformKpis = [
            'completed_orders'    => $periodCompletedOrdersCount,
            'gross_merchandise'   => $periodGrossMerchandise,
            'admin_commission_10' => $periodAdminComm10,
            'voucher_subsidy'     => $periodVoucherSubsidy,
            'net_admin_profit'    => $periodNetAdminProfit,
            'seller_net_90'       => $periodSellerNet90,
            'gross_shipping'      => $periodGrossShipping,
            'logistics_net_90'    => $periodLogisticsNet90,
            'courier_comm'        => $periodCourierComm,
            'rider_comm_10'       => $periodRiderComm10,
        ];

        $sellerReports = SellerProfile::with('user')
            ->when($search !== '' && $roleTab === 'sellers', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('business_name', 'like', "%{$search}%")
                       ->orWhere('municipality', 'like', "%{$search}%")
                       ->orWhere('province', 'like', "%{$search}%");
                });
            })
            ->orderBy('business_name')
            ->get()
            ->map(function (SellerProfile $sp) use ($from, $to, $periodCommissions) {
                $spCommissions = $periodCommissions->where('seller_profile_id', $sp->id)->values();
                $wallet = RoleWallet::where('owner_type', 'Seller')->where('owner_id', $sp->id)->first();
                $ordersInPeriod = SellerOrder::where('seller_profile_id', $sp->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->get();

                $avgRating = Schema::hasTable('order_service_ratings')
                    ? round((float) OrderServiceRating::where('seller_profile_id', $sp->id)->avg('seller_service_rating'), 2)
                    : 0.00;

                return [
                    'id'                  => $sp->id,
                    'business_name'       => $sp->business_name,
                    'owner_name'          => $sp->user?->full_name ?? 'Owner',
                    'email'               => $sp->user?->email ?? '',
                    'category'            => $sp->line_of_business,
                    'location'            => "{$sp->municipality}, {$sp->province}",
                    'completed_orders'    => $spCommissions->count(),
                    'cancelled_orders'    => $ordersInPeriod->whereIn('status', ['cancelled', 'rejected', 'returned_to_seller'])->count(),
                    'gross_sales'         => round((float) $spCommissions->sum('product_subtotal'), 2),
                    'admin_commission_10' => round((float) $spCommissions->sum('admin_commission_amount'), 2),
                    'net_earnings_90'     => round((float) $spCommissions->sum(fn ($r) => (float) ($r->seller_net_earning ?? $r->seller_net_amount ?? 0)), 2),
                    'wallet_available'    => round((float) ($wallet?->available_balance ?? 0), 2),
                    'wallet_pending'      => round((float) ($wallet?->pending_balance ?? 0), 2),
                    'wallet_withdrawn'    => round((float) ($wallet?->total_withdrawn ?? 0), 2),
                    'avg_rating'          => $avgRating,
                    'recent_ledger'       => $spCommissions->take(10)->map(fn ($r) => [
                        'date'     => $r->created_at?->format('M d, Y h:i A'),
                        'order_no' => $r->sellerOrder->seller_order_number ?? ('#' . $r->seller_order_id),
                        'gross'    => (float) $r->product_subtotal,
                        'admin_10' => (float) $r->admin_commission_amount,
                        'net_90'   => (float) ($r->seller_net_earning ?? $r->seller_net_amount ?? 0),
                    ])->values()->all(),
                ];
            });

        $logisticsReports = LogisticsProfile::with(['user', 'hubs'])
            ->when($search !== '' && $roleTab === 'logistics', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('business_name', 'like', "%{$search}%")
                       ->orWhere('municipality', 'like', "%{$search}%")
                       ->orWhere('province', 'like', "%{$search}%");
                });
            })
            ->orderBy('business_name')
            ->get()
            ->map(function (LogisticsProfile $lp) use ($from, $to, $periodCommissions) {
                $lpCommissions = $periodCommissions->where('logistics_profile_id', $lp->id)->values();
                $wallet = RoleWallet::where('owner_type', 'Logistics')->where('owner_id', $lp->id)->first();
                $shipmentsInPeriod = Shipment::where('logistics_profile_id', $lp->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->get();

                $avgRating = Schema::hasTable('order_service_ratings')
                    ? round((float) OrderServiceRating::where('logistics_profile_id', $lp->id)->avg('logistics_hub_rating'), 2)
                    : 0.00;

                return [
                    'id'                  => $lp->id,
                    'business_name'       => $lp->business_name,
                    'owner_name'          => $lp->user?->full_name ?? 'Hub Manager',
                    'email'               => $lp->user?->email ?? '',
                    'location'            => "{$lp->municipality}, {$lp->province}",
                    'active_hubs_count'   => $lp->hubs->where('status', 'active')->count(),
                    'couriers_count'      => Courier::where('logistics_profile_id', $lp->id)->count(),
                    'riders_count'        => Rider::where('logistics_profile_id', $lp->id)->count(),
                    'delivered_shipments' => $lpCommissions->count(),
                    'failed_shipments'    => $shipmentsInPeriod->whereIn('status', ['delivery_failed', 'returned_to_seller', 'lost', 'damaged'])->count(),
                    'gross_shipping'      => round((float) $lpCommissions->sum('shipping_fee'), 2),
                    'hub_net_90'          => round((float) $lpCommissions->sum(fn ($c) => (float) ($c->logistics_total_earning ?: ((float) $c->logistics_commission_amount + (float) ($c->logistics_operational_revenue ?? $c->logistics_operational_amount ?? 0)))), 2),
                    'rider_share_10'      => round((float) $lpCommissions->sum('rider_commission_amount'), 2),
                    'wallet_available'    => round((float) ($wallet?->available_balance ?? 0), 2),
                    'wallet_pending'      => round((float) ($wallet?->pending_balance ?? 0), 2),
                    'wallet_withdrawn'    => round((float) ($wallet?->total_withdrawn ?? 0), 2),
                    'avg_rating'          => $avgRating,
                    'recent_ledger'       => $lpCommissions->take(10)->map(fn ($r) => [
                        'date'       => $r->created_at?->format('M d, Y h:i A'),
                        'tracking'   => $r->shipment->tracking_number ?? ('#' . $r->shipment_id),
                        'gross_ship' => (float) $r->shipping_fee,
                        'hub_90'     => round((float) ($r->logistics_total_earning ?: ((float) $r->logistics_commission_amount + (float) ($r->logistics_operational_revenue ?? $r->logistics_operational_amount ?? 0))), 2),
                        'rider_10'   => (float) $r->rider_commission_amount,
                    ])->values()->all(),
                ];
            });

        $courierReports = Courier::with('logisticsProfile')
            ->when($search !== '' && $roleTab === 'couriers', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                       ->orWhere('plate_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->get()
            ->map(function (Courier $courier) use ($from, $to) {
                $pickups = PickupAssignment::with('shipment.sellerProfile')
                    ->where('courier_id', $courier->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->get();

                $completedPickups = $pickups->whereIn('status', ['in_transit_to_hub', 'completed']);
                $totalWeight = round((float) $completedPickups->sum(fn ($p) => (float) ($p->shipment?->total_weight ?? 0)), 2);

                $earnedInPeriod = round((float) $completedPickups->sum(function ($p) {
                    if (! $p->shipment) {
                        return 30.00;
                    }
                    $pay = PickupController::calculateFirstMileCourierPay($p->shipment);
                    return (float) $pay['total_pay'];
                }), 2);

                $wallet = RoleWallet::where('owner_type', 'Courier')->where('owner_id', $courier->id)->first();

                return [
                    'id'                => $courier->id,
                    'full_name'         => $courier->full_name,
                    'email'             => $courier->email,
                    'contact_no'        => $courier->contact_no,
                    'hub_name'          => $courier->logisticsProfile->business_name ?? $courier->home_hub,
                    'vehicle'           => "{$courier->vehicle_type} ({$courier->plate_number})",
                    'load_capacity'     => (float) $courier->load_capacity,
                    'status'            => $courier->status,
                    'assigned_pickups'  => $pickups->count(),
                    'completed_pickups' => $completedPickups->count(),
                    'weight_hauled_kg'  => $totalWeight,
                    'period_earnings'   => $earnedInPeriod,
                    'wallet_available'  => round((float) ($wallet?->available_balance ?? 0), 2),
                    'wallet_withdrawn'  => round((float) ($wallet?->total_withdrawn ?? 0), 2),
                    'recent_ledger'     => $completedPickups->take(10)->map(function ($p) {
                        $pay = $p->shipment ? PickupController::calculateFirstMileCourierPay($p->shipment) : ['total_pay' => 30.00, 'distance_tier' => 'Same Municipality'];
                        return [
                            'date'     => $p->updated_at?->format('M d, Y h:i A'),
                            'tracking' => $p->shipment->tracking_number ?? 'N/A',
                            'seller'   => $p->shipment->sellerProfile->business_name ?? 'Seller',
                            'weight'   => (float) ($p->shipment->total_weight ?? 0),
                            'tier'     => $pay['distance_tier'],
                            'pay'      => (float) $pay['total_pay'],
                        ];
                    })->values()->all(),
                ];
            });

        $riderReports = Rider::with('logisticsProfile')
            ->when($search !== '' && $roleTab === 'riders', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                       ->orWhere('plate_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->get()
            ->map(function (Rider $rider) use ($from, $to) {
                $deliveries = DeliveryAssignment::with('shipment')
                    ->where('rider_id', $rider->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->get();

                $completedDeliveries = $deliveries->where('status', 'delivered');
                $failedDeliveries = $deliveries->where('status', 'failed');

                $earnedInPeriod = round((float) $completedDeliveries->sum(function ($d) {
                    if (! $d->shipment) {
                        return 50.00;
                    }
                    $pay = DeliveryController::calculateDistanceScaledRiderPay($d->shipment);
                    return (float) $pay['total_pay'];
                }), 2);

                $wallet = RoleWallet::where('owner_type', 'Rider')->where('owner_id', $rider->id)->first();
                $avgRating = Schema::hasTable('order_service_ratings')
                    ? round((float) OrderServiceRating::where('rider_id', $rider->id)->avg('rider_delivery_rating'), 2)
                    : 0.00;

                return [
                    'id'                   => $rider->id,
                    'full_name'            => $rider->full_name,
                    'email'                => $rider->email,
                    'contact_no'           => $rider->contact_no,
                    'hub_name'             => $rider->logisticsProfile->business_name ?? 'Logistics Hub',
                    'vehicle'              => "{$rider->vehicle_type} ({$rider->plate_number})",
                    'status'               => $rider->status,
                    'assigned_deliveries'  => $deliveries->count(),
                    'completed_deliveries' => $completedDeliveries->count(),
                    'failed_deliveries'    => $failedDeliveries->count(),
                    'period_earnings'      => $earnedInPeriod,
                    'wallet_available'     => round((float) ($wallet?->available_balance ?? 0), 2),
                    'wallet_pending'       => round((float) ($wallet?->pending_balance ?? 0), 2),
                    'wallet_withdrawn'     => round((float) ($wallet?->total_withdrawn ?? 0), 2),
                    'avg_rating'           => $avgRating,
                    'recent_ledger'        => $completedDeliveries->take(10)->map(function ($d) {
                        $pay = $d->shipment ? DeliveryController::calculateDistanceScaledRiderPay($d->shipment) : ['total_pay' => 50.00, 'distance_tier' => 'Same Barangay'];
                        return [
                            'date'     => $d->completed_at?->format('M d, Y h:i A') ?? $d->updated_at?->format('M d, Y h:i A'),
                            'tracking' => $d->shipment->tracking_number ?? 'N/A',
                            'cod'      => (float) ($d->shipment->cod_amount ?? 0),
                            'tier'     => $pay['distance_tier'],
                            'pay'      => (float) $pay['total_pay'],
                        ];
                    })->values()->all(),
                ];
            });

        return view('admin.reports.sales', compact(
            'roleTab',
            'preset',
            'fromDate',
            'toDate',
            'search',
            'platformKpis',
            'sellerReports',
            'logisticsReports',
            'courierReports',
            'riderReports'
        ));
    }
}