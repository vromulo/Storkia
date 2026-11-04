<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CommissionRecord;
use App\Models\OrderServiceRating;
use App\Models\Rider;
use App\Models\SellerProfile;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementItem;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function __construct(
        protected RoleWalletService $roleWalletService
    ) {}

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403, 'Approved Logistics profile required.');
        }

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        $logisticsWallet = $this->roleWalletService->getOrCreateWallet('Logistics', (int) $profile->id, $profile->business_name);

        $remittances = Schema::hasTable('cod_remittances')
            ? CodRemittance::with(['rider', 'items.codCollection.sellerOrder', 'items.codCollection.shipment'])
                ->where('logistics_profile_id', $profile->id)
                ->orderBy('created_at', 'asc')
                ->get()
            : collect();

        $codCollections = CodCollection::with(['rider', 'sellerOrder', 'shipment'])
            ->where('logistics_profile_id', $profile->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $commissionRecords = CommissionRecord::with(['sellerOrder', 'sellerProfile', 'shipment'])
            ->where('logistics_profile_id', $profile->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $settlements = Schema::hasTable('seller_settlements')
            ? SellerSettlement::with(['sellerProfile', 'items'])
                ->where('logistics_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
            : collect();

        $walletTransactions = WalletTransaction::with(['sellerOrder', 'shipment'])
            ->where('owner_type', 'Logistics')
            ->where('owner_id', $profile->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::with('histories')
                ->where('requester_type', 'Logistics')
                ->where('requester_id', $profile->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $totalCodCollected = round((float) $codCollections->sum('collected_amount'), 2);
        $verifiedCodRemitted = round((float) $remittances->where('status', 'verified')->sum('total_collected_amount'), 2);
        $pendingCodRemittances = round((float) $remittances->where('status', 'submitted')->sum('total_collected_amount'), 2);

        $grossShippingRevenue = round((float) $commissionRecords->sum('shipping_fee'), 2);
        $logisticsNet90 = round((float) $commissionRecords->sum(fn ($r) => (float) ($r->logistics_total_earning ?: ((float) $r->logistics_commission_amount + (float) $r->logistics_operational_revenue))), 2);
        $riderShare10 = round((float) $commissionRecords->sum('rider_commission_amount'), 2);

        $metrics = [
            'wallet_available'        => round((float) $logisticsWallet->available_balance, 2),
            'wallet_pending'          => round((float) $logisticsWallet->pending_balance, 2),
            'wallet_total_earned'     => round((float) $logisticsWallet->total_earned, 2),
            'wallet_total_withdrawn'  => round((float) $logisticsWallet->total_withdrawn, 2),
            'total_cod_collected'     => $totalCodCollected,
            'verified_cod_remitted'   => $verifiedCodRemitted,
            'pending_cod_remittances' => $pendingCodRemittances,
            'gross_shipping_revenue'  => $grossShippingRevenue,
            'logistics_net_90'        => $logisticsNet90,
            'rider_share_10'          => $riderShare10,
        ];

        $sellers = SellerProfile::orderBy('business_name')->get();

        return view('logistics.finance.index', compact(
            'profile',
            'fromDate',
            'toDate',
            'logisticsWallet',
            'remittances',
            'codCollections',
            'commissionRecords',
            'settlements',
            'walletTransactions',
            'withdrawalRequests',
            'metrics',
            'sellers'
        ));
    }

    public function verifyRemittance(Request $request, CodRemittance $remittance)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $remittance->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        if ($remittance->status === 'verified') {
            return back()->with('info', 'This COD remittance batch is already verified.');
        }

        DB::transaction(function () use ($remittance, $user) {
            $remittance->update([
                'status'      => 'verified',
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            foreach ($remittance->items as $item) {
                $col = $item->codCollection ?? $item->collection;
                if ($col) {
                    $col->update([
                        'status'      => 'verified',
                        'verified_by' => $user->id,
                        'verified_at' => now(),
                    ]);
                }
            }

            AuditLoggerService::log(
                action: 'cod_remittance_verified',
                targetType: 'CodRemittance',
                targetId: (int) $remittance->id,
                targetReference: $remittance->remittance_reference,
                oldValues: ['status' => 'submitted'],
                newValues: ['status' => 'verified', 'net_remitted_amount' => (float) $remittance->net_remitted_amount],
                reason: "Verified by Logistics Hub ({$user->full_name})"
            );
        });

        return back()->with('success', "COD Remittance Batch #{$remittance->remittance_reference} verified!");
    }

    public function settleSeller(Request $request)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'seller_profile_id' => ['required', 'integer', 'exists:seller_profiles,id'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ]);

        $sellerProfile = SellerProfile::with('user')->findOrFail($validated['seller_profile_id']);

        $unsettledRecords = CommissionRecord::where('logistics_profile_id', $profile->id)
            ->where('seller_profile_id', $sellerProfile->id)
            ->whereIn('status', ['pending_settlement', 'recorded'])
            ->whereDoesntHave('settlementItem')
            ->get();

        if ($unsettledRecords->isEmpty()) {
            return back()->withErrors(['settlement' => "No pending unsettled commission records found for {$sellerProfile->business_name}."]);
        }

        $settlementRef = 'STL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

        DB::transaction(function () use ($profile, $user, $sellerProfile, $unsettledRecords, $settlementRef, $validated) {
            $grossSales = round((float) $unsettledRecords->sum('product_subtotal'), 2);
            $adminComm = round((float) $unsettledRecords->sum('admin_commission_amount'), 2);
            $netPayout = round((float) $unsettledRecords->sum('seller_net_earning'), 2);

            $settlement = SellerSettlement::create([
                'settlement_reference'   => $settlementRef,
                'logistics_profile_id'   => $profile->id,
                'seller_profile_id'      => $sellerProfile->id,
                'seller_id'              => $sellerProfile->user_id,
                'gross_product_subtotal' => $grossSales,
                'admin_commission_total' => $adminComm,
                'net_settlement_amount'  => $netPayout,
                'status'                 => 'released',
                'processed_by'           => $user->id,
                'settled_at'             => now(),
                'notes'                  => $validated['notes'] ?? "Settled by {$profile->business_name}",
            ]);

            foreach ($unsettledRecords as $rec) {
                SellerSettlementItem::updateOrCreate(
                    ['commission_record_id' => $rec->id],
                    [
                        'seller_settlement_id'    => $settlement->id,
                        'seller_order_id'         => $rec->seller_order_id,
                        'product_subtotal'        => $rec->product_subtotal,
                        'admin_commission_amount' => $rec->admin_commission_amount,
                        'seller_net_earning'      => $rec->seller_net_earning,
                    ]
                );

                $rec->update([
                    'status'     => 'settled',
                    'settled_at' => now(),
                ]);
            }

            try {
                $sellerProfile->user?->notify(new OrderLifecycleNotification(
                    title: "Logistics Settlement Recorded ({$settlementRef})",
                    message: "{$profile->business_name} settled {$unsettledRecords->count()} order(s) totaling ₱" . number_format($netPayout, 2) . " (90% Net Merchandise Share).",
                    type: 'success',
                    url: route('seller.settlements.index')
                ));
            } catch (\Throwable $e) {
            }
        });

        return back()->with('success', "Settlement #{$settlementRef} recorded for {$sellerProfile->business_name}!");
    }

    public function requestWithdrawal(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:100', 'max:1000000'],
            'payout_method'    => ['nullable', 'in:GCASH,BANK'],
            'account_name'     => ['required', 'string', 'min:2', 'max:120'],
            'account_number'   => ['required', 'string', 'min:8', 'max:50'],
            'idempotency_key'  => ['nullable', 'string', 'max:64'],
        ]);

        $withdrawal = $this->roleWalletService->submitWithdrawalRequest(
            ownerType: 'Logistics',
            ownerId: (int) $profile->id,
            ownerName: $profile->business_name,
            amount: (float) $validated['requested_amount'],
            payoutMethod: 'GCASH',
            accountName: $validated['account_name'],
            accountNumber: $validated['account_number'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return back()->with(
            'success',
            "Withdrawal Request #{$withdrawal->withdrawal_reference} for ₱" . number_format((float) $withdrawal->requested_amount, 2) . " via GCash submitted to Storkia Admin for approval!"
        );
    }

    public function ratingsIndex(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $tab = $request->query('tab', 'hub');
        $selectedRiderId = $request->query('rider_id');

        $hubRatingsQuery = OrderServiceRating::with(['buyer', 'sellerOrder', 'sellerProfile', 'rider'])
            ->where('logistics_profile_id', $profile->id)
            ->orderByDesc('created_at');

        if ($selectedRiderId) {
            $hubRatingsQuery->where('rider_id', (int) $selectedRiderId);
        }

        $ratings = Schema::hasTable('order_service_ratings')
            ? $hubRatingsQuery->paginate(20)->withQueryString()
            : collect();

        $hubAvgRating = Schema::hasTable('order_service_ratings')
            ? round((float) OrderServiceRating::where('logistics_profile_id', $profile->id)->avg('logistics_hub_rating'), 2)
            : 0.00;

        $riderAvgRatingOverall = Schema::hasTable('order_service_ratings')
            ? round((float) OrderServiceRating::where('logistics_profile_id', $profile->id)->whereNotNull('rider_id')->avg('rider_delivery_rating'), 2)
            : 0.00;

        $totalRatingsCount = Schema::hasTable('order_service_ratings')
            ? OrderServiceRating::where('logistics_profile_id', $profile->id)->count()
            : 0;

        $ridersWithRatings = Rider::where('logistics_profile_id', $profile->id)
            ->orderBy('first_name')
            ->get()
            ->map(function (Rider $r) {
                $rQuery = OrderServiceRating::where('rider_id', $r->id);
                $r->ratings_count = $rQuery->count();
                $r->avg_delivery_rating = $r->ratings_count > 0
                    ? round((float) $rQuery->avg('rider_delivery_rating'), 2)
                    : 0.00;
                return $r;
            });

        return view('logistics.ratings.index', compact(
            'profile',
            'tab',
            'selectedRiderId',
            'ratings',
            'hubAvgRating',
            'riderAvgRatingOverall',
            'totalRatingsCount',
            'ridersWithRatings'
        ));
    }
}