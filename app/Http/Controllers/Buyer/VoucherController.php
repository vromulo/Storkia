<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\StorkVoucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function __construct(
        protected VoucherService $voucherService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->voucherService->expireOverdueVouchers();

        $claimedRecords = BuyerVoucherClaim::with(['voucher.sellerProfile'])
            ->where('buyer_id', $user->id)
            ->latest('claimed_at')
            ->get();

        $claimedVoucherIds = $claimedRecords->pluck('voucher_id')->all();

        // Global Discover tab only lists Platform & Free Shipping vouchers;
        // Seller Store Vouchers are exclusively claimable on that Seller's storefront/product pages!
        $discoverableVouchers = StorkVoucher::with('sellerProfile')
            ->where('status', 'active')
            ->whereIn('scope', ['platform', 'shipping'])
            ->where('visibility', 'claimable')
            ->where('ends_at', '>=', now())
            ->whereColumn('used_count', '<', 'total_usage_limit')
            ->whereNotIn('id', $claimedVoucherIds)
            ->orderBy('scope')
            ->orderBy('min_spend')
            ->get();

        return view('buyer.option.vouchers.index', compact(
            'claimedRecords',
            'discoverableVouchers'
        ));
    }

    public function claim(Request $request)
    {
        $validated = $request->validate([
            'code'              => ['required', 'string', 'max:25'],
            'seller_profile_id' => ['nullable', 'integer'],
        ]);

        $normCode = $this->voucherService->normalizeCode($validated['code']);
        $candidate = StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [$normCode])->first();

        if ($candidate && $candidate->scope === 'store' && $candidate->visibility === 'claimable') {
            if (empty($validated['seller_profile_id']) || (int) $validated['seller_profile_id'] !== (int) $candidate->seller_profile_id) {
                throw ValidationException::withMessages([
                    'code' => "Store voucher \"{$candidate->code}\" can only be claimed directly on {$candidate->sellerProfile?->business_name}'s Storefront or Product Page.",
                ]);
            }
        }

        $voucher = $this->voucherService->claimVoucherByCode(
            buyerId: (int) $request->user()->id,
            rawCode: $validated['code']
        );

        return back()->with('success', "Voucher \"{$voucher->code}\" ({$voucher->badge_label}) has been added to your Voucher Wallet!");
    }
}