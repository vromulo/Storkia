<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAttempt;
use App\Models\ReturnShipment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\ReturnRefundLedgerService;
use App\Services\RiderMobileSchemaService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RiderReportApiController extends Controller
{
    use ResolvesReportPeriod;

    protected const MAX_TRANSACTIONS = 200;

    protected const MAX_WITHDRAWALS = 20;

    public function __construct(protected RoleWalletService $roleWalletService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        $rider = $request->user();

        [$fromDate, $toDate, $from, $to] = $this->resolveReportPeriod($request);

        $wallet = $this->roleWalletService->getOrCreateWallet('Rider', (int) $rider->id, $rider->full_name);

        $ledger = WalletTransaction::where('owner_type', 'Rider')
            ->where('owner_id', $rider->id)
            ->whereBetween('created_at', [$from, $to]);

        $transactions = (clone $ledger)
            ->with(['sellerOrder', 'shipment'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_TRANSACTIONS)
            ->get();

        $transactionsTotal = (clone $ledger)->count();

        $realizedIncome = round((float) (clone $ledger)
            ->where('entry_type', 'commission_released')
            ->sum('amount'), 2);

        $returnPickupPay = round((float) (clone $ledger)
            ->where('entry_type', ReturnRefundLedgerService::ENTRY_RETURN_RIDER_PAY)
            ->sum('amount'), 2);

        $attempts = DeliveryAttempt::where('rider_id', $rider->id)
            ->whereBetween('attempted_at', [$from, $to]);

        $deliveredCount = (clone $attempts)->where('outcome', 'delivered')->count();
        $failedCount = (clone $attempts)->where('outcome', 'failed')->count();

        $assignedCount = DeliveryAssignment::where('rider_id', $rider->id)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $collections = CodCollection::where('rider_id', $rider->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $codCollected = round((float) $collections->sum('collected_amount'), 2);
        $codRemitted = round((float) $collections->sum(fn ($c) => (float) ($c->remitted_amount ?? 0)), 2);
        $codShortfall = round((float) $collections->sum(
            fn ($c) => max(0, (float) $c->expected_amount - (float) ($c->remitted_amount ?? $c->collected_amount))
        ), 2);

        $returnPickups = ReturnShipment::where('current_rider_id', $rider->id)
            ->whereNotNull('rider_paid_at')
            ->whereBetween('rider_paid_at', [$from, $to]);

        $withdrawals = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::where('requester_type', 'Rider')
                ->where('requester_id', (int) $rider->id)
                ->orderByDesc('created_at')
                ->limit(self::MAX_WITHDRAWALS)
                ->get()
            : collect();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'period'              => [
                    'from_date' => $fromDate,
                    'to_date'   => $toDate,
                ],
                'summary'             => [
                    'total_assigned'         => max($assignedCount, $deliveredCount + $failedCount),
                    'delivered_count'        => $deliveredCount,
                    'failed_count'           => $failedCount,
                    'period_realized_income' => $realizedIncome,
                    'period_return_pickups'  => (clone $returnPickups)->count(),
                    'period_return_pay'      => $returnPickupPay,
                    'period_cod_collected'   => $codCollected,
                    'cod_remitted'           => $codRemitted,
                    'cod_shortfall'          => $codShortfall,
                    'wallet_available'       => round((float) $wallet->available_balance, 2),
                    'wallet_pending'         => round((float) $wallet->pending_balance, 2),
                    'total_earned'           => round((float) $wallet->total_earned, 2),
                ],
                'transactions_total'  => $transactionsTotal,
                'transactions'        => $transactions->map(fn (WalletTransaction $tx) => [
                    'id'                      => (int) $tx->id,
                    'transaction_reference'   => (string) $tx->transaction_reference,
                    'entry_type'              => (string) $tx->entry_type,
                    'entry_label'             => ucwords(str_replace('_', ' ', (string) $tx->entry_type)),
                    'amount'                  => round((float) $tx->amount, 2),
                    'available_balance_after' => round((float) $tx->available_balance_after, 2),
                    'status'                  => (string) $tx->status,
                    'description'             => (string) $tx->description,
                    'seller_order_number'     => $tx->sellerOrder?->seller_order_number,
                    'tracking_number'         => $tx->shipment?->tracking_number,
                    'created_at'              => $tx->created_at?->toIso8601String(),
                ])->values()->all(),
                'withdrawal_requests' => $withdrawals->map(fn (WithdrawalRequest $w) => [
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
}