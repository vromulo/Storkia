<?php

namespace App\Services;

use App\Models\BuyerCancellationLog;
use App\Models\SellerOrder;
use Illuminate\Validation\ValidationException;

class BuyerCancellationGuardService
{
    public function validateCanCheckoutItems(int $buyerId, array $rawLineItems): void
    {
        $cutoff = now()->subDays(BuyerCancellationLog::WINDOW_DAYS);

        $totalBuyerCancels = BuyerCancellationLog::where('buyer_id', $buyerId)
            ->where('cancelled_at', '>=', $cutoff)
            ->distinct('seller_order_id')
            ->count('seller_order_id');

        if ($totalBuyerCancels >= BuyerCancellationLog::LIMIT_BY_BUYER) {
            throw ValidationException::withMessages([
                'cart' => 'Anti-Sabotage Protection: You have reached the maximum of ' . BuyerCancellationLog::LIMIT_BY_BUYER . ' order cancellations within ' . BuyerCancellationLog::WINDOW_DAYS . ' days. New order placement is temporarily restricted.',
            ]);
        }

        foreach ($rawLineItems as $line) {
            $product = $line['product'] ?? null;
            $sellerProfile = $line['seller_profile'] ?? null;

            if ($product) {
                $productCancels = BuyerCancellationLog::where('buyer_id', $buyerId)
                    ->where('product_id', $product->id)
                    ->where('cancelled_at', '>=', $cutoff)
                    ->count();

                if ($productCancels >= BuyerCancellationLog::LIMIT_BY_PRODUCT) {
                    throw ValidationException::withMessages([
                        'cart' => "Anti-Sabotage Protection: You have cancelled orders containing \"{$product->name}\" {$productCancels} times in the last " . BuyerCancellationLog::WINDOW_DAYS . " days (Limit: " . BuyerCancellationLog::LIMIT_BY_PRODUCT . " per product). You cannot reorder this item right now.",
                    ]);
                }
            }

            if ($sellerProfile) {
                $sellerCancels = BuyerCancellationLog::where('buyer_id', $buyerId)
                    ->where('seller_profile_id', $sellerProfile->id)
                    ->where('cancelled_at', '>=', $cutoff)
                    ->distinct('seller_order_id')
                    ->count('seller_order_id');

                if ($sellerCancels >= BuyerCancellationLog::LIMIT_BY_SELLER) {
                    throw ValidationException::withMessages([
                        'cart' => "Anti-Sabotage Protection: You have cancelled {$sellerCancels} orders from \"{$sellerProfile->business_name}\" in the last " . BuyerCancellationLog::WINDOW_DAYS . " days (Limit: " . BuyerCancellationLog::LIMIT_BY_SELLER . " per seller). You cannot place new orders with this store right now.",
                    ]);
                }
            }
        }
    }

    public function validateCanCancelOrder(SellerOrder $sellerOrder, int $buyerId): void
    {
        if ((int) $sellerOrder->buyer_id !== $buyerId) {
            abort(403);
        }

        if (! $sellerOrder->canBuyerCancelPreShipment()) {
            throw ValidationException::withMessages([
                'cancel' => 'This order can no longer be cancelled because the package has already been prepared for pickup or handed over to Logistics.',
            ]);
        }

        $cutoff = now()->subDays(BuyerCancellationLog::WINDOW_DAYS);

        $totalBuyerCancels = BuyerCancellationLog::where('buyer_id', $buyerId)
            ->where('cancelled_at', '>=', $cutoff)
            ->distinct('seller_order_id')
            ->count('seller_order_id');

        if ($totalBuyerCancels >= BuyerCancellationLog::LIMIT_BY_BUYER) {
            throw ValidationException::withMessages([
                'cancel' => 'Cancellation limit reached: You have already cancelled ' . BuyerCancellationLog::LIMIT_BY_BUYER . ' orders within the last ' . BuyerCancellationLog::WINDOW_DAYS . ' days.',
            ]);
        }

        $sellerCancels = BuyerCancellationLog::where('buyer_id', $buyerId)
            ->where('seller_profile_id', $sellerOrder->seller_profile_id)
            ->where('cancelled_at', '>=', $cutoff)
            ->distinct('seller_order_id')
            ->count('seller_order_id');

        if ($sellerCancels >= BuyerCancellationLog::LIMIT_BY_SELLER) {
            throw ValidationException::withMessages([
                'cancel' => 'Store cancellation limit reached: You have already cancelled ' . BuyerCancellationLog::LIMIT_BY_SELLER . ' orders from this seller within the last ' . BuyerCancellationLog::WINDOW_DAYS . ' days.',
            ]);
        }

        $sellerOrder->loadMissing('items');
        foreach ($sellerOrder->items as $item) {
            $prodCancels = BuyerCancellationLog::where('buyer_id', $buyerId)
                ->where('product_id', $item->product_id)
                ->where('cancelled_at', '>=', $cutoff)
                ->count();

            if ($prodCancels >= BuyerCancellationLog::LIMIT_BY_PRODUCT) {
                throw ValidationException::withMessages([
                    'cancel' => "Product cancellation limit reached: You have already cancelled \"{$item->product_name}\" {$prodCancels} times within the last " . BuyerCancellationLog::WINDOW_DAYS . " days.",
                ]);
            }
        }
    }

    public function recordOrderCancellation(SellerOrder $sellerOrder, int $buyerId, string $reason): void
    {
        $sellerOrder->loadMissing('items');

        $alreadyLogged = BuyerCancellationLog::where('seller_order_id', $sellerOrder->id)
            ->where('buyer_id', $buyerId)
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        foreach ($sellerOrder->items as $item) {
            BuyerCancellationLog::create([
                'buyer_id'          => $buyerId,
                'seller_order_id'   => $sellerOrder->id,
                'seller_profile_id' => $sellerOrder->seller_profile_id,
                'product_id'        => $item->product_id,
                'cancel_reason'     => substr(trim($reason), 0, 150),
                'cancelled_at'      => now(),
            ]);
        }
    }

    public function getBuyerQuotaSummary(int $buyerId): array
    {
        $cutoff = now()->subDays(BuyerCancellationLog::WINDOW_DAYS);

        $usedBuyerTotal = BuyerCancellationLog::where('buyer_id', $buyerId)
            ->where('cancelled_at', '>=', $cutoff)
            ->distinct('seller_order_id')
            ->count('seller_order_id');

        return [
            'window_days'       => BuyerCancellationLog::WINDOW_DAYS,
            'limit_by_buyer'    => BuyerCancellationLog::LIMIT_BY_BUYER,
            'used_by_buyer'     => $usedBuyerTotal,
            'remaining_buyer'   => max(0, BuyerCancellationLog::LIMIT_BY_BUYER - $usedBuyerTotal),
            'limit_by_seller'   => BuyerCancellationLog::LIMIT_BY_SELLER,
            'limit_by_product'  => BuyerCancellationLog::LIMIT_BY_PRODUCT,
        ];
    }
}