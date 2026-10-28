<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StorkVoucher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $vouchers = StorkVoucher::withCount('claims')
            ->where('seller_profile_id', $profile->id)
            ->orderByDesc('id')
            ->get();

        $products = Product::where('user_id', $user->id)
            ->whereHas('approval', fn ($q) => $q->where('status', 'Approved'))
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        return view('seller.vouchers.index', compact('profile', 'vouchers', 'products'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        $normalizedCode = strtoupper(trim((string) $request->input('code')));
        $request->merge(['code' => $normalizedCode]);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'min:4',
                'max:12',
                'regex:/^[A-Z0-9]+$/',
                function ($attr, $val, $fail) {
                    if (StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($val)])->exists()) {
                        $fail("Voucher code \"{$val}\" is already in use across Storkia.");
                    }
                },
            ],
            'title'                  => ['required', 'string', 'min:3', 'max:120'],
            'discount_type'          => ['required', 'in:fixed,percentage'],
            'discount_value'         => ['required', 'numeric', 'gt:0'],
            'max_discount_cap'       => ['nullable', 'numeric', 'gt:0'],
            'min_spend'              => ['required', 'numeric', 'min:1'],
            'total_usage_limit'      => ['required', 'integer', 'min:1', 'max:100000'],
            'per_buyer_limit'        => ['required', 'integer', 'min:1', 'max:50'],
            'visibility'             => ['required', 'in:claimable,code_only'],
            'applicable_product_ids' => ['nullable', 'array'],
            'applicable_product_ids.*' => ['integer'],
            'starts_at'              => ['required', 'date'],
            'ends_at'                => ['required', 'date', 'after:starts_at'],
        ], [
            'code.min'        => 'Voucher code must be between 4 and 12 characters.',
            'code.max'        => 'Voucher code must be between 4 and 12 characters.',
            'code.regex'      => 'Voucher code can only contain letters and numbers (no spaces or symbols).',
            'ends_at.after'   => 'End date must be after the start date.',
        ]);

        if ((int) $validated['per_buyer_limit'] > (int) $validated['total_usage_limit']) {
            throw ValidationException::withMessages([
                'per_buyer_limit' => 'Per-buyer usage limit cannot exceed the total usage limit.',
            ]);
        }

        if ($validated['discount_type'] === 'fixed') {
            if ((float) $validated['discount_value'] > (float) $validated['min_spend']) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Fixed voucher discount cannot exceed the minimum spend requirement.',
                ]);
            }
        } else {
            if ((float) $validated['discount_value'] < 1 || (float) $validated['discount_value'] >= 100) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Percentage discount must be between 1% and 99%.',
                ]);
            }
            if (empty($validated['max_discount_cap']) || (float) $validated['max_discount_cap'] <= 0) {
                throw ValidationException::withMessages([
                    'max_discount_cap' => 'A maximum discount cap greater than ₱0 is required for percentage vouchers.',
                ]);
            }
        }

        StorkVoucher::create([
            'seller_profile_id'      => $profile->id,
            'created_by'             => $user->id,
            'code'                   => $validated['code'],
            'title'                  => trim($validated['title']),
            'description'            => $validated['discount_type'] === 'percentage'
                ? "{$validated['discount_value']}% off (Cap ₱" . number_format((float) $validated['max_discount_cap'], 2) . "), Min. spend ₱" . number_format((float) $validated['min_spend'], 2)
                : "₱" . number_format((float) $validated['discount_value'], 2) . " off, Min. spend ₱" . number_format((float) $validated['min_spend'], 2),
            'scope'                  => 'store',
            'discount_type'          => $validated['discount_type'],
            'discount_value'         => $validated['discount_value'],
            'max_discount_cap'       => $validated['discount_type'] === 'percentage' ? $validated['max_discount_cap'] : null,
            'min_spend'              => $validated['min_spend'],
            'total_usage_limit'      => (int) $validated['total_usage_limit'],
            'used_count'             => 0,
            'per_buyer_limit'        => (int) $validated['per_buyer_limit'],
            'visibility'             => $validated['visibility'],
            'applicable_product_ids' => ! empty($validated['applicable_product_ids']) ? array_values(array_map('intval', $validated['applicable_product_ids'])) : null,
            'starts_at'              => $validated['starts_at'],
            'ends_at'                => $validated['ends_at'],
            'status'                 => 'active',
        ]);

        return back()->with('success', "Store Voucher \"{$validated['code']}\" created successfully!");
    }

    public function update(Request $request, StorkVoucher $voucher)
    {
        $profile = $request->user()->sellerProfile;
        if ((int) $voucher->seller_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $validated = $request->validate([
            'ends_at'           => ['required', 'date', 'after:starts_at'],
            'total_usage_limit' => ['required', 'integer', 'min:' . max(1, (int) $voucher->used_count), 'max:100000'],
        ]);

        $voucher->update([
            'ends_at'           => $validated['ends_at'],
            'total_usage_limit' => (int) $validated['total_usage_limit'],
        ]);

        return back()->with('success', "Store Voucher \"{$voucher->code}\" validity and usage limit updated.");
    }

    public function endNow(Request $request, StorkVoucher $voucher)
    {
        $profile = $request->user()->sellerProfile;
        if ((int) $voucher->seller_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $voucher->update([
            'status'  => 'ended',
            'ends_at' => now(),
        ]);

        return back()->with('success', "Voucher \"{$voucher->code}\" has been ended immediately.");
    }
}