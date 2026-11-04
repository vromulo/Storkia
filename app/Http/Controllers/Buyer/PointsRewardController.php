<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerRewardTransaction;
use App\Models\BuyerVoucherClaim;
use App\Services\BuyerRewardService;
use App\Services\OrderCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointsRewardController extends Controller
{
    public function __construct(
        protected BuyerRewardService $buyerRewardService,
        protected OrderCompletionService $orderCompletionService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $this->orderCompletionService->runLifecycleMaintenance(buyerId: (int) $user->id);

        $dailySpinGrant = $this->buyerRewardService->grantDailyLoginSpinIfEligible((int) $user->id);
        $wallet = $dailySpinGrant['wallet'];
        $justGrantedDailySpin = (bool) $dailySpinGrant['granted'];

        $wheelSegments = $this->buyerRewardService->getWheelSegments();
        $redeemCatalog = $this->buyerRewardService->getRedeemableVoucherCatalog();

        $transactions = BuyerRewardTransaction::with(['sellerOrder', 'voucher'])
            ->where('buyer_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        $activeRewardVouchers = BuyerVoucherClaim::with('voucher')
            ->where('buyer_id', $user->id)
            ->whereHas('voucher', function ($q) {
                $q->where('status', 'active')
                  ->where('ends_at', '>=', now())
                  ->whereColumn('used_count', '<', 'total_usage_limit');
            })
            ->latest('claimed_at')
            ->limit(12)
            ->get();

        return view('buyer.option.points', compact(
            'user',
            'wallet',
            'justGrantedDailySpin',
            'wheelSegments',
            'redeemCatalog',
            'transactions',
            'activeRewardVouchers'
        ));
    }

    public function spin(Request $request): JsonResponse
    {
        $result = $this->buyerRewardService->spinWheel((int) $request->user()->id);

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function openChest(Request $request): JsonResponse
    {
        $result = $this->buyerRewardService->openDailyChest((int) $request->user()->id);

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function redeemVoucher(Request $request)
    {
        $validated = $request->validate([
            'catalog_key' => ['required', 'string', 'max:40'],
        ]);

        $result = $this->buyerRewardService->redeemCoinsForVoucher(
            buyerId: (int) $request->user()->id,
            catalogKey: $validated['catalog_key']
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => $result['message'],
                'coins_balance' => $result['coins_balance'],
                'voucher'       => [
                    'code'        => $result['voucher']->code,
                    'title'       => $result['voucher']->title,
                    'badge_label' => $result['voucher']->badge_label,
                    'min_spend'   => number_format((float) $result['voucher']->min_spend, 2),
                    'ends_at'     => $result['voucher']->ends_at?->format('M d, Y'),
                ],
            ]);
        }

        return back()->with('success', $result['message']);
    }
}