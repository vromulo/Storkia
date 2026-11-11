<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CommissionRecord;
use App\Models\LogisticsAdminRemittance;
use App\Models\OrderServiceRating;
use App\Models\Rider;
use App\Models\SellerProfile;
use App\Models\SellerSettlement;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\AuditLoggerService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $unremittedToAdminBalance = $this->roleWalletService->syncLogisticsUnremittedCodBalance(
            (int) $profile->id,
            $profile->business_name
        );

        $logisticsWallet = $this->roleWalletService->getOrCreateWallet('Logistics', (int) $profile->id, $profile->business_name);

        $remittances = Schema::hasTable('cod_remittances')
            ? CodRemittance::with(['rider', 'items.codCollection.sellerOrder', 'items.codCollection.shipment'])
                ->where('logistics_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $codCollections = CodCollection::with(['rider', 'sellerOrder', 'shipment', 'commissionRecord'])
            ->where('logistics_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->get();

        $unremittedToAdminCollections = $codCollections->filter(function (CodCollection $col) {
            if ($col->status !== 'verified') {
                return false;
            }
            $stage = $col->admin_remittance_status ?? 'unremitted';
            return in_array($stage, ['unremitted', 'held_by_logistics', 'rejected', ''], true);
        })->values();

        $adminRemittances = Schema::hasTable('logistics_admin_remittances')
            ? LogisticsAdminRemittance::with(['items.sellerOrder', 'items.shipment', 'reviewedByAdmin'])
                ->where('logistics_profile_id', $profile->id)
                ->orderByDesc('submitted_at')
                ->get()
            : collect();

        $platformGcash = Schema::hasTable('platform_gcash_settings')
            ? DB::table('platform_gcash_settings')->where('is_active', true)->orderByDesc('id')->first()
            : null;

        $commissionRecords = CommissionRecord::with(['sellerOrder', 'sellerProfile', 'shipment', 'courier', 'rider'])
            ->where('logistics_profile_id', $profile->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
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
            ->orderByDesc('created_at')
            ->get();

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::with('histories')
                ->where('requester_type', 'Logistics')
                ->where('requester_id', $profile->id)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        $totalCodCollected = round((float) $codCollections->sum('collected_amount'), 2);
        $verifiedCodFromRiders = round((float) $remittances->where('status', 'verified')->sum('total_collected_amount'), 2);
        $pendingCodFromRiders = round((float) $remittances->where('status', 'submitted')->sum('total_collected_amount'), 2);

        $readyToRemitToAdminAmount = round((float) $unremittedToAdminCollections->sum(fn ($c) => (float) ($c->remitted_amount ?? $c->collected_amount)), 2);
        $pendingAdminVerificationAmount = round((float) $adminRemittances->where('status', 'pending_verification')->sum('total_cod_amount'), 2);
        $verifiedByAdminAmount = round((float) $adminRemittances->where('status', 'verified')->sum('total_cod_amount'), 2);

        $grossShippingRevenue = round((float) $commissionRecords->sum('shipping_fee'), 2);
        $logisticsNetShare = round((float) $commissionRecords->sum(fn ($r) => (float) ($r->logistics_total_earning ?: ((float) $r->logistics_commission_amount + (float) $r->logistics_operational_revenue))), 2);
        $courierShareTotal = round((float) $commissionRecords->sum(fn ($r) => (float) ($r->courier_commission_amount ?? 0)), 2);
        $riderShareTotal = round((float) $commissionRecords->sum('rider_commission_amount'), 2);

        $metrics = [
            'wallet_available'                => round((float) $logisticsWallet->available_balance, 2),
            'wallet_pending'                  => round((float) $logisticsWallet->pending_balance, 2),
            'wallet_unremitted_cod'           => $unremittedToAdminBalance,
            'wallet_total_earned'             => round((float) $logisticsWallet->total_earned, 2),
            'wallet_total_withdrawn'          => round((float) $logisticsWallet->total_withdrawn, 2),
            'total_cod_collected'             => $totalCodCollected,
            'verified_cod_remitted'           => $verifiedCodFromRiders,
            'pending_cod_remittances'         => $pendingCodFromRiders,
            'ready_to_remit_to_admin_amount'  => $readyToRemitToAdminAmount,
            'pending_admin_remittance_amount' => $pendingAdminVerificationAmount,
            'verified_admin_remittance_amount'=> $verifiedByAdminAmount,
            'gross_shipping_revenue'          => $grossShippingRevenue,
            'logistics_net_90'                => $logisticsNetShare,
            'courier_share_total'             => $courierShareTotal,
            'rider_share_10'                  => $riderShareTotal,
        ];

        $sellers = SellerProfile::orderBy('business_name')->get();

        return view('logistics.finance.index', compact(
            'profile',
            'fromDate',
            'toDate',
            'logisticsWallet',
            'remittances',
            'codCollections',
            'unremittedToAdminCollections',
            'adminRemittances',
            'platformGcash',
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

        $this->roleWalletService->recordRiderCodVerifiedByLogistics($remittance, (int) $user->id);

        AuditLoggerService::log(
            action: 'cod_remittance_verified_at_hub',
            targetType: 'CodRemittance',
            targetId: (int) $remittance->id,
            targetReference: $remittance->remittance_reference,
            oldValues: ['status' => 'submitted'],
            newValues: [
                'status'                 => 'verified',
                'total_collected_amount' => (float) $remittance->total_collected_amount,
            ],
            reason: "100% Gross COD Cash verified by Logistics Hub ({$user->full_name}). Ready for Logistics-to-Admin GCash Remittance."
        );

        return back()->with(
            'success',
            "Rider COD Remittance Batch #{$remittance->remittance_reference} (₱" . number_format((float) $remittance->total_collected_amount, 2) . ") verified! Please remit the collected COD to Storkia Admin via GCash to release Courier, Rider, Logistics, and Seller commissions and unlock Hub withdrawals."
        );
    }

    public function submitAdminRemittance(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'sender_account_name'    => ['required', 'string', 'min:2', 'max:120'],
            'sender_account_number'  => ['required', 'string', 'min:10', 'max:25'],
            'gcash_reference_number' => ['required', 'string', 'min:4', 'max:80'],
            'proof_image'            => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'collection_ids'         => ['nullable', 'array'],
            'collection_ids.*'       => ['integer', 'exists:cod_collections,id'],
        ], [
            'proof_image.required' => 'Please upload a screenshot of your GCash transfer to Storkia Admin.',
        ]);

        $proofPath = $request->file('proof_image')->store('logistics_admin_remittances', 'public');

        $remittanceBatch = $this->roleWalletService->submitLogisticsCodRemittanceToAdmin(
            logisticsProfile: $profile,
            submittedByUserId: (int) $user->id,
            senderAccountName: $validated['sender_account_name'],
            senderAccountNumber: $validated['sender_account_number'],
            gcashReferenceNumber: $validated['gcash_reference_number'],
            proofImagePath: $proofPath,
            collectionIds: $validated['collection_ids'] ?? null
        );

        AuditLoggerService::log(
            action: 'logistics_admin_cod_remittance_submitted',
            targetType: 'LogisticsAdminRemittance',
            targetId: (int) $remittanceBatch->id,
            targetReference: $remittanceBatch->remittance_reference,
            oldValues: null,
            newValues: [
                'total_cod_amount'       => (float) $remittanceBatch->total_cod_amount,
                'gcash_reference_number' => $remittanceBatch->gcash_reference_number,
            ],
            reason: "Submitted by {$profile->business_name} ({$user->full_name}) to Storkia Admin GCash."
        );

        return redirect()
            ->route('logistics.finance.index', ['tab' => 'admin_remittances'])
            ->with(
                'success',
                "Logistics-to-Admin GCash Remittance #{$remittanceBatch->remittance_reference} (₱" . number_format((float) $remittanceBatch->total_cod_amount, 2) . ") submitted! Once Admin verifies receipt, Courier, Rider, Hub, and Seller commissions will be automatically credited to Available E-Wallets and Hub withdrawals will unlock."
            );
    }

    public function settleSeller(Request $request)
    {
        return redirect()
            ->route('logistics.finance.index', ['tab' => 'admin_remittances'])
            ->with(
                'info',
                'Direct Logistics-to-Seller manual settlement is now automated through the Logistics-to-Admin GCash Remittance pipeline. Remit collected COD to Storkia Admin to automatically credit Seller, Courier, Rider, and Hub E-Wallets.'
            );
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