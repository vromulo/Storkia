<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Product;
use App\Services\SuperDealPricingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait LoadsSuperDeals
{
    use FormatsStorefrontProducts;

    protected function loadSuperDealEntries(SuperDealPricingService $pricing): Collection
    {
        if (! Schema::hasTable('super_deals')) {
            return collect();
        }

        $now = now();

        $rows = DB::table('super_deals')
            ->where('deal_type', 'super_deal')
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(function ($query) {
                $query->whereNull('flash_quantity')
                    ->orWhereColumn('sold_quantity', '<', 'flash_quantity');
            })
            ->orderBy('ends_at')
            ->orderBy('id')
            ->get()
            ->unique('product_id');

        if ($rows->isEmpty()) {
            return collect();
        }

        $products = $this->applyStorefrontVisibility(
            Product::with('approval')->whereIn('id', $rows->pluck('product_id')->all())
        )->get()->keyBy('id');

        return $rows->map(function ($row) use ($products, $pricing) {
            $product = $products->get($row->product_id);
            if (! $product) {
                return null;
            }

            $basePrice = round((float) $product->price, 2);
            $dealPrice = $pricing->superDealUnitPrice($row, $basePrice);

            $hasFlashCap = $row->flash_quantity !== null;
            $flashQty = $hasFlashCap ? (int) $row->flash_quantity : null;
            $claimed = (int) $row->sold_quantity;
            $remaining = $hasFlashCap
                ? min(max(0, $flashQty - $claimed), (int) $product->stock_quantity)
                : (int) $product->stock_quantity;

            if ($remaining <= 0) {
                return null;
            }

            $endsAt = Carbon::parse($row->ends_at);

            return [
                'deal_id'         => (int) $row->id,
                'product'         => $product,
                'deal_price'      => $dealPrice,
                'original_price'  => $basePrice,
                'percent_off'     => ($basePrice > 0 && $dealPrice < $basePrice)
                    ? max(1, (int) round((1 - ($dealPrice / $basePrice)) * 100))
                    : 0,
                'has_flash_cap'   => $hasFlashCap,
                'remaining'       => $remaining,
                'claimed_percent' => $hasFlashCap && $flashQty > 0
                    ? min(100, (int) round(($claimed / $flashQty) * 100))
                    : 0,
                'ends_at'         => $endsAt,
                'ends_ts'         => $endsAt->timestamp,
            ];
        })->filter()->values();
    }

    protected function presentSuperDealEntries(Collection $entries, ?int $buyerId): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $this->prepareStorefrontProducts($entries->pluck('product'), $buyerId);

        $nowTs = now()->timestamp;

        return $entries->map(fn (array $entry) => [
            'deal_id'         => $entry['deal_id'],
            'product'         => $this->storefrontProduct($entry['product'], [
                'super_deal_price' => $entry['deal_price'],
                'super_deal_left'  => $entry['remaining'],
            ]),
            'deal_price'      => $entry['deal_price'],
            'original_price'  => $entry['original_price'],
            'percent_off'     => $entry['percent_off'],
            'has_flash_cap'   => $entry['has_flash_cap'],
            'remaining'       => $entry['remaining'],
            'claimed_percent' => $entry['claimed_percent'],
            'ends_at'         => $entry['ends_at']->toIso8601String(),
            'ends_ts'         => $entry['ends_ts'],
            'seconds_left'    => max(0, $entry['ends_ts'] - $nowTs),
        ])->values()->all();
    }
}