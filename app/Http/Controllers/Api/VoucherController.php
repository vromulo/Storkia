<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsVouchers;
use App\Http\Controllers\Controller;
use App\Models\BuyerVoucherClaim;
use App\Models\StorkVoucher;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    use FormatsVouchers;

    public function __construct(
        protected VoucherService $voucherService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $buyerId = (int) $request->user()->id;
        $this->voucherService->expireOverdueVouchers();

        $claims = BuyerVoucherClaim::with('voucher.sellerProfile')
            ->where('buyer_id', $buyerId)
            ->whereHas('voucher')
            ->latest('claimed_at')
            ->get();

        $claimedVoucherIds = $claims->pluck('voucher_id')->all();

        $discoverable = StorkVoucher::with('sellerProfile')
            ->where('status', 'active')
            ->whereIn('scope', ['platform', 'shipping'])
            ->where('visibility', 'claimable')
            ->where('ends_at', '>=', now())
            ->whereColumn('used_count', '<', 'total_usage_limit')
            ->whereNotIn('id', $claimedVoucherIds)
            ->orderBy('scope')
            ->orderBy('min_spend')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'wallet'   => $claims
                    ->map(fn (BuyerVoucherClaim $claim) => $this->formatVoucher($claim->voucher, $claim))
                    ->values(),
                'discover' => $discoverable
                    ->map(fn (StorkVoucher $voucher) => $this->formatVoucher($voucher, null, false))
                    ->values(),
            ],
        ]);
    }

    public function activeVouchers(Request $request): JsonResponse
    {
        $buyerId = (int) $request->user()->id;
        $this->voucherService->expireOverdueVouchers();

        $usable = BuyerVoucherClaim::with('voucher.sellerProfile')
            ->where('buyer_id', $buyerId)
            ->whereHas('voucher', function ($query) {
                $query->where('status', 'active')
                    ->where('ends_at', '>=', now())
                    ->whereColumn('used_count', '<', 'total_usage_limit');
            })
            ->latest('claimed_at')
            ->get()
            ->map(fn (BuyerVoucherClaim $claim) => $this->formatVoucher($claim->voucher, $claim))
            ->filter(fn (array $voucher) => $voucher['is_usable'])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $usable,
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'              => ['required', 'string', 'max:25'],
            'seller_profile_id' => ['nullable', 'integer'],
        ]);

        $buyerId = (int) $request->user()->id;
        $normalizedCode = $this->voucherService->normalizeCode($validated['code']);

        $candidate = StorkVoucher::with('sellerProfile')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$normalizedCode])
            ->first();

        if ($candidate && $candidate->scope === 'store' && $candidate->visibility === 'claimable') {
            $sellerProfileId = (int) ($validated['seller_profile_id'] ?? 0);

            if ($sellerProfileId === 0 || $sellerProfileId !== (int) $candidate->seller_profile_id) {
                throw ValidationException::withMessages([
                    'code' => "Store voucher \"{$candidate->code}\" can only be claimed directly on {$candidate->sellerProfile?->business_name}'s Storefront or Product Page.",
                ]);
            }
        }

        $voucher = $this->voucherService->claimVoucherByCode($buyerId, $validated['code']);
        $voucher->loadMissing('sellerProfile');

        $claim = BuyerVoucherClaim::where('voucher_id', $voucher->id)
            ->where('buyer_id', $buyerId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => "Voucher \"{$voucher->code}\" ({$voucher->badge_label}) has been added to your Voucher Wallet!",
            'data'    => $this->formatVoucher($voucher, $claim),
        ], 201);
    }
}