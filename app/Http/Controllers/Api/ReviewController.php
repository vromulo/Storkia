<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\ProductReview;
use App\Models\SellerReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::with('items')->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!in_array($sellerOrder->status, ['delivered', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews can only be submitted for delivered or completed orders.'
            ], 422);
        }

        $validated = $request->validate([
            'seller_rating' => ['required', 'integer', 'between:1,5'],
            'seller_comment' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.rating' => ['required', 'integer', 'between:1,5'],
            'items.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $sellerOrder, $validated) {
            SellerReview::updateOrCreate(
                [
                    'seller_order_id' => $sellerOrder->id,
                    'buyer_id' => $user->id,
                ],
                [
                    'seller_id' => $sellerOrder->seller_id,
                    'seller_profile_id' => $sellerOrder->seller_profile_id,
                    'rating' => $validated['seller_rating'],
                    'comment' => $validated['seller_comment'] ?? null,
                ]
            );

            foreach ($validated['items'] as $itemReview) {
                $orderItem = $sellerOrder->items->firstWhere('id', $itemReview['order_item_id']);
                if ($orderItem) {
                    ProductReview::updateOrCreate(
                        [
                            'order_item_id' => $orderItem->id,
                            'buyer_id' => $user->id,
                        ],
                        [
                            'product_id' => $orderItem->product_id,
                            'rating' => $itemReview['rating'],
                            'comment' => $itemReview['comment'] ?? null,
                        ]
                    );
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback! Review submitted successfully.'
        ]);
    }
}