<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Api\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\PickupAssignment;
use App\Models\ReturnShipment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\CourierMobileSchemaService;
use App\Services\ReturnRefundLedgerService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CourierReportApiController extends Controller
{
    use ResolvesReportPeriod;

    protected const MAX_ASSIGNMENTS = 200;

    protected const MAX_TRANSACTIONS = 200;

    protected const MAX_WITHDRAWALS = 20;

    protected const ACTIVE_PICKUP_STATUSES = ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'];

    public function __construct(protected RoleWalletService $roleWalletService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        $courier = $request->user();

        [$fromDate, $toDate, $from, $to] = $this->resolveReportPeriod($request);

        $wallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        $assignmentsInPeriod = PickupAssignment::with(['shipment.sellerProfile', 'sellerOrder'])
            ->where('courier_id', $courier->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $completedPickups = $assignmentsInPeriod->where('status', 'completed');

        $totalWeightHauled = $completedPickups->sum(fn ($a) => (float) ($a->shipment->total_weight ?? 0));

        $ledger = WalletTransaction::where('owner_type', 'Courier')
            ->where('owner_id', $courier->id)
            ->whereBetween('created_at', [$from, $to]);

        $transactions = (clone $ledger)
            ->with(['sellerOrder', 'shipment'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_TRANSACTIONS)
            ->get();

        $transactionsTotal = (clone $ledger)->count();

        $returnDeliveryPay = round((float) (clone $ledger)
            ->where('entry_type', ReturnRefundLedgerService::ENTRY_RETURN_COURIER_PAY)
            ->sum('amount'), 2);

        $returnDeliveries = ReturnShipment::where('current_courier_id', $courier->id)
            ->whereNotNull('courier_paid_at')
            ->whereBetween('courier_paid_at', [$from, $to]);

        $withdrawals = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::where('requester_type', 'Courier')
                ->where('requester_id', (int) $courier->id)
                ->orderByDesc('created_at')
                ->limit(self::MAX_WITHDRAWALS)
                ->get()
            : collect();

        $assignmentLog = $assignmentsInPeriod->take(self::MAX_ASSIGNMENTS);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'period'              => [
                    'from_date' => $fromDate,
                    'to_date'   => $toDate,
                ],
                'summary'             => [
                    'total_assigned'           => $assignmentsInPeriod->count(),
                    'completed_count'          => $completedPickups->count(),
                    'active_count'             => $assignmentsInPeriod->whereIn('status', self::ACTIVE_PICKUP_STATUSES)->count(),
                    'reassigned_count'         => $assignmentsInPeriod->where('status', 'reassigned')->count(),
                    'total_weight_hauled'      => round((float) $totalWeightHauled, 2),
                    'period_return_deliveries' => (clone $returnDeliveries)->count(),
                    'period_return_pay'        => $returnDeliveryPay,
                    'wallet_available'         => round((float) $wallet->available_balance, 2),
                    'wallet_pending'           => round((float) $wallet->pending_balance, 2),
                    'total_earned'             => round((float) $wallet->total_earned, 2),
                ],
                'assignments_total'   => $assignmentsInPeriod->count(),
                'assignments'         => $assignmentLog->map(fn (PickupAssignment $a) => [
                    'id'              => (int) $a->id,
                    'tracking_number' => (string) ($a->shipment?->tracking_number ?? 'N/A'),
                    'seller_name'     => (string) ($a->shipment?->sellerProfile?->business_name ?? 'N/A'),
                    'weight'          => round((float) ($a->shipment?->total_weight ?? 0), 2),
                    'status'          => (string) $a->status,
                    'assigned_at'     => $a->created_at?->toIso8601String(),
                ])->values()->all(),
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
                    'payout_method'          => (string) $w->payout_method,
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