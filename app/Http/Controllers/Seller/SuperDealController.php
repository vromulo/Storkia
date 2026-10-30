<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SuperDealPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SuperDealController extends Controller
{
    public function __construct(
        protected SuperDealPricingService $superDealPricingService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $this->superDealPricingService->expireEndedPromotions();

        $products = Product::where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $deals = Schema::hasTable('super_deals')
            ? DB::table('super_deals')
                ->where('seller_profile_id', $profile->id)
                ->orderBy('starts_at', 'asc')
                ->get()
            : collect();

        $bundles = Schema::hasTable('bundle_deals')
            ->where('seller_profile_id', $profile->id)
            ->orderBy('starts_at', 'asc')
            ->get();

        $productsById = $products->keyBy('id');

        return view('seller.promotions.index', compact(
            'profile',
            'products',
            'productsById',
            'deals',
            'bundles'
        ));
    }

    public function storeSuperDealOrSale(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $validated = $request->validate([
            'deal_type'           => ['required', 'in:super_deal,store_sale'],
            'product_id'          => ['required', 'integer', 'exists:products,id'],
            'title'               => ['nullable', 'string', 'max:120'],
            'deal_price'          => ['nullable', 'numeric', 'min:1'],
            'discount_percentage' => ['nullable', 'integer', 'min:1', 'max:99'],
            'flash_quantity'      => ['required_if:deal_type,super_deal', 'nullable', 'integer', 'min:1'],
            'starts_at'           => ['required', 'date'],
            'ends_at'             => ['required', 'date', 'after:starts_at'],
        ], [
            'ends_at.after'                => 'End date and time must be strictly after the start date and time.',
            'discount_percentage.integer'  => 'Discount percentage must be a whole number from 1% to 99%.',
            'flash_quantity.required_if'   => 'Please enter the Super Deal slot quantity.',
        ]);

        $product = Product::where('user_id', $user->id)->findOrFail($validated['product_id']);
        $originalPrice = (float) $product->price;

        $dealPrice = ! empty($validated['deal_price']) ? round((float) $validated['deal_price'], 2) : null;
        $discountPct = ! empty($validated['discount_percentage']) ? (int) $validated['discount_percentage'] : null;

        if ($dealPrice === null && $discountPct === null) {
            throw ValidationException::withMessages([
                'deal_price' => 'Please enter either a fixed sale price (below ₱' . number_format($originalPrice, 2) . ') or a whole discount percentage (1%–99%).',
            ]);
        }

        if ($dealPrice !== null && $dealPrice >= $originalPrice) {
            throw ValidationException::withMessages([
                'deal_price' => 'Fixed sale price must be strictly lower than the original product price (₱' . number_format($originalPrice, 2) . ').',
            ]);
        }

        if ($validated['deal_type'] === 'super_deal' && (int) $validated['flash_quantity'] > (int) $product->stock_quantity) {
            throw ValidationException::withMessages([
                'flash_quantity' => "Super Deal quantity cannot exceed the product's available stock ({$product->stock_quantity}).",
            ]);
        }

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);

        // Prevent overlapping store sales on the same product (SEL-DISC-007 / Assumption A28)
        if ($validated['deal_type'] === 'store_sale') {
            $overlapping = DB::table('super_deals')
                ->where('product_id', $product->id)
                ->where('deal_type', 'store_sale')
                ->where('status', 'active')
                ->where(function ($q) use ($startsAt, $endsAt) {
                    $q->whereBetween('starts_at', [$startsAt, $endsAt])
                      ->orWhereBetween('ends_at', [$startsAt, $endsAt])
                      ->orWhere(function ($oq) use ($startsAt, $endsAt) {
                          $oq->where('starts_at', '<=', $startsAt)->where('ends_at', '>=', $endsAt);
                      });
                })
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages([
                    'product_id' => "\"{$product->name}\" already has a scheduled Store Sale during that time period.",
                ]);
            }
        }

        if ($dealPrice === null && $discountPct !== null) {
            $dealPrice = round($originalPrice * (1 - ($discountPct / 100)), 2);
        } elseif ($discountPct === null && $dealPrice !== null && $originalPrice > 0) {
            $discountPct = max(1, (int) round((1 - ($dealPrice / $originalPrice)) * 100));
        }

        DB::table('super_deals')->insert([
            'seller_profile_id'   => $profile->id,
            'product_id'          => $product->id,
            'deal_type'           => $validated['deal_type'],
            'title'               => $validated['title'] ?: ($validated['deal_type'] === 'super_deal' ? 'Super Deal Slot' : 'Scheduled Store Sale'),
            'deal_price'          => $dealPrice,
            'discount_percentage' => $discountPct,
            'flash_quantity'      => $validated['deal_type'] === 'super_deal' ? (int) $validated['flash_quantity'] : null,
            'sold_quantity'       => 0,
            'starts_at'           => $startsAt,
            'ends_at'             => $endsAt,
            'status'              => 'active',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return back()->with(
            'success',
            ($validated['deal_type'] === 'super_deal' ? 'Super Deal time slot' : 'Scheduled Store Sale') . " created for \"{$product->name}\"!"
        );
    }

    public function updateRunningSale(Request $request, int $id)
    {
        $profile = $request->user()->sellerProfile;
        $deal = DB::table('super_deals')
            ->where('id', $id)
            ->where('seller_profile_id', $profile->id)
            ->first();

        if (! $deal) {
            abort(404);
        }

        $validated = $request->validate([
            'discount_percentage' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::findOrFail($deal->product_id);
        $newPrice = round((float) $product->price * (1 - ((int) $validated['discount_percentage'] / 100)), 2);

        DB::table('super_deals')->where('id', $id)->update([
            'discount_percentage' => (int) $validated['discount_percentage'],
            'deal_price'          => $newPrice,
            'updated_at'          => now(),
        ]);

        return back()->with('success', "Sale discount updated to {$validated['discount_percentage']}% (₱" . number_format($newPrice, 2) . ") immediately for new orders.");
    }

    public function endPromotionNow(Request $request, int $id)
    {
        $profile = $request->user()->sellerProfile;

        DB::table('super_deals')
            ->where('id', $id)
            ->where('seller_profile_id', $profile->id)
            ->update([
                'status'     => 'ended',
                'ends_at'    => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Promotion ended immediately. Product price has returned to normal for new orders.');
    }

    public function storeBundleDeal(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $validated = $request->validate([
            'title'                  => ['required', 'string', 'min:3', 'max:120'],
            'bundle_type'            => ['required', 'in:percentage_off,buy_x_get_y'],
            'required_quantity'      => ['required', 'integer', 'min:2', 'max:20'],
            'discount_percentage'    => ['required_if:bundle_type,percentage_off', 'nullable', 'numeric', 'min:1', 'max:99'],
            'free_gift_product_id'   => ['required_if:bundle_type,buy_x_get_y', 'nullable', 'integer', 'exists:products,id'],
            'applicable_product_ids' => ['required', 'array', 'min:1'],
            'starts_at'              => ['required', 'date'],
            'ends_at'                => ['required', 'date', 'after:starts_at'],
        ]);

        DB::table('bundle_deals')->insert([
            'seller_profile_id'      => $profile->id,
            'title'                  => trim($validated['title']),
            'bundle_type'            => $validated['bundle_type'],
            'required_quantity'      => (int) $validated['required_quantity'],
            'discount_percentage'    => $validated['bundle_type'] === 'percentage_off' ? (float) $validated['discount_percentage'] : null,
            'free_gift_product_id'   => $validated['bundle_type'] === 'buy_x_get_y' ? (int) $validated['free_gift_product_id'] : null,
            'applicable_product_ids' => json_encode(array_values(array_map('intval', $validated['applicable_product_ids']))),
            'starts_at'              => Carbon::parse($validated['starts_at']),
            'ends_at'                => Carbon::parse($validated['ends_at']),
            'status'                 => 'active',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        return back()->with('success', "Bundle Deal \"{$validated['title']}\" published!");
    }

    public function toggleVacationMode(Request $request)
    {
        $profile = $request->user()->sellerProfile;

        if (! Schema::hasColumn('seller_profiles', 'is_on_vacation')) {
            return back()->withErrors(['vacation' => 'Vacation mode column is not ready yet.']);
        }

        $nextState = ! $profile->is_on_vacation;
        $profile->update([
            'is_on_vacation' => $nextState,
        ]);

        return back()->with(
            'success',
            $nextState
                ? 'Vacation Mode is now ON. Your products remain visible with an "On Vacation" badge, and Add to Cart / Buy Now / Checkout are paused.'
                : 'Vacation Mode is now OFF. Add to Cart, Buy Now, and Checkout are re-enabled for your store!'
        );
    }
}