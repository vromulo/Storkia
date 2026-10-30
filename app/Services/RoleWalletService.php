<?php

namespace App\Services;

use App\Models\CommissionRecord;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementItem;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleWalletService
{
    public const GCASH_ARREARS_CREDIT_LIMIT = 1000.00;
    public const MIN_SELLER_PAYOUT_AMOUNT = 100.00;

    public function __construct(
        protected CommissionCalculatorService $commissionCalculatorService
    ) {}

    public function getOrCreateWallet(string $ownerType, int $ownerId, ?string $ownerName = null): RoleWallet
    {
        $wallet = RoleWallet::where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->first();

        if (! $wallet) {
            $wallet = RoleWallet::create([
                'owner_type'        => $ownerType,
                'owner_id'          => $ownerId,
                'owner_name'        => $ownerName ?: "{$ownerType} #{$ownerId}",
                'pending_balance'   => 0.00,
                'available_balance' => 0.00,
                'total_earned'      => 0.00,
                'total_withdrawn'   => 0.00,
            ]);
        } elseif ($ownerName && $wallet->owner_name !== $ownerName) {
            $wallet->update(['owner_name' => $ownerName]);
        }

        return $wallet;
    }

    public function ensureOrderCommissionRecord(SellerOrder $sellerOrder): CommissionRecord
    {
        $existing = CommissionRecord::where('seller_order_id', $sellerOrder->id)->first();
        if ($existing) {
            return $existing;
        }

        $sellerOrder->loadMissing(['shipment', 'codCollection', 'sellerProfile', 'logisticsProfile']);
        $split = $this->commissionCalculatorService->calculateOrderSplitWithSubsidies($sellerOrder);

        return CommissionRecord::create([
            'seller_order_id'               => $sellerOrder->id,
            'shipment_id'                   => $sellerOrder->shipment?->id,
            'cod_collection_id'             => $sellerOrder->codCollection?->id,
            'seller_profile_id'             => $sellerOrder->seller_profile_id,
            'logistics_profile_id'          => $sellerOrder->logistics_profile_id ?? $sellerOrder->shipment?->logistics_profile_id ?? 1,
            'rider_id'                      => $sellerOrder->shipment?->current_rider_id ?? $sellerOrder->codCollection?->rider_id,
            'product_subtotal'              => $split['product_subtotal'],
            'shipping_fee'                  => $split['shipping_fee'],
            'total_cod_amount'              => $split['total_cod_amount'],
            'admin_commission_rate'         => $split['admin_commission_rate'],
            'admin_commission_amount'       => $split['admin_commission_amount'],
            'seller_net_earning'            => $split['seller_net_earning'],
            'logistics_commission_rate'     => $split['logistics_commission_rate'],
            'logistics_commission_amount'   => $split['logistics_commission_amount'],
            'rider_commission_rate'         => $split['rider_commission_rate'],
            'rider_commission_amount'       => $split['rider_commission_amount'],
            'logistics_operational_revenue' => $split['logistics_operational_revenue'],
            'logistics_total_earning'       => $split['logistics_total_earning'],
            'status'                        => 'recorded',
            'calculated_at'                 => now(),
        ]);
    }

    public function recordCourierPickupPay(
        int $courierId,
        string $courierName,
        SellerOrder $sellerOrder,
        float $amount
    ): void {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($courierId, $courierName, $sellerOrder, $amount) {
            $alreadyPaid = WalletTransaction::where('seller_order_id', $sellerOrder->id)
                ->where('owner_type', 'Courier')
                ->where('owner_id', $courierId)
                ->where('entry_type', 'pickup_pay_released')
                ->exists();

            if ($alreadyPaid) {
                return;
            }

            $wallet = $this->getOrCreateWallet('Courier', $courierId, $courierName);
            $wallet = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newAvailable = round((float) $wallet->available_balance + $amount, 2);
            $newEarned = round((float) $wallet->total_earned + $amount, 2);

            $wallet->update([
                'available_balance' => $newAvailable,
                'total_earned'      => $newEarned,
                'last_credited_at'  => now(),
            ]);

            WalletTransaction::create([
                'role_wallet_id'          => $wallet->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(7)),
                'owner_type'              => 'Courier',
                'owner_id'                => $courierId,
                'seller_order_id'         => $sellerOrder->id,
                'shipment_id'             => $sellerOrder->shipment?->id,
                'entry_type'              => 'pickup_pay_released',
                'payment_method'          => $sellerOrder->effective_payment_method,
                'amount'                  => $amount,
                'pending_balance_after'   => (float) $wallet->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => "Distance-scaled First-Mile Pickup Pay credited for Order #{$sellerOrder->seller_order_number}.",
            ]);
        });
    }

    public function recordPendingEscrowOnDelivery(SellerOrder $sellerOrder): CommissionRecord
    {
        return DB::transaction(function () use ($sellerOrder) {
            $sellerOrder->loadMissing(['sellerProfile', 'logisticsProfile', 'shipment.currentRider', 'payment', 'extra']);
            $record = $this->ensureOrderCommissionRecord($sellerOrder);
            $split = $this->commissionCalculatorService->calculateOrderSplitWithSubsidies($sellerOrder);
            $payMethod = $sellerOrder->effective_payment_method;

            $allocations = $this->buildRoleAllocations($sellerOrder, $record, $split);

            foreach ($allocations as $alloc) {
                if ($alloc['amount'] <= 0) {
                    continue;
                }

                $alreadyEscrowed = WalletTransaction::where('seller_order_id', $sellerOrder->id)
                    ->where('owner_type', $alloc['owner_type'])
                    ->where('owner_id', $alloc['owner_id'])
                    ->where('entry_type', 'escrow_pending')
                    ->exists();

                if ($alreadyEscrowed) {
                    continue;
                }

                $wallet = RoleWallet::where('owner_type', $alloc['owner_type'])
                    ->where('owner_id', $alloc['owner_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    $wallet = $this->getOrCreateWallet($alloc['owner_type'], $alloc['owner_id'], $alloc['owner_name']);
                    $wallet = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
                }

                $newPending = round((float) $wallet->pending_balance + $alloc['amount'], 2);
                $wallet->update([
                    'pending_balance' => $newPending,
                ]);

                WalletTransaction::create([
                    'role_wallet_id'          => $wallet->id,
                    'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(7)),
                    'owner_type'              => $alloc['owner_type'],
                    'owner_id'                => $alloc['owner_id'],
                    'seller_order_id'         => $sellerOrder->id,
                    'shipment_id'             => $sellerOrder->shipment?->id,
                    'entry_type'              => 'escrow_pending',
                    'payment_method'          => $payMethod,
                    'amount'                  => $alloc['amount'],
                    'pending_balance_after'   => $newPending,
                    'available_balance_after' => (float) $wallet->available_balance,
                    'status'                  => 'pending',
                    'description'             => $alloc['pending_description'],
                    'meta_snapshot'           => $split,
                ]);
            }

            return $record;
        });
    }

    public function releaseCompletedOrderEarnings(SellerOrder $sellerOrder): CommissionRecord
    {
        return DB::transaction(function () use ($sellerOrder) {
            $sellerOrder->loadMissing(['sellerProfile', 'logisticsProfile', 'shipment.currentRider', 'payment', 'extra']);
            $record = $this->ensureOrderCommissionRecord($sellerOrder);
            $split = $this->commissionCalculatorService->calculateOrderSplitWithSubsidies($sellerOrder);
            $payMethod = $sellerOrder->effective_payment_method;

            $allocations = $this->buildRoleAllocations($sellerOrder, $record, $split);

            foreach ($allocations as $alloc) {
                if ($alloc['amount'] <= 0) {
                    continue;
                }

                $alreadyReleased = WalletTransaction::where('seller_order_id', $sellerOrder->id)
                    ->where('owner_type', $alloc['owner_type'])
                    ->where('owner_id', $alloc['owner_id'])
                    ->where('entry_type', 'commission_released')
                    ->exists();

                if ($alreadyReleased) {
                    continue;
                }

                $wallet = RoleWallet::where('owner_type', $alloc['owner_type'])
                    ->where('owner_id', $alloc['owner_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    $wallet = $this->getOrCreateWallet($alloc['owner_type'], $alloc['owner_id'], $alloc['owner_name']);
                    $wallet = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
                }

                $hadEscrow = WalletTransaction::where('seller_order_id', $sellerOrder->id)
                    ->where('owner_type', $alloc['owner_type'])
                    ->where('owner_id', $alloc['owner_id'])
                    ->where('entry_type', 'escrow_pending')
                    ->exists();

                $newPending = $hadEscrow
                    ? max(0.0, round((float) $wallet->pending_balance - $alloc['amount'], 2))
                    : (float) $wallet->pending_balance;

                // Direct-to-Seller GCash Auto-Offset Clearing Logic:
                // When payment is GCASH, the Buyer already sent 100% of Merchandise + Shipping directly to the Seller's personal GCash!
                // Therefore, for the Seller's E-Wallet on a GCASH order, we record their 90% earning in total_earned,
                // and auto-offset the 10% Admin Commission + 100% Shipping Fee owed by the Seller to the platform!
                if ($alloc['owner_type'] === 'Seller' && $payMethod === 'GCASH') {
                    $platformPayableFromSeller = round((float) $split['gcash_seller_owes_platform'], 2);
                    $newAvailable = round((float) $wallet->available_balance - $platformPayableFromSeller, 2);
                    $newTotalEarned = round((float) $wallet->total_earned + $alloc['amount'], 2);
                    $arrears = $newAvailable < 0 ? abs($newAvailable) : 0.00;

                    $walletUpdate = [
                        'pending_balance'   => $newPending,
                        'available_balance' => $newAvailable,
                        'total_earned'      => $newTotalEarned,
                        'last_credited_at'  => now(),
                    ];

                    if (Schema::hasColumn('role_wallets', 'platform_commission_arrears')) {
                        $walletUpdate['platform_commission_arrears'] = $arrears;
                    }

                    $wallet->update($walletUpdate);

                    if ($arrears >= self::GCASH_ARREARS_CREDIT_LIMIT && $sellerOrder->sellerProfile) {
                        if (Schema::hasColumn('seller_profiles', 'gcash_blocked_for_arrears')) {
                            $sellerOrder->sellerProfile->update(['gcash_blocked_for_arrears' => true]);
                        }
                    }

                    $releaseDesc = "GCash Direct Order #{$sellerOrder->seller_order_number} Completed: Buyer paid Seller's GCash directly; auto-offset -₱" . number_format($platformPayableFromSeller, 2) . " (10% Admin Comm + Shipping Fee) from Seller E-Wallet.";
                    $txAmount = -$platformPayableFromSeller;
                } else {
                    $newAvailable = round((float) $wallet->available_balance + $alloc['amount'], 2);
                    $newTotalEarned = round((float) $wallet->total_earned + $alloc['amount'], 2);
                    $arrears = $newAvailable < 0 ? abs($newAvailable) : 0.00;

                    $walletUpdate = [
                        'pending_balance'   => $newPending,
                        'available_balance' => $newAvailable,
                        'total_earned'      => $newTotalEarned,
                        'last_credited_at'  => now(),
                    ];

                    if (Schema::hasColumn('role_wallets', 'platform_commission_arrears')) {
                        $walletUpdate['platform_commission_arrears'] = $arrears;
                    }

                    $wallet->update($walletUpdate);

                    if ($alloc['owner_type'] === 'Seller' && $arrears < self::GCASH_ARREARS_CREDIT_LIMIT && $sellerOrder->sellerProfile) {
                        if (Schema::hasColumn('seller_profiles', 'gcash_blocked_for_arrears')) {
                            $sellerOrder->sellerProfile->update(['gcash_blocked_for_arrears' => false]);
                        }
                    }

                    $releaseDesc = $alloc['released_description'];
                    $txAmount = $alloc['amount'];
                }

                WalletTransaction::where('seller_order_id', $sellerOrder->id)
                    ->where('owner_type', $alloc['owner_type'])
                    ->where('owner_id', $alloc['owner_id'])
                    ->where('entry_type', 'escrow_pending')
                    ->update(['status' => 'released']);

                WalletTransaction::create([
                    'role_wallet_id'          => $wallet->id,
                    'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(7)),
                    'owner_type'              => $alloc['owner_type'],
                    'owner_id'                => $alloc['owner_id'],
                    'seller_order_id'         => $sellerOrder->id,
                    'shipment_id'             => $sellerOrder->shipment?->id,
                    'entry_type'              => 'commission_released',
                    'payment_method'          => $payMethod,
                    'amount'                  => $txAmount,
                    'pending_balance_after'   => $newPending,
                    'available_balance_after' => $newAvailable,
                    'status'                  => 'completed',
                    'description'             => $releaseDesc,
                    'meta_snapshot'           => $split,
                ]);
            }

            if ($record->status !== 'settled') {
                $record->update([
                    'status'     => 'settled',
                    'settled_at' => now(),
                ]);
            }

            if (! SellerSettlementItem::where('seller_order_id', $sellerOrder->id)->exists() && $sellerOrder->sellerProfile) {
                $settlement = SellerSettlement::create([
                    'settlement_reference'   => 'STL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
                    'logistics_profile_id'   => $record->logistics_profile_id,
                    'seller_profile_id'      => $sellerOrder->seller_profile_id,
                    'seller_id'              => $sellerOrder->seller_id,
                    'gross_product_subtotal' => $record->product_subtotal,
                    'admin_commission_total' => $record->admin_commission_amount,
                    'net_settlement_amount'  => $record->seller_net_earning,
                    'status'                 => 'released',
                    'processed_by'           => null,
                    'settled_at'             => now(),
                    'notes'                  => "Auto-settled upon Buyer order completion ({$payMethod}).",
                ]);

                SellerSettlementItem::create([
                    'seller_settlement_id'    => $settlement->id,
                    'seller_order_id'         => $sellerOrder->id,
                    'commission_record_id'    => $record->id,
                    'product_subtotal'        => $record->product_subtotal,
                    'admin_commission_amount' => $record->admin_commission_amount,
                    'seller_net_earning'      => $record->seller_net_earning,
                ]);
            }

            $sellerOrder->update([
                'payment_status' => 'settled',
            ]);

            $sellerOrder->payment?->update([
                'status'     => 'settled',
                'settled_at' => now(),
            ]);

            return $record;
        });
    }

    public function requestSellerPayout(
        SellerProfile $sellerProfile,
        float $requestedAmount,
        string $payoutMethod,
        string $accountName,
        string $accountNumber,
        ?string $idempotencyKey = null
    ): int {
        return DB::transaction(function () use (
            $sellerProfile,
            $requestedAmount,
            $payoutMethod,
            $accountName,
            $accountNumber,
            $idempotencyKey
        ) {
            $wallet = $this->getOrCreateWallet('Seller', (int) $sellerProfile->id, $sellerProfile->business_name);
            $wallet = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey) {
                $existingId = DB::table('seller_payout_requests')
                    ->where('idempotency_key', $idempotencyKey)
                    ->value('id');
                if ($existingId) {
                    return (int) $existingId;
                }
            }

            $hasPending = DB::table('seller_payout_requests')
                ->where('seller_profile_id', $sellerProfile->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'payout' => 'You already have a pending payout request. Only one payout request can be pending at a time.',
                ]);
            }

            $cleanAmount = round($requestedAmount, 2);
            if ($cleanAmount < self::MIN_SELLER_PAYOUT_AMOUNT) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Payout request amount must be at least ₱' . number_format(self::MIN_SELLER_PAYOUT_AMOUNT, 2) . '.',
                ]);
            }

            $available = round((float) $wallet->available_balance, 2);
            if ($cleanAmount > $available) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Payout request cannot exceed your Available E-Wallet Balance (₱' . number_format(max(0, $available), 2) . ').',
                ]);
            }

            $newAvailable = round($available - $cleanAmount, 2);
            $newWithdrawn = round((float) $wallet->total_withdrawn + $cleanAmount, 2);

            $wallet->update([
                'available_balance' => $newAvailable,
                'total_withdrawn'   => $newWithdrawn,
            ]);

            $payoutRef = 'PYT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

            $payoutId = DB::table('seller_payout_requests')->insertGetId([
                'payout_reference'  => $payoutRef,
                'seller_profile_id' => $sellerProfile->id,
                'role_wallet_id'    => $wallet->id,
                'requested_amount'  => $cleanAmount,
                'payout_method'     => $payoutMethod,
                'account_name'      => trim($accountName),
                'account_number'    => trim($accountNumber),
                'status'            => 'pending',
                'idempotency_key'   => $idempotencyKey ?: Str::uuid()->toString(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            WalletTransaction::create([
                'role_wallet_id'          => $wallet->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(7)),
                'owner_type'              => 'Seller',
                'owner_id'                => $sellerProfile->id,
                'entry_type'              => 'payout_withdrawal_request',
                'payment_method'          => $payoutMethod,
                'amount'                  => -$cleanAmount,
                'pending_balance_after'   => (float) $wallet->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'pending',
                'description'             => "Seller Payout Withdrawal Request #{$payoutRef} to {$payoutMethod} ({$accountNumber}).",
            ]);

            return (int) $payoutId;
        });
    }

    protected function buildRoleAllocations(SellerOrder $sellerOrder, CommissionRecord $record, array $split): array
    {
        $orderNo = $sellerOrder->seller_order_number;
        $allocations = [
            [
                'owner_type'           => 'Admin',
                'owner_id'             => 1,
                'owner_name'           => 'Storkia Platform Admin',
                'amount'               => round((float) $record->admin_commission_amount, 2),
                'pending_description'  => "Pending 10% Admin Commission on Order #{$orderNo} (Delivered — 72h confirmation window).",
                'released_description' => "Realized 10% Admin Commission credited on Completed Order #{$orderNo}.",
            ],
            [
                'owner_type'           => 'Seller',
                'owner_id'             => (int) $sellerOrder->seller_profile_id,
                'owner_name'           => $sellerOrder->sellerProfile?->business_name ?? "Seller #{$sellerOrder->seller_profile_id}",
                'amount'               => round((float) $record->seller_net_earning, 2),
                'pending_description'  => "Pending 90% Seller Net Earning on Order #{$orderNo} (Delivered — awaiting Buyer confirmation).",
                'released_description' => "Realized 90% Seller Net Earning credited to E-Wallet for Completed Order #{$orderNo}.",
            ],
        ];

        $logisticsId = (int) ($sellerOrder->logistics_profile_id ?: $record->logistics_profile_id);
        if ($logisticsId > 0) {
            $allocations[] = [
                'owner_type'           => 'Logistics',
                'owner_id'             => $logisticsId,
                'owner_name'           => $sellerOrder->logisticsProfile?->business_name ?? "Logistics Hub #{$logisticsId}",
                'amount'               => round((float) $record->logistics_total_earning, 2),
                'pending_description'  => "Pending 90% Shipping Share (10% Comm + 80% Ops on ₱" . number_format((float) $split['original_shipping_fee'], 2) . ") for Order #{$orderNo}.",
                'released_description' => "Realized 90% Shipping Share credited to Logistics E-Wallet for Completed Order #{$orderNo}.",
            ];
        }

        $riderId = (int) ($sellerOrder->shipment?->current_rider_id ?: $record->rider_id);
        if ($riderId > 0) {
            $riderPayAmount = round((float) ($split['rider_commission_amount'] ?? $record->rider_commission_amount), 2);
            $allocations[] = [
                'owner_type'           => 'Rider',
                'owner_id'             => $riderId,
                'owner_name'           => $sellerOrder->shipment?->currentRider?->full_name ?? "Rider #{$riderId}",
                'amount'               => $riderPayAmount,
                'pending_description'  => "Pending Distance-Scaled Rider Delivery Pay (₱" . number_format($riderPayAmount, 2) . ") for Order #{$orderNo}.",
                'released_description' => "Realized Distance-Scaled Rider Delivery Pay (₱" . number_format($riderPayAmount, 2) . ") credited to Rider E-Wallet for Completed Order #{$orderNo}.",
            ];
        }

        return $allocations;
    }
}