<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BuyerAccountSanction;
use App\Models\BuyerBanAppeal;
use App\Models\SellerOrder;
use App\Models\SellerRtsClaim;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\OrderLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerAbandonmentService
{
    public function getOrCreateSanction(int $buyerId): BuyerAccountSanction
    {
        return BuyerAccountSanction::firstOrCreate(
            ['buyer_id' => $buyerId],
            [
                'cod_abandonment_strikes' => 0,
                'ban_level'               => 0,
                'account_status'          => 'active',
                'appeal_required'         => false,
                'strike_history'          => [],
            ]
        );
    }

    public function recordCodParcelAbandonment(
        Shipment $shipment,
        SellerOrder $sellerOrder,
        string $abandonmentReason,
        int $failedAttemptsCount = 1
    ): BuyerAccountSanction {
        $notifications = [];

        $sanction = DB::transaction(function () use (
            $shipment,
            $sellerOrder,
            $abandonmentReason,
            $failedAttemptsCount,
            &$notifications
        ) {
            $sellerOrder->loadMissing(['buyer', 'seller', 'sellerProfile', 'logisticsProfile.user', 'payment']);

            SellerRtsClaim::updateOrCreate(
                ['seller_order_id' => $sellerOrder->id],
                [
                    'shipment_id'           => $shipment->id,
                    'seller_profile_id'     => $sellerOrder->seller_profile_id,
                    'logistics_profile_id'  => $sellerOrder->logistics_profile_id,
                    'buyer_id'              => $sellerOrder->buyer_id,
                    'abandonment_reason'    => $abandonmentReason,
                    'failed_attempts_count' => max(1, $failedAttemptsCount),
                    'rts_status'            => 'returning_to_seller',
                ]
            );

            $isCodOrder = $sellerOrder->effective_payment_method === 'COD';
            $lockedSanction = BuyerAccountSanction::where('buyer_id', $sellerOrder->buyer_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedSanction) {
                $lockedSanction = $this->getOrCreateSanction((int) $sellerOrder->buyer_id);
                $lockedSanction = BuyerAccountSanction::where('id', $lockedSanction->id)->lockForUpdate()->firstOrFail();
            }

            if (! $isCodOrder) {
                return $lockedSanction;
            }

            $history = is_array($lockedSanction->strike_history) ? $lockedSanction->strike_history : [];
            $alreadyRecordedForOrder = collect($history)->contains(
                fn ($entry) => (int) ($entry['seller_order_id'] ?? 0) === (int) $sellerOrder->id
            );

            if ($alreadyRecordedForOrder) {
                return $lockedSanction;
            }

            $newStrikes = (int) $lockedSanction->cod_abandonment_strikes + 1;
            $currentBanLevel = (int) $lockedSanction->ban_level;

            $history[] = [
                'strike_number'       => $newStrikes,
                'seller_order_id'     => $sellerOrder->id,
                'seller_order_number' => $sellerOrder->seller_order_number,
                'tracking_number'     => $shipment->tracking_number,
                'reason'              => $abandonmentReason,
                'failed_attempts'     => $failedAttemptsCount,
                'recorded_at'         => now()->toDateTimeString(),
            ];

            if ($currentBanLevel >= 1) {
                // Repeat abandonment after already having been banned & reinstated -> Immediate Permanent Ban (2nd Ban)
                $lockedSanction->update([
                    'cod_abandonment_strikes'        => $newStrikes,
                    'ban_level'                      => 2,
                    'account_status'                 => 'permanently_banned',
                    'appeal_required'                => false,
                    'permanently_banned_at'          => now(),
                    'last_abandoned_seller_order_id' => $sellerOrder->id,
                    'last_abandonment_reason'        => $abandonmentReason,
                    'strike_history'                 => $history,
                ]);
            } elseif ($newStrikes >= BuyerAccountSanction::MAX_STRIKES_BEFORE_BAN) {
                // 3rd Strike -> 1st Ban: 7-Day Temporary Ban Countdown + Mandatory Admin Appeal Required
                $lockedSanction->update([
                    'cod_abandonment_strikes'        => $newStrikes,
                    'ban_level'                      => 1,
                    'account_status'                 => 'temp_banned',
                    'temp_ban_started_at'            => now(),
                    'temp_ban_ends_at'               => now()->addDays(BuyerAccountSanction::TEMP_BAN_DAYS),
                    'appeal_required'                => true,
                    'last_abandoned_seller_order_id' => $sellerOrder->id,
                    'last_abandonment_reason'        => $abandonmentReason,
                    'strike_history'                 => $history,
                ]);
            } else {
                // Strike 1 or Strike 2 Warning
                $lockedSanction->update([
                    'cod_abandonment_strikes'        => $newStrikes,
                    'last_abandoned_seller_order_id' => $sellerOrder->id,
                    'last_abandonment_reason'        => $abandonmentReason,
                    'strike_history'                 => $history,
                ]);
            }

            $buyerName = $sellerOrder->buyer?->full_name ?? "Buyer #{$sellerOrder->buyer_id}";
            $strikeBadge = $lockedSanction->isPermanentlyBanned()
                ? 'PERMANENT BAN APPLIED (2nd Ban Offense)'
                : ($lockedSanction->account_status === 'temp_banned'
                    ? '3/3 STRIKES — 7-DAY TEMPORARY BAN + APPEAL REQUIRED'
                    : "STRIKE {$newStrikes}/3 WARNING");

            if ($sellerOrder->buyer) {
                $buyerMsg = match (true) {
                    $lockedSanction->isPermanentlyBanned() => "Your account has been permanently banned due to repeated COD parcel abandonment after prior reinstatement (Order #{$sellerOrder->seller_order_number}).",
                    $lockedSanction->account_status === 'temp_banned' => "Strike 3/3: Your account is temporarily suspended for 7 days due to COD parcel abandonment on Order #{$sellerOrder->seller_order_number}. After the 7-day countdown finishes, you must submit an appeal for Admin review to reactivate your account.",
                    default => "COD Abandonment Warning (Strike {$newStrikes}/3) on Order #{$sellerOrder->seller_order_number}: {$abandonmentReason}. Reaching 3 strikes will result in a 7-day ban and mandatory appeal.",
                };

                $notifications[] = [
                    'recipient'    => $sellerOrder->buyer,
                    'notification' => new OrderLifecycleNotification(
                        title: "COD Abandonment {$strikeBadge}",
                        message: $buyerMsg,
                        type: 'danger',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            if ($sellerOrder->seller) {
                $notifications[] = [
                    'recipient'    => $sellerOrder->seller,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Buyer Abandoned COD Parcel — Returning to Seller',
                        message: "{$buyerName} abandoned Order #{$sellerOrder->seller_order_number} ({$abandonmentReason}) [{$strikeBadge}]. The package is returning to your facility. Please confirm receipt once it arrives to restore stock or file an RTS claim if damaged.",
                        type: 'warning',
                        url: route('seller.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            if ($sellerOrder->logisticsProfile?->user) {
                $notifications[] = [
                    'recipient'    => $sellerOrder->logisticsProfile->user,
                    'notification' => new OrderLifecycleNotification(
                        title: 'Buyer COD Abandonment Logged (' . $strikeBadge . ')',
                        message: "Shipment #{$shipment->tracking_number} was abandoned by {$buyerName} ({$abandonmentReason}). Return-to-Seller (RTS) initiated.",
                        type: 'warning',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            foreach (Admin::all() as $admin) {
                $notifications[] = [
                    'recipient'    => $admin,
                    'notification' => new OrderLifecycleNotification(
                        title: "Buyer COD Abandonment Alert ({$strikeBadge})",
                        message: "{$buyerName} abandoned COD Order #{$sellerOrder->seller_order_number} (Shipment #{$shipment->tracking_number}). Reason: {$abandonmentReason}.",
                        type: 'danger',
                        url: route('admin.orders.disputes'),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $shipment->id
                    ),
                ];
            }

            return $lockedSanction;
        });

        foreach ($notifications as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        return $sanction;
    }

    public function submitBuyerAppeal(User $buyer, string $explanation, ?string $photoPath = null): BuyerBanAppeal
    {
        $sanction = $this->getOrCreateSanction((int) $buyer->id);

        if ($sanction->isPermanentlyBanned()) {
            throw ValidationException::withMessages([
                'appeal' => 'Permanently banned accounts (2nd ban offense) are not eligible for reactivation appeals.',
            ]);
        }

        if ($sanction->isTemporarilyBannedCountdownActive()) {
            $remaining = $sanction->temp_ban_ends_at?->diffForHumans( null, true );
            throw ValidationException::withMessages([
                'appeal' => "Your 7-day temporary suspension countdown is still active ({$remaining} remaining). You may submit your reactivation appeal once the 7-day countdown finishes.",
            ]);
        }

        if (! $sanction->appeal_required && $sanction->account_status === 'active') {
            throw ValidationException::withMessages([
                'appeal' => 'Your account is already active and does not require an appeal.',
            ]);
        }

        $pendingAppeal = BuyerBanAppeal::where('buyer_id', $buyer->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingAppeal) {
            throw ValidationException::withMessages([
                'appeal' => 'You already have a pending reactivation appeal under Admin review.',
            ]);
        }

        return DB::transaction(function () use ($buyer, $sanction, $explanation, $photoPath) {
            $sanction->update([
                'account_status' => 'awaiting_appeal_review',
            ]);

            return BuyerBanAppeal::create([
                'buyer_id'              => $buyer->id,
                'sanction_id'           => $sanction->id,
                'ban_level'             => $sanction->ban_level,
                'appeal_explanation'    => trim($explanation),
                'supporting_photo_path' => $photoPath,
                'status'                => 'pending',
            ]);
        });
    }

    public function resolveBuyerAppeal(
        BuyerBanAppeal $appeal,
        int $adminId,
        string $decision,
        string $remarks
    ): void {
        DB::transaction(function () use ($appeal, $adminId, $decision, $remarks) {
            $lockedAppeal = BuyerBanAppeal::with('sanction')->where('id', $appeal->id)->lockForUpdate()->firstOrFail();
            $sanction = $lockedAppeal->sanction;

            if ($decision === 'approve') {
                $lockedAppeal->update([
                    'status'               => 'approved',
                    'reviewed_by_admin_id' => $adminId,
                    'admin_remarks'        => trim($remarks),
                    'reviewed_at'          => now(),
                ]);

                // Reactivate buyer on probation (ban_level remains 1 so any future abandonment triggers immediate Permanent Ban)
                $sanction?->update([
                    'account_status'  => 'active',
                    'appeal_required' => false,
                ]);
            } else {
                $lockedAppeal->update([
                    'status'               => 'rejected',
                    'reviewed_by_admin_id' => $adminId,
                    'admin_remarks'        => trim($remarks),
                    'reviewed_at'          => now(),
                ]);

                $sanction?->update([
                    'account_status'  => 'temp_banned',
                    'appeal_required' => true,
                ]);
            }
        });
    }
}