<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItemReview;
use App\Models\OrderServiceRating;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ReviewController extends Controller
{
    public function store(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::with(['items', 'sellerProfile', 'logisticsProfile', 'shipment'])->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($sellerOrder->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'You can only rate an order after confirming receipt (Order Completed).',
            ], 422);
        }

        if ($sellerOrder->serviceRating()->exists()) {
            return response()->json([
                'success' => true,
                'message' => 'This order has already been rated.',
            ]);
        }

        foreach (['item_ratings', 'item_reviews'] as $key) {
            $val = $request->input($key);
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    $request->merge([$key => $decoded]);
                }
            }
        }

        if ($request->has('items') && is_array($request->input('items')) && ! $request->has('item_ratings')) {
            $mappedRatings = [];
            $mappedReviews = [];
            foreach ($request->input('items') as $it) {
                if (isset($it['order_item_id'])) {
                    $mappedRatings[$it['order_item_id']] = (int) ($it['rating'] ?? 5);
                    $mappedReviews[$it['order_item_id']] = (string) ($it['comment'] ?? '');
                }
            }
            $request->merge([
                'item_ratings'            => $mappedRatings,
                'item_reviews'            => $mappedReviews,
                'parcel_condition_rating' => (int) $request->input('parcel_condition_rating', 5),
                'seller_service_rating'   => (int) $request->input('seller_service_rating', $request->input('seller_rating', 5)),
                'logistics_hub_rating'    => (int) $request->input('logistics_hub_rating', 5),
                'rider_delivery_rating'   => (int) $request->input('rider_delivery_rating', 5),
                'review_comment'          => $request->input('review_comment', $request->input('seller_comment')),
            ]);
        }

        $validated = $request->validate([
            'item_ratings'            => ['required', 'array'],
            'item_ratings.*'          => ['required', 'integer', 'min:1', 'max:5'],
            'item_reviews'            => ['nullable', 'array'],
            'item_reviews.*'          => ['nullable', 'string', 'max:1000'],
            'parcel_condition_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'seller_service_rating'   => ['required', 'integer', 'min:1', 'max:5'],
            'logistics_hub_rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'rider_delivery_rating'   => ['required', 'integer', 'min:1', 'max:5'],
            'review_comment'          => ['nullable', 'string', 'max:1000'],
        ], [
            'item_ratings.required'            => 'Please select a star rating (1 to 5) for each purchased product.',
            'parcel_condition_rating.required' => 'Please rate the parcel and packaging condition.',
            'seller_service_rating.required'   => 'Please rate the seller store service.',
            'logistics_hub_rating.required'    => 'Please rate the logistics hub handling.',
            'rider_delivery_rating.required'   => 'Please rate the delivery rider service.',
        ]);

        $manager = ImageManager::usingDriver(Driver::class);

        DB::transaction(function () use ($request, $user, $sellerOrder, $validated, $manager) {
            $itemRatingValues = [];

            foreach ($sellerOrder->items as $item) {
                $ratingVal = (int) ($validated['item_ratings'][$item->id] ?? ($validated['item_ratings'][(string) $item->id] ?? 5));
                $ratingVal = max(1, min(5, $ratingVal));
                $itemRatingValues[] = $ratingVal;

                $reviewText = trim((string) ($validated['item_reviews'][$item->id] ?? ($validated['item_reviews'][(string) $item->id] ?? '')));

                $photoPath = null;
                $file = $request->file("item_photos.{$item->id}") ?? $request->file("item_photo_{$item->id}");
                if ($file && $file->isValid()) {
                    $filename = 'rev_' . $sellerOrder->id . '_' . $item->id . '_' . time() . '.webp';
                    $photoPath = 'product_reviews/' . $filename;

                    $img = $manager->decode($file->getRealPath());
                    $img->scaleDown(width: 1200, height: 1200);
                    $encoded = $img->encode(new WebpEncoder(quality: 82));
                    Storage::disk('public')->put($photoPath, $encoded->toString());
                }

                $reviewAttributes = [
                    'order_id'          => $sellerOrder->order_id,
                    'seller_order_id'   => $sellerOrder->id,
                    'product_id'        => $item->product_id,
                    'seller_profile_id' => $sellerOrder->seller_profile_id,
                    'buyer_id'          => $user->id,
                    'rating'            => $ratingVal,
                    'review_text'       => $reviewText !== '' ? $reviewText : ($validated['review_comment'] ?? null),
                    'review_photo_path' => $photoPath,
                ];

                if ($photoPath && Schema::hasColumn('order_item_reviews', 'review_photos')) {
                    $reviewAttributes['review_photos'] = [$photoPath];
                }

                OrderItemReview::updateOrCreate(
                    ['order_item_id' => $item->id],
                    $reviewAttributes
                );
            }

            $avgProductQuality = count($itemRatingValues) > 0
                ? (int) round(array_sum($itemRatingValues) / count($itemRatingValues))
                : 5;

            $overallAvg = round((
                $avgProductQuality
                + (int) $validated['parcel_condition_rating']
                + (int) $validated['seller_service_rating']
                + (int) $validated['logistics_hub_rating']
                + (int) $validated['rider_delivery_rating']
            ) / 5, 2);

            OrderServiceRating::updateOrCreate(
                ['seller_order_id' => $sellerOrder->id],
                [
                    'order_id'                => $sellerOrder->order_id,
                    'buyer_id'                => $user->id,
                    'seller_profile_id'       => $sellerOrder->seller_profile_id,
                    'logistics_profile_id'    => $sellerOrder->logistics_profile_id ?? $sellerOrder->shipment?->logistics_profile_id,
                    'rider_id'                => $sellerOrder->shipment?->current_rider_id,
                    'product_quality_rating'  => $avgProductQuality,
                    'parcel_condition_rating' => (int) $validated['parcel_condition_rating'],
                    'seller_service_rating'   => (int) $validated['seller_service_rating'],
                    'logistics_hub_rating'    => (int) $validated['logistics_hub_rating'],
                    'rider_delivery_rating'   => (int) $validated['rider_delivery_rating'],
                    'overall_average_rating'  => $overallAvg,
                    'review_comment'          => ! empty($validated['review_comment']) ? trim($validated['review_comment']) : null,
                ]
            );

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => 'completed',
                'to_status'       => 'completed',
                'actor_type'      => 'Buyer',
                'actor_id'        => $user->id,
                'remarks'         => "Buyer submitted 5-point ratings (Overall {$overallAvg}/5.0 ★). Order moved to Completed (Buy Again).",
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Thank you for rating Order #{$sellerOrder->seller_order_number}! Your review is now live and the Buy Again button is unlocked.",
        ]);
    }

    public function update(Request $request, OrderItemReview $review): JsonResponse
    {
        $user = $request->user();

        if ((int) $review->buyer_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own product reviews.',
            ], 403);
        }

        $editCount = (int) ($review->edit_count ?? 0);
        if ($editCount >= 1) {
            throw ValidationException::withMessages([
                'review' => 'You have already edited this review once. A second edit is not permitted.',
            ]);
        }

        if ($review->created_at && $review->created_at->lt(now()->subDays(30))) {
            throw ValidationException::withMessages([
                'review' => 'Reviews can only be edited within 30 days of submission.',
            ]);
        }

        $validated = $request->validate([
            'rating'          => ['required', 'integer', 'min:1', 'max:5'],
            'review_text'     => ['nullable', 'string', 'max:500'],
            'review_photos'   => ['nullable', 'array', 'max:5'],
            'review_photos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'rating.required' => 'Please select a star rating between 1 and 5.',
            'review_text.max' => 'Review text cannot exceed 500 characters.',
        ]);

        $photoPaths = is_array($review->review_photos) ? $review->review_photos : [];
        if ($request->hasFile('review_photos')) {
            $photoPaths = [];
            foreach ($request->file('review_photos') as $file) {
                $photoPaths[] = $file->store('product_reviews', 'public');
            }
        }

        $updateData = [
            'rating'            => (int) $validated['rating'],
            'review_text'       => isset($validated['review_text']) ? trim((string) $validated['review_text']) : null,
            'review_photo_path' => $photoPaths[0] ?? $review->review_photo_path,
        ];

        if (Schema::hasColumn('order_item_reviews', 'edit_count')) {
            $updateData['review_photos'] = $photoPaths;
            $updateData['edit_count'] = $editCount + 1;
            $updateData['edited_at'] = now();
        }

        $review->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Your review has been updated (1 of 1 allowed edit used).',
            'review'  => $review->fresh(),
        ]);
    }
}