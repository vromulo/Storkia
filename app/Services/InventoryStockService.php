<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellerOrder;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function parseWeightToKg(mixed $rawWeight): float
    {
        if (is_numeric($rawWeight)) {
            $val = (float) $rawWeight;
            return $val > 0 ? round($val, 3) : 0.100;
        }

        $str = strtolower(trim((string) $rawWeight));
        if ($str === '') {
            return 0.100;
        }

        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(kg|kilogram|kilograms|g|gram|grams)?/', $str, $matches)) {
            $num = (float) $matches[1];
            $unit = $matches[2] ?? 'kg';

            if (in_array($unit, ['g', 'gram', 'grams'], true)) {
                return max(0.001, round($num / 1000, 3));
            }

            return max(0.001, round($num, 3));
        }

        return 0.100;
    }

    public function resolveProductSelection(Product $product, ?string $variantName, ?string $subVariantName): array
    {
        $isApproved = $product->relationLoaded('approval')
            ? ($product->approval?->status === 'Approved')
            : $product->approval()->where('status', 'Approved')->exists();

        if (! $isApproved || $product->trashed()) {
            throw ValidationException::withMessages([
                'cart' => "Product \"{$product->name}\" is no longer available for purchase.",
            ]);
        }

        $rawVariants = $product->raw_variants;
        $defaultImage = (! empty($product->pictures) && is_array($product->pictures))
            ? $product->pictures[0]
            : null;
        $discountPercent = (float) ($product->discount ?? 0);

        if (! is_array($rawVariants) || empty($rawVariants['items']) || ! is_array($rawVariants['items'])) {
            $unitPrice = (float) ($product->price ?? 0);
            $discountedPrice = $discountPercent > 0
                ? round($unitPrice - ($unitPrice * ($discountPercent / 100)), 2)
                : round($unitPrice, 2);

            return [
                'variant_id'            => null,
                'variant_name'          => null,
                'sub_variant_id'        => null,
                'sub_variant_name'      => null,
                'unit_price'            => round($unitPrice, 2),
                'discount_percent'      => $discountPercent,
                'discounted_unit_price' => $discountedPrice,
                'unit_weight'           => $this->parseWeightToKg($product->weight ?? 0.2),
                'available_stock'       => (int) ($product->stock_quantity ?? 0),
                'image_path'            => $defaultImage,
            ];
        }

        $priceDependency = $rawVariants['price_dependency'] ?? 'main';
        $normVariant = strtolower(trim((string) $variantName));
        $normSub = strtolower(trim((string) $subVariantName));

        if ($normVariant === '') {
            throw ValidationException::withMessages([
                'cart' => "Please select a variant for \"{$product->name}\".",
            ]);
        }

        foreach ($rawVariants['items'] as $vItem) {
            if (! empty($vItem['is_archived']) || ($vItem['status'] ?? 'Approved') !== 'Approved') {
                continue;
            }

            if (strtolower(trim((string) ($vItem['name'] ?? ''))) !== $normVariant) {
                continue;
            }

            $variantImage = ! empty($vItem['image']) ? $vItem['image'] : $defaultImage;
            $hasSubs = ! empty($vItem['subs']) && is_array($vItem['subs']);

            if ($priceDependency === 'sub' || ($hasSubs && $normSub !== '')) {
                if ($normSub === '') {
                    throw ValidationException::withMessages([
                        'cart' => "Please select a sub-variant for \"{$product->name} ({$vItem['name']})\".",
                    ]);
                }

                foreach ($vItem['subs'] as $sItem) {
                    if (! empty($sItem['is_archived']) || ($sItem['status'] ?? 'Approved') !== 'Approved') {
                        continue;
                    }

                    if (strtolower(trim((string) ($sItem['name'] ?? ''))) !== $normSub) {
                        continue;
                    }

                    $unitPrice = (float) ($sItem['price'] ?? $vItem['price'] ?? $product->price ?? 0);
                    $discountedPrice = $discountPercent > 0
                        ? round($unitPrice - ($unitPrice * ($discountPercent / 100)), 2)
                        : round($unitPrice, 2);

                    return [
                        'variant_id'            => $vItem['id'] ?? null,
                        'variant_name'          => $vItem['name'],
                        'sub_variant_id'        => $sItem['id'] ?? null,
                        'sub_variant_name'      => $sItem['name'],
                        'unit_price'            => round($unitPrice, 2),
                        'discount_percent'      => $discountPercent,
                        'discounted_unit_price' => $discountedPrice,
                        'unit_weight'           => $this->parseWeightToKg($sItem['weight'] ?? $vItem['weight'] ?? $product->weight ?? 0.2),
                        'available_stock'       => (int) ($sItem['stock'] ?? 0),
                        'image_path'            => ! empty($sItem['image']) ? $sItem['image'] : $variantImage,
                    ];
                }

                throw ValidationException::withMessages([
                    'cart' => "Selected option \"{$subVariantName}\" for \"{$product->name}\" is no longer available.",
                ]);
            }

            $unitPrice = (float) ($vItem['price'] ?? $product->price ?? 0);
            $discountedPrice = $discountPercent > 0
                ? round($unitPrice - ($unitPrice * ($discountPercent / 100)), 2)
                : round($unitPrice, 2);

            return [
                'variant_id'            => $vItem['id'] ?? null,
                'variant_name'          => $vItem['name'],
                'sub_variant_id'        => null,
                'sub_variant_name'      => null,
                'unit_price'            => round($unitPrice, 2),
                'discount_percent'      => $discountPercent,
                'discounted_unit_price' => $discountedPrice,
                'unit_weight'           => $this->parseWeightToKg($vItem['weight'] ?? $product->weight ?? 0.2),
                'available_stock'       => (int) ($vItem['stock'] ?? 0),
                'image_path'            => $variantImage,
            ];
        }

        throw ValidationException::withMessages([
            'cart' => "Selected variant \"{$variantName}\" for \"{$product->name}\" is no longer available.",
        ]);
    }

    public function deductStock(int $productId, ?string $variantName, ?string $subVariantName, int $quantity): void
    {
        /** @var Product $product */
        $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

        $rawVariants = $product->raw_variants;

        if (! is_array($rawVariants) || empty($rawVariants['items']) || ! is_array($rawVariants['items'])) {
            if ((int) $product->stock_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Insufficient stock for \"{$product->name}\". Only {$product->stock_quantity} left.",
                ]);
            }
            $product->stock_quantity = (int) $product->stock_quantity - $quantity;
            $product->save();
            return;
        }

        $priceDependency = $rawVariants['price_dependency'] ?? 'main';
        $normVariant = strtolower(trim((string) $variantName));
        $normSub = strtolower(trim((string) $subVariantName));
        $deducted = false;

        foreach ($rawVariants['items'] as &$vItem) {
            if (! empty($vItem['is_archived']) || ($vItem['status'] ?? 'Approved') !== 'Approved') {
                continue;
            }

            if (strtolower(trim((string) ($vItem['name'] ?? ''))) !== $normVariant) {
                continue;
            }

            if ($priceDependency === 'sub' || (! empty($vItem['subs']) && $normSub !== '')) {
                foreach ($vItem['subs'] as &$sItem) {
                    if (! empty($sItem['is_archived']) || ($sItem['status'] ?? 'Approved') !== 'Approved') {
                        continue;
                    }

                    if (strtolower(trim((string) ($sItem['name'] ?? ''))) !== $normSub) {
                        continue;
                    }

                    $currentStock = (int) ($sItem['stock'] ?? 0);
                    if ($currentStock < $quantity) {
                        throw ValidationException::withMessages([
                            'stock' => "Insufficient stock for \"{$product->name} ({$vItem['name']} / {$sItem['name']})\". Only {$currentStock} left.",
                        ]);
                    }

                    $sItem['stock'] = $currentStock - $quantity;
                    $deducted = true;
                    break 2;
                }
            } else {
                $currentStock = (int) ($vItem['stock'] ?? 0);
                if ($currentStock < $quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for \"{$product->name} ({$vItem['name']})\". Only {$currentStock} left.",
                    ]);
                }

                $vItem['stock'] = $currentStock - $quantity;
                $deducted = true;
                break;
            }
        }
        unset($vItem, $sItem);

        if (! $deducted) {
            throw ValidationException::withMessages([
                'stock' => "Could not reserve stock for \"{$product->name}\". Option not found.",
            ]);
        }

        $product->variants = $rawVariants;
        $product->stock_quantity = $this->calculateTotalApprovedStock($rawVariants);
        $product->save();
    }

    public function restoreStockForSellerOrder(SellerOrder $sellerOrder): void
    {
        $lockedOrder = SellerOrder::where('id', $sellerOrder->id)->lockForUpdate()->firstOrFail();

        if (! $lockedOrder->stock_deducted || $lockedOrder->stock_restored) {
            return;
        }

        $lockedOrder->loadMissing('items');

        foreach ($lockedOrder->items as $item) {
            /** @var Product|null $product */
            $product = Product::withTrashed()->where('id', $item->product_id)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }

            $rawVariants = $product->raw_variants;

            if (! is_array($rawVariants) || empty($rawVariants['items']) || ! is_array($rawVariants['items'])) {
                $product->stock_quantity = (int) $product->stock_quantity + (int) $item->quantity;
                $product->save();
                continue;
            }

            $priceDependency = $rawVariants['price_dependency'] ?? 'main';
            $normVariant = strtolower(trim((string) $item->variant_name));
            $normSub = strtolower(trim((string) $item->sub_variant_name));

            foreach ($rawVariants['items'] as &$vItem) {
                if (strtolower(trim((string) ($vItem['name'] ?? ''))) !== $normVariant) {
                    continue;
                }

                if ($priceDependency === 'sub' || (! empty($vItem['subs']) && $normSub !== '')) {
                    foreach ($vItem['subs'] as &$sItem) {
                        if (strtolower(trim((string) ($sItem['name'] ?? ''))) !== $normSub) {
                            continue;
                        }
                        $sItem['stock'] = (int) ($sItem['stock'] ?? 0) + (int) $item->quantity;
                        break 2;
                    }
                } else {
                    $vItem['stock'] = (int) ($vItem['stock'] ?? 0) + (int) $item->quantity;
                    break;
                }
            }
            unset($vItem, $sItem);

            $product->variants = $rawVariants;
            $product->stock_quantity = $this->calculateTotalApprovedStock($rawVariants);
            $product->save();
        }

        $lockedOrder->update([
            'stock_restored'    => true,
            'stock_restored_at' => now(),
        ]);
    }

    public function calculateTotalApprovedStock(array $variants): int
    {
        $priceDependency = $variants['price_dependency'] ?? 'main';
        $totalStock = 0;

        foreach ($variants['items'] ?? [] as $item) {
            if (! empty($item['is_archived']) || ($item['status'] ?? 'Approved') !== 'Approved') {
                continue;
            }

            if ($priceDependency === 'main') {
                $totalStock += (int) ($item['stock'] ?? 0);
            } else {
                foreach ($item['subs'] ?? [] as $sub) {
                    if (! empty($sub['is_archived']) || ($sub['status'] ?? 'Approved') !== 'Approved') {
                        continue;
                    }
                    $totalStock += (int) ($sub['stock'] ?? 0);
                }
            }
        }

        return $totalStock;
    }
}