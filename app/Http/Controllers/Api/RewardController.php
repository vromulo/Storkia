<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsVouchers;
use App\Http\Controllers\Controller;
use App\Models\BuyerRewardTransaction;
use App\Models\BuyerRewardWallet;
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
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class RewardController extends Controller
{
    use FormatsVouchers;

    public function __construct(
        protected BuyerRewardService $buyerRewardService,
        protected OrderCompletionService $orderCompletionService,
        protected RoleWalletService $roleWalletService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $buyerId = (int) $user->id;

        try {
            $this->orderCompletionService->runLifecycleMaintenance(buyerId: $buyerId);
        } catch (\Throwable $e) {
            report($e);
        }

        $grant = $this->buyerRewardService->grantDailyLoginSpinIfEligible($buyerId);
        $wallet = $grant['wallet'];
        $buyerWallet = $this->roleWalletService->getOrCreateBuyerWallet($user);

        $activeVouchers = BuyerVoucherClaim::with('voucher.sellerProfile')
            ->where('buyer_id', $buyerId)
            ->whereHas('voucher', function ($query) {
                $query->where('status', 'active')
                    ->where('ends_at', '>=', now())
                    ->whereColumn('used_count', '<', 'total_usage_limit');
            })
            ->latest('claimed_at')
            ->limit(12)
            ->get()
            ->map(fn (BuyerVoucherClaim $claim) => $this->formatVoucher($claim->voucher, $claim))
            ->values();

        $recentTopups = Schema::hasTable('buyer_wallet_topups')
            ? BuyerWalletTopup::where('buyer_id', $buyerId)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(fn (BuyerWalletTopup $t) => $this->formatTopup($t))
                ->values()
            : collect();

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->walletPayload($wallet), [
                'daily_spin_granted' => (bool) $grant['granted'],
                'wheel_segments'     => $this->publicWheelSegments(),
                'redeem_catalog'     => $this->publicRedeemCatalog((int) $wallet->coins_balance),
                'active_vouchers'    => $activeVouchers,
                'buyer_ewallet'      => [
                    'available_balance' => round((float) $buyerWallet->available_balance, 2),
                    'pending_balance'   => round((float) $buyerWallet->pending_balance, 2),
                    'total_earned'      => round((float) $buyerWallet->total_earned, 2),
                    'total_withdrawn'   => round((float) $buyerWallet->total_withdrawn, 2),
                ],
                'platform_gcash'     => $this->resolvePlatformGcashSettings(),
                'recent_topups'      => $recentTopups,
            ]),
        ]);
    }

    public function walletOverview(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $buyerWallet = $this->roleWalletService->getOrCreateBuyerWallet($user);

        $topups = Schema::hasTable('buyer_wallet_topups')
            ? BuyerWalletTopup::where('buyer_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get()
                ->map(fn (BuyerWalletTopup $t) => $this->formatTopup($t))
                ->values()
            : collect();

        $ledger = Schema::hasTable('wallet_transactions')
            ? WalletTransaction::where('owner_type', 'Buyer')
                ->where('owner_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (WalletTransaction $tx) => [
                    'id'                      => (int) $tx->id,
                    'transaction_reference'   => $tx->transaction_reference,
                    'entry_type'              => $tx->entry_type,
                    'payment_method'          => $tx->payment_method,
                    'amount'                  => (float) $tx->amount,
                    'available_balance_after' => (float) $tx->available_balance_after,
                    'status'                  => $tx->status,
                    'description'             => $tx->description,
                    'created_at'              => $tx->created_at?->toIso8601String(),
                ])
                ->values()
            : collect();

        return response()->json([
            'success' => true,
            'data'    => [
                'buyer_ewallet'  => [
                    'available_balance' => round((float) $buyerWallet->available_balance, 2),
                    'pending_balance'   => round((float) $buyerWallet->pending_balance, 2),
                    'total_earned'      => round((float) $buyerWallet->total_earned, 2),
                    'total_withdrawn'   => round((float) $buyerWallet->total_withdrawn, 2),
                ],
                'platform_gcash' => $this->resolvePlatformGcashSettings(),
                'topups'         => $topups,
                'transactions'   => $ledger,
            ],
        ]);
    }

    public function submitWalletTopup(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => "GCash Top-Up Receipt #{$topup->topup_reference} submitted! Storkia Admin will verify your screenshot and credit the exact amount to your E-Wallet.",
            'data'    => $this->formatTopup($topup),
        ]);
    }

    public function chestStatus(Request $request): JsonResponse
    {
        $wallet = $this->buyerRewardService->getOrCreateWallet((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => $this->walletPayload($wallet),
        ]);
    }

    public function openChest(Request $request): JsonResponse
    {
        $buyerId = (int) $request->user()->id;

        try {
            $result = $this->buyerRewardService->openDailyChest($buyerId);
        } catch (ValidationException $e) {
            return $this->failureResponse($e, $buyerId);
        }

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function spin(Request $request): JsonResponse
    {
        $buyerId = (int) $request->user()->id;

        try {
            $result = $this->buyerRewardService->spinWheel($buyerId);
        } catch (ValidationException $e) {
            return $this->failureResponse($e, $buyerId);
        }

        $result['segment'] = $this->publicSegment($result['segment']);

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function redeem(Request $request): JsonResponse
    {
        $buyerId = (int) $request->user()->id;

        try {
            $validated = $request->validate([
                'catalog_key' => ['required', 'string', 'max:40'],
            ]);

            $result = $this->buyerRewardService->redeemCoinsForVoucher($buyerId, $validated['catalog_key']);
        } catch (ValidationException $e) {
            return $this->failureResponse($e, $buyerId);
        }

        $voucher = $result['voucher'];
        $claim = BuyerVoucherClaim::where('voucher_id', $voucher->id)
            ->where('buyer_id', $buyerId)
            ->first();

        return response()->json([
            'success'        => true,
            'message'        => $result['message'],
            'coins_balance'  => (int) $result['coins_balance'],
            'coins_spent'    => (int) $result['coins_spent'],
            'voucher'        => $this->formatVoucher($voucher, $claim),
            'redeem_catalog' => $this->publicRedeemCatalog((int) $result['coins_balance']),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);

        $paginator = BuyerRewardTransaction::with('voucher')
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn (BuyerRewardTransaction $tx) => [
            'id'                  => (int) $tx->id,
            'reference_code'      => $tx->reference_code,
            'source_type'         => $tx->source_type,
            'reward_type'         => $tx->reward_type,
            'coins_delta'         => (int) $tx->coins_delta,
            'spins_delta'         => (int) $tx->spins_delta,
            'coins_balance_after' => (int) $tx->coins_balance_after,
            'spins_balance_after' => (int) $tx->spins_balance_after,
            'stage_level'         => $tx->stage_level !== null ? (int) $tx->stage_level : null,
            'description'         => $tx->description,
            'voucher_code'        => $tx->voucher?->code,
            'created_at'          => $tx->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $paginator,
        ]);
    }

    protected function resolvePlatformGcashSettings(): ?array
    {
        if (! Schema::hasTable('platform_gcash_settings')) {
            return null;
        }

        $platGcash = DB::table('platform_gcash_settings')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (! $platGcash || empty($platGcash->account_name) || empty($platGcash->gcash_number)) {
            return null;
        }

        return [
            'account_name'  => $platGcash->account_name,
            'gcash_number'  => $platGcash->gcash_number,
            'qr_image_path' => $platGcash->qr_image_path,
            'qr_image_url'  => ! empty($platGcash->qr_image_path) ? asset('storage/' . $platGcash->qr_image_path) : null,
            'instructions'  => $platGcash->instructions ?? null,
        ];
    }

    protected function formatTopup(BuyerWalletTopup $t): array
    {
        return [
            'id'                    => (int) $t->id,
            'topup_reference'       => $t->topup_reference,
            'sender_account_name'   => $t->sender_account_name,
            'sender_account_number' => $t->sender_account_number,
            'reference_number'      => $t->reference_number,
            'approved_amount'       => $t->approved_amount !== null ? (float) $t->approved_amount : null,
            'status'                => $t->status,
            'admin_remarks'         => $t->admin_remarks,
            'rejection_reason'      => $t->rejection_reason,
            'screenshot_url'        => $t->screenshot_url,
            'reviewed_at'           => $t->reviewed_at?->toIso8601String(),
            'created_at'            => $t->created_at?->toIso8601String(),
        ];
    }

    protected function walletPayload(BuyerRewardWallet $wallet): array
    {
        $chestCooldown = $wallet->secondsUntilNextDailyChest();
        $balance = (int) $wallet->coins_balance;

        return [
            'wallet'     => [
                'coins_balance'          => $balance,
                'coins_peso_equivalent'  => round($balance * BuyerRewardService::COIN_TO_PESO_VALUE, 2),
                'coin_to_peso_value'     => BuyerRewardService::COIN_TO_PESO_VALUE,
                'coins_per_peso'         => BuyerRewardService::COINS_PER_PESO,
                'min_checkout_coins'     => BuyerRewardService::MIN_CHECKOUT_REDEEMABLE_COINS,
                'lifetime_coins_earned'  => (int) $wallet->lifetime_coins_earned,
                'lifetime_coins_spent'   => (int) $wallet->lifetime_coins_spent,
                'spin_tickets'           => (int) $wallet->spin_tickets,
            ],
            'chest'      => [
                'can_open'       => $wallet->canOpenDailyChest(),
                'cooldown_sec'   => $chestCooldown,
                'cooldown_hours' => BuyerRewardWallet::COOLDOWN_HOURS,
                'last_opened_at' => $wallet->last_chest_opened_at?->toIso8601String(),
                'next_available' => ($wallet->last_chest_opened_at && $chestCooldown > 0)
                    ? $wallet->last_chest_opened_at->copy()->addHours(BuyerRewardWallet::COOLDOWN_HOURS)->toIso8601String()
                    : null,
            ],
            'daily_spin' => [
                'can_claim'    => $wallet->canClaimDailyLoginSpin(),
                'cooldown_sec' => $wallet->secondsUntilNextDailyLoginSpin(),
            ],
        ];
    }

    protected function publicSegment(array $segment): array
    {
        return [
            'index'      => (int) $segment['index'],
            'key'        => $segment['key'],
            'label'      => $segment['label'],
            'sub_label'  => $segment['sub_label'],
            'type'       => $segment['type'],
            'coins'      => (int) ($segment['coins'] ?? 0),
            'spins'      => (int) ($segment['spins'] ?? 0),
            'color'      => $segment['color'],
            'text_color' => $segment['text_color'],
        ];
    }

    protected function publicWheelSegments(): array
    {
        return collect($this->buyerRewardService->getWheelSegments())
            ->map(fn (array $segment) => $this->publicSegment($segment))
            ->values()
            ->all();
    }

    protected function publicRedeemCatalog(int $coinsBalance): array
    {
        return collect($this->buyerRewardService->getRedeemableVoucherCatalog())
            ->map(fn (array $item) => [
                'key'            => $item['key'],
                'coins_cost'     => (int) $item['coins_cost'],
                'badge'          => $item['badge'],
                'title'          => $item['title'],
                'description'    => $item['description'],
                'scope'          => $item['scope'],
                'discount_type'  => $item['discount_type'],
                'discount_value' => (float) $item['discount_value'],
                'max_cap'        => $item['max_cap'] !== null ? (float) $item['max_cap'] : null,
                'min_spend'      => (float) $item['min_spend'],
                'valid_days'     => (int) ($item['valid_days'] ?? 30),
                'can_afford'     => $coinsBalance >= (int) $item['coins_cost'],
            ])
            ->values()
            ->all();
    }

    protected function failureResponse(ValidationException $e, int $buyerId): JsonResponse
    {
        $wallet = $this->buyerRewardService->getOrCreateWallet($buyerId);
        $message = collect($e->errors())->flatten()->first() ?? 'This request could not be completed.';

        return response()->json([
            'success'            => false,
            'message'            => $message,
            'errors'             => $e->errors(),
            'coins_balance'      => (int) $wallet->coins_balance,
            'spin_tickets'       => (int) $wallet->spin_tickets,
            'chest_cooldown_sec' => $wallet->secondsUntilNextDailyChest(),
        ], 422);
    }
}