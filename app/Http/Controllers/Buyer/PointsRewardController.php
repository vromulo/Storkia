<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerRewardTransaction;
use App\Models\BuyerVoucherClaim;
use App\Models\BuyerWalletTopup;
use App\Models\WalletTransaction;
use App\Services\BuyerRewardService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PointsRewardController extends Controller
{
    public function __construct(
        protected BuyerRewardService $buyerRewardService,
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService
    ) {}

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();

        $this->orderCompletionService->runLifecycleMaintenance(buyerId: (int) $user->id);

        $dailySpinGrant = $this->buyerRewardService->grantDailyLoginSpinIfEligible((int) $user->id);
        $wallet = $dailySpinGrant['wallet'];
        $justGrantedDailySpin = (bool) $dailySpinGrant['granted'];

        $buyerWallet = $this->roleWalletService->getOrCreateBuyerWallet($user);
        $walletTopups = Schema::hasTable('buyer_wallet_topups')
            ? BuyerWalletTopup::where('buyer_id', $user->id)->orderByDesc('created_at')->limit(20)->get()
            : collect();

        $walletTransactions = Schema::hasTable('wallet_transactions')
            ? WalletTransaction::where('owner_type', 'Buyer')
                ->where('owner_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get()
            : collect();

        $platformGcash = Schema::hasTable('platform_gcash_settings')
            ? DB::table('platform_gcash_settings')->where('is_active', true)->orderByDesc('id')->first()
            : null;

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
            'buyerWallet',
            'walletTopups',
            'walletTransactions',
            'platformGcash',
            'justGrantedDailySpin',
            'wheelSegments',
            'redeemCatalog',
            'transactions',
            'activeRewardVouchers'
        ));
    }

    public function topupWallet(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $validated = $request->validate([
            'sender_account_name'   => ['required', 'string', 'min:2', 'max:120'],
            'sender_account_number' => ['nullable', 'string', 'max:25'],
            'reference_number'      => ['required', 'string', 'min:4', 'max:80'],
            'screenshot'            => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'sender_account_name.required' => 'Please enter the GCash sender account name.',
            'reference_number.required'    => 'Please enter the unique GCash reference number from your receipt.',
            'screenshot.required'          => 'Please upload a clear screenshot of your GCash transfer receipt.',
        ]);

        $file = $request->file('screenshot');
        $filename = 'buyer_wallet_topups/topup_' . $request->user()->id . '_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1400, 1400)->toWebp(85);
            Storage::disk('public')->put($filename, (string) $encoded);
        } catch (\Throwable $e) {
            $filename = $file->store('buyer_wallet_topups', 'public');
        }

        $topup = $this->roleWalletService->submitBuyerWalletTopup(
            buyer: $request->user(),
            senderAccountName: $validated['sender_account_name'],
            referenceNumber: $validated['reference_number'],
            screenshotPath: $filename,
            senderAccountNumber: $validated['sender_account_number'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "GCash Top-Up Receipt #{$topup->topup_reference} submitted! Storkia Admin will verify your screenshot and credit the exact amount to your E-Wallet.",
                'topup'   => [
                    'id'                  => $topup->id,
                    'topup_reference'     => $topup->topup_reference,
                    'sender_account_name' => $topup->sender_account_name,
                    'reference_number'    => $topup->reference_number,
                    'status'              => $topup->status,
                    'screenshot_url'      => $topup->screenshot_url,
                    'created_at'          => $topup->created_at?->format('M d, Y h:i A'),
                ],
            ]);
        }

        return back()->with(
            'success',
            "GCash Top-Up Receipt #{$topup->topup_reference} (Ref #{$topup->reference_number}) submitted! Storkia Admin will inspect your screenshot and credit the exact amount to your Storkia E-Wallet."
        );
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