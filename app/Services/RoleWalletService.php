<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\LogisticsProfile;
use App\Models\Rider;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Models\WithdrawalRequestHistory;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleWalletService
{
    public const MIN_WITHDRAWAL_AMOUNT = 100.00;
    public const GCASH_ARREARS_CAP = 1000.00;

    public function __construct(
        protected ?CommissionCalculatorService $commissionCalculator = null
    ) {
        $this->commissionCalculator = $commissionCalculator ?? app(CommissionCalculatorService::class);
    }

    public function getOrCreateWallet(string $ownerType, int $ownerId, ?string $ownerName = null): RoleWallet
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $wallet = RoleWallet::firstOrCreate(
            [
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
            ],
            [
                'owner_name'                  => $ownerName ?? "{$ownerType} #{$ownerId}",
                'pending_balance'             => 0.00,
                'available_balance'           => 0.00,
                'platform_commission_arrears' => 0.00,
                'total_earned'                => 0.00,
                'total_withdrawn'             => 0.00,
            ]
        );

        if ($ownerName && $wallet->owner_name !== $ownerName) {
            $wallet->update(['owner_name' => $ownerName]);
        }

        return $wallet;
    }

    public function recordPendingEscrowOnDelivery(SellerOrder $sellerOrder): CommissionRecord
    {
        $sellerOrder->loadMissing(['extra', 'payment', 'shipment.currentRider', 'sellerProfile', 'logisticsProfile']);

        $split = $this->commissionCalculator->calculateOrderSplitWithSubsidies($sellerOrder);
        $shipment = $sellerOrder->shipment;
        $riderId = $shipment?->current_rider_id;
        $codCollection = $shipment
            ? DB::table('cod_collections')->where('shipment_id', $shipment->id)->first()
            : null;

        $record = CommissionRecord::updateOrCreate(
            ['seller_order_id' => $sellerOrder->id],
            [
                'shipment_id'                   => $shipment?->id,
                'cod_collection_id'             => $codCollection?->id,
                'seller_profile_id'             => $sellerOrder->seller_profile_id,
                'logistics_profile_id'          => $sellerOrder->logistics_profile_id,
                'rider_id'                      => $riderId,
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
                'status'                        => 'pending_settlement',
                'calculated_at'                 => now(),
            ]
        );

        $payMethod = $sellerOrder->effective_payment_method;

        if ($sellerOrder->sellerProfile) {
            $this->creditPendingEscrow(
                ownerType: 'Seller',
                ownerId: (int) $sellerOrder->seller_profile_id,
                ownerName: $sellerOrder->sellerProfile->business_name,
                amount: (float) $split['seller_net_earning'],
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipment?->id,
                entryType: 'pending_escrow_credit',
                paymentMethod: $payMethod,
                description: "Pending 90% Seller Escrow for Order #{$sellerOrder->seller_order_number}"
            );
        }

        if ($sellerOrder->logisticsProfile) {
            $this->creditPendingEscrow(
                ownerType: 'Logistics',
                ownerId: (int) $sellerOrder->logistics_profile_id,
                ownerName: $sellerOrder->logisticsProfile->business_name,
                amount: (float) $split['logistics_total_earning'],
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipment?->id,
                entryType: 'pending_escrow_credit',
                paymentMethod: $payMethod,
                description: "Pending 90% Hub Shipping Escrow for Order #{$sellerOrder->seller_order_number}"
            );
        }

        if ($riderId) {
            $rider = Rider::find($riderId);
            $this->creditPendingEscrow(
                ownerType: 'Rider',
                ownerId: (int) $riderId,
                ownerName: $rider?->full_name ?? "Rider #{$riderId}",
                amount: (float) $split['rider_commission_amount'],
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipment?->id,
                entryType: 'pending_escrow_credit',
                paymentMethod: $payMethod,
                description: "Pending Rider Delivery Pay for Order #{$sellerOrder->seller_order_number}"
            );
        }

        return $record;
    }

    public function releaseCompletedOrderEarnings(SellerOrder $sellerOrder): CommissionRecord
    {
        $record = CommissionRecord::where('seller_order_id', $sellerOrder->id)->first();
        if (! $record) {
            $record = $this->recordPendingEscrowOnDelivery($sellerOrder);
        }

        $sellerOrder->loadMissing(['sellerProfile', 'logisticsProfile', 'shipment']);
        $payMethod = $sellerOrder->effective_payment_method;
        $shipmentId = $sellerOrder->shipment?->id ?? $record->shipment_id;

        if ($sellerOrder->seller_profile_id) {
            $this->releasePendingEscrowToAvailable(
                ownerType: 'Seller',
                ownerId: (int) $sellerOrder->seller_profile_id,
                ownerName: $sellerOrder->sellerProfile?->business_name ?? "Seller #{$sellerOrder->seller_profile_id}",
                amount: (float) $record->seller_net_earning,
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipmentId,
                entryType: 'commission_released',
                paymentMethod: $payMethod,
                description: "90% Seller Net Earning released for Order #{$sellerOrder->seller_order_number}"
            );
        }

        if ($sellerOrder->logistics_profile_id) {
            $this->releasePendingEscrowToAvailable(
                ownerType: 'Logistics',
                ownerId: (int) $sellerOrder->logistics_profile_id,
                ownerName: $sellerOrder->logisticsProfile?->business_name ?? "Logistics #{$sellerOrder->logistics_profile_id}",
                amount: (float) $record->logistics_total_earning,
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipmentId,
                entryType: 'commission_released',
                paymentMethod: $payMethod,
                description: "90% Hub Shipping Share released for Order #{$sellerOrder->seller_order_number}"
            );
        }

        if ($record->rider_id) {
            $rider = Rider::find($record->rider_id);
            $this->releasePendingEscrowToAvailable(
                ownerType: 'Rider',
                ownerId: (int) $record->rider_id,
                ownerName: $rider?->full_name ?? "Rider #{$record->rider_id}",
                amount: (float) $record->rider_commission_amount,
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipmentId,
                entryType: 'commission_released',
                paymentMethod: $payMethod,
                description: "Rider Delivery Pay released for Order #{$sellerOrder->seller_order_number}"
            );
        }

        if ($record->status !== 'settled') {
            $record->update([
                'status'     => 'settled',
                'settled_at' => now(),
            ]);
        }

        return $record;
    }

    public function creditPendingEscrow(
        string $ownerType,
        int $ownerId,
        string $ownerName,
        float $amount,
        ?int $sellerOrderId = null,
        ?int $shipmentId = null,
        string $entryType = 'pending_escrow_credit',
        string $paymentMethod = 'COD',
        string $description = 'Pending escrow credit upon parcel delivery',
        array $meta = []
    ): ?WalletTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $ownerType,
            $ownerId,
            $ownerName,
            $amount,
            $sellerOrderId,
            $shipmentId,
            $entryType,
            $paymentMethod,
            $description,
            $meta
        ) {
            $wallet = $this->getOrCreateWallet($ownerType, $ownerId, $ownerName);
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ($sellerOrderId !== null) {
                $existing = WalletTransaction::where('role_wallet_id', $locked->id)
                    ->where('seller_order_id', $sellerOrderId)
                    ->where('entry_type', $entryType)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $newPending = round((float) $locked->pending_balance + $amount, 2);

            $locked->update([
                'owner_name'      => $ownerName ?: $locked->owner_name,
                'pending_balance' => $newPending,
            ]);

            return WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => $ownerType,
                'owner_id'                => $ownerId,
                'seller_order_id'         => $sellerOrderId,
                'shipment_id'             => $shipmentId,
                'entry_type'              => $entryType,
                'payment_method'          => $paymentMethod,
                'amount'                  => $amount,
                'pending_balance_after'   => $newPending,
                'available_balance_after' => (float) $locked->available_balance,
                'status'                  => 'pending',
                'description'             => $description,
                'meta_snapshot'           => $meta,
            ]);
        });
    }

    public function releasePendingEscrowToAvailable(
        string $ownerType,
        int $ownerId,
        string $ownerName,
        float $amount,
        ?int $sellerOrderId = null,
        ?int $shipmentId = null,
        string $entryType = 'commission_released',
        string $paymentMethod = 'COD',
        string $description = 'Escrow released to available balance upon order completion',
        array $meta = []
    ): ?WalletTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $ownerType,
            $ownerId,
            $ownerName,
            $amount,
            $sellerOrderId,
            $shipmentId,
            $entryType,
            $paymentMethod,
            $description,
            $meta
        ) {
            $wallet = $this->getOrCreateWallet($ownerType, $ownerId, $ownerName);
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ($sellerOrderId !== null) {
                $existing = WalletTransaction::where('role_wallet_id', $locked->id)
                    ->where('seller_order_id', $sellerOrderId)
                    ->where('entry_type', $entryType)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $newPending = max(0.00, round((float) $locked->pending_balance - $amount, 2));
            $newAvailable = round((float) $locked->available_balance + $amount, 2);
            $newTotalEarned = round((float) $locked->total_earned + $amount, 2);

            $locked->update([
                'owner_name'        => $ownerName ?: $locked->owner_name,
                'pending_balance'   => $newPending,
                'available_balance' => $newAvailable,
                'total_earned'      => $newTotalEarned,
                'last_credited_at'  => now(),
            ]);

            if ($sellerOrderId !== null) {
                WalletTransaction::where('role_wallet_id', $locked->id)
                    ->where('seller_order_id', $sellerOrderId)
                    ->where('status', 'pending')
                    ->update(['status' => 'completed']);
            }

            return WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => $ownerType,
                'owner_id'                => $ownerId,
                'seller_order_id'         => $sellerOrderId,
                'shipment_id'             => $shipmentId,
                'entry_type'              => $entryType,
                'payment_method'          => $paymentMethod,
                'amount'                  => $amount,
                'pending_balance_after'   => $newPending,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => $description,
                'meta_snapshot'           => $meta,
            ]);
        });
    }

    public function creditAvailableDirect(
        string $ownerType,
        int $ownerId,
        string $ownerName,
        float $amount,
        ?int $sellerOrderId = null,
        ?int $shipmentId = null,
        string $entryType = 'direct_credit',
        string $paymentMethod = 'COD',
        string $description = 'Direct wallet credit',
        array $meta = []
    ): ?WalletTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $ownerType,
            $ownerId,
            $ownerName,
            $amount,
            $sellerOrderId,
            $shipmentId,
            $entryType,
            $paymentMethod,
            $description,
            $meta
        ) {
            $wallet = $this->getOrCreateWallet($ownerType, $ownerId, $ownerName);
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newAvailable = round((float) $locked->available_balance + $amount, 2);
            $newTotalEarned = round((float) $locked->total_earned + $amount, 2);

            $locked->update([
                'owner_name'        => $ownerName ?: $locked->owner_name,
                'available_balance' => $newAvailable,
                'total_earned'      => $newTotalEarned,
                'last_credited_at'  => now(),
            ]);

            return WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => $ownerType,
                'owner_id'                => $ownerId,
                'seller_order_id'         => $sellerOrderId,
                'shipment_id'             => $shipmentId,
                'entry_type'              => $entryType,
                'payment_method'          => $paymentMethod,
                'amount'                  => $amount,
                'pending_balance_after'   => (float) $locked->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => $description,
                'meta_snapshot'           => $meta,
            ]);
        });
    }

    public function recordDirectGcashArrearsOffset(
        int $sellerProfileId,
        string $sellerName,
        float $owedToPlatform,
        int $sellerOrderId,
        ?int $shipmentId = null,
        string $description = 'Direct-GCash 10% Admin Commission + Shipping Fee Offset',
        array $meta = []
    ): ?WalletTransaction {
        $owedToPlatform = round($owedToPlatform, 2);
        if ($owedToPlatform <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $sellerProfileId,
            $sellerName,
            $owedToPlatform,
            $sellerOrderId,
            $shipmentId,
            $description,
            $meta
        ) {
            $wallet = $this->getOrCreateWallet('Seller', $sellerProfileId, $sellerName);
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $existing = WalletTransaction::where('role_wallet_id', $locked->id)
                ->where('seller_order_id', $sellerOrderId)
                ->where('entry_type', 'gcash_platform_offset')
                ->first();

            if ($existing) {
                return $existing;
            }

            $currentAvailable = (float) $locked->available_balance;
            $currentArrears = (float) ($locked->platform_commission_arrears ?? 0);

            if ($currentAvailable >= $owedToPlatform) {
                $newAvailable = round($currentAvailable - $owedToPlatform, 2);
                $newArrears = $currentArrears;
            } else {
                $shortfall = round($owedToPlatform - max(0, $currentAvailable), 2);
                $newAvailable = round($currentAvailable - $owedToPlatform, 2);
                $newArrears = round($currentArrears + $shortfall, 2);
            }

            $locked->update([
                'available_balance'           => $newAvailable,
                'platform_commission_arrears' => $newArrears,
            ]);

            if ($newArrears >= self::GCASH_ARREARS_CAP) {
                SellerProfile::where('id', $sellerProfileId)->update([
                    'gcash_blocked_for_arrears' => true,
                ]);
            }

            return WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => 'Seller',
                'owner_id'                => $sellerProfileId,
                'seller_order_id'         => $sellerOrderId,
                'shipment_id'             => $shipmentId,
                'entry_type'              => 'gcash_platform_offset',
                'payment_method'          => 'GCASH',
                'amount'                  => -$owedToPlatform,
                'pending_balance_after'   => (float) $locked->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => $description,
                'meta_snapshot'           => $meta,
            ]);
        });
    }

    public function submitWithdrawalRequest(
        string $ownerType,
        int $ownerId,
        string $ownerName,
        float $amount,
        string $payoutMethod,
        string $accountName,
        string $accountNumber,
        ?string $idempotencyKey = null
    ): WithdrawalRequest {
        StorkSchemaBootstrapService::ensureTablesExist();

        $normalizedType = match (strtolower(trim($ownerType))) {
            'seller'    => 'Seller',
            'logistics' => 'Logistics',
            'rider'     => 'Rider',
            'courier'   => 'Courier',
            default     => ucfirst(strtolower(trim($ownerType))),
        };

        $amount = round($amount, 2);
        if ($amount < self::MIN_WITHDRAWAL_AMOUNT) {
            throw ValidationException::withMessages([
                'requested_amount' => 'Minimum withdrawal request amount is ₱' . number_format(self::MIN_WITHDRAWAL_AMOUNT, 2) . '.',
            ]);
        }

        // Enforce GCash-only payout method across all operational roles
        $payoutMethod = 'GCASH';

        $accountName = trim($accountName);
        $accountNumber = preg_replace('/\s+/', '', trim($accountNumber));

        $digits = preg_replace('/\D/', '', $accountNumber);
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }
        if (! preg_match('/^09\d{9}$/', $digits)) {
            throw ValidationException::withMessages([
                'account_number' => 'Please enter a valid 11-digit GCash mobile number starting with 09 (e.g., 09171234567).',
            ]);
        }
        $accountNumber = $digits;

        $notifications = [];

        $withdrawal = DB::transaction(function () use (
            $normalizedType,
            $ownerId,
            $ownerName,
            $amount,
            $payoutMethod,
            $accountName,
            $accountNumber,
            $idempotencyKey,
            &$notifications
        ) {
            if ($idempotencyKey) {
                $existingByKey = WithdrawalRequest::where('idempotency_key', $idempotencyKey)->first();
                if ($existingByKey) {
                    return $existingByKey;
                }
            }

            $wallet = $this->getOrCreateWallet($normalizedType, $ownerId, $ownerName);
            $lockedWallet = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $hasPending = WithdrawalRequest::where('requester_type', $normalizedType)
                ->where('requester_id', $ownerId)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'You already have an active withdrawal request pending Admin processing. Only one pending withdrawal request is allowed at a time.',
                ]);
            }

            $available = round((float) $lockedWallet->available_balance, 2);
            if ($available < $amount) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Requested amount (₱' . number_format($amount, 2) . ') exceeds your available E-Wallet balance of ₱' . number_format($available, 2) . '.',
                ]);
            }

            if ($normalizedType === 'Seller' && (float) $lockedWallet->platform_commission_arrears > 0) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Please settle your outstanding platform commission arrears of ₱' . number_format((float) $lockedWallet->platform_commission_arrears, 2) . ' before requesting a payout.',
                ]);
            }

            $newAvailable = round($available - $amount, 2);
            $lockedWallet->update([
                'available_balance' => $newAvailable,
            ]);

            $ref = 'WDR-' . strtoupper(substr($normalizedType, 0, 3)) . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $created = WithdrawalRequest::create([
                'withdrawal_reference' => $ref,
                'requester_type'       => $normalizedType,
                'requester_id'         => $ownerId,
                'requester_name'       => $ownerName ?: $lockedWallet->owner_name,
                'role_wallet_id'       => $lockedWallet->id,
                'requested_amount'     => $amount,
                'payout_method'        => $payoutMethod,
                'account_name'         => $accountName,
                'account_number'       => $accountNumber,
                'status'               => 'pending',
                'idempotency_key'      => $idempotencyKey ?: Str::uuid()->toString(),
            ]);

            WithdrawalRequestHistory::create([
                'withdrawal_request_id' => $created->id,
                'from_status'           => null,
                'to_status'             => 'pending',
                'actor_type'            => $normalizedType,
                'actor_id'              => $ownerId,
                'actor_name'            => $ownerName ?: $lockedWallet->owner_name,
                'remarks'               => "Withdrawal request of ₱" . number_format($amount, 2) . " via {$payoutMethod} ({$accountName} - {$accountNumber}) submitted.",
            ]);

            if ($normalizedType === 'Seller' && Schema::hasTable('seller_payout_requests')) {
                DB::table('seller_payout_requests')->insert([
                    'payout_reference'  => $ref,
                    'seller_profile_id' => $ownerId,
                    'role_wallet_id'    => $lockedWallet->id,
                    'requested_amount'  => $amount,
                    'payout_method'     => $payoutMethod,
                    'account_name'      => $accountName,
                    'account_number'    => $accountNumber,
                    'status'            => 'pending',
                    'idempotency_key'   => $created->idempotency_key,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            WalletTransaction::create([
                'role_wallet_id'          => $lockedWallet->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => $normalizedType,
                'owner_id'                => $ownerId,
                'entry_type'              => 'withdrawal_requested',
                'payment_method'          => $payoutMethod,
                'amount'                  => -$amount,
                'pending_balance_after'   => (float) $lockedWallet->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'pending',
                'description'             => "Withdrawal Request #{$ref} reserved (₱" . number_format($amount, 2) . " to {$payoutMethod} {$accountNumber})",
                'meta_snapshot'           => [
                    'withdrawal_request_id' => $created->id,
                    'withdrawal_reference'  => $ref,
                    'payout_method'         => $payoutMethod,
                    'account_name'          => $accountName,
                    'account_number'        => $accountNumber,
                ],
            ]);

            foreach (Admin::all() as $admin) {
                $notifications[] = [
                    'recipient'    => $admin,
                    'notification' => new OrderLifecycleNotification(
                        title: "New {$normalizedType} E-Wallet Withdrawal Request ({$ref})",
                        message: "{$ownerName} ({$normalizedType}) requested a withdrawal of ₱" . number_format($amount, 2) . " via {$payoutMethod} ({$accountName} - {$accountNumber}).",
                        type: 'info',
                        url: route('admin.commissions.index', ['tab' => 'withdrawals'])
                    ),
                ];
            }

            return $created;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $withdrawal;
    }

    public function requestSellerPayout(
        int $sellerProfileId,
        string $sellerName,
        float $amount,
        string $payoutMethod,
        string $accountName,
        string $accountNumber,
        ?string $idempotencyKey = null
    ): WithdrawalRequest {
        return $this->submitWithdrawalRequest(
            ownerType: 'Seller',
            ownerId: $sellerProfileId,
            ownerName: $sellerName,
            amount: $amount,
            payoutMethod: 'GCASH',
            accountName: $accountName,
            accountNumber: $accountNumber,
            idempotencyKey: $idempotencyKey
        );
    }

    public function processWithdrawalDecision(
        int $withdrawalRequestId,
        int $adminId,
        string $decision,
        ?string $remarks = null,
        ?string $proofReference = null
    ): WithdrawalRequest {
        StorkSchemaBootstrapService::ensureTablesExist();

        if (! in_array($decision, ['approve', 'reject', 'completed', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Invalid withdrawal decision.',
            ]);
        }

        $isApproval = in_array($decision, ['approve', 'completed'], true);

        if (! $isApproval && empty(trim((string) $remarks))) {
            throw ValidationException::withMessages([
                'remarks' => 'Please provide a rejection reason so the requester knows why the withdrawal was declined.',
            ]);
        }

        $notifications = [];

        $updated = DB::transaction(function () use (
            $withdrawalRequestId,
            $adminId,
            $isApproval,
            $remarks,
            $proofReference,
            &$notifications
        ) {
            $lockedReq = WithdrawalRequest::where('id', $withdrawalRequestId)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedReq->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'withdrawal' => "Withdrawal Request #{$lockedReq->withdrawal_reference} has already been processed ({$lockedReq->status}).",
                ]);
            }

            $lockedWallet = RoleWallet::where('id', $lockedReq->role_wallet_id)->lockForUpdate()->firstOrFail();
            $oldStatus = $lockedReq->status;
            $amount = round((float) $lockedReq->requested_amount, 2);
            $adminUser = Admin::find($adminId);
            $adminName = $adminUser?->name ?? "Admin #{$adminId}";

            if ($isApproval) {
                $newWithdrawn = round((float) $lockedWallet->total_withdrawn + $amount, 2);
                $lockedWallet->update([
                    'total_withdrawn' => $newWithdrawn,
                ]);

                $cleanProof = trim((string) $proofReference) ?: ('PAYOUT-' . now()->format('YmdHis'));
                $cleanRemarks = trim((string) $remarks) ?: "Approved & disbursed by {$adminName} (Ref: {$cleanProof}).";

                $lockedReq->update([
                    'status'                 => 'completed',
                    'reviewed_by_admin_id'   => $adminId,
                    'admin_remarks'          => $cleanRemarks,
                    'payout_proof_reference' => $cleanProof,
                    'approved_at'            => $lockedReq->approved_at ?? now(),
                    'paid_at'                => now(),
                ]);

                WithdrawalRequestHistory::create([
                    'withdrawal_request_id' => $lockedReq->id,
                    'from_status'           => $oldStatus,
                    'to_status'             => 'completed',
                    'actor_type'            => 'Admin',
                    'actor_id'              => $adminId,
                    'actor_name'            => $adminName,
                    'remarks'               => $cleanRemarks,
                ]);

                if ($lockedReq->requester_type === 'Seller' && Schema::hasTable('seller_payout_requests')) {
                    DB::table('seller_payout_requests')
                        ->where('payout_reference', $lockedReq->withdrawal_reference)
                        ->update([
                            'status'                => 'completed',
                            'processed_by_admin_id' => $adminId,
                            'admin_remarks'         => $cleanRemarks,
                            'processed_at'          => now(),
                            'updated_at'            => now(),
                        ]);
                }

                WalletTransaction::where('role_wallet_id', $lockedWallet->id)
                    ->where('entry_type', 'withdrawal_requested')
                    ->where('status', 'pending')
                    ->get()
                    ->each(function (WalletTransaction $tx) use ($lockedReq, $cleanProof) {
                        $meta = is_array($tx->meta_snapshot) ? $tx->meta_snapshot : [];
                        if (($meta['withdrawal_reference'] ?? '') === $lockedReq->withdrawal_reference) {
                            $meta['payout_proof_reference'] = $cleanProof;
                            $tx->update([
                                'entry_type'    => 'payout_completed',
                                'status'        => 'completed',
                                'description'   => "Withdrawal #{$lockedReq->withdrawal_reference} Disbursed ({$lockedReq->payout_method} Ref: {$cleanProof})",
                                'meta_snapshot' => $meta,
                            ]);
                        }
                    });

                AuditLoggerService::log(
                    action: 'withdrawal_request_approved',
                    targetType: 'WithdrawalRequest',
                    targetId: (int) $lockedReq->id,
                    targetReference: $lockedReq->withdrawal_reference,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'completed', 'amount' => $amount, 'proof' => $cleanProof],
                    reason: $cleanRemarks,
                    actorType: 'Admin',
                    actorId: $adminId,
                    actorName: $adminName
                );
            } else {
                $refundedAvailable = round((float) $lockedWallet->available_balance + $amount, 2);
                $lockedWallet->update([
                    'available_balance' => $refundedAvailable,
                ]);

                $cleanReason = trim((string) $remarks);

                $lockedReq->update([
                    'status'               => 'rejected',
                    'reviewed_by_admin_id' => $adminId,
                    'rejection_reason'     => $cleanReason,
                    'admin_remarks'        => $cleanReason,
                    'rejected_at'          => now(),
                ]);

                WithdrawalRequestHistory::create([
                    'withdrawal_request_id' => $lockedReq->id,
                    'from_status'           => $oldStatus,
                    'to_status'             => 'rejected',
                    'actor_type'            => 'Admin',
                    'actor_id'              => $adminId,
                    'actor_name'            => $adminName,
                    'remarks'               => "Rejected by Admin: {$cleanReason}. ₱" . number_format($amount, 2) . " refunded to Available E-Wallet.",
                ]);

                if ($lockedReq->requester_type === 'Seller' && Schema::hasTable('seller_payout_requests')) {
                    DB::table('seller_payout_requests')
                        ->where('payout_reference', $lockedReq->withdrawal_reference)
                        ->update([
                            'status'                => 'rejected',
                            'processed_by_admin_id' => $adminId,
                            'admin_remarks'         => $cleanReason,
                            'processed_at'          => now(),
                            'updated_at'            => now(),
                        ]);
                }

                WalletTransaction::where('role_wallet_id', $lockedWallet->id)
                    ->where('entry_type', 'withdrawal_requested')
                    ->where('status', 'pending')
                    ->get()
                    ->each(function (WalletTransaction $tx) use ($lockedReq) {
                        $meta = is_array($tx->meta_snapshot) ? $tx->meta_snapshot : [];
                        if (($meta['withdrawal_reference'] ?? '') === $lockedReq->withdrawal_reference) {
                            $tx->update(['status' => 'cancelled']);
                        }
                    });

                WalletTransaction::create([
                    'role_wallet_id'          => $lockedWallet->id,
                    'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                    'owner_type'              => $lockedReq->requester_type,
                    'owner_id'                => $lockedReq->requester_id,
                    'entry_type'              => 'withdrawal_refunded',
                    'payment_method'          => $lockedReq->payout_method,
                    'amount'                  => $amount,
                    'pending_balance_after'   => (float) $lockedWallet->pending_balance,
                    'available_balance_after' => $refundedAvailable,
                    'status'                  => 'completed',
                    'description'             => "Refund for Rejected Withdrawal #{$lockedReq->withdrawal_reference}: {$cleanReason}",
                    'meta_snapshot'           => [
                        'withdrawal_request_id' => $lockedReq->id,
                        'withdrawal_reference'  => $lockedReq->withdrawal_reference,
                        'rejection_reason'      => $cleanReason,
                    ],
                ]);

                AuditLoggerService::log(
                    action: 'withdrawal_request_rejected',
                    targetType: 'WithdrawalRequest',
                    targetId: (int) $lockedReq->id,
                    targetReference: $lockedReq->withdrawal_reference,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'rejected', 'refunded_amount' => $amount],
                    reason: $cleanReason,
                    actorType: 'Admin',
                    actorId: $adminId,
                    actorName: $adminName
                );
            }

            $recipientNotifiable = $this->resolveNotifiableOwner($lockedReq->requester_type, (int) $lockedReq->requester_id);
            if ($recipientNotifiable) {
                $portalUrl = match ($lockedReq->requester_type) {
                    'Seller'    => route('seller.settlements.index'),
                    'Logistics' => route('logistics.finance.index'),
                    'Rider'     => route('rider.report'),
                    'Courier'   => route('courier.report'),
                    default     => '/',
                };

                $notifications[] = [
                    'recipient'    => $recipientNotifiable,
                    'notification' => new OrderLifecycleNotification(
                        title: $isApproval
                            ? "Withdrawal #{$lockedReq->withdrawal_reference} Approved & Disbursed!"
                            : "Withdrawal #{$lockedReq->withdrawal_reference} Rejected — Funds Returned",
                        message: $isApproval
                            ? "Your withdrawal of ₱" . number_format($amount, 2) . " to {$lockedReq->payout_method} ({$lockedReq->account_number}) has been disbursed. Reference: {$lockedReq->payout_proof_reference}."
                            : "Your withdrawal request of ₱" . number_format($amount, 2) . " was declined ({$lockedReq->rejection_reason}). The full amount has been returned to your Available E-Wallet.",
                        type: $isApproval ? 'success' : 'warning',
                        url: $portalUrl
                    ),
                ];
            }

            return $lockedReq;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $updated;
    }

    protected function resolveNotifiableOwner(string $ownerType, int $ownerId)
    {
        return match ($ownerType) {
            'Seller'    => SellerProfile::with('user')->find($ownerId)?->user,
            'Logistics' => LogisticsProfile::with('user')->find($ownerId)?->user,
            'Rider'     => Rider::find($ownerId),
            'Courier'   => Courier::find($ownerId),
            default     => null,
        };
    }
}