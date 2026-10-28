<?php

namespace App\Services;

use App\Models\BuyerVoucherClaim;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\StorkVoucher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function normalizeCode(?string $rawCode): string
    {
        return strtoupper(trim((string) $rawCode));
    }

    public function claimVoucherByCode(int $buyerId, string $rawCode): StorkVoucher
    {
        $code = $this->normalizeCode($rawCode);
        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Please enter a valid voucher code.',
            ]);
        }

        /** @var StorkVoucher|null $voucher */
        $voucher = StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();

        if (! $voucher) {
            throw ValidationException::withMessages([
                'code' => "Voucher code \"{$code}\" is invalid or does not exist.",
            ]);
        }

        $this->assertVoucherActiveAndAvailable($voucher, $buyerId);

        $existingClaim = BuyerVoucherClaim::where('voucher_id', $voucher->id)
            ->where('buyer_id', $buyerId)
            ->first();

        if ($existingClaim) {
            throw ValidationException::withMessages([
                'code' => "You have already claimed voucher \"{$voucher->code}\" into your wallet.",
            ]);
        }

        BuyerVoucherClaim::create([
            'voucher_id' => $voucher->id,
            'buyer_id'   => $buyerId,
            'times_used' => 0,
            'claimed_at' => now(),
        ]);

        return $voucher;
    }

    public function assertVoucherActiveAndAvailable(StorkVoucher $voucher, int $buyerId): void
    {
        if ($voucher->status !== 'active') {
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" has ended and is no longer valid.",
            ]);
        }

        if ($voucher->starts_at && now()->lt($voucher->starts_at)) {
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" has not started yet (Starts {$voucher->starts_at->format('M d, Y h:i A')}).",
            ]);
        }

        if ($voucher->ends_at && now()->gt($voucher->ends_at)) {
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" has already expired.",
            ]);
        }

        if ((int) $voucher->used_count >= (int) $voucher->total_usage_limit) {
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" has reached its maximum redemption limit.",
            ]);
        }

        $claim = BuyerVoucherClaim::where('voucher_id', $voucher->id)
            ->where('buyer_id', $buyerId)
            ->first();

        if ($claim && (int) $claim->times_used >= (int) $voucher->per_buyer_limit) {
            throw ValidationException::withMessages([
                'voucher' => "You have already used voucher \"{$voucher->code}\" the maximum number of times ({$voucher->per_buyer_limit} per buyer).",
            ]);
        }
    }

    public function calculateStoreVoucherDiscount(
        StorkVoucher $voucher,
        int $buyerId,
        int $sellerProfileId,
        array $packageItems,
        float $packageSubtotal
    ): float {
        $this->assertVoucherActiveAndAvailable($voucher, $buyerId);

        if ($voucher->scope !== 'store' || (int) $voucher->seller_profile_id !== $sellerProfileId) {
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" does not belong to this store.",
            ]);
        }

        $eligibleSubtotal = $packageSubtotal;
        $scopedProductIds = is_array($voucher->applicable_product_ids)
            ? array_values(array_filter(array_map('intval', $voucher->applicable_product_ids)))
            : [];

        if (! empty($scopedProductIds)) {
            $eligibleSubtotal = 0.0;
            foreach ($packageItems as $line) {
                $prodId = (int) ($line['product']->id ?? ($line['product_id'] ?? 0));
                if (in_array($prodId, $scopedProductIds, true)) {
                    $eligibleSubtotal = round($eligibleSubtotal + (float) $line['line_total'], 2);
                }
            }

            if ($eligibleSubtotal <= 0.0) {
                throw ValidationException::withMessages([
                    'voucher' => "Voucher \"{$voucher->code}\" is only applicable to specific products not in this store package.",
                ]);
            }
        }

        $minSpend = round((float) $voucher->min_spend, 2);
        if ($eligibleSubtotal + 0.0001 < $minSpend) {
            $shortfall = round($minSpend - $eligibleSubtotal, 2);
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" requires a minimum spend of ₱" . number_format($minSpend, 2) . " (Spend ₱" . number_format($shortfall, 2) . " more).",
            ]);
        }

        if ($voucher->discount_type === 'percentage') {
            $rawDiscount = round($eligibleSubtotal * (((float) $voucher->discount_value) / 100), 2);
            if ($voucher->max_discount_cap !== null && (float) $voucher->max_discount_cap > 0) {
                $rawDiscount = min($rawDiscount, round((float) $voucher->max_discount_cap, 2));
            }
        } else {
            $rawDiscount = round((float) $voucher->discount_value, 2);
        }

        return min($eligibleSubtotal, max(0.0, $rawDiscount));
    }

    public function calculatePlatformOrShippingDiscount(
        StorkVoucher $voucher,
        int $buyerId,
        float $overallMerchandiseSubtotal,
        float $overallShippingTotal
    ): float {
        $this->assertVoucherActiveAndAvailable($voucher, $buyerId);

        $minSpend = round((float) $voucher->min_spend, 2);
        if ($overallMerchandiseSubtotal + 0.0001 < $minSpend) {
            $shortfall = round($minSpend - $overallMerchandiseSubtotal, 2);
            throw ValidationException::withMessages([
                'voucher' => "Voucher \"{$voucher->code}\" requires a minimum spend of ₱" . number_format($minSpend, 2) . " (Spend ₱" . number_format($shortfall, 2) . " more).",
            ]);
        }

        if ($voucher->scope === 'shipping') {
            $cap = $voucher->max_discount_cap !== null && (float) $voucher->max_discount_cap > 0
                ? round((float) $voucher->max_discount_cap, 2)
                : round((float) $voucher->discount_value, 2);

            return min($overallShippingTotal, max(0.0, $cap));
        }

        if ($voucher->scope === 'platform') {
            if ($voucher->discount_type === 'percentage') {
                $raw = round($overallMerchandiseSubtotal * (((float) $voucher->discount_value) / 100), 2);
                if ($voucher->max_discount_cap !== null && (float) $voucher->max_discount_cap > 0) {
                    $raw = min($raw, round((float) $voucher->max_discount_cap, 2));
                }
            } else {
                $raw = round((float) $voucher->discount_value, 2);
            }

            return min($overallMerchandiseSubtotal, max(0.0, $raw));
        }

        return 0.0;
    }

    public function consumeVoucher(StorkVoucher $voucher, int $buyerId, int $sellerOrderId): void
    {
        $locked = StorkVoucher::where('id', $voucher->id)->lockForUpdate()->firstOrFail();

        $this->assertVoucherActiveAndAvailable($locked, $buyerId);

        $locked->increment('used_count');

        $claim = BuyerVoucherClaim::where('voucher_id', $locked->id)
            ->where('buyer_id', $buyerId)
            ->lockForUpdate()
            ->first();

        if ($claim) {
            $claim->update([
                'times_used'           => (int) $claim->times_used + 1,
                'last_seller_order_id' => $sellerOrderId,
                'last_used_at'         => now(),
            ]);
        } else {
            BuyerVoucherClaim::create([
                'voucher_id'           => $locked->id,
                'buyer_id'             => $buyerId,
                'times_used'           => 1,
                'last_seller_order_id' => $sellerOrderId,
                'claimed_at'           => now(),
                'last_used_at'         => now(),
            ]);
        }
    }

    public function restoreVouchersForSellerOrder(SellerOrder $sellerOrder): void
    {
        $extra = SellerOrderExtra::where('seller_order_id', $sellerOrder->id)->first();
        if (! $extra) {
            return;
        }

        $voucherIds = array_values(array_unique(array_filter([
            $extra->store_voucher_id,
            $extra->platform_voucher_id,
            $extra->shipping_voucher_id,
        ])));

        foreach ($voucherIds as $vId) {
            $voucher = StorkVoucher::where('id', $vId)->lockForUpdate()->first();
            if ($voucher && (int) $voucher->used_count > 0) {
                $voucher->decrement('used_count');
            }

            $claim = BuyerVoucherClaim::where('voucher_id', $vId)
                ->where('buyer_id', $sellerOrder->buyer_id)
                ->lockForUpdate()
                ->first();

            if ($claim && (int) $claim->times_used > 0) {
                $claim->decrement('times_used');
            }
        }
    }

    public function getBuyerAvailableVouchers(int $buyerId): Collection
    {
        return StorkVoucher::with('sellerProfile')
            ->where('status', 'active')
            ->where('ends_at', '>=', now())
            ->where(function ($q) use ($buyerId) {
                $q->where('visibility', 'claimable')
                  ->orWhereHas('claims', fn ($cq) => $cq->where('buyer_id', $buyerId));
            })
            ->orderBy('scope')
            ->orderBy('min_spend')
            ->get();
    }
}