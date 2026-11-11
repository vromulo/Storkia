<?php

namespace App\Services;

use App\Models\BuyerRewardTransaction;
use App\Models\BuyerRewardWallet;
use App\Models\BuyerVoucherClaim;
use App\Models\OrderItemReview;
use App\Models\SellerOrder;
use App\Models\StorkVoucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyerRewardService
{
    public const SLA_TIMEOUT_COMPENSATION_COINS = 50;
    public const COINS_PER_PESO = 10;
    public const COIN_TO_PESO_VALUE = 0.10;
    public const MIN_CHECKOUT_REDEEMABLE_COINS = 10;
    public const MAX_CHECKOUT_COIN_OFFSET_PERCENT = 25.00;
    public const PESOS_PER_CASHBACK_COIN = 100.00;
    public const TEXT_REVIEW_REWARD_COINS = 5;
    public const PHOTO_REVIEW_REWARD_COINS = 10;

    public static function ensureTablesExist(): void
    {
        try {
            if (! Schema::hasTable('users')) {
                return;
            }

            if (! Schema::hasTable('buyer_reward_wallets')) {
                Schema::create('buyer_reward_wallets', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('buyer_id')->unique()->constrained('users')->cascadeOnDelete();
                    $table->unsignedBigInteger('coins_balance')->default(0);
                    $table->unsignedBigInteger('lifetime_coins_earned')->default(0);
                    $table->unsignedBigInteger('lifetime_coins_spent')->default(0);
                    $table->unsignedInteger('spin_tickets')->default(0);
                    $table->timestamp('last_daily_login_spin_at')->nullable()->index();
                    $table->timestamp('last_spin_played_at')->nullable()->index();
                    $table->timestamp('last_chest_opened_at')->nullable()->index();
                    $table->timestamps();                 });
            }

            if (! Schema::hasTable('buyer_reward_transactions')) {
                Schema::create('buyer_reward_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('reward_wallet_id')->constrained('buyer_reward_wallets')->cascadeOnDelete();
                    $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
                    $table->foreignId('voucher_id')->nullable()->constrained('stork_vouchers')->nullOnDelete();
                    $table->string('reference_code', 50)->unique();
                    $table->string('source_type', 40)->index();
                    $table->string('reward_type', 30)->default('coins');
                    $table->integer('coins_delta')->default(0);
                    $table->integer('spins_delta')->default(0);
                    $table->unsignedBigInteger('coins_balance_after')->default(0);
                    $table->unsignedInteger('spins_balance_after')->default(0);
                    $table->unsignedTinyInteger('stage_level')->nullable();
                    $table->string('description', 255);
                    $table->json('meta_snapshot')->nullable();
                    $table->timestamps();

                    $table->index(['buyer_id', 'created_at'], 'brt_buyer_created_idx');
                    $table->index(['seller_order_id', 'source_type'], 'brt_order_source_idx');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getOrCreateWallet(int $buyerId): BuyerRewardWallet
    {
        self::ensureTablesExist();

        return BuyerRewardWallet::firstOrCreate(
            ['buyer_id' => $buyerId],
            [
                'coins_balance'            => 0,
                'lifetime_coins_earned'    => 0,
                'lifetime_coins_spent'     => 0,
                'spin_tickets'             => 0,
                'last_daily_login_spin_at' => null,
                'last_spin_played_at'      => null,
                'last_chest_opened_at'     => null,
            ]
        );
    }

    public function calculateMaxRedeemableCoinsForCheckout(int $buyerId, float $merchandiseNetSubtotal): array
    {
        $wallet = $this->getOrCreateWallet($buyerId);
        $balance = max(0, (int) $wallet->coins_balance);
        $cleanSubtotal = max(0.0, round($merchandiseNetSubtotal, 2));

        if ($balance < self::MIN_CHECKOUT_REDEEMABLE_COINS || $cleanSubtotal <= 0.0) {
            return [
                'coins_balance'        => $balance,
                'max_redeemable_coins' => 0,
                'peso_discount'        => 0.00,
            ];
        }

        $maxCapPesos = round($cleanSubtotal * (self::MAX_CHECKOUT_COIN_OFFSET_PERCENT / 100), 2);
        $maxCapCoins = (int) floor(($maxCapPesos + 0.0001) * self::COINS_PER_PESO);
        $maxSubtotalCoins = (int) floor(($cleanSubtotal + 0.0001) * self::COINS_PER_PESO);

        $maxRedeemableCoins = (int) min($balance, $maxCapCoins, $maxSubtotalCoins);

        if ($maxRedeemableCoins < self::MIN_CHECKOUT_REDEEMABLE_COINS) {
            $maxRedeemableCoins = 0;
        }

        $pesoDiscount = round($maxRedeemableCoins * self::COIN_TO_PESO_VALUE, 2);

        return [
            'coins_balance'        => $balance,
            'max_redeemable_coins' => $maxRedeemableCoins,
            'peso_discount'        => $pesoDiscount,
        ];
    }

    public function allocateCoinsAcrossPackages(int $totalCoinsToUse, array $eligibleSubtotals): array
    {
        $allocations = [];
        foreach ($eligibleSubtotals as $key => $sub) {
            $allocations[$key] = [
                'coins_used'     => 0,
                'coins_discount' => 0.00,
            ];
        }

        if ($totalCoinsToUse <= 0 || empty($eligibleSubtotals)) {
            return $allocations;
        }

        $cleanSubtotals = [];
        $maxCoinsPerKey = [];
        $totalEligibleSubtotal = 0.0;

        foreach ($eligibleSubtotals as $key => $sub) {
            $cleanSub = max(0.0, round((float) $sub, 2));
            $cleanSubtotals[$key] = $cleanSub;
            $maxCoinsPerKey[$key] = (int) floor(($cleanSub + 0.0001) * self::COINS_PER_PESO);
            $totalEligibleSubtotal = round($totalEligibleSubtotal + $cleanSub, 2);
        }

        if ($totalEligibleSubtotal <= 0.0) {
            return $allocations;
        }

        $totalMaxCapacity = array_sum($maxCoinsPerKey);
        $effectiveTotalCoins = min($totalCoinsToUse, $totalMaxCapacity);

        if ($effectiveTotalCoins <= 0) {
            return $allocations;
        }

        $flooredCoins = [];
        $remainders = [];
        $allocatedSum = 0;

        foreach ($cleanSubtotals as $key => $sub) {
            if ($sub <= 0.0 || $maxCoinsPerKey[$key] <= 0) {
                $flooredCoins[$key] = 0;
                $remainders[$key] = 0.0;
                continue;
            }

            $exactQuota = $effectiveTotalCoins * ($sub / $totalEligibleSubtotal);
            $floorVal = (int) min($maxCoinsPerKey[$key], floor($exactQuota));
            $flooredCoins[$key] = $floorVal;
            $remainders[$key] = $exactQuota - $floorVal;
            $allocatedSum += $floorVal;
        }

        $leftoverCoins = max(0, $effectiveTotalCoins - $allocatedSum);

        if ($leftoverCoins > 0) {
            $sortableKeys = array_keys($cleanSubtotals);
            usort($sortableKeys, function ($a, $b) use ($remainders, $cleanSubtotals) {
                $diff = $remainders[$b] <=> $remainders[$a];
                if ($diff !== 0) {
                    return $diff;
                }
                return $cleanSubtotals[$b] <=> $cleanSubtotals[$a];
            });

            while ($leftoverCoins > 0) {
                $distributedInPass = false;
                foreach ($sortableKeys as $k) {
                    if ($leftoverCoins <= 0) {
                        break;
                    }
                    if ($flooredCoins[$k] < $maxCoinsPerKey[$k]) {
                        $flooredCoins[$k]++;
                        $leftoverCoins--;
                        $distributedInPass = true;
                    }
                }
                if (! $distributedInPass) {
                    break;
                }
            }
        }

        foreach ($flooredCoins as $key => $coins) {
            $allocations[$key] = [
                'coins_used'     => (int) $coins,
                'coins_discount' => round($coins * self::COIN_TO_PESO_VALUE, 2),
            ];
        }

        return $allocations;
    }

    public function redeemCoinsForCheckoutOrder(
        int $buyerId,
        int $coinsToSpend,
        int $sellerOrderId,
        string $sellerOrderNumber
    ): ?BuyerRewardTransaction {
        self::ensureTablesExist();

        if ($coinsToSpend <= 0) {
            return null;
        }

        return DB::transaction(function () use ($buyerId, $coinsToSpend, $sellerOrderId, $sellerOrderNumber) {
            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->coins_balance < $coinsToSpend) {
                throw ValidationException::withMessages([
                    'use_storkia_coins' => 'Insufficient Storkia Coins balance for this checkout.',
                ]);
            }

            $newBalance = max(0, (int) $locked->coins_balance - $coinsToSpend);
            $newSpent = (int) $locked->lifetime_coins_spent + $coinsToSpend;

            $locked->update([
                'coins_balance'        => $newBalance,
                'lifetime_coins_spent' => $newSpent,
            ]);

            $pesoSaved = number_format($coinsToSpend * self::COIN_TO_PESO_VALUE, 2);

            return BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'seller_order_id'     => $sellerOrderId,
                'reference_code'      => $this->generateReference('CHK'),
                'source_type'         => 'checkout_coin_redemption',
                'reward_type'         => 'coins',
                'coins_delta'         => -$coinsToSpend,
                'spins_delta'         => 0,
                'coins_balance_after' => $newBalance,
                'spins_balance_after' => (int) $locked->spin_tickets,
                'description'         => "Redeemed {$coinsToSpend} Storkia Coins (-₱{$pesoSaved}) on Order #{$sellerOrderNumber}",
                'meta_snapshot'       => [
                    'seller_order_id'     => $sellerOrderId,
                    'seller_order_number' => $sellerOrderNumber,
                    'coins_spent'         => $coinsToSpend,
                    'peso_discount'       => round($coinsToSpend * self::COIN_TO_PESO_VALUE, 2),
                ],
            ]);
        });
    }

    public function restoreCoinsForSellerOrder(SellerOrder $sellerOrder): ?BuyerRewardTransaction
    {
        self::ensureTablesExist();

        $sellerOrder->loadMissing('extra');
        $coinsUsed = (int) ($sellerOrder->extra?->coins_used ?? 0);
        $buyerId = (int) $sellerOrder->buyer_id;

        if ($coinsUsed <= 0 || $buyerId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($sellerOrder, $buyerId, $coinsUsed) {
            $alreadyRestored = BuyerRewardTransaction::where('seller_order_id', $sellerOrder->id)
                ->where('source_type', 'checkout_coin_refund')
                ->exists();

            if ($alreadyRestored) {
                return null;
            }

            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = (int) $locked->coins_balance + $coinsUsed;
            $newSpent = max(0, (int) $locked->lifetime_coins_spent - $coinsUsed);

            $locked->update([
                'coins_balance'        => $newBalance,
                'lifetime_coins_spent' => $newSpent,
            ]);

            return BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'seller_order_id'     => $sellerOrder->id,
                'reference_code'      => $this->generateReference('CRF'),
                'source_type'         => 'checkout_coin_refund',
                'reward_type'         => 'coins',
                'coins_delta'         => $coinsUsed,
                'spins_delta'         => 0,
                'coins_balance_after' => $newBalance,
                'spins_balance_after' => (int) $locked->spin_tickets,
                'description'         => "Refunded +{$coinsUsed} Storkia Coins from cancelled/returned Order #{$sellerOrder->seller_order_number}",
                'meta_snapshot'       => [
                    'seller_order_number' => $sellerOrder->seller_order_number,
                    'coins_restored'      => $coinsUsed,
                ],
            ]);
        });
    }

    public function awardOrderCompletionCashbackCoins(SellerOrder $sellerOrder): ?BuyerRewardTransaction
    {
        self::ensureTablesExist();

        $buyerId = (int) $sellerOrder->buyer_id;
        if ($buyerId <= 0) {
            return null;
        }

        $subtotal = max(0.0, (float) $sellerOrder->subtotal_amount);
        if ($subtotal <= 0.0) {
            return null;
        }

        $coinsEarned = max(1, (int) floor($subtotal / self::PESOS_PER_CASHBACK_COIN));

        return DB::transaction(function () use ($sellerOrder, $buyerId, $coinsEarned) {
            $alreadyAwarded = BuyerRewardTransaction::where('seller_order_id', $sellerOrder->id)
                ->where('source_type', 'order_completion_cashback')
                ->exists();

            if ($alreadyAwarded) {
                return null;
            }

            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = (int) $locked->coins_balance + $coinsEarned;
            $newLifetime = (int) $locked->lifetime_coins_earned + $coinsEarned;

            $locked->update([
                'coins_balance'         => $newBalance,
                'lifetime_coins_earned' => $newLifetime,
            ]);

            if ($sellerOrder->extra && Schema::hasColumn('seller_order_extras', 'coins_earned_on_completion')) {
                $sellerOrder->extra->update([
                    'coins_earned_on_completion' => $coinsEarned,
                ]);
            }

            return BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'seller_order_id'     => $sellerOrder->id,
                'reference_code'      => $this->generateReference('ORD'),
                'source_type'         => 'order_completion_cashback',
                'reward_type'         => 'coins',
                'coins_delta'         => $coinsEarned,
                'spins_delta'         => 0,
                'coins_balance_after' => $newBalance,
                'spins_balance_after' => (int) $locked->spin_tickets,
                'description'         => "Order Completion Cashback for Order #{$sellerOrder->seller_order_number} (+{$coinsEarned} Storkia Coins)",
                'meta_snapshot'       => [
                    'seller_order_number' => $sellerOrder->seller_order_number,
                    'subtotal_amount'     => (float) $sellerOrder->subtotal_amount,
                    'coins_awarded'       => $coinsEarned,
                ],
            ]);
        });
    }

    public function awardProductReviewCoins(OrderItemReview $review, bool $hasPhoto = false): ?BuyerRewardTransaction
    {
        self::ensureTablesExist();

        $buyerId = (int) $review->buyer_id;
        if ($buyerId <= 0) {
            return null;
        }

        if (Schema::hasColumn('order_item_reviews', 'coins_rewarded') && (int) $review->coins_rewarded > 0) {
            return null;
        }

        $hasText = trim((string) $review->review_text) !== '';
        if (! $hasText && ! $hasPhoto) {
            return null;
        }

        $coinsAwarded = $hasPhoto ? self::PHOTO_REVIEW_REWARD_COINS : self::TEXT_REVIEW_REWARD_COINS;

        return DB::transaction(function () use ($review, $buyerId, $coinsAwarded, $hasPhoto) {
            $lockedReview = OrderItemReview::where('id', $review->id)->lockForUpdate()->first();
            if (! $lockedReview) {
                return null;
            }

            if (Schema::hasColumn('order_item_reviews', 'coins_rewarded') && (int) $lockedReview->coins_rewarded > 0) {
                return null;
            }

            $wallet = $this->getOrCreateWallet($buyerId);
            $lockedWallet = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $newBalance = (int) $lockedWallet->coins_balance + $coinsAwarded;
            $newLifetime = (int) $lockedWallet->lifetime_coins_earned + $coinsAwarded;

            $lockedWallet->update([
                'coins_balance'         => $newBalance,
                'lifetime_coins_earned' => $newLifetime,
            ]);

            if (Schema::hasColumn('order_item_reviews', 'coins_rewarded')) {
                $lockedReview->update([
                    'coins_rewarded' => $coinsAwarded,
                ]);
            }

            $bonusType = $hasPhoto ? 'Photo Product Review Bonus' : 'Verified Product Review Bonus';

            return BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $lockedWallet->id,
                'seller_order_id'     => $lockedReview->seller_order_id,
                'reference_code'      => $this->generateReference('REV'),
                'source_type'         => 'product_review_reward',
                'reward_type'         => 'coins',
                'coins_delta'         => $coinsAwarded,
                'spins_delta'         => 0,
                'coins_balance_after' => $newBalance,
                'spins_balance_after' => (int) $lockedWallet->spin_tickets,
                'description'         => "{$bonusType}: +{$coinsAwarded} Storkia Coins earned!",
                'meta_snapshot'       => [
                    'review_id'     => $lockedReview->id,
                    'order_item_id' => $lockedReview->order_item_id,
                    'product_id'    => $lockedReview->product_id,
                    'has_photo'     => $hasPhoto,
                ],
            ]);
        });
    }

    public function grantDailyLoginSpinIfEligible(int $buyerId): array
    {
        self::ensureTablesExist();

        return DB::transaction(function () use ($buyerId) {
            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canClaimDailyLoginSpin()) {
                return [
                    'granted' => false,
                    'wallet'  => $locked,
                ];
            }

            $newSpins = (int) $locked->spin_tickets + 1;
            $locked->update([
                'spin_tickets'             => $newSpins,
                'last_daily_login_spin_at' => now(),
            ]);

            BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'reference_code'      => $this->generateReference('DLS'),
                'source_type'         => 'daily_login_spin',
                'reward_type'         => 'spins',
                'coins_delta'         => 0,
                'spins_delta'         => 1,
                'coins_balance_after' => (int) $locked->coins_balance,
                'spins_balance_after' => $newSpins,
                'description'         => 'Daily Login Reward: +1 Spin Attempt added to your stack.',
            ]);

            return [
                'granted' => true,
                'wallet'  => $locked->fresh(),
            ];
        });
    }

    public function awardSellerTimeoutCompensation(SellerOrder $sellerOrder): ?BuyerRewardTransaction
    {
        self::ensureTablesExist();

        $buyerId = (int) $sellerOrder->buyer_id;
        if ($buyerId <= 0) {
            return null;
        }

        return DB::transaction(function () use ($sellerOrder, $buyerId) {
            $alreadyAwarded = BuyerRewardTransaction::where('seller_order_id', $sellerOrder->id)
                ->where('source_type', 'sla_timeout_compensation')
                ->exists();

            if ($alreadyAwarded) {
                return null;
            }

            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $coinsAwarded = self::SLA_TIMEOUT_COMPENSATION_COINS;
            $newBalance = (int) $locked->coins_balance + $coinsAwarded;
            $newLifetime = (int) $locked->lifetime_coins_earned + $coinsAwarded;

            $locked->update([
                'coins_balance'         => $newBalance,
                'lifetime_coins_earned' => $newLifetime,
            ]);

            return BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'seller_order_id'     => $sellerOrder->id,
                'reference_code'      => $this->generateReference('SLA'),
                'source_type'         => 'sla_timeout_compensation',
                'reward_type'         => 'coins',
                'coins_delta'         => $coinsAwarded,
                'spins_delta'         => 0,
                'coins_balance_after' => $newBalance,
                'spins_balance_after' => (int) $locked->spin_tickets,
                'description'         => "Seller 24h Response Timeout Compensation for Order #{$sellerOrder->seller_order_number} (+{$coinsAwarded} Storkia Coins)",
                'meta_snapshot'       => [
                    'seller_order_number' => $sellerOrder->seller_order_number,
                    'seller_profile_id'   => $sellerOrder->seller_profile_id,
                ],
            ]);
        });
    }

    public function getWheelSegments(): array
    {
        return [
            [
                'index'      => 0,
                'key'        => 'coins_5',
                'label'      => '5 Coins',
                'sub_label'  => 'Storkia Coins',
                'type'       => 'coins',
                'coins'      => 5,
                'spins'      => 0,
                'weight'     => 28,
                'color'      => '#CF4173',
                'text_color' => '#FFFFFF',
            ],
            [
                'index'      => 1,
                'key'        => 'free_3_spins',
                'label'      => 'Free 3 Spins',
                'sub_label'  => '+3 Spin Attempts',
                'type'       => 'spins',
                'coins'      => 0,
                'spins'      => 3,
                'weight'     => 10,
                'color'      => '#5D3140',
                'text_color' => '#F6D8BD',
            ],
            [
                'index'      => 2,
                'key'        => 'coins_15',
                'label'      => '15 Coins',
                'sub_label'  => 'Storkia Coins',
                'type'       => 'coins',
                'coins'      => 15,
                'spins'      => 0,
                'weight'     => 22,
                'color'      => '#F39399',
                'text_color' => '#5D3140',
            ],
            [
                'index'       => 3,
                'key'         => 'voucher_ship_25',
                'label'       => '₱25 Ship Voucher',
                'sub_label'   => 'Min. Spend ₱150',
                'type'        => 'voucher',
                'voucher_def' => [
                    'scope'          => 'shipping',
                    'discount_type'  => 'fixed',
                    'discount_value' => 25.00,
                    'max_cap'        => 25.00,
                    'min_spend'      => 150.00,
                    'title'          => 'Spin Reward: ₱25 Free Shipping',
                ],
                'coins'      => 0,
                'spins'      => 0,
                'weight'     => 12,
                'color'      => '#F6D8BD',
                'text_color' => '#5D3140',
            ],
            [
                'index'      => 4,
                'key'        => 'coins_30',
                'label'      => '30 Coins',
                'sub_label'  => 'Storkia Coins',
                'type'       => 'coins',
                'coins'      => 30,
                'spins'      => 0,
                'weight'     => 12,
                'color'      => '#CF4173',
                'text_color' => '#FFFFFF',
            ],
            [
                'index'       => 5,
                'key'         => 'voucher_plat_40',
                'label'       => '₱40 Voucher',
                'sub_label'   => 'Min. Spend ₱250',
                'type'        => 'voucher',
                'voucher_def' => [
                    'scope'          => 'platform',
                    'discount_type'  => 'fixed',
                    'discount_value' => 40.00,
                    'max_cap'        => null,
                    'min_spend'      => 250.00,
                    'title'          => 'Spin Reward: ₱40 Platform Discount',
                ],
                'coins'      => 0,
                'spins'      => 0,
                'weight'     => 7,
                'color'      => '#5D3140',
                'text_color' => '#FFFFFF',
            ],
            [
                'index'      => 6,
                'key'        => 'coins_60',
                'label'      => '60 Coins',
                'sub_label'  => 'Jackpot Coins',
                'type'       => 'coins',
                'coins'      => 60,
                'spins'      => 0,
                'weight'     => 5,
                'color'      => '#F39399',
                'text_color' => '#5D3140',
            ],
            [
                'index'       => 7,
                'key'         => 'voucher_plat_pct10',
                'label'       => '10% Off Voucher',
                'sub_label'   => 'Cap ₱80 • Min ₱300',
                'type'        => 'voucher',
                'voucher_def' => [
                    'scope'          => 'platform',
                    'discount_type'  => 'percentage',
                    'discount_value' => 10.00,
                    'max_cap'        => 80.00,
                    'min_spend'      => 300.00,
                    'title'          => 'Spin Reward: 10% Off Platform Voucher',
                ],
                'coins'      => 0,
                'spins'      => 0,
                'weight'     => 4,
                'color'      => '#F6D8BD',
                'text_color' => '#5D3140',
            ],
        ];
    }

    public function getRedeemableVoucherCatalog(): array
    {
        return [
            'ship_30' => [
                'key'            => 'ship_30',
                'coins_cost'     => 40,
                'badge'          => '₱30 SHIPPING OFF',
                'title'          => '₱30 Off Shipping Fee Voucher',
                'description'    => 'Redeemable on any store order with a minimum spend of ₱150.00.',
                'scope'          => 'shipping',
                'discount_type'  => 'fixed',
                'discount_value' => 30.00,
                'max_cap'        => 30.00,
                'min_spend'      => 150.00,
                'valid_days'     => 30,
            ],
            'plat_50' => [
                'key'            => 'plat_50',
                'coins_cost'     => 75,
                'badge'          => '₱50 PLATFORM OFF',
                'title'          => '₱50 Off Storkia Platform Voucher',
                'description'    => 'Usable across all products on Storkia with a minimum spend of ₱300.00.',
                'scope'          => 'platform',
                'discount_type'  => 'fixed',
                'discount_value' => 50.00,
                'max_cap'        => null,
                'min_spend'      => 300.00,
                'valid_days'     => 30,
            ],
            'plat_pct10' => [
                'key'            => 'plat_pct10',
                'coins_cost'     => 110,
                'badge'          => '10% OFF (CAP ₱100)',
                'title'          => '10% Off Platform Discount Voucher',
                'description'    => '10% discount capped at ₱100.00 on orders ₱400.00 and above.',
                'scope'          => 'platform',
                'discount_type'  => 'percentage',
                'discount_value' => 10.00,
                'max_cap'        => 100.00,
                'min_spend'      => 400.00,
                'valid_days'     => 30,
            ],
            'plat_100' => [
                'key'            => 'plat_100',
                'coins_cost'     => 140,
                'badge'          => '₱100 PLATFORM OFF',
                'title'          => '₱100 Off Storkia Platform Voucher',
                'description'    => 'Save ₱100.00 on any product checkout with a minimum spend of ₱600.00.',
                'scope'          => 'platform',
                'discount_type'  => 'fixed',
                'discount_value' => 100.00,
                'max_cap'        => null,
                'min_spend'      => 600.00,
                'valid_days'     => 30,
            ],
            'plat_200' => [
                'key'            => 'plat_200',
                'coins_cost'     => 260,
                'badge'          => '₱200 PLATFORM OFF',
                'title'          => '₱200 Off Mega Platform Voucher',
                'description'    => 'Save ₱200.00 on any product checkout with a minimum spend of ₱1,100.00.',
                'scope'          => 'platform',
                'discount_type'  => 'fixed',
                'discount_value' => 200.00,
                'max_cap'        => null,
                'min_spend'      => 1100.00,
                'valid_days'     => 30,
            ],
        ];
    }

    public function spinWheel(int $buyerId): array
    {
        self::ensureTablesExist();

        $this->grantDailyLoginSpinIfEligible($buyerId);

        return DB::transaction(function () use ($buyerId) {
            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->spin_tickets <= 0) {
                $waitSeconds = $locked->secondsUntilNextDailyLoginSpin();
                $hours = max(1, (int) ceil($waitSeconds / 3600));
                throw ValidationException::withMessages([
                    'spin' => "You have 0 Spin Attempts remaining. Next daily login spin unlocks in ~{$hours} hour(s).",
                ]);
            }

            $segments = $this->getWheelSegments();
            $winningSegment = $this->pickWeightedItem($segments);

            $spinsDelta = -1 + (int) ($winningSegment['spins'] ?? 0);
            $coinsDelta = (int) ($winningSegment['coins'] ?? 0);

            $newSpins = max(0, (int) $locked->spin_tickets + $spinsDelta);
            $newCoins = (int) $locked->coins_balance + $coinsDelta;
            $newLifetimeEarned = (int) $locked->lifetime_coins_earned + max(0, $coinsDelta);

            $issuedVoucher = null;
            if ($winningSegment['type'] === 'voucher' && ! empty($winningSegment['voucher_def'])) {
                $def = $winningSegment['voucher_def'];
                $issuedVoucher = $this->issuePersonalBuyerVoucher(
                    buyerId: $buyerId,
                    prefix: 'SPN',
                    title: $def['title'],
                    scope: $def['scope'],
                    discountType: $def['discount_type'],
                    discountValue: (float) $def['discount_value'],
                    maxCap: $def['max_cap'] !== null ? (float) $def['max_cap'] : null,
                    minSpend: (float) $def['min_spend'],
                    validDays: 14
                );
            }

            $locked->update([
                'coins_balance'         => $newCoins,
                'lifetime_coins_earned' => $newLifetimeEarned,
                'spin_tickets'          => $newSpins,
                'last_spin_played_at'   => now(),
            ]);

            $desc = match ($winningSegment['type']) {
                'spins'   => 'Spin the Wheel: Won Free 3 Spins! (+3 Spin Attempts added)',
                'voucher' => "Spin the Wheel: Won {$winningSegment['label']} (Code: {$issuedVoucher?->code})",
                default   => "Spin the Wheel: Won +{$coinsDelta} Storkia Coins!",
            };

            $tx = BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'voucher_id'          => $issuedVoucher?->id,
                'reference_code'      => $this->generateReference('SPW'),
                'source_type'         => 'spin_wheel',
                'reward_type'         => $winningSegment['type'],
                'coins_delta'         => $coinsDelta,
                'spins_delta'         => $spinsDelta,
                'coins_balance_after' => $newCoins,
                'spins_balance_after' => $newSpins,
                'description'         => $desc,
                'meta_snapshot'       => [
                    'segment_index' => $winningSegment['index'],
                    'segment_key'   => $winningSegment['key'],
                    'segment_label' => $winningSegment['label'],
                    'voucher_code'  => $issuedVoucher?->code,
                ],
            ]);

            return [
                'segment'             => $winningSegment,
                'segment_index'       => (int) $winningSegment['index'],
                'coins_won'           => $coinsDelta,
                'spins_won'           => (int) ($winningSegment['spins'] ?? 0),
                'voucher'             => $issuedVoucher ? [
                    'id'          => $issuedVoucher->id,
                    'code'        => $issuedVoucher->code,
                    'title'       => $issuedVoucher->title,
                    'badge_label' => $issuedVoucher->badge_label,
                    'min_spend'   => number_format((float) $issuedVoucher->min_spend, 2),
                    'ends_at'     => $issuedVoucher->ends_at?->format('M d, Y'),
                ] : null,
                'coins_balance'       => $newCoins,
                'spin_tickets'        => $newSpins,
                'next_daily_spin_sec' => $locked->secondsUntilNextDailyLoginSpin(),
                'message'             => $desc,
                'transaction_id'      => $tx->id,
            ];
        });
    }

    public function openDailyChest(int $buyerId): array
    {
        self::ensureTablesExist();

        return DB::transaction(function () use ($buyerId) {
            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canOpenDailyChest()) {
                throw ValidationException::withMessages([
                    'chest' => 'Your Daily Treasure Chest is cooling down. Please wait for the 24-hour timer to finish.',
                ]);
            }

            $chestStages = [
                [
                    'stage'         => 1,
                    'label'         => 'Stage 1 Coin Pouch',
                    'coins_options' => [10, 12, 15],
                    'weight'        => 68,
                    'reward_image'  => asset('game-assets/chest-for-reward-stage-1.png'),
                ],
                [
                    'stage'         => 2,
                    'label'         => 'Stage 2 Treasure Stack',
                    'coins_options' => [25, 30, 35],
                    'weight'        => 25,
                    'reward_image'  => asset('game-assets/chest-for-reward-stage-2.png'),
                ],
                [
                    'stage'         => 3,
                    'label'         => 'Stage 3 Grand Jackpot Hoard',
                    'coins_options' => [55, 65, 80],
                    'weight'        => 7,
                    'reward_image'  => asset('game-assets/chest-for-reward-stage-3.png'),
                ],
            ];

            $pickedStage = $this->pickWeightedItem($chestStages);
            $options = $pickedStage['coins_options'];
            $coinsWon = (int) $options[array_rand($options)];
            $stageNumber = (int) $pickedStage['stage'];

            $newCoins = (int) $locked->coins_balance + $coinsWon;
            $newLifetime = (int) $locked->lifetime_coins_earned + $coinsWon;

            $locked->update([
                'coins_balance'         => $newCoins,
                'lifetime_coins_earned' => $newLifetime,
                'last_chest_opened_at'  => now(),
            ]);

            $desc = "Daily Treasure Chest (Stage {$stageNumber}): Unlocked +{$coinsWon} Storkia Coins!";

            BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'reference_code'      => $this->generateReference('CHS'),
                'source_type'         => 'daily_chest',
                'reward_type'         => 'coins',
                'coins_delta'         => $coinsWon,
                'spins_delta'         => 0,
                'coins_balance_after' => $newCoins,
                'spins_balance_after' => (int) $locked->spin_tickets,
                'stage_level'         => $stageNumber,
                'description'         => $desc,
                'meta_snapshot'       => [
                    'stage'        => $stageNumber,
                    'stage_label'  => $pickedStage['label'],
                    'reward_image' => $pickedStage['reward_image'],
                ],
            ]);

            return [
                'stage'          => $stageNumber,
                'stage_label'    => $pickedStage['label'],
                'coins_won'      => $coinsWon,
                'reward_image'   => $pickedStage['reward_image'],
                'coins_balance'  => $newCoins,
                'cooldown_sec'   => $locked->fresh()->secondsUntilNextDailyChest(),
                'next_available' => $locked->fresh()->last_chest_opened_at?->copy()->addHours(BuyerRewardWallet::COOLDOWN_HOURS)->toIso8601String(),
                'message'        => $desc,
            ];
        });
    }

    public function redeemCoinsForVoucher(int $buyerId, string $catalogKey): array
    {
        self::ensureTablesExist();

        $catalog = $this->getRedeemableVoucherCatalog();
        if (! isset($catalog[$catalogKey])) {
            throw ValidationException::withMessages([
                'voucher' => 'Selected reward voucher package does not exist.',
            ]);
        }

        $item = $catalog[$catalogKey];
        $cost = (int) $item['coins_cost'];

        return DB::transaction(function () use ($buyerId, $item, $cost) {
            $wallet = $this->getOrCreateWallet($buyerId);
            $locked = BuyerRewardWallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->coins_balance < $cost) {
                $shortfall = $cost - (int) $locked->coins_balance;
                throw ValidationException::withMessages([
                    'coins' => "Insufficient Storkia Coins. You need {$shortfall} more coin(s) to redeem \"{$item['title']}\".",
                ]);
            }

            $voucher = $this->issuePersonalBuyerVoucher(
                buyerId: $buyerId,
                prefix: 'COIN',
                title: $item['title'],
                scope: $item['scope'],
                discountType: $item['discount_type'],
                discountValue: (float) $item['discount_value'],
                maxCap: $item['max_cap'] !== null ? (float) $item['max_cap'] : null,
                minSpend: (float) $item['min_spend'],
                validDays: (int) ($item['valid_days'] ?? 30)
            );

            $newCoins = (int) $locked->coins_balance - $cost;
            $newSpent = (int) $locked->lifetime_coins_spent + $cost;

            $locked->update([
                'coins_balance'        => $newCoins,
                'lifetime_coins_spent' => $newSpent,
            ]);

            BuyerRewardTransaction::create([
                'buyer_id'            => $buyerId,
                'reward_wallet_id'    => $locked->id,
                'voucher_id'          => $voucher->id,
                'reference_code'      => $this->generateReference('RDM'),
                'source_type'         => 'voucher_redemption',
                'reward_type'         => 'voucher',
                'coins_delta'         => -$cost,
                'spins_delta'         => 0,
                'coins_balance_after' => $newCoins,
                'spins_balance_after' => (int) $locked->spin_tickets,
                'description'         => "Redeemed {$cost} Storkia Coins for {$item['title']} (Code: {$voucher->code})",
                'meta_snapshot'       => [
                    'catalog_key'  => $item['key'],
                    'voucher_code' => $voucher->code,
                    'coins_spent'  => $cost,
                ],
            ]);

            return [
                'voucher'       => $voucher,
                'coins_balance' => $newCoins,
                'coins_spent'   => $cost,
                'message'       => "Successfully redeemed \"{$item['title']}\" (Code: {$voucher->code})! It is now in your Voucher Wallet and ready for checkout.",
            ];
        });
    }

    protected function issuePersonalBuyerVoucher(
        int $buyerId,
        string $prefix,
        string $title,
        string $scope,
        string $discountType,
        float $discountValue,
        ?float $maxCap,
        float $minSpend,
        int $validDays = 30
    ): StorkVoucher {
        do {
            $code = strtoupper($prefix . substr(preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::random(7))), 0, 7));
        } while (StorkVoucher::where('code', $code)->exists());

        $desc = $discountType === 'percentage'
            ? rtrim(rtrim(number_format($discountValue, 2), '0'), '.') . '% off' . ($maxCap ? ' (Cap ₱' . number_format($maxCap, 0) . ')' : '') . ', Min. spend ₱' . number_format($minSpend, 2)
            : '₱' . number_format($discountValue, 2) . ' off ' . ($scope === 'shipping' ? 'shipping fees' : 'products') . ', Min. spend ₱' . number_format($minSpend, 2);

        $voucher = StorkVoucher::create([
            'seller_profile_id' => null,
            'created_by'        => $buyerId,
            'code'              => $code,
            'title'             => $title,
            'description'       => $desc,
            'scope'             => $scope,
            'discount_type'     => $discountType,
            'discount_value'    => $discountValue,
            'max_discount_cap'  => $maxCap,
            'min_spend'         => $minSpend,
            'total_usage_limit' => 1,
            'used_count'        => 0,
            'per_buyer_limit'   => 1,
            'visibility'        => 'reward_only',
            'starts_at'         => now()->subMinute(),
            'ends_at'           => now()->addDays($validDays),
            'status'            => 'active',
        ]);

        BuyerVoucherClaim::create([
            'voucher_id' => $voucher->id,
            'buyer_id'   => $buyerId,
            'times_used' => 0,
            'claimed_at' => now(),
        ]);

        return $voucher;
    }

    protected function pickWeightedItem(array $items): array
    {
        $totalWeight = array_sum(array_column($items, 'weight'));
        $rand = random_int(1, max(1, $totalWeight));
        $cumulative = 0;

        foreach ($items as $item) {
            $cumulative += (int) $item['weight'];
            if ($rand <= $cumulative) {
                return $item;
            }
        }

        return $items[0];
    }

    protected function generateReference(string $prefix): string
    {
        do {
            $ref = strtoupper($prefix) . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (BuyerRewardTransaction::where('reference_code', $ref)->exists());

        return $ref;
    }
}