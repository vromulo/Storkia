<?php

namespace App\Services;

use App\Models\StorkVoucher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Picks the "Most Preferred" voucher for each checkout slot (one store voucher
 * per seller package, one platform voucher, one free-shipping voucher).
 *
 * "Most preferred" = the eligible voucher that saves the buyer the most pesos on
 * the current order. A voucher is only considered when it actually applies right
 * now (minimum spend met, product scope matched, not expired, within usage limits),
 * so vouchers the order does not qualify for are never recommended.
 *
 * Ties are broken by the soonest expiry, then by the lowest voucher id.
 */
class CheckoutVoucherRecommender
{
    public function __construct(protected VoucherService $voucherService) {}

    /**
     * @param  Collection  $availableVouchers  Result of VoucherService::getBuyerAvailableVouchers()
     * @param  array       $preview            Result of CheckoutController::buildGroupedSellerPreview()
     * @param  array       $touchedSlots       Slots the buyer already chose manually (store_{sellerProfileId}, platform, shipping)
     * @return array{store: array<int, string>, platform: string, shipping: string}
     */
    public function recommend(Collection $availableVouchers, array $preview, int $buyerId, array $touchedSlots = []): array
    {
        $packages = (array) ($preview['seller_packages'] ?? []);
        $overallSubtotal = round((float) ($preview['overall_subtotal'] ?? 0), 2);
        $overallShipping = round((float) ($preview['overall_shipping'] ?? 0), 2);

        $bestStore = [];
        $subtotalAfterStoreVouchers = 0.0;

        foreach ($packages as $pkg) {
            $sellerProfileId = (int) $pkg['seller_profile']->id;
            $subtotalAfterBundle = max(0.0, round((float) $pkg['subtotal'] - (float) $pkg['bundle_discount'], 2));

            $best = $this->pickBest(
                $availableVouchers->where('scope', 'store')->where('seller_profile_id', $sellerProfileId),
                fn (StorkVoucher $voucher) => $this->voucherService->calculateStoreVoucherDiscount(
                    voucher: $voucher,
                    buyerId: $buyerId,
                    sellerProfileId: $sellerProfileId,
                    packageItems: (array) $pkg['items'],
                    packageSubtotal: $subtotalAfterBundle
                )
            );

            if ($best !== null) {
                $bestStore[$sellerProfileId] = $best['code'];
            }

            // The platform discount is capped by what is left after store vouchers, so use the
            // store discount that will really be in effect: the buyer's own pick when the slot
            // was touched, otherwise the recommended one.
            $effectiveStoreDiscount = in_array('store_' . $sellerProfileId, $touchedSlots, true)
                ? (float) ($pkg['store_discount'] ?? 0)
                : (float) ($best['discount'] ?? 0);

            $subtotalAfterStoreVouchers += max(0.0, round($subtotalAfterBundle - $effectiveStoreDiscount, 2));
        }

        $subtotalAfterStoreVouchers = round($subtotalAfterStoreVouchers, 2);

        $bestPlatform = $this->pickBest(
            $availableVouchers->where('scope', 'platform'),
            fn (StorkVoucher $voucher) => min(
                $subtotalAfterStoreVouchers,
                $this->voucherService->calculatePlatformOrShippingDiscount($voucher, $buyerId, $overallSubtotal, $overallShipping)
            )
        );

        $bestShipping = $this->pickBest(
            $availableVouchers->where('scope', 'shipping'),
            fn (StorkVoucher $voucher) => $this->voucherService->calculatePlatformOrShippingDiscount(
                $voucher,
                $buyerId,
                $overallSubtotal,
                $overallShipping
            )
        );

        return [
            'store'    => $bestStore,
            'platform' => $bestPlatform['code'] ?? '',
            'shipping' => $bestShipping['code'] ?? '',
        ];
    }

    /**
     * @param  iterable<StorkVoucher>  $candidates
     * @param  callable(StorkVoucher): float  $calculateDiscount  Throws ValidationException when the voucher does not apply.
     * @return array{code: string, discount: float}|null
     */
    protected function pickBest(iterable $candidates, callable $calculateDiscount): ?array
    {
        $ranked = [];

        foreach ($candidates as $voucher) {
            try {
                $discount = round((float) $calculateDiscount($voucher), 2);
            } catch (ValidationException) {
                continue;
            }

            if ($discount <= 0.0) {
                continue;
            }

            $ranked[] = ['voucher' => $voucher, 'discount' => $discount];
        }

        if ($ranked === []) {
            return null;
        }

        usort($ranked, function (array $a, array $b): int {
            if (abs($a['discount'] - $b['discount']) > 0.0001) {
                return $b['discount'] <=> $a['discount'];
            }

            $aEnds = $a['voucher']->ends_at?->getTimestamp() ?? PHP_INT_MAX;
            $bEnds = $b['voucher']->ends_at?->getTimestamp() ?? PHP_INT_MAX;
            if ($aEnds !== $bEnds) {
                return $aEnds <=> $bEnds;
            }

            return (int) $a['voucher']->id <=> (int) $b['voucher']->id;
        });

        return [
            'code'     => (string) $ranked[0]['voucher']->code,
            'discount' => $ranked[0]['discount'],
        ];
    }
}