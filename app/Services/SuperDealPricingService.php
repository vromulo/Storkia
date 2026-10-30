<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SuperDealPricingService
{
    public function expireEndedPromotions(): void
    {
        if (Schema::hasTable('super_deals')) {
            DB::table('super_deals')
                ->where('status', 'active')
                ->where('ends_at', '<', now())
                ->update([
                    'status'     => 'ended',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('bundle_deals')) {
            DB::table('bundle_deals')
                ->where('status', 'active')
                ->where('ends_at', '<', now())
                ->update([
                    'status'     => 'ended',
                    'updated_at' => now(),
                ]);
        }
    }

    public function assertStoreNotOnVacation(?SellerProfile $sellerProfile): void
    {
        if ($sellerProfile && Schema::hasColumn('seller_profiles', 'is_on_vacation') && $sellerProfile->is_on_vacation) {
            throw ValidationException::withMessages([
                'vacation' => "Store \"{$sellerProfile->business_name}\" is currently on vacation and cannot accept new orders right now.",
            ]);
        }
    }

    public function getActiveSuperDeal(int $productId): ?object
    {
        if (! Schema::hasTable('super_deals')) {
            return null;
        }

        $this->expireEndedPromotions();

        $deal = DB::table('super_deals')
            ->where('product_id', $productId)
            ->where('deal_type', 'super_deal')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->first();

        if (! $deal) {
            return null;
        }

        if ($deal->flash_quantity !== null && (int) $deal->sold_quantity >= (int) $deal->flash_quantity) {
            return null;
        }

        return $deal;
    }

    public function getActiveStoreSale(int $productId): ?object
    {
        if (! Schema::hasTable('super_deals')) {
            return null;
        }

        $this->expireEndedPromotions();

        return DB::table('super_deals')
            ->where('product_id', $productId)
            ->where('deal_type', 'store_sale')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at', 'asc')
            ->first();
    }

    public function resolveEffectiveStoreSalePrice(Product $product, float $baseUnitPrice): float
    {
        $storeSale = $this->getActiveStoreSale((int) $product->id);

        if ($storeSale) {
            if ($storeSale->deal_price !== null && (float) $storeSale->deal_price > 0) {
                return min($baseUnitPrice, round((float) $storeSale->deal_price, 2));
            }
            if ($storeSale->discount_percentage !== null && (float) $storeSale->discount_percentage > 0) {
                return round($baseUnitPrice * (1 - ((float) $storeSale->discount_percentage / 100)), 2);
            }
        }

        $productDiscount = (float) ($product->discount ?? 0);
        if ($productDiscount > 0) {
            return round($baseUnitPrice * (1 - ($productDiscount / 100)), 2);
        }

        return round($baseUnitPrice, 2);
    }

    public function buildSplitCheckoutLines(
        Product $product,
        int $requestedQty,
        float $baseUnitPrice,
        float $unitWeight,
        ?string $variantName = null,
        ?string $subVariantName = null,
        ?string $imagePath = null
    ): array {
        $storeSaleUnitPrice = $this->resolveEffectiveStoreSalePrice($product, $baseUnitPrice);
        $superDeal = $this->getActiveSuperDeal((int) $product->id);

        if (! $superDeal) {
            return [[
                'product'               => $product,
                'product_id'            => $product->id,
                'variant_name'          => $variantName,
                'sub_variant_name'      => $subVariantName,
                'image_path'            => $imagePath,
                'quantity'              => $requestedQty,
                'original_unit_price'   => round($baseUnitPrice, 2),
                'discounted_unit_price' => $storeSaleUnitPrice,
                'line_total'            => round($storeSaleUnitPrice * $requestedQty, 2),
                'unit_weight'           => $unitWeight,
                'line_weight'           => round($unitWeight * $requestedQty, 3),
                'super_deal_id'         => null,
                'pricing_label'         => $storeSaleUnitPrice < $baseUnitPrice ? 'Store Sale' : 'Regular',
            ]];
        }

        $superDealPrice = $superDeal->deal_price !== null && (float) $superDeal->deal_price > 0
            ? round((float) $superDeal->deal_price, 2)
            : round($baseUnitPrice * (1 - ((float) ($superDeal->discount_percentage ?? 0) / 100)), 2);

        $remainingSuperQty = $superDeal->flash_quantity !== null
            ? max(0, (int) $superDeal->flash_quantity - (int) $superDeal->sold_quantity)
            : $requestedQty;

        $superUnits = min($requestedQty, $remainingSuperQty);
        $overflowUnits = max(0, $requestedQty - $superUnits);

        $lines = [];

        if ($superUnits > 0) {
            $lines[] = [
                'product'               => $product,
                'product_id'            => $product->id,
                'variant_name'          => $variantName,
                'sub_variant_name'      => $subVariantName,
                'image_path'            => $imagePath,
                'quantity'              => $superUnits,
                'original_unit_price'   => round($baseUnitPrice, 2),
                'discounted_unit_price' => $superDealPrice,
                'line_total'            => round($superDealPrice * $superUnits, 2),
                'unit_weight'           => $unitWeight,
                'line_weight'           => round($unitWeight * $superUnits, 3),
                'super_deal_id'         => (int) $superDeal->id,
                'pricing_label'         => 'Super Deal',
            ];
        }

        // Units beyond the remaining Super Deal quantity are charged on a separate line at the Store Sale price (or Regular price)
        if ($overflowUnits > 0) {
            $lines[] = [
                'product'               => $product,
                'product_id'            => $product->id,
                'variant_name'          => $variantName,
                'sub_variant_name'      => $subVariantName,
                'image_path'            => $imagePath,
                'quantity'              => $overflowUnits,
                'original_unit_price'   => round($baseUnitPrice, 2),
                'discounted_unit_price' => $storeSaleUnitPrice,
                'line_total'            => round($storeSaleUnitPrice * $overflowUnits, 2),
                'unit_weight'           => $unitWeight,
                'line_weight'           => round($unitWeight * $overflowUnits, 3),
                'super_deal_id'         => null,
                'pricing_label'         => $storeSaleUnitPrice < $baseUnitPrice ? 'Store Sale' : 'Regular Price',
            ];
        }

        return $lines;
    }

    public function calculateStoreBundleDiscount(int $sellerProfileId, array $packageLines): array
    {
        if (! Schema::hasTable('bundle_deals')) {
            return [
                'bundle_discount' => 0.00,
                'bundle_title'    => null,
            ];
        }

        $this->expireEndedPromotions();

        $activeBundles = DB::table('bundle_deals')
            ->where('seller_profile_id', $sellerProfileId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get();

        foreach ($activeBundles as $bundle) {
            $applicableIds = json_decode((string) $bundle->applicable_product_ids, true) ?: [];
            $applicableIds = array_map('intval', $applicableIds);

            $qualifyingQty = 0;
            $qualifyingSubtotal = 0.00;

            foreach ($packageLines as $line) {
                $prodId = (int) ($line['product_id'] ?? ($line['product']->id ?? 0));
                if (in_array($prodId, $applicableIds, true)) {
                    $qualifyingQty += (int) $line['quantity'];
                    $qualifyingSubtotal = round($qualifyingSubtotal + (float) $line['line_total'], 2);
                }
            }

            if ($bundle->bundle_type === 'percentage_off' && $qualifyingQty >= (int) $bundle->required_quantity) {
                $pct = (float) ($bundle->discount_percentage ?? 0);
                $disc = round($qualifyingSubtotal * ($pct / 100), 2);
                if ($disc > 0) {
                    return [
                        'bundle_discount' => $disc,
                        'bundle_title'    => $bundle->title,
                    ];
                }
            }

            if ($bundle->bundle_type === 'buy_x_get_y' && $qualifyingQty >= (int) $bundle->required_quantity && $bundle->free_gift_product_id) {
                foreach ($packageLines as $line) {
                    $prodId = (int) ($line['product_id'] ?? ($line['product']->id ?? 0));
                    if ($prodId === (int) $bundle->free_gift_product_id) {
                        $freeAmount = round((float) $line['discounted_unit_price'], 2);
                        return [
                            'bundle_discount' => $freeAmount,
                            'bundle_title'    => $bundle->title . ' (Free Gift Applied)',
                        ];
                    }
                }
            }
        }

        return [
            'bundle_discount' => 0.00,
            'bundle_title'    => null,
        ];
    }
}