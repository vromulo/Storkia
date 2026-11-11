<?php

namespace App\Services;

use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReturnRefundLedgerService
{
    public const ENTRY_ESCROW_REVERSAL     = 'refund_escrow_reversal';
    public const ENTRY_COMMISSION_RELEASED = 'refund_commission_released';
    public const ENTRY_SELLER_DEDUCTION    = 'refund_seller_deduction';
    public const ENTRY_RETURN_RIDER_PAY    = 'return_pickup_pay';
    public const ENTRY_RETURN_COURIER_PAY  = 'return_delivery_pay';

    public function __construct(
        protected RoleWalletService $roleWalletService,
        protected CommissionCalculatorService $commissionCalculator
    ) {}

    public function estimateSellerDeduction(SellerOrder $sellerOrder, ReturnRequest $request): array
    {
        $sellerOrder->loadMissing(['commissionRecord', 'payment']);

        return $this->composeDeduction(
            $sellerOrder,
            $sellerOrder->commissionRecord,
            $request,
            $request->returnShipment
        );
    }

    public function settleRefund(ReturnRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            /** @var ReturnRequest $locked */
            $locked = ReturnRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($locked->ledger_settled_at !== null) {
                return $locked->settlement_snapshot ?? [];
            }

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = SellerOrder::with(['sellerProfile', 'logisticsProfile', 'payment', 'shipment'])
                ->findOrFail($locked->seller_order_id);

            $record = $this->ensureCommissionRecord($sellerOrder);
            $returnShipment = $locked->returnShipment;
            $breakdown = $this->composeDeduction($sellerOrder, $record, $locked, $returnShipment);

            $method = $sellerOrder->effective_payment_method;
            $forwardShipmentId = $sellerOrder->shipment?->id;
            $orderNumber = $sellerOrder->seller_order_number;

            $escrowReversed = $this->reverseSellerPendingEscrow($sellerOrder, $method);

            $this->roleWalletService->releasePendingEscrowToAvailable(
                ownerType: 'Logistics',
                ownerId: (int) $sellerOrder->logistics_profile_id,
                ownerName: $sellerOrder->logisticsProfile?->business_name ?? "Logistics #{$sellerOrder->logistics_profile_id}",
                amount: $breakdown['logistics_earning'],
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $forwardShipmentId,
                entryType: self::ENTRY_COMMISSION_RELEASED,
                paymentMethod: $method,
                description: "Shipping earnings kept for refunded Order #{$orderNumber} (not affected by the refund)",
                meta: ['return_request' => $locked->request_number]
            );

            if ($record->courier_id && $breakdown['courier_earning'] > 0) {
                $alreadyPaidDirect = WalletTransaction::where('owner_type', 'Courier')
                    ->where('owner_id', $record->courier_id)
                    ->where('shipment_id', $forwardShipmentId)
                    ->where('entry_type', 'first_mile_pickup_pay')
                    ->exists();

                if (! $alreadyPaidDirect) {
                    $courier = Courier::find($record->courier_id);
                    $this->roleWalletService->releasePendingEscrowToAvailable(
                        ownerType: 'Courier',
                        ownerId: (int) $record->courier_id,
                        ownerName: $courier?->full_name ?? "Courier #{$record->courier_id}",
                        amount: $breakdown['courier_earning'],
                        sellerOrderId: (int) $sellerOrder->id,
                        shipmentId: $forwardShipmentId,
                        entryType: self::ENTRY_COMMISSION_RELEASED,
                        paymentMethod: $method,
                        description: "First-Mile pickup earnings kept for refunded Order #{$orderNumber} (not affected by the refund)",
                        meta: ['return_request' => $locked->request_number]
                    );
                }
            }

            if ($record->rider_id && $breakdown['rider_earning'] > 0) {
                $rider = Rider::find($record->rider_id);
                $this->roleWalletService->releasePendingEscrowToAvailable(
                    ownerType: 'Rider',
                    ownerId: (int) $record->rider_id,
                    ownerName: $rider?->full_name ?? "Rider #{$record->rider_id}",
                    amount: $breakdown['rider_earning'],
                    sellerOrderId: (int) $sellerOrder->id,
                    shipmentId: $forwardShipmentId,
                    entryType: self::ENTRY_COMMISSION_RELEASED,
                    paymentMethod: $method,
                    description: "Delivery earnings kept for refunded Order #{$orderNumber} (not affected by the refund)",
                    meta: ['return_request' => $locked->request_number]
                );
            }

            $this->debitSellerWallet(
                sellerProfileId: (int) $sellerOrder->seller_profile_id,
                sellerName: $sellerOrder->sellerProfile?->business_name ?? "Seller #{$sellerOrder->seller_profile_id}",
                amount: $breakdown['seller_deduction_total'],
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $forwardShipmentId,
                paymentMethod: $method,
                description: "Refund deduction for Order #{$orderNumber} ({$locked->request_number})",
                meta: $breakdown
            );

            $record->update([
                'status'     => 'refunded',
                'settled_at' => now(),
            ]);

            $snapshot = array_merge($breakdown, [
                'seller_escrow_reversed' => $escrowReversed,
                'settled_at'             => now()->toIso8601String(),
            ]);

            $locked->update([
                'ledger_settled_at'       => now(),
                'seller_deduction_amount' => $breakdown['seller_deduction_total'],
                'settlement_snapshot'     => $snapshot,
            ]);

            return $snapshot;
        });
    }

    public function creditReturnLegPay(ReturnShipment $returnShipment, string $leg): ?WalletTransaction
    {
        if (! in_array($leg, ['rider', 'courier'], true)) {
            throw new InvalidArgumentException("Unknown return leg [{$leg}]. Use 'rider' or 'courier'.");
        }

        return DB::transaction(function () use ($returnShipment, $leg) {
            /** @var ReturnShipment $locked */
            $locked = ReturnShipment::where('id', $returnShipment->id)->lockForUpdate()->firstOrFail();

            $isRider = $leg === 'rider';
            $paidAtColumn = $isRider ? 'rider_paid_at' : 'courier_paid_at';
            $payColumn = $isRider ? 'rider_pay' : 'courier_pay';

            if ($locked->{$paidAtColumn} !== null) {
                return null;
            }

            $driver = $isRider ? $locked->rider : $locked->courier;
            if (! $driver) {
                return null;
            }

            $weight = (float) $locked->total_weight;
            $payInfo = $isRider
                ? $this->commissionCalculator->calculateDistanceScaledDriverPay(
                    'delivery',
                    (array) ($locked->pickup_address_snapshot ?? []),
                    (array) ($locked->hub_address_snapshot ?? []),
                    $weight
                )
                : $this->commissionCalculator->calculateDistanceScaledDriverPay(
                    'pickup',
                    (array) ($locked->hub_address_snapshot ?? []),
                    (array) ($locked->return_address_snapshot ?? []),
                    $weight
                );

            $pay = round((float) ($payInfo['total_pay'] ?? 0), 2);
            $sellerOrder = $locked->sellerOrder()->with('payment')->first();
            $method = $sellerOrder?->effective_payment_method ?? 'COD';

            $transaction = null;
            if ($pay > 0) {
                $transaction = $this->roleWalletService->creditAvailableDirect(
                    ownerType: $isRider ? 'Rider' : 'Courier',
                    ownerId: (int) $driver->id,
                    ownerName: $driver->full_name ?? ($isRider ? "Rider #{$driver->id}" : "Courier #{$driver->id}"),
                    amount: $pay,
                    sellerOrderId: (int) $locked->seller_order_id,
                    shipmentId: $locked->shipment_id ? (int) $locked->shipment_id : null,
                    entryType: $isRider ? self::ENTRY_RETURN_RIDER_PAY : self::ENTRY_RETURN_COURIER_PAY,
                    paymentMethod: $method,
                    description: ($isRider ? 'Return pickup from buyer' : 'Return delivery to seller')
                        . " ({$payInfo['distance_tier']}) for Return #{$locked->tracking_number}",
                    meta: array_merge($payInfo, ['return_tracking_number' => $locked->tracking_number])
                );
            }

            $locked->update([
                $payColumn    => $pay,
                $paidAtColumn => now(),
            ]);

            return $transaction;
        });
    }

    protected function composeDeduction(
        SellerOrder $sellerOrder,
        ?CommissionRecord $record,
        ReturnRequest $request,
        ?ReturnShipment $returnShipment
    ): array {
        $method = $sellerOrder->effective_payment_method;
        $logisticsEarning = round((float) ($record?->logistics_total_earning ?? 0), 2);
        $courierEarning = ($record && $record->courier_id)
            ? round((float) ($record->courier_commission_amount ?? 0), 2)
            : 0.00;
        $riderEarning = ($record && $record->rider_id)
            ? round((float) ($record->rider_commission_amount ?? 0), 2)
            : 0.00;
        $returnRiderPay = round((float) ($returnShipment?->rider_pay ?? 0), 2);
        $returnCourierPay = round((float) ($returnShipment?->courier_pay ?? 0), 2);

        $gcashRecovery = $method === 'GCASH'
            ? round((float) $request->refund_amount, 2)
            : 0.00;

        $total = round(
            $logisticsEarning + $courierEarning + $riderEarning + $returnRiderPay + $returnCourierPay + $gcashRecovery,
            2
        );

        return [
            'payment_method'          => $method,
            'resolution'              => $request->effectiveResolution(),
            'refund_amount'           => round((float) $request->refund_amount, 2),
            'seller_net_cancelled'    => round((float) ($record?->seller_net_earning ?? 0), 2),
            'admin_commission_waived' => round((float) ($record?->admin_commission_amount ?? 0), 2),
            'logistics_earning'       => $logisticsEarning,
            'courier_earning'         => $courierEarning,
            'rider_earning'           => $riderEarning,
            'return_rider_pay'        => $returnRiderPay,
            'return_courier_pay'      => $returnCourierPay,
            'gcash_refund_recovery'   => $gcashRecovery,
            'seller_deduction_total'  => $total,
        ];
    }

    protected function ensureCommissionRecord(SellerOrder $sellerOrder): CommissionRecord
    {
        $record = CommissionRecord::where('seller_order_id', $sellerOrder->id)->first();

        return $record ?: $this->roleWalletService->recordPendingEscrowOnDelivery($sellerOrder);
    }

    protected function reverseSellerPendingEscrow(SellerOrder $sellerOrder, string $paymentMethod): float
    {
        $wallet = $this->roleWalletService->getOrCreateWallet(
            'Seller',
            (int) $sellerOrder->seller_profile_id,
            $sellerOrder->sellerProfile?->business_name
        );

        /** @var RoleWallet $locked */
        $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

        $existing = WalletTransaction::where('role_wallet_id', $locked->id)
            ->where('seller_order_id', $sellerOrder->id)
            ->where('entry_type', self::ENTRY_ESCROW_REVERSAL)
            ->first();

        if ($existing) {
            return abs((float) $existing->amount);
        }

        $pendingCredit = WalletTransaction::where('role_wallet_id', $locked->id)
            ->where('seller_order_id', $sellerOrder->id)
            ->where('entry_type', 'pending_escrow_credit')
            ->first();

        $amount = $pendingCredit ? round((float) $pendingCredit->amount, 2) : 0.00;
        if ($amount <= 0) {
            return 0.00;
        }

        $newPending = max(0.00, round((float) $locked->pending_balance - $amount, 2));
        $locked->update(['pending_balance' => $newPending]);

        if ($pendingCredit->status === 'pending') {
            $pendingCredit->update(['status' => 'cancelled']);
        }

        WalletTransaction::create([
            'role_wallet_id'          => $locked->id,
            'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
            'owner_type'              => 'Seller',
            'owner_id'                => (int) $sellerOrder->seller_profile_id,
            'seller_order_id'         => (int) $sellerOrder->id,
            'shipment_id'             => $sellerOrder->shipment?->id,
            'entry_type'              => self::ENTRY_ESCROW_REVERSAL,
            'payment_method'          => $paymentMethod,
            'amount'                  => -$amount,
            'pending_balance_after'   => $newPending,
            'available_balance_after' => (float) $locked->available_balance,
            'status'                  => 'completed',
            'description'             => "Pending earnings cancelled: Order #{$sellerOrder->seller_order_number} was refunded to the buyer.",
            'meta_snapshot'           => ['reversed_amount' => $amount],
        ]);

        return $amount;
    }

    protected function debitSellerWallet(
        int $sellerProfileId,
        string $sellerName,
        float $amount,
        int $sellerOrderId,
        ?int $shipmentId,
        string $paymentMethod,
        string $description,
        array $meta = []
    ): ?WalletTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $wallet = $this->roleWalletService->getOrCreateWallet('Seller', $sellerProfileId, $sellerName);
        /** @var RoleWallet $locked */
        $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

        $existing = WalletTransaction::where('role_wallet_id', $locked->id)
            ->where('seller_order_id', $sellerOrderId)
            ->where('entry_type', self::ENTRY_SELLER_DEDUCTION)
            ->first();

        if ($existing) {
            return $existing;
        }

        $currentAvailable = round((float) $locked->available_balance, 2);
        $currentArrears = round((float) ($locked->platform_commission_arrears ?? 0), 2);

        if ($currentAvailable >= $amount) {
            $newAvailable = round($currentAvailable - $amount, 2);
            $newArrears = $currentArrears;
        } else {
            $shortfall = round($amount - max(0.0, $currentAvailable), 2);
            $newAvailable = round($currentAvailable - $amount, 2);
            $newArrears = round($currentArrears + $shortfall, 2);
        }

        $locked->update([
            'available_balance'           => $newAvailable,
            'platform_commission_arrears' => $newArrears,
        ]);

        if (
            $newArrears >= RoleWalletService::GCASH_ARREARS_CAP
            && Schema::hasColumn('seller_profiles', 'gcash_blocked_for_arrears')
        ) {
            SellerProfile::where('id', $sellerProfileId)->update(['gcash_blocked_for_arrears' => true]);
        }

        return WalletTransaction::create([
            'role_wallet_id'          => $locked->id,
            'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
            'owner_type'              => 'Seller',
            'owner_id'                => $sellerProfileId,
            'seller_order_id'         => $sellerOrderId,
            'shipment_id'             => $shipmentId,
            'entry_type'              => self::ENTRY_SELLER_DEDUCTION,
            'payment_method'          => $paymentMethod,
            'amount'                  => -$amount,
            'pending_balance_after'   => (float) $locked->pending_balance,
            'available_balance_after' => $newAvailable,
            'status'                  => 'completed',
            'description'             => $description,
            'meta_snapshot'           => $meta,
        ]);
    }
}