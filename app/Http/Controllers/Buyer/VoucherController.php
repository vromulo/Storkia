<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\StorkVoucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(
        protected VoucherService $voucherService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $claimedRecords = BuyerVoucherClaim::with(['voucher.sellerProfile'])
            ->where('buyer_id', $user->id)
            ->latest('claimed_at')
            ->get();

        $claimedVoucherIds = $claimedRecords->pluck('voucher_id')->all();

        $discoverableVouchers = StorkVoucher::with('sellerProfile')
            ->where('status', 'active')
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
            'code' => ['required', 'string', 'max:25'],
        ]);

        $voucher = $this->voucherService->claimVoucherByCode(
            buyerId: (int) $request->user()->id,
            rawCode: $validated['code']
        );

        return back()->with('success', "Voucher \"{$voucher->code}\" ({$voucher->badge_label}) has been added to your Voucher Wallet!");
    }
}