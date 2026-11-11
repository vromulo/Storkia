<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BuyerWalletTopup;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CommissionRecord;
use App\Models\Courier;
use App\Models\LogisticsAdminRemittance;
use App\Models\LogisticsAdminRemittanceItem;
use App\Models\LogisticsProfile;
use App\Models\Rider;
use App\Models\RoleWallet;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
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

        $defaults = [
            'owner_name'                  => $ownerName ?? "{$ownerType} #{$ownerId}",
            'pending_balance'             => 0.00,
            'available_balance'           => 0.00,
            'platform_commission_arrears' => 0.00,
            'total_earned'                => 0.00,
            'total_withdrawn'             => 0.00,
        ];

        if (Schema::hasColumn('role_wallets', 'unremitted_cod_balance')) {
            $defaults['unremitted_cod_balance'] = 0.00;
        }

        $wallet = RoleWallet::firstOrCreate(
            [
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
            ],
            $defaults
        );

        if ($ownerName && $wallet->owner_name !== $ownerName) {
            $wallet->update(['owner_name' => $ownerName]);
        }

        return $wallet;
    }

    public function getOrCreateBuyerWallet(User $buyer): RoleWallet
    {
        return $this->getOrCreateWallet('Buyer', (int) $buyer->id, $buyer->full_name ?: $buyer->email);
    }

    public function submitBuyerWalletTopup(
        User $buyer,
        string $senderAccountName,
        string $referenceNumber,
        string $screenshotPath,
        ?string $senderAccountNumber = null
    ): BuyerWalletTopup {
        StorkSchemaBootstrapService::ensureTablesExist();

        $cleanName = trim($senderAccountName);
        if (mb_strlen($cleanName) < 2) {
            throw ValidationException::withMessages([
                'sender_account_name' => 'Please enter the GCash sender account name.',
            ]);
        }

        $cleanRef = strtoupper(trim($referenceNumber));
        if (mb_strlen($cleanRef) < 4 || mb_strlen($cleanRef) > 80) {
            throw ValidationException::withMessages([
                'reference_number' => 'Please enter a valid GCash Reference Number (4 to 80 characters).',
            ]);
        }

        $duplicateTopup = BuyerWalletTopup::whereRaw('UPPER(TRIM(reference_number)) = ?', [$cleanRef])
            ->where('status', '!=', 'rejected')
            ->exists();

        $duplicateLegacy = Schema::hasTable('order_payment_submissions')
            && DB::table('order_payment_submissions')->whereRaw('UPPER(TRIM(reference_number)) = ?', [$cleanRef])->exists();

        if ($duplicateTopup || $duplicateLegacy) {
            throw ValidationException::withMessages([
                'reference_number' => "GCash Reference Number \"{$cleanRef}\" has already been submitted.",
            ]);
        }

        $cleanPhone = null;
        if ($senderAccountNumber !== null && trim($senderAccountNumber) !== '') {
            $digits = preg_replace('/\D/', '', trim($senderAccountNumber));
            if (str_starts_with($digits, '63') && strlen($digits) === 12) {
                $digits = '0' . substr($digits, 2);
            } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
                $digits = '0' . $digits;
            }
            $cleanPhone = $digits ?: null;
        }

        $notifications = [];

        $topup = DB::transaction(function () use (
            $buyer,
            $cleanName,
            $cleanPhone,
            $cleanRef,
            $screenshotPath,
            &$notifications
        ) {
            $wallet = $this->getOrCreateBuyerWallet($buyer);

            $platGcash = Schema::hasTable('platform_gcash_settings')
                ? DB::table('platform_gcash_settings')->where('is_active', true)->orderByDesc('id')->first()
                : null;

            do {
                $topupRef = 'TOP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
            } while (BuyerWalletTopup::where('topup_reference', $topupRef)->exists());

            $created = BuyerWalletTopup::create([
                'topup_reference'       => $topupRef,
                'buyer_id'              => $buyer->id,
                'role_wallet_id'        => $wallet->id,
                'admin_gcash_name'      => $platGcash->account_name ?? 'Storkia Admin Escrow',
                'admin_gcash_number'    => $platGcash->gcash_number ?? null,
                'sender_account_name'   => $cleanName,
                'sender_account_number' => $cleanPhone,
                'reference_number'      => $cleanRef,
                'screenshot_path'       => $screenshotPath,
                'approved_amount'       => null,
                'status'                => 'pending_verification',
            ]);

            foreach (Admin::all() as $admin) {
                $notifications[] = [
                    'recipient'    => $admin,
                    'notification' => new OrderLifecycleNotification(
                        title: "New Buyer E-Wallet Top-Up Request ({$topupRef})",
                        message: "{$buyer->full_name} submitted a GCash top-up receipt (Sender: {$cleanName}, Ref #{$cleanRef}). Inspect the screenshot and enter the verified Peso amount to credit their Buyer E-Wallet.",
                        type: 'info',
                        url: route('admin.commissions.index', ['tab' => 'wallet_topups'])
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

        return $topup;
    }

    public function processBuyerWalletTopupDecision(
        int $topupId,
        int $adminId,
        string $decision,
        ?float $approvedAmount = null,
        ?string $remarks = null
    ): BuyerWalletTopup {
        StorkSchemaBootstrapService::ensureTablesExist();

        $isApprove = in_array(strtolower(trim($decision)), ['approve', 'verify', 'verified', 'approved'], true);

        if ($isApprove) {
            $cleanAmount = round((float) $approvedAmount, 2);
            if ($cleanAmount <= 0) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'Please enter the exact verified Peso amount from the GCash screenshot (must be greater than ₱0.00).',
                ]);
            }
        } else {
            $cleanAmount = null;
            if (empty(trim((string) $remarks))) {
                throw ValidationException::withMessages([
                    'remarks' => 'Please enter a rejection reason so the buyer knows why the top-up receipt was declined.',
                ]);
            }
        }

        $notifications = [];

        $updated = DB::transaction(function () use (
            $topupId,
            $adminId,
            $isApprove,
            $cleanAmount,
            $remarks,
            &$notifications
        ) {
            /** @var BuyerWalletTopup $lockedTopup */
            $lockedTopup = BuyerWalletTopup::with('buyer')
                ->where('id', $topupId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTopup->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'topup' => "Top-Up Request #{$lockedTopup->topup_reference} has already been processed ({$lockedTopup->status}).",
                ]);
            }

            $cleanRemarks = trim((string) $remarks);

            if ($isApprove) {
                $buyerName = $lockedTopup->buyer?->full_name ?? "Buyer #{$lockedTopup->buyer_id}";
                $tx = $this->creditAvailableDirect(
                    ownerType: 'Buyer',
                    ownerId: (int) $lockedTopup->buyer_id,
                    ownerName: $buyerName,
                    amount: (float) $cleanAmount,
                    sellerOrderId: null,
                    shipmentId: null,
                    entryType: 'buyer_wallet_topup',
                    paymentMethod: 'GCASH',
                    description: "E-Wallet Top-Up Verified by Admin (GCash Ref #{$lockedTopup->reference_number})",
                    meta: [
                        'topup_id'            => $lockedTopup->id,
                        'topup_reference'     => $lockedTopup->topup_reference,
                        'sender_account_name' => $lockedTopup->sender_account_name,
                        'reference_number'    => $lockedTopup->reference_number,
                        'approved_by_admin'   => $adminId,
                    ]
                );

                $lockedTopup->update([
                    'approved_amount'       => $cleanAmount,
                    'status'                => 'approved',
                    'reviewed_by_admin_id'  => $adminId,
                    'admin_remarks'         => $cleanRemarks ?: 'Verified by Storkia Admin and credited to Buyer E-Wallet.',
                    'reviewed_at'           => now(),
                    'wallet_transaction_id' => $tx?->id,
                ]);

                if ($lockedTopup->buyer) {
                    $notifications[] = [
                        'recipient'    => $lockedTopup->buyer,
                        'notification' => new OrderLifecycleNotification(
                            title: 'E-Wallet Top-Up Approved! (+₱' . number_format((float) $cleanAmount, 2) . ')',
                            message: "Your GCash top-up (Ref #{$lockedTopup->reference_number}) was verified by Storkia Admin! ₱" . number_format((float) $cleanAmount, 2) . " has been added to your Storkia E-Wallet and is ready for checkout.",
                            type: 'success',
                            url: route('user.points')
                        ),
                    ];
                }
            } else {
                $lockedTopup->update([
                    'status'               => 'rejected',
                    'reviewed_by_admin_id' => $adminId,
                    'rejection_reason'     => $cleanRemarks,
                    'admin_remarks'        => $cleanRemarks,
                    'reviewed_at'          => now(),
                ]);

                if ($lockedTopup->buyer) {
                    $notifications[] = [
                        'recipient'    => $lockedTopup->buyer,
                        'notification' => new OrderLifecycleNotification(
                            title: "E-Wallet Top-Up #{$lockedTopup->topup_reference} Declined",
                            message: "Your GCash top-up receipt (Ref #{$lockedTopup->reference_number}) could not be verified: {$cleanRemarks}.",
                            type: 'danger',
                            url: route('user.points')
                        ),
                    ];
                }
            }

            return $lockedTopup;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $updated;
    }

    public function debitBuyerWalletForCheckout(
        User $buyer,
        float $amount,
        int $sellerOrderId,
        string $sellerOrderNumber
    ): ?WalletTransaction {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($buyer, $amount, $sellerOrderId, $sellerOrderNumber) {
            $wallet = $this->getOrCreateBuyerWallet($buyer);
            /** @var RoleWallet $locked */
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $existing = WalletTransaction::where('role_wallet_id', $locked->id)
                ->where('seller_order_id', $sellerOrderId)
                ->where('entry_type', 'buyer_checkout_payment')
                ->first();

            if ($existing) {
                return $existing;
            }

            $available = round((float) $locked->available_balance, 2);
            if ($available + 0.0001 < $amount) {
                $shortfall = round($amount - $available, 2);
                throw ValidationException::withMessages([
                    'payment_methods' => "Insufficient Storkia E-Wallet balance (Available: ₱" . number_format($available, 2) . "). Please top up ₱" . number_format($shortfall, 2) . " more or choose Cash on Delivery (COD).",
                ]);
            }

            $newAvailable = max(0.00, round($available - $amount, 2));
            $newWithdrawn = round((float) $locked->total_withdrawn + $amount, 2);

            $locked->update([
                'available_balance' => $newAvailable,
                'total_withdrawn'   => $newWithdrawn,
            ]);

            return WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => 'Buyer',
                'owner_id'                => $buyer->id,
                'seller_order_id'         => $sellerOrderId,
                'entry_type'              => 'buyer_checkout_payment',
                'payment_method'          => 'WALLET',
                'amount'                  => -$amount,
                'pending_balance_after'   => (float) $locked->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => "Paid Order #{$sellerOrderNumber} using Storkia E-Wallet (-₱" . number_format($amount, 2) . ")",
                'meta_snapshot'           => [
                    'seller_order_id'     => $sellerOrderId,
                    'seller_order_number' => $sellerOrderNumber,
                ],
            ]);
        });
    }

    public function refundBuyerWalletForOrder(
        SellerOrder $sellerOrder,
        float $refundAmount,
        string $reasonLabel = 'Order Refunded to Storkia E-Wallet'
    ): ?WalletTransaction {
        $refundAmount = round($refundAmount, 2);
        if ($refundAmount <= 0 || (int) $sellerOrder->buyer_id <= 0) {
            return null;
        }

        return DB::transaction(function () use ($sellerOrder, $refundAmount, $reasonLabel) {
            $buyer = $sellerOrder->buyer ?: User::find($sellerOrder->buyer_id);
            $wallet = $this->getOrCreateWallet('Buyer', (int) $sellerOrder->buyer_id, $buyer?->full_name ?? "Buyer #{$sellerOrder->buyer_id}");
            /** @var RoleWallet $locked */
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $existing = WalletTransaction::where('role_wallet_id', $locked->id)
                ->where('seller_order_id', $sellerOrder->id)
                ->where('entry_type', 'buyer_order_refund')
                ->first();

            if ($existing) {
                return $existing;
            }

            $newAvailable = round((float) $locked->available_balance + $refundAmount, 2);
            $newEarned = round((float) $locked->total_earned + $refundAmount, 2);

            $locked->update([
                'available_balance' => $newAvailable,
                'total_earned'      => $newEarned,
                'last_credited_at'  => now(),
            ]);

            return WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => 'Buyer',
                'owner_id'                => (int) $sellerOrder->buyer_id,
                'seller_order_id'         => (int) $sellerOrder->id,
                'shipment_id'             => $sellerOrder->shipment?->id,
                'entry_type'              => 'buyer_order_refund',
                'payment_method'          => 'WALLET',
                'amount'                  => $refundAmount,
                'pending_balance_after'   => (float) $locked->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => "{$reasonLabel} for Order #{$sellerOrder->seller_order_number} (+₱" . number_format($refundAmount, 2) . ")",
                'meta_snapshot'           => [
                    'seller_order_number' => $sellerOrder->seller_order_number,
                    'refund_amount'       => $refundAmount,
                ],
            ]);
        });
    }

    public function settleSellerArrearsFromAvailableBalance(int $sellerProfileId, string $sellerName): array
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        return DB::transaction(function () use ($sellerProfileId, $sellerName) {
            $wallet = $this->getOrCreateWallet('Seller', $sellerProfileId, $sellerName);
            /** @var RoleWallet $locked */
            $locked = RoleWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $arrears = round((float) $locked->platform_commission_arrears, 2);
            $available = round((float) $locked->available_balance, 2);

            if ($arrears <= 0) {
                throw ValidationException::withMessages([
                    'arrears' => 'You do not have any outstanding platform commission arrears.',
                ]);
            }

            if ($available <= 0) {
                throw ValidationException::withMessages([
                    'arrears' => 'Your Available E-Wallet balance is ₱0.00. Complete orders or remit via GCash to clear arrears.',
                ]);
            }

            $offsetAmount = min($available, $arrears);
            $newAvailable = round($available - $offsetAmount, 2);
            $newArrears = max(0.00, round($arrears - $offsetAmount, 2));

            $locked->update([
                'available_balance'           => $newAvailable,
                'platform_commission_arrears' => $newArrears,
            ]);

            if ($newArrears < self::GCASH_ARREARS_CAP && Schema::hasColumn('seller_profiles', 'gcash_blocked_for_arrears')) {
                SellerProfile::where('id', $sellerProfileId)->update([
                    'gcash_blocked_for_arrears' => false,
                ]);
            }

            WalletTransaction::create([
                'role_wallet_id'          => $locked->id,
                'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                'owner_type'              => 'Seller',
                'owner_id'                => $sellerProfileId,
                'entry_type'              => 'arrears_wallet_offset',
                'payment_method'          => 'WALLET',
                'amount'                  => -$offsetAmount,
                'pending_balance_after'   => (float) $locked->pending_balance,
                'available_balance_after' => $newAvailable,
                'status'                  => 'completed',
                'description'             => "Auto-offset ₱" . number_format($offsetAmount, 2) . " of platform commission arrears using Available Seller E-Wallet balance.",
                'meta_snapshot'           => [
                    'arrears_before' => $arrears,
                    'offset_amount'  => $offsetAmount,
                    'arrears_after'  => $newArrears,
                ],
            ]);

            return [
                'offset_amount'   => $offsetAmount,
                'remaining_arrears'=> $newArrears,
                'available_after' => $newAvailable,
            ];
        });
    }

    public function recordFirstMileCourierTaskCredit(
        Shipment $shipment,
        Courier $courier,
        array $payInfo
    ): ?WalletTransaction {
        StorkSchemaBootstrapService::ensureTablesExist();

        $sellerOrder = $shipment->sellerOrder()->with(['payment', 'extra'])->first();
        if (! $sellerOrder) {
            return null;
        }

        $split = $this->commissionCalculator->calculateOrderSplitWithSubsidies($sellerOrder);
        $amount = round((float) ($split['courier_commission_amount'] > 0 ? $split['courier_commission_amount'] : ($payInfo['total_pay'] ?? 0)), 2);

        if ($amount <= 0) {
            return null;
        }

        $payMethod = $sellerOrder->effective_payment_method;
        $isCod = strtoupper($payMethod) === 'COD';

        if ($isCod) {
            return $this->creditPendingEscrow(
                ownerType: 'Courier',
                ownerId: (int) $courier->id,
                ownerName: $courier->full_name,
                amount: $amount,
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: (int) $shipment->id,
                entryType: 'pending_escrow_credit',
                paymentMethod: $payMethod,
                description: "Pending First-Mile Pickup Task Pay ({$payInfo['distance_tier']}) from Shipping/Ops Fee for Shipment #{$shipment->tracking_number} (Releases upon Admin COD Remittance verification)",
                meta: $payInfo
            );
        }

        $alreadyDirect = WalletTransaction::where('owner_type', 'Courier')
            ->where('owner_id', $courier->id)
            ->where('shipment_id', $shipment->id)
            ->whereIn('entry_type', ['first_mile_pickup_pay', 'commission_released'])
            ->first();

        if ($alreadyDirect) {
            return $alreadyDirect;
        }

        return $this->creditAvailableDirect(
            ownerType: 'Courier',
            ownerId: (int) $courier->id,
            ownerName: $courier->full_name,
            amount: $amount,
            sellerOrderId: (int) $sellerOrder->id,
            shipmentId: (int) $shipment->id,
            entryType: 'first_mile_pickup_pay',
            paymentMethod: $payMethod,
            description: "First-Mile Pickup Task Pay ({$payInfo['distance_tier']}) from Shipping/Ops Fee for Prepaid Shipment #{$shipment->tracking_number}",
            meta: $payInfo
        );
    }

    public function recordPendingEscrowOnDelivery(SellerOrder $sellerOrder): CommissionRecord
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $sellerOrder->loadMissing([
            'extra',
            'payment',
            'shipment.currentRider',
            'shipment.currentCourier',
            'shipment.latestPickupAssignment.courier',
            'sellerProfile',
            'logisticsProfile',
        ]);

        $split = $this->commissionCalculator->calculateOrderSplitWithSubsidies($sellerOrder);
        $shipment = $sellerOrder->shipment;
        $riderId = $shipment?->current_rider_id;
        $courierId = $shipment?->current_courier_id ?? $shipment?->latestPickupAssignment?->courier_id;

        $codCollection = $shipment
            ? DB::table('cod_collections')->where('shipment_id', $shipment->id)->first()
            : null;

        $payMethod = $sellerOrder->effective_payment_method;
        $isCod = strtoupper($payMethod) === 'COD';

        $recordPayload = [
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
        ];

        if (Schema::hasColumn('commission_records', 'courier_id')) {
            $recordPayload['courier_id'] = $courierId;
            $recordPayload['courier_commission_rate'] = $split['courier_commission_rate'] ?? 0.00;
            $recordPayload['courier_commission_amount'] = $courierId ? ($split['courier_commission_amount'] ?? 0.00) : 0.00;
            $recordPayload['operational_fare_fee'] = $split['operational_fare_fee'] ?? 0.00;
            $recordPayload['cod_remittance_stage'] = $isCod ? 'awaiting_rider' : 'prepaid_escrow';
        }

        $record = CommissionRecord::updateOrCreate(
            ['seller_order_id' => $sellerOrder->id],
            $recordPayload
        );

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
                description: "Pending 90% Seller Merchandise Escrow (from Product Price) for Order #{$sellerOrder->seller_order_number}"
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
                description: "Pending Hub Shipping & Operational Escrow (from Shipping Fee) for Order #{$sellerOrder->seller_order_number}"
            );
        }

        if ($courierId && (float) ($split['courier_commission_amount'] ?? 0) > 0) {
            $courier = Courier::find($courierId);
            $alreadyPaidDirect = WalletTransaction::where('owner_type', 'Courier')
                ->where('owner_id', $courierId)
                ->where('shipment_id', $shipment?->id)
                ->where('entry_type', 'first_mile_pickup_pay')
                ->exists();

            if (! $alreadyPaidDirect) {
                $this->creditPendingEscrow(
                    ownerType: 'Courier',
                    ownerId: (int) $courierId,
                    ownerName: $courier?->full_name ?? "Courier #{$courierId}",
                    amount: (float) $split['courier_commission_amount'],
                    sellerOrderId: (int) $sellerOrder->id,
                    shipmentId: $shipment?->id,
                    entryType: 'pending_escrow_credit',
                    paymentMethod: $payMethod,
                    description: "Pending First-Mile Courier Pickup Pay (from Shipping/Ops Fee) for Order #{$sellerOrder->seller_order_number}"
                );
            }
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
                description: "Pending Last-Mile Rider Delivery Pay (from Shipping/Ops Fee) for Order #{$sellerOrder->seller_order_number}"
            );
        }

        return $record;
    }

    public function releaseCompletedOrderEarnings(SellerOrder $sellerOrder, bool $forceCodRelease = false): CommissionRecord
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $record = CommissionRecord::where('seller_order_id', $sellerOrder->id)->first();
        if (! $record) {
            $record = $this->recordPendingEscrowOnDelivery($sellerOrder);
        }

        $sellerOrder->loadMissing(['sellerProfile', 'logisticsProfile', 'shipment', 'codCollection']);
        $payMethod = $sellerOrder->effective_payment_method;
        $isCod = strtoupper($payMethod) === 'COD';
        $shipmentId = $sellerOrder->shipment?->id ?? $record->shipment_id;

        $codAdminVerified = ! $isCod || $forceCodRelease;

        if ($isCod && ! $codAdminVerified) {
            $col = $sellerOrder->codCollection
                ?? ($record->cod_collection_id ? CodCollection::find($record->cod_collection_id) : null);

            if (
                ($record->cod_remittance_stage ?? '') === 'settled'
                || ($col && ($col->admin_remittance_status ?? '') === 'admin_verified')
            ) {
                $codAdminVerified = true;
            }
        }

        if ($codAdminVerified) {
            $this->releaseOperationalShippingShares($record, $sellerOrder);

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
                    description: "90% Seller Net Merchandise Earning (from Product Price) released for Order #{$sellerOrder->seller_order_number}"
                );
            }

            $updates = [
                'status'     => 'settled',
                'settled_at' => $record->settled_at ?? now(),
            ];
            if (Schema::hasColumn('commission_records', 'cod_remittance_stage')) {
                $updates['cod_remittance_stage'] = 'settled';
            }
            $record->update($updates);
        }

        return $record;
    }

    public function releaseOperationalShippingShares(CommissionRecord $record, ?SellerOrder $sellerOrder = null): void
    {
        $sellerOrder = $sellerOrder ?: $record->sellerOrder()->with(['logisticsProfile', 'shipment'])->first();
        if (! $sellerOrder) {
            return;
        }

        $payMethod = $sellerOrder->effective_payment_method;
        $shipmentId = $sellerOrder->shipment?->id ?? $record->shipment_id;

        if ($sellerOrder->logistics_profile_id && (float) $record->logistics_total_earning > 0) {
            $this->releasePendingEscrowToAvailable(
                ownerType: 'Logistics',
                ownerId: (int) $sellerOrder->logistics_profile_id,
                ownerName: $sellerOrder->logisticsProfile?->business_name ?? "Logistics #{$sellerOrder->logistics_profile_id}",
                amount: (float) $record->logistics_total_earning,
                sellerOrderId: (int) $sellerOrder->id,
                shipmentId: $shipmentId,
                entryType: 'commission_released',
                paymentMethod: $payMethod,
                description: "Logistics Hub Shipping & Operational Share released from Shipping Fee for Order #{$sellerOrder->seller_order_number}"
            );
        }

        $courierId = $record->courier_id ?? $sellerOrder->shipment?->current_courier_id;
        $courierPay = round((float) ($record->courier_commission_amount ?? 0), 2);
        if ($courierId && $courierPay > 0) {
            $alreadyPaidDirect = WalletTransaction::where('owner_type', 'Courier')
                ->where('owner_id', $courierId)
                ->where('shipment_id', $shipmentId)
                ->where('entry_type', 'first_mile_pickup_pay')
                ->exists();

            if (! $alreadyPaidDirect) {
                $courier = Courier::find($courierId);
                $this->releasePendingEscrowToAvailable(
                    ownerType: 'Courier',
                    ownerId: (int) $courierId,
                    ownerName: $courier?->full_name ?? "Courier #{$courierId}",
                    amount: $courierPay,
                    sellerOrderId: (int) $sellerOrder->id,
                    shipmentId: $shipmentId,
                    entryType: 'commission_released',
                    paymentMethod: $payMethod,
                    description: "First-Mile Courier Task Commission released from Shipping/Ops Fee for Order #{$sellerOrder->seller_order_number}"
                );
            }
        }

        if ($record->rider_id && (float) $record->rider_commission_amount > 0) {
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
                description: "Last-Mile Rider Delivery Task Commission released from Shipping/Ops Fee for Order #{$sellerOrder->seller_order_number}"
            );
        }
    }

    public function syncLogisticsUnremittedCodBalance(int $logisticsProfileId, ?string $businessName = null): float
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $unremittedCod = round((float) CodCollection::where('logistics_profile_id', $logisticsProfileId)
            ->where('status', 'verified')
            ->where(function ($q) {
                if (Schema::hasColumn('cod_collections', 'admin_remittance_status')) {
                    $q->whereNull('admin_remittance_status')
                      ->orWhereIn('admin_remittance_status', ['unremitted', 'held_by_logistics', 'submitted_to_admin', 'rejected']);
                }
            })
            ->sum(DB::raw('COALESCE(remitted_amount, collected_amount)')), 2);

        $wallet = $this->getOrCreateWallet('Logistics', $logisticsProfileId, $businessName);
        if (Schema::hasColumn('role_wallets', 'unremitted_cod_balance')) {
            $wallet->update(['unremitted_cod_balance' => $unremittedCod]);
        }

        return $unremittedCod;
    }

    public function recordRiderCodVerifiedByLogistics(CodRemittance $remittance, int $verifiedByUserId): void
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        DB::transaction(function () use ($remittance, $verifiedByUserId) {
            $lockedRemittance = CodRemittance::with('items.codCollection')
                ->where('id', $remittance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRemittance->status === 'verified') {
                return;
            }

            $lockedRemittance->update([
                'status'      => 'verified',
                'verified_by' => $verifiedByUserId,
                'verified_at' => now(),
            ]);

            foreach ($lockedRemittance->items as $item) {
                $col = $item->codCollection;
                if (! $col) {
                    continue;
                }

                $colUpdates = [
                    'status'      => 'verified',
                    'verified_by' => $verifiedByUserId,
                    'verified_at' => now(),
                ];
                if (Schema::hasColumn('cod_collections', 'admin_remittance_status')) {
                    $colUpdates['admin_remittance_status'] = 'held_by_logistics';
                }
                $col->update($colUpdates);

                if (Schema::hasColumn('commission_records', 'cod_remittance_stage')) {
                    CommissionRecord::where('seller_order_id', $col->seller_order_id)
                        ->update([
                            'cod_collection_id'    => $col->id,
                            'cod_remittance_stage' => 'held_by_logistics',
                        ]);
                }
            }

            $this->syncLogisticsUnremittedCodBalance((int) $lockedRemittance->logistics_profile_id);
        });
    }

    public function submitLogisticsCodRemittanceToAdmin(
        LogisticsProfile $logisticsProfile,
        int $submittedByUserId,
        string $senderAccountName,
        string $senderAccountNumber,
        string $gcashReferenceNumber,
        ?string $proofImagePath = null,
        ?array $collectionIds = null
    ): LogisticsAdminRemittance {
        StorkSchemaBootstrapService::ensureTablesExist();

        $cleanSenderPhone = preg_replace('/\D/', '', trim($senderAccountNumber));
        if (str_starts_with($cleanSenderPhone, '63') && strlen($cleanSenderPhone) === 12) {
            $cleanSenderPhone = '0' . substr($cleanSenderPhone, 2);
        } elseif (str_starts_with($cleanSenderPhone, '9') && strlen($cleanSenderPhone) === 10) {
            $cleanSenderPhone = '0' . $cleanSenderPhone;
        }

        if (! preg_match('/^09\d{9}$/', $cleanSenderPhone)) {
            throw ValidationException::withMessages([
                'sender_account_number' => 'Please enter a valid 11-digit Sender GCash mobile number starting with 09 (e.g., 09171234567).',
            ]);
        }

        $cleanRef = strtoupper(trim($gcashReferenceNumber));
        if (strlen($cleanRef) < 4) {
            throw ValidationException::withMessages([
                'gcash_reference_number' => 'Please enter a valid GCash Reference Number (at least 4 characters).',
            ]);
        }

        if (LogisticsAdminRemittance::whereRaw('UPPER(TRIM(gcash_reference_number)) = ?', [$cleanRef])->where('status', '!=', 'rejected')->exists()) {
            throw ValidationException::withMessages([
                'gcash_reference_number' => "GCash Reference Number \"{$cleanRef}\" has already been submitted.",
            ]);
        }

        $notifications = [];

        $batch = DB::transaction(function () use (
            $logisticsProfile,
            $submittedByUserId,
            $senderAccountName,
            $cleanSenderPhone,
            $cleanRef,
            $proofImagePath,
            $collectionIds,
            &$notifications
        ) {
            $query = CodCollection::with(['sellerOrder', 'shipment', 'remittanceItem', 'commissionRecord'])
                ->where('logistics_profile_id', $logisticsProfile->id)
                ->where('status', 'verified')
                ->where(function ($q) {
                    $q->whereNull('admin_remittance_status')
                      ->orWhereIn('admin_remittance_status', ['unremitted', 'held_by_logistics', 'rejected']);
                })
                ->lockForUpdate();

            if (! empty($collectionIds)) {
                $query->whereIn('id', array_map('intval', $collectionIds));
            }

            $collections = $query->orderBy('verified_at', 'asc')->get();

            if ($collections->isEmpty()) {
                throw ValidationException::withMessages([
                    'remittance' => 'There are no verified Rider COD collections awaiting remittance to Storkia Admin.',
                ]);
            }

            $platGcash = Schema::hasTable('platform_gcash_settings')
                ? DB::table('platform_gcash_settings')->where('is_active', true)->orderByDesc('id')->first()
                : null;

            $totalCod = 0.00;
            $totalProdSubtotal = 0.00;
            $totalShipFee = 0.00;
            $totalAdminComm = 0.00;
            $totalSellerNet = 0.00;
            $totalLogisticsEarn = 0.00;
            $totalCourierComm = 0.00;
            $totalRiderComm = 0.00;
            $preparedItems = [];

            foreach ($collections as $col) {
                $record = $col->commissionRecord;
                if (! $record && $col->sellerOrder) {
                    $record = $this->recordPendingEscrowOnDelivery($col->sellerOrder);
                }

                $codAmt = round((float) ($col->remitted_amount ?? $col->collected_amount), 2);
                $prodSub = round((float) ($record?->product_subtotal ?? $col->product_subtotal), 2);
                $shipFee = round((float) ($record?->shipping_fee ?? $col->shipping_fee), 2);
                $adminComm = round((float) ($record?->admin_commission_amount ?? round($prodSub * 0.10, 2)), 2);
                $sellerNet = round((float) ($record?->seller_net_earning ?? round($prodSub * 0.90, 2)), 2);
                $courierComm = round((float) ($record?->courier_commission_amount ?? 0.00), 2);
                $riderComm = round((float) ($record?->rider_commission_amount ?? round($shipFee * 0.10, 2)), 2);
                $logisticsEarn = round((float) ($record?->logistics_total_earning ?? max(0.0, $shipFee - $courierComm - $riderComm)), 2);

                $totalCod = round($totalCod + $codAmt, 2);
                $totalProdSubtotal = round($totalProdSubtotal + $prodSub, 2);
                $totalShipFee = round($totalShipFee + $shipFee, 2);
                $totalAdminComm = round($totalAdminComm + $adminComm, 2);
                $totalSellerNet = round($totalSellerNet + $sellerNet, 2);
                $totalLogisticsEarn = round($totalLogisticsEarn + $logisticsEarn, 2);
                $totalCourierComm = round($totalCourierComm + $courierComm, 2);
                $totalRiderComm = round($totalRiderComm + $riderComm, 2);

                $preparedItems[] = [
                    'col'                       => $col,
                    'record'                    => $record,
                    'cod_remittance_id'         => $col->remittanceItem?->cod_remittance_id,
                    'cod_amount'                => $codAmt,
                    'product_subtotal'          => $prodSub,
                    'shipping_fee'              => $shipFee,
                    'admin_commission_amount'   => $adminComm,
                    'seller_net_earning'        => $sellerNet,
                    'logistics_earning'         => $logisticsEarn,
                    'courier_commission_amount' => $courierComm,
                    'rider_commission_amount'   => $riderComm,
                ];
            }

            do {
                $batchRef = 'LAR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
            } while (LogisticsAdminRemittance::where('remittance_reference', $batchRef)->exists());

            $remittanceBatch = LogisticsAdminRemittance::create([
                'remittance_reference'     => $batchRef,
                'logistics_profile_id'     => $logisticsProfile->id,
                'submitted_by_user_id'     => $submittedByUserId,
                'total_cod_amount'         => $totalCod,
                'total_product_subtotal'   => $totalProdSubtotal,
                'total_shipping_fee'       => $totalShipFee,
                'total_admin_commission'   => $totalAdminComm,
                'total_seller_net'         => $totalSellerNet,
                'total_logistics_earning'  => $totalLogisticsEarn,
                'total_courier_commission' => $totalCourierComm,
                'total_rider_commission'   => $totalRiderComm,
                'payout_method'            => 'GCASH',
                'admin_gcash_name'         => $platGcash->account_name ?? 'Storkia Admin Escrow',
                'admin_gcash_number'       => $platGcash->gcash_number ?? null,
                'sender_account_name'      => trim($senderAccountName),
                'sender_account_number'    => $cleanSenderPhone,
                'gcash_reference_number'   => $cleanRef,
                'proof_image_path'         => $proofImagePath,
                'status'                   => 'pending_verification',
                'submitted_at'             => now(),
            ]);

            foreach ($preparedItems as $pItem) {
                /** @var CodCollection $colModel */
                $colModel = $pItem['col'];
                /** @var CommissionRecord|null $recModel */
                $recModel = $pItem['record'];

                LogisticsAdminRemittanceItem::create([
                    'logistics_admin_remittance_id' => $remittanceBatch->id,
                    'cod_collection_id'             => $colModel->id,
                    'cod_remittance_id'             => $pItem['cod_remittance_id'],
                    'seller_order_id'               => $colModel->seller_order_id,
                    'shipment_id'                   => $colModel->shipment_id,
                    'commission_record_id'          => $recModel?->id,
                    'cod_amount'                    => $pItem['cod_amount'],
                    'product_subtotal'              => $pItem['product_subtotal'],
                    'shipping_fee'                  => $pItem['shipping_fee'],
                    'admin_commission_amount'       => $pItem['admin_commission_amount'],
                    'seller_net_earning'            => $pItem['seller_net_earning'],
                    'logistics_earning'             => $pItem['logistics_earning'],
                    'courier_commission_amount'     => $pItem['courier_commission_amount'],
                    'rider_commission_amount'       => $pItem['rider_commission_amount'],
                ]);

                $colModel->update([
                    'logistics_admin_remittance_id' => $remittanceBatch->id,
                    'admin_remittance_status'       => 'submitted_to_admin',
                    'admin_remitted_at'             => now(),
                ]);

                if ($recModel) {
                    $recModel->update([
                        'logistics_admin_remittance_id' => $remittanceBatch->id,
                        'cod_remittance_stage'          => 'submitted_to_admin',
                    ]);
                }
            }

            $this->syncLogisticsUnremittedCodBalance((int) $logisticsProfile->id, $logisticsProfile->business_name);

            foreach (Admin::all() as $admin) {
                $notifications[] = [
                    'recipient'    => $admin,
                    'notification' => new OrderLifecycleNotification(
                        title: "Logistics COD Remittance Submitted ({$batchRef})",
                        message: "{$logisticsProfile->business_name} remitted ₱" . number_format($totalCod, 2) . " via GCash (Ref #{$cleanRef}) for {$collections->count()} COD order(s). Verify to release multi-role E-Wallet commissions.",
                        type: 'info',
                        url: route('admin.commissions.index', ['tab' => 'logistics_remittances'])
                    ),
                ];
            }

            return $remittanceBatch;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $batch;
    }

    public function processLogisticsAdminRemittanceDecision(
        int $remittanceId,
        int $adminId,
        string $decision,
        ?string $remarks = null
    ): LogisticsAdminRemittance {
        StorkSchemaBootstrapService::ensureTablesExist();

        $isApprove = in_array(strtolower($decision), ['approve', 'verify', 'verified'], true);
        if (! $isApprove && empty(trim((string) $remarks))) {
            throw ValidationException::withMessages([
                'remarks' => 'Please provide a rejection reason so the Logistics Hub knows why the GCash remittance was declined.',
            ]);
        }

        $notifications = [];

        $updatedBatch = DB::transaction(function () use ($remittanceId, $adminId, $isApprove, $remarks, &$notifications) {
            /** @var LogisticsAdminRemittance $lockedBatch */
            $lockedBatch = LogisticsAdminRemittance::with([
                'logisticsProfile.user',
                'items.codCollection',
                'items.sellerOrder.seller',
                'items.sellerOrder.payment',
                'items.sellerOrder.order',
                'items.commissionRecord',
            ])->where('id', $remittanceId)->lockForUpdate()->firstOrFail();

            if ($lockedBatch->status !== 'pending_verification') {
                throw ValidationException::withMessages([
                    'remittance' => "Logistics Remittance #{$lockedBatch->remittance_reference} has already been processed ({$lockedBatch->status}).",
                ]);
            }

            $cleanRemarks = trim((string) $remarks);

            if ($isApprove) {
                $lockedBatch->update([
                    'status'               => 'verified',
                    'reviewed_by_admin_id' => $adminId,
                    'admin_remarks'        => $cleanRemarks ?: 'Verified by Storkia Admin. Commissions distributed to Role E-Wallets.',
                    'verified_at'          => now(),
                ]);

                $notifiedSellers = [];
                $notifiedRiders = [];
                $notifiedCouriers = [];

                foreach ($lockedBatch->items as $item) {
                    $col = $item->codCollection;
                    $sellerOrder = $item->sellerOrder;
                    $record = $item->commissionRecord
                        ?? ($sellerOrder ? CommissionRecord::where('seller_order_id', $sellerOrder->id)->first() : null);

                    if ($col) {
                        $col->update([
                            'admin_remittance_status'    => 'admin_verified',
                            'admin_verified_at'          => now(),
                            'admin_verified_by_admin_id' => $adminId,
                        ]);
                    }

                    if ($sellerOrder) {
                        $sellerOrder->update([
                            'payment_status' => $sellerOrder->status === 'completed' ? 'settled' : 'remitted',
                        ]);
                        $sellerOrder->payment?->update([
                            'status'      => $sellerOrder->status === 'completed' ? 'settled' : 'remitted',
                            'remitted_at' => now(),
                            'settled_at'  => $sellerOrder->status === 'completed' ? now() : null,
                        ]);
                    }

                    if ($record && $sellerOrder && ! in_array($sellerOrder->status, ['returned_to_seller', 'cancelled', 'rejected'], true)) {
                        $this->releaseOperationalShippingShares($record, $sellerOrder);

                        if ($record->rider_id && (float) $record->rider_commission_amount > 0) {
                            $notifiedRiders[(int) $record->rider_id] = ($notifiedRiders[(int) $record->rider_id] ?? 0.0) + (float) $record->rider_commission_amount;
                        }
                        if ($record->courier_id && (float) $record->courier_commission_amount > 0) {
                            $notifiedCouriers[(int) $record->courier_id] = ($notifiedCouriers[(int) $record->courier_id] ?? 0.0) + (float) $record->courier_commission_amount;
                        }

                        if ($sellerOrder->status === 'completed') {
                            $this->releaseCompletedOrderEarnings($sellerOrder, true);
                            if ($sellerOrder->seller_id) {
                                $notifiedSellers[(int) $sellerOrder->seller_id] = [
                                    'user'   => $sellerOrder->seller,
                                    'amount' => ($notifiedSellers[(int) $sellerOrder->seller_id]['amount'] ?? 0.0) + (float) $record->seller_net_earning,
                                ];
                            }
                        } else {
                            $record->update([
                                'cod_remittance_stage' => 'settled',
                            ]);
                        }
                    }
                }

                $this->syncLogisticsUnremittedCodBalance(
                    (int) $lockedBatch->logistics_profile_id,
                    $lockedBatch->logisticsProfile?->business_name
                );

                if ($lockedBatch->logisticsProfile?->user) {
                    $notifications[] = [
                        'recipient'    => $lockedBatch->logisticsProfile->user,
                        'notification' => new OrderLifecycleNotification(
                            title: "Admin Verified COD Remittance #{$lockedBatch->remittance_reference}!",
                            message: "Your GCash remittance of ₱" . number_format((float) $lockedBatch->total_cod_amount, 2) . " was verified. ₱" . number_format((float) $lockedBatch->total_logistics_earning, 2) . " Hub Share, ₱" . number_format((float) $lockedBatch->total_courier_commission, 2) . " Courier Pay, and ₱" . number_format((float) $lockedBatch->total_rider_commission, 2) . " Rider Pay have been released to Available E-Wallets!",
                            type: 'success',
                            url: route('logistics.finance.index', ['tab' => 'admin_remittances'])
                        ),
                    ];
                }

                foreach ($notifiedRiders as $rId => $rTotal) {
                    $rider = Rider::find($rId);
                    if ($rider) {
                        $notifications[] = [
                            'recipient'    => $rider,
                            'notification' => new OrderLifecycleNotification(
                                title: 'Rider Delivery Commissions Released to E-Wallet!',
                                message: "Admin verified your hub's COD remittance (#{$lockedBatch->remittance_reference}). ₱" . number_format((float) $rTotal, 2) . " has been credited to your Available Rider E-Wallet!",
                                type: 'success',
                                url: route('rider.report')
                            ),
                        ];
                    }
                }

                foreach ($notifiedCouriers as $cId => $cTotal) {
                    $courier = Courier::find($cId);
                    if ($courier) {
                        $notifications[] = [
                            'recipient'    => $courier,
                            'notification' => new OrderLifecycleNotification(
                                title: 'First-Mile Pickup Commissions Released to E-Wallet!',
                                message: "Admin verified your hub's COD remittance (#{$lockedBatch->remittance_reference}). ₱" . number_format((float) $cTotal, 2) . " has been credited to your Available Courier E-Wallet!",
                                type: 'success',
                                url: route('courier.report')
                            ),
                        ];
                    }
                }

                foreach ($notifiedSellers as $sData) {
                    if (! empty($sData['user'])) {
                        $notifications[] = [
                            'recipient'    => $sData['user'],
                            'notification' => new OrderLifecycleNotification(
                                title: '90% COD Merchandise Earnings Released to Seller E-Wallet!',
                                message: "Storkia Admin verified the COD remittance for your completed order(s). ₱" . number_format((float) $sData['amount'], 2) . " is now in your Available Seller E-Wallet!",
                                type: 'success',
                                url: route('seller.settlements.index')
                            ),
                        ];
                    }
                }
            } else {
                $lockedBatch->update([
                    'status'               => 'rejected',
                    'reviewed_by_admin_id' => $adminId,
                    'rejection_reason'     => $cleanRemarks,
                    'admin_remarks'        => $cleanRemarks,
                    'rejected_at'          => now(),
                ]);

                foreach ($lockedBatch->items as $item) {
                    $col = $item->codCollection;
                    $record = $item->commissionRecord;

                    if ($col) {
                        $col->update([
                            'logistics_admin_remittance_id' => null,
                            'admin_remittance_status'       => 'held_by_logistics',
                        ]);
                    }
                    if ($record) {
                        $record->update([
                            'logistics_admin_remittance_id' => null,
                            'cod_remittance_stage'          => 'held_by_logistics',
                        ]);
                    }
                }

                $this->syncLogisticsUnremittedCodBalance(
                    (int) $lockedBatch->logistics_profile_id,
                    $lockedBatch->logisticsProfile?->business_name
                );

                if ($lockedBatch->logisticsProfile?->user) {
                    $notifications[] = [
                        'recipient'    => $lockedBatch->logisticsProfile->user,
                        'notification' => new OrderLifecycleNotification(
                            title: "Logistics COD Remittance #{$lockedBatch->remittance_reference} Rejected",
                            message: "Your GCash remittance of ₱" . number_format((float) $lockedBatch->total_cod_amount, 2) . " was declined ({$cleanRemarks}). Please review and re-submit the remittance to unlock withdrawals.",
                            type: 'danger',
                            url: route('logistics.finance.index', ['tab' => 'admin_remittances'])
                        ),
                    ];
                }
            }

            return $lockedBatch;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $updatedBatch;
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

        if ($normalizedType === 'Logistics') {
            $unremittedToAdmin = $this->syncLogisticsUnremittedCodBalance($ownerId, $ownerName);
            if ($unremittedToAdmin > 0.009) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Withdrawal Restricted: Your Logistics Hub currently holds ₱' . number_format($unremittedToAdmin, 2) . ' in Rider-remitted COD funds that have not yet been remitted to and verified by Storkia Admin. Please remit all collected COD to Admin GCash and wait for verification before requesting a withdrawal.',
                ]);
            }
        }

        if ($normalizedType === 'Rider') {
            $unremittedRiderCod = round((float) CodCollection::where('rider_id', $ownerId)
                ->where('status', 'collected')
                ->sum('collected_amount'), 2);

            if ($unremittedRiderCod > 0.009) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Withdrawal Restricted: You currently hold ₱' . number_format($unremittedRiderCod, 2) . ' in unremitted COD cash on hand. Please remit 100% of your collected COD cash to your Logistics Hub first.',
                ]);
            }
        }

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
            $arrears = round((float) $lockedWallet->platform_commission_arrears, 2);

            if ($normalizedType === 'Seller' && $arrears > 0) {
                if ($available <= $arrears) {
                    throw ValidationException::withMessages([
                        'requested_amount' => 'Your available balance (₱' . number_format($available, 2) . ') is insufficient to cover your outstanding platform arrears of ₱' . number_format($arrears, 2) . '.',
                    ]);
                }

                $available = round($available - $arrears, 2);
                $lockedWallet->update([
                    'available_balance'           => $available,
                    'platform_commission_arrears' => 0.00,
                ]);

                if (Schema::hasColumn('seller_profiles', 'gcash_blocked_for_arrears')) {
                    SellerProfile::where('id', $ownerId)->update(['gcash_blocked_for_arrears' => false]);
                }

                WalletTransaction::create([
                    'role_wallet_id'          => $lockedWallet->id,
                    'transaction_reference'   => 'WTX-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                    'owner_type'              => 'Seller',
                    'owner_id'                => $ownerId,
                    'entry_type'              => 'arrears_wallet_offset',
                    'payment_method'          => 'WALLET',
                    'amount'                  => -$arrears,
                    'pending_balance_after'   => (float) $lockedWallet->pending_balance,
                    'available_balance_after' => $available,
                    'status'                  => 'completed',
                    'description'             => 'Auto-deducted ₱' . number_format($arrears, 2) . ' platform arrears prior to withdrawal request.',
                ]);

                if ($amount > $available) {
                    $amount = $available;
                }
                if ($amount < self::MIN_WITHDRAWAL_AMOUNT) {
                    throw ValidationException::withMessages([
                        'requested_amount' => 'After auto-deducting ₱' . number_format($arrears, 2) . ' in arrears, your remaining withdrawable balance is ₱' . number_format($available, 2) . ' (minimum withdrawal is ₱' . number_format(self::MIN_WITHDRAWAL_AMOUNT, 2) . ').',
                    ]);
                }
            }

            if ($available < $amount) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Requested amount (₱' . number_format($amount, 2) . ') exceeds your available E-Wallet balance of ₱' . number_format($available, 2) . '.',
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
            'Buyer'     => User::find($ownerId),
            default     => null,
        };
    }
}