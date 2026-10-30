<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\OrderItemReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function update(Request $request, OrderItemReview $review)
    {
        $user = $request->user();

        if ((int) $review->buyer_id !== (int) $user->id) {
            abort(403, 'You can only edit your own product reviews.');
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
            'review_photos.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'rating.required'           => 'Please select a star rating between 1 and 5.',
            'rating.min'                => 'Star rating must be at least 1 star.',
            'review_text.max'           => 'Review text cannot exceed 500 characters.',
            'review_photos.max'         => 'You may attach a maximum of 5 photos.',
            'review_photos.*.mimetypes' => 'Review photos must be authentic JPG or PNG images.',
            'review_photos.*.max'       => 'Each review photo must not exceed 5 MB.',
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

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your review has been updated (1 of 1 allowed edit used).',
                'review'  => $review->fresh(),
            ]);
        }

        return back()->with('success', 'Your review has been updated (1 of 1 allowed edit used).');
    }
}