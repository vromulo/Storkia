<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\DeliveryController as WebRiderDeliveryController;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CodRemittanceItem;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\OrderServiceRating;
use App\Models\Rider;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\AuditLoggerService;
use App\Services\RiderMobileSchemaService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RiderFinanceApiController extends Controller
{
    public function __construct(
        protected RoleWalletService $roleWalletService,
        protected RiderDeliveryApiController $deliveryApiController
    ) {}

    public function codOverview(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $unremittedCollections = CodCollection::with(['shipment', 'sellerOrder', 'commissionRecord'])
            ->where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->orderBy('collected_at', 'asc')
            ->get();

        $recentCollections = CodCollection::with(['shipment', 'sellerOrder', 'commissionRecord'])
            ->where('rider_id', $rider->id)
            ->orderByDesc('collected_at')
            ->limit(50)
            ->get();

        $remittances = Schema::hasTable('cod_remittances')
            ? CodRemittance::with('items')
                ->where('rider_id', $rider->id)
                ->orderByDesc('submitted_at')
                ->limit(40)
                ->get()
            : collect();

        $codOnHand = round((float) $unremittedCollections->sum('collected_amount'), 2);
        $pendingRemittanceTotal = round((float) $remittances->where('status', 'submitted')->sum('total_collected_amount'), 2);
        $verifiedRemittanceTotal = round((float) $remittances->where('status', 'verified')->sum('total_collected_amount'), 2);

        $outstandingShortfall = round((float) $recentCollections->sum(function (CodCollection $col) {
            if ($col->remitted_amount === null) {
                return 0.0;
            }
            return max(0.0, (float) $col->expected_amount - (float) $col->remitted_amount);
        }), 2);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary'                => [
                    'cod_on_hand'               => $codOnHand,
                    'unremitted_count'          => $unremittedCollections->count(),
                    'pending_remittance_total'  => $pendingRemittanceTotal,
                    'verified_remittance_total' => $verifiedRemittanceTotal,
                    'outstanding_shortfall'     => $outstandingShortfall,
                ],
                'unremitted_collections' => $unremittedCollections->map(fn (CodCollection $c) => $this->formatCodCollection($c))->values()->all(),
                'recent_collections'     => $recentCollections->map(fn (CodCollection $c) => $this->formatCodCollection($c))->values()->all(),
                'remittances'            => $remittances->map(fn (CodRemittance $r) => $this->formatCodRemittance($r))->values()->all(),
            ],
        ]);
    }

    public function remitAllCod(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'remitted_amount'      => ['nullable', 'numeric', 'min:0.01'],
            'remittance_reference' => ['nullable', 'string', 'max:80'],
            'shortfall_reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $collections = CodCollection::with(['shipment', 'commissionRecord'])
            ->where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->orderBy('collected_at', 'asc')
            ->get();

        if ($collections->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You have no unremitted COD collections on hand to remit.',
            ], 422);
        }

        $totalExpected = round((float) $collections->sum('collected_amount'), 2);
        $actualRemitted = isset($validated['remitted_amount'])
            ? round((float) $validated['remitted_amount'], 2)
            : $totalExpected;

        $isShort = $actualRemitted + 0.009 < $totalExpected;
        $shortfallAmount = $isShort ? round($totalExpected - $actualRemitted, 2) : 0.00;

        if ($isShort && empty(trim((string) ($validated['shortfall_reason'] ?? '')))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Remitted amount (₱' . number_format($actualRemitted, 2) . ') is less than total collected COD (₱' . number_format($totalExpected, 2) . '). Please provide a shortfall explanation.',
                'errors'  => [
                    'shortfall_reason' => ['A shortfall reason is required when remitting less than the collected COD balance.'],
                ],
            ], 422);
        }

        $remittanceRef = ! empty($validated['remittance_reference'])
            ? strtoupper(trim((string) $validated['remittance_reference']))
            : ('REM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)));

        $createdRemittance = DB::transaction(function () use (
            $rider,
            $collections,
            $remittanceRef,
            $totalExpected,
            $actualRemitted,
            $isShort,
            $shortfallAmount,
            $validated
        ) {
            $totalSellerShare = round((float) $collections->sum(fn ($c) => round((float) $c->product_subtotal * 0.90, 2)), 2);
            $totalAdminComm = round((float) $collections->sum(fn ($c) => round((float) $c->product_subtotal * 0.10, 2)), 2);
            $totalRiderComm = round((float) $collections->sum(fn (CodCollection $c) => WebRiderDeliveryController::resolveCollectionRiderCommission($c)), 2);
            $totalLogisticsFee = round((float) $collections->sum(fn (CodCollection $c) => max(0.0, (float) $c->shipping_fee - WebRiderDeliveryController::resolveCollectionRiderCommission($c))), 2);
            $netRemitted = $actualRemitted;

            $notesText = $isShort
                ? "Shortfall ₱" . number_format($shortfallAmount, 2) . ": " . trim((string) $validated['shortfall_reason'])
                : "100% Gross COD Cash remitted by Rider {$rider->full_name} ({$collections->count()} collection(s)).";

            $remittanceData = [
                'remittance_reference'   => $remittanceRef,
                'logistics_profile_id'   => $rider->logistics_profile_id,
                'rider_id'               => $rider->id,
                'total_collected_amount' => $actualRemitted,
                'total_rider_commission' => $totalRiderComm,
                'net_remitted_amount'    => $netRemitted,
                'status'                 => 'submitted',
                'submitted_at'           => now(),
                'notes'                  => $notesText,
            ];

            if (Schema::hasColumn('cod_remittances', 'total_seller_share')) {
                $remittanceData['total_seller_share'] = $totalSellerShare;
                $remittanceData['total_admin_commission'] = $totalAdminComm;
                $remittanceData['total_logistics_fee'] = $totalLogisticsFee;
            }

            $remittance = CodRemittance::create($remittanceData);

            $remainingToAllocate = $actualRemitted;
            $lastIndex = $collections->count() - 1;

            foreach ($collections->values() as $idx => $col) {
                $colExpected = round((float) $col->collected_amount, 2);
                $allocated = ($idx === $lastIndex)
                    ? max(0.0, round($remainingToAllocate, 2))
                    : min($colExpected, max(0.0, round($remainingToAllocate, 2)));
                $remainingToAllocate = round($remainingToAllocate - $allocated, 2);

                $colIsShort = $allocated + 0.009 < $colExpected;
                $colRiderComm = WebRiderDeliveryController::resolveCollectionRiderCommission($col);

                $col->update([
                    'remitted_amount'      => $allocated,
                    'status'               => $colIsShort ? 'short_remitted' : 'remitted',
                    'remittance_reference' => $remittanceRef,
                    'notes'                => $colIsShort ? ($validated['shortfall_reason'] ?? 'Short remittance') : null,
                    'remitted_at'          => now(),
                ]);

                $itemData = [
                    'cod_remittance_id'       => $remittance->id,
                    'seller_order_id'         => $col->seller_order_id,
                    'collected_amount'        => $allocated,
                    'rider_commission_amount' => $colRiderComm,
                ];
                if (Schema::hasColumn('cod_remittance_items', 'amount')) {
                    $itemData['amount'] = $allocated;
                }

                CodRemittanceItem::updateOrCreate(
                    ['cod_collection_id' => $col->id],
                    $itemData
                );
            }

            if ($isShort) {
                AuditLoggerService::log(
                    action: 'short_cod_remittance_flagged',
                    targetType: 'CodRemittance',
                    targetId: (int) $remittance->id,
                    targetReference: $remittanceRef,
                    oldValues: ['expected_amount' => $totalExpected],
                    newValues: ['remitted_amount' => $actualRemitted, 'shortfall' => $shortfallAmount],
                    reason: $validated['shortfall_reason'] ?? 'Short remittance submitted by Rider.',
                    actorType: 'Rider',
                    actorId: (int) $rider->id,
                    actorName: $rider->full_name
                );
            }

            return $remittance;
        });

        return response()->json([
            'status'  => 'success',
            'message' => $isShort
                ? "Short COD Remittance #{$remittanceRef} (₱" . number_format($actualRemitted, 2) . " of ₱" . number_format($totalExpected, 2) . ") submitted and flagged with ₱" . number_format($shortfallAmount, 2) . " shortfall for Logistics reconciliation."
                : "100% COD Remittance Batch #{$remittanceRef} (₱" . number_format($actualRemitted, 2) . ") submitted to your Logistics Hub! Your Rider Delivery Commission will be credited to your E-Wallet once Logistics remits to Admin and Admin verifies.",
            'data'    => $this->formatCodRemittance($createdRemittance),
        ]);
    }

    public function remitSingleCod(Request $request, int $codCollectionId): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $codCollection = CodCollection::with(['shipment', 'commissionRecord'])->find($codCollectionId);

        if (! $codCollection || (int) $codCollection->rider_id !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You cannot remit another rider\'s COD collection.',
            ], 403);
        }

        if ($codCollection->status !== 'collected') {
            return response()->json([
                'status'  => 'error',
                'message' => 'This COD collection has already been remitted.',
            ], 422);
        }

        $validated = $request->validate([
            'remitted_amount'      => ['nullable', 'numeric', 'min:0.01'],
            'remittance_reference' => ['nullable', 'string', 'max:80'],
            'shortfall_reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $expected = round((float) $codCollection->expected_amount, 2);
        $remitted = isset($validated['remitted_amount'])
            ? round((float) $validated['remitted_amount'], 2)
            : round((float) $codCollection->collected_amount, 2);

        $isShort = $remitted + 0.009 < $expected;
        if ($isShort && empty(trim((string) ($validated['shortfall_reason'] ?? '')))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Remitted amount (₱' . number_format($remitted, 2) . ') is less than expected COD (₱' . number_format($expected, 2) . '). Please provide an explanation for the shortfall.',
                'errors'  => [
                    'shortfall_reason' => ['Please provide a reason for the COD shortfall.'],
                ],
            ], 422);
        }

        $remittanceRef = ! empty($validated['remittance_reference'])
            ? strtoupper(trim((string) $validated['remittance_reference']))
            : ('REM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)));

        $remittance = DB::transaction(function () use ($codCollection, $rider, $remitted, $expected, $isShort, $validated, $remittanceRef) {
            $codCollection->update([
                'remitted_amount'      => $remitted,
                'status'               => $isShort ? 'short_remitted' : 'remitted',
                'remittance_reference' => $remittanceRef,
                'notes'                => $validated['shortfall_reason'] ?? null,
                'remitted_at'          => now(),
            ]);

            $sellerShare = round((float) $codCollection->product_subtotal * 0.90, 2);
            $adminComm = round((float) $codCollection->product_subtotal * 0.10, 2);
            $riderComm = WebRiderDeliveryController::resolveCollectionRiderCommission($codCollection);
            $logisticsFee = max(0.0, round((float) $codCollection->shipping_fee - $riderComm, 2));
            $netRemitted = $remitted;

            $remittanceData = [
                'remittance_reference'   => $remittanceRef,
                'logistics_profile_id'   => $codCollection->logistics_profile_id,
                'rider_id'               => $rider->id,
                'total_collected_amount' => $remitted,
                'total_rider_commission' => $riderComm,
                'net_remitted_amount'    => $netRemitted,
                'status'                 => 'submitted',
                'submitted_at'           => now(),
                'notes'                  => $validated['shortfall_reason'] ?? "100% Gross COD collection remittance ({$codCollection->collection_reference}).",
            ];

            if (Schema::hasColumn('cod_remittances', 'total_seller_share')) {
                $remittanceData['total_seller_share'] = $sellerShare;
                $remittanceData['total_admin_commission'] = $adminComm;
                $remittanceData['total_logistics_fee'] = $logisticsFee;
            }

            $rem = CodRemittance::create($remittanceData);

            $itemData = [
                'cod_remittance_id'       => $rem->id,
                'seller_order_id'         => $codCollection->seller_order_id,
                'collected_amount'        => $remitted,
                'rider_commission_amount' => $riderComm,
            ];
            if (Schema::hasColumn('cod_remittance_items', 'amount')) {
                $itemData['amount'] = $remitted;
            }

            CodRemittanceItem::updateOrCreate(
                ['cod_collection_id' => $codCollection->id],
                $itemData
            );

            if ($isShort) {
                AuditLoggerService::log(
                    action: 'short_cod_remittance_flagged',
                    targetType: 'CodRemittance',
                    targetId: (int) $codCollection->id,
                    targetReference: 'COD-' . $codCollection->seller_order_id,
                    oldValues: ['expected_amount' => $expected],
                    newValues: ['remitted_amount' => $remitted, 'shortfall' => round($expected - $remitted, 2)],
                    reason: $validated['shortfall_reason'],
                    actorType: 'Rider',
                    actorId: (int) $rider->id,
                    actorName: $rider->full_name
                );
            }

            return $rem;
        });

        return response()->json([
            'status'  => 'success',
            'message' => $isShort
                ? 'Short COD remittance recorded and flagged for Logistics & Admin reconciliation.'
                : '100% Gross COD remittance submitted to Logistics Hub for verification.',
            'data'    => $this->formatCodRemittance($remittance),
        ]);
    }

    public function confirmRemittanceBlocked(): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => 'Forbidden: Riders cannot confirm or verify their own COD remittances. Only authorized Logistics hub staff can confirm remittances.',
        ], 403);
    }

    public function earningsAndWallet(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $fromDate = (string) $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = (string) $request->query('to_date', now()->toDateString());

        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();

        $riderWallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        $walletTransactions = WalletTransaction::with(['sellerOrder', 'shipment'])
            ->where('owner_type', 'Rider')
            ->where('owner_id', $rider->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $todayStart = now()->startOfDay();
        $weekStart = now()->startOfWeek();

        $todayDeliveries = DeliveryAssignment::with('shipment')
            ->where('rider_id', $rider->id)
            ->where('status', 'delivered')
            ->where('completed_at', '>=', $todayStart)
            ->get();

        $weekDeliveries = DeliveryAssignment::with('shipment')
            ->where('rider_id', $rider->id)
            ->where('status', 'delivered')
            ->where('completed_at', '>=', $weekStart)
            ->get();

        $todayEarnings = round((float) $todayDeliveries->sum(function (DeliveryAssignment $a) {
            return $a->shipment ? WebRiderDeliveryController::calculateDistanceScaledRiderPay($a->shipment)['total_pay'] : 50.00;
        }), 2);

        $weekEarnings = round((float) $weekDeliveries->sum(function (DeliveryAssignment $a) {
            return $a->shipment ? WebRiderDeliveryController::calculateDistanceScaledRiderPay($a->shipment)['total_pay'] : 50.00;
        }), 2);

        $attemptsInPeriod = DeliveryAttempt::where('rider_id', $rider->id)
            ->whereBetween('attempted_at', [$from, $to])
            ->get();

        $deliveredCount = $attemptsInPeriod->where('outcome', 'delivered')->count();
        $failedCount = $attemptsInPeriod->where('outcome', 'failed')->count();

        $unremittedCodOnHand = round((float) CodCollection::where('rider_id', $rider->id)
            ->where('status', 'collected')
            ->sum('collected_amount'), 2);

        $withdrawalRequests = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::where('requester_type', 'Rider')
                ->where('requester_id', (int) $rider->id)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get()
            : collect();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'period'              => [
                    'from_date' => $fromDate,
                    'to_date'   => $toDate,
                ],
                'wallet'              => [
                    'id'                     => (int) $riderWallet->id,
                    'available_balance'      => round((float) $riderWallet->available_balance, 2),
                    'pending_balance'        => round((float) $riderWallet->pending_balance, 2),
                    'unremitted_cod_on_hand' => $unremittedCodOnHand,
                    'can_withdraw'           => $unremittedCodOnHand <= 0.009 && (float) $riderWallet->available_balance >= 100.00,
                    'total_earned'           => round((float) $riderWallet->total_earned, 2),
                    'total_withdrawn'        => round((float) $riderWallet->total_withdrawn, 2),
                    'last_credited_at'       => $riderWallet->last_credited_at?->toIso8601String(),
                ],
                'summary'             => [
                    'today_earnings'         => $todayEarnings,
                    'today_deliveries_count' => $todayDeliveries->count(),
                    'week_earnings'          => $weekEarnings,
                    'week_deliveries_count'  => $weekDeliveries->count(),
                    'period_delivered_count' => $deliveredCount,
                    'period_failed_count'    => $failedCount,
                    'period_realized_income' => round((float) $walletTransactions->where('entry_type', 'commission_released')->sum('amount'), 2),
                ],
                'transactions'        => $walletTransactions->map(fn (WalletTransaction $tx) => [
                    'id'                      => (int) $tx->id,
                    'transaction_reference'   => (string) $tx->transaction_reference,
                    'entry_type'              => (string) $tx->entry_type,
                    'payment_method'          => (string) $tx->payment_method,
                    'amount'                  => round((float) $tx->amount, 2),
                    'pending_balance_after'   => round((float) $tx->pending_balance_after, 2),
                    'available_balance_after' => round((float) $tx->available_balance_after, 2),
                    'status'                  => (string) $tx->status,
                    'description'             => (string) $tx->description,
                    'seller_order_number'     => $tx->sellerOrder?->seller_order_number,
                    'tracking_number'         => $tx->shipment?->tracking_number,
                    'created_at'              => $tx->created_at?->toIso8601String(),
                ])->values()->all(),
                'withdrawal_requests' => $withdrawalRequests->map(fn (WithdrawalRequest $w) => [
                    'id'                     => (int) $w->id,
                    'withdrawal_reference'   => (string) $w->withdrawal_reference,
                    'requested_amount'       => round((float) $w->requested_amount, 2),
                    'payout_method'          => 'GCASH',
                    'account_name'           => (string) $w->account_name,
                    'account_number'         => (string) $w->account_number,
                    'status'                 => (string) $w->status,
                    'rejection_reason'       => $w->rejection_reason,
                    'payout_proof_reference' => $w->payout_proof_reference,
                    'created_at'             => $w->created_at?->toIso8601String(),
                    'paid_at'                => $w->paid_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:100', 'max:500000'],
            'account_number'   => ['required', 'string', 'min:10', 'max:20'],
            'account_name'     => ['nullable', 'string', 'max:120'],
            'idempotency_key'  => ['nullable', 'string', 'max:64'],
        ]);

        $accountName = trim((string) ($validated['account_name'] ?? ''));
        if ($accountName === '') {
            $accountName = $rider->full_name ?: "Rider #{$rider->id}";
        }

        try {
            $withdrawal = $this->roleWalletService->submitWithdrawalRequest(
                ownerType: 'Rider',
                ownerId: (int) $rider->id,
                ownerName: $rider->full_name,
                amount: (float) $validated['requested_amount'],
                payoutMethod: 'GCASH',
                accountName: $accountName,
                accountNumber: (string) $validated['account_number'],
                idempotencyKey: $validated['idempotency_key'] ?? null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => collect($e->errors())->flatten()->first() ?? 'Could not submit GCash withdrawal request.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "GCash Withdrawal Request #{$withdrawal->withdrawal_reference} for ₱" . number_format((float) $withdrawal->requested_amount, 2) . " submitted for approval!",
            'data'    => [
                'id'                   => (int) $withdrawal->id,
                'withdrawal_reference' => (string) $withdrawal->withdrawal_reference,
                'requested_amount'     => round((float) $withdrawal->requested_amount, 2),
                'payout_method'        => 'GCASH',
                'account_name'         => (string) $withdrawal->account_name,
                'account_number'       => (string) $withdrawal->account_number,
                'status'               => (string) $withdrawal->status,
                'created_at'           => $withdrawal->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function ratings(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        if (! Schema::hasTable('order_service_ratings')) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'average_rating' => 5.0,
                    'total_ratings'  => 0,
                    'ratings'        => [],
                ],
            ]);
        }

        $ratings = OrderServiceRating::with(['buyer', 'sellerOrder', 'sellerProfile'])
            ->where('rider_id', $rider->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $avgRating = round((float) OrderServiceRating::where('rider_id', $rider->id)->avg('rider_delivery_rating'), 2);
        $totalRatings = OrderServiceRating::where('rider_id', $rider->id)->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'average_rating' => $totalRatings > 0 ? $avgRating : 5.0,
                'total_ratings'  => $totalRatings,
                'ratings'        => $ratings->map(fn (OrderServiceRating $r) => [
                    'id'                      => (int) $r->id,
                    'seller_order_number'     => $r->sellerOrder?->seller_order_number ?? 'N/A',
                    'buyer_name'              => $r->buyer?->first_name ? ($r->buyer->first_name . ' ' . substr((string) $r->buyer->last_name, 0, 1) . '.') : 'Verified Buyer',
                    'rider_delivery_rating'   => (int) $r->rider_delivery_rating,
                    'parcel_condition_rating' => (int) $r->parcel_condition_rating,
                    'overall_average_rating'  => round((float) $r->overall_average_rating, 2),
                    'review_comment'          => $r->review_comment,
                    'created_at'              => $r->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $notifications = $rider->notifications()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $activeAssignments = DeliveryAssignment::where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->get();

        $unremittedCount = CodCollection::where('rider_id', $rider->id)->where('status', 'collected')->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'unread_count'  => $rider->unreadNotifications()->count(),
                'badges'        => $this->deliveryApiController->buildPendingActionBadges($rider, $activeAssignments, $unremittedCount),
                'notifications' => $notifications->map(fn ($n) => [
                    'id'              => (string) $n->id,
                    'title'           => (string) ($n->data['title'] ?? 'Rider Notification'),
                    'message'         => (string) ($n->data['message'] ?? ''),
                    'type'            => (string) ($n->data['type'] ?? 'info'),
                    'order_id'        => $n->data['order_id'] ?? null,
                    'seller_order_id' => $n->data['seller_order_id'] ?? null,
                    'shipment_id'     => $n->data['shipment_id'] ?? null,
                    'is_read'         => $n->read_at !== null,
                    'read_at'         => $n->read_at?->toIso8601String(),
                    'created_at'      => $n->created_at?->toIso8601String(),
                ])->values()->all(),
            ],
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        /** @var Rider $rider */
        $rider = $request->user();

        $notification = $rider->notifications()->where('id', $id)->first();
        if ($notification && $notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'status'       => 'success',
            'unread_count' => $rider->unreadNotifications()->count(),
        ]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        /** @var Rider $rider */
        $rider = $request->user();

        $rider->unreadNotifications->markAsRead();

        return response()->json([
            'status'       => 'success',
            'unread_count' => 0,
        ]);
    }

    protected function formatCodCollection(CodCollection $c): array
    {
        $expected = round((float) $c->expected_amount, 2);
        $collected = round((float) $c->collected_amount, 2);
        $remitted = $c->remitted_amount !== null ? round((float) $c->remitted_amount, 2) : null;
        $shortfall = $remitted !== null ? max(0.0, round($expected - $remitted, 2)) : 0.00;
        $riderCommission = WebRiderDeliveryController::resolveCollectionRiderCommission($c);

        return [
            'id'                   => (int) $c->id,
            'collection_reference' => (string) $c->collection_reference,
            'shipment_id'          => (int) $c->shipment_id,
            'seller_order_id'      => (int) $c->seller_order_id,
            'tracking_number'      => (string) ($c->shipment?->tracking_number ?? 'N/A'),
            'seller_order_number'  => (string) ($c->sellerOrder?->seller_order_number ?? 'N/A'),
            'expected_amount'      => $expected,
            'collected_amount'     => $collected,
            'remitted_amount'      => $remitted,
            'shortfall_amount'     => $shortfall,
            'product_subtotal'     => round((float) $c->product_subtotal, 2),
            'shipping_fee'         => round((float) $c->shipping_fee, 2),
            'rider_commission'     => $riderCommission,
            'status'               => (string) $c->status,
            'remittance_reference' => $c->remittance_reference,
            'notes'                => $c->notes,
            'collected_at'         => $c->collected_at?->toIso8601String(),
            'remitted_at'          => $c->remitted_at?->toIso8601String(),
            'verified_at'          => $c->verified_at?->toIso8601String(),
        ];
    }

    protected function formatCodRemittance(CodRemittance $r): array
    {
        return [
            'id'                     => (int) $r->id,
            'remittance_reference'   => (string) $r->remittance_reference,
            'total_collected_amount' => round((float) $r->total_collected_amount, 2),
            'total_rider_commission' => round((float) $r->total_rider_commission, 2),
            'net_remitted_amount'    => round((float) $r->total_collected_amount, 2),
            'status'                 => (string) $r->status,
            'notes'                  => $r->notes,
            'submitted_at'           => $r->submitted_at?->toIso8601String(),
            'verified_at'            => $r->verified_at?->toIso8601String(),
        ];
    }
}