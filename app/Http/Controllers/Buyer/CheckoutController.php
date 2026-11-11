<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerWalletTopup;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\SellerProfile;
use App\Models\StorkVoucher;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerCancellationGuardService;
use App\Services\BuyerRewardService;
use App\Services\InventoryStockService;
use App\Services\RoleWalletService;
use App\Services\ShippingCalculatorService;
use App\Services\StorkSchemaBootstrapService;
use App\Services\SuperDealPricingService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected ShippingCalculatorService $shippingCalculatorService,
        protected VoucherService $voucherService,
        protected BuyerCancellationGuardService $cancellationGuardService,
        protected SuperDealPricingService $superDealPricingService,
        protected RoleWalletService $roleWalletService,
        protected BuyerRewardService $buyerRewardService
    ) {}

    protected function resolvePlatformGcashSettings(): ?object
    {
        if (! Schema::hasTable('platform_gcash_settings')) {
            return null;
        }

        $platGcash = DB::table('platform_gcash_settings')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($platGcash && ! empty($platGcash->account_name) && ! empty($platGcash->gcash_number)) {
            return (object) [
                'account_name'  => $platGcash->account_name,
                'gcash_number'  => $platGcash->gcash_number,
                'qr_image_path' => $platGcash->qr_image_path,
                'qr_image_url'  => ! empty($platGcash->qr_image_path) ? asset('storage/' . $platGcash->qr_image_path) : null,
                'is_active'     => (bool) $platGcash->is_active,
                'instructions'  => $platGcash->instructions ?? null,
            ];
        }

        return null;
    }

    protected function assertPerBuyerLimitNotExceeded(int $buyerId, Product $product, int $checkoutQuantity): void
    {
        if (! Schema::hasColumn('products', 'per_buyer_limit')) {
            return;
        }

        $limit = (int) ($product->per_buyer_limit ?? 0);
        if ($limit <= 0) {
            return;
        }

        $previouslyPurchased = (int) OrderItem::where('product_id', $product->id)
            ->whereHas('sellerOrder', function ($q) use ($buyerId) {
                $q->where('buyer_id', $buyerId)
                  ->whereNotIn('status', ['cancelled', 'rejected', 'returned_to_seller']);
            })
            ->sum('quantity');

        if (($previouslyPurchased + $checkoutQuantity) > $limit) {
            throw ValidationException::withMessages([
                'quantity' => "Purchase limit reached for \"{$product->name}\": Maximum {$limit} unit(s) allowed per buyer across orders.",
            ]);
        }
    }

    public function buyNow(Request $request)
    {
        $validated = $request->validate([
            'product_id'  => ['required', 'exists:products,id'],
            'quantity'    => ['required', 'integer', 'min:1', 'max:99999'],
            'variant'     => ['nullable', 'string'],
            'sub_variant' => ['nullable', 'string'],
        ]);

        $product = Product::with(['approval', 'user.sellerProfile'])->findOrFail($validated['product_id']);
        $sellerProfile = $product->user?->sellerProfile;

        $this->superDealPricingService->assertStoreNotOnVacation($sellerProfile);
        $this->assertPerBuyerLimitNotExceeded((int) $request->user()->id, $product, (int) $validated['quantity']);

        $defaultAddress = $request->user()->addresses()->orderByDesc('is_default')->latest('id')->first();
        if ($defaultAddress && $sellerProfile) {
            $routeCheck = $this->shippingCalculatorService->isRouteServiceable(
                $sellerProfile->province,
                $sellerProfile->municipality,
                $defaultAddress->province,
                $defaultAddress->municipality
            );

            if (! $routeCheck['is_serviceable']) {
                return back()->withErrors([
                    'stock' => $routeCheck['reason'],
                ]);
            }
        }

        $resolved = $this->inventoryStockService->resolveProductSelection(
            $product,
            $validated['variant'] ?? null,
            $validated['sub_variant'] ?? null
        );

        if ($resolved['available_stock'] < (int) $validated['quantity']) {
            return back()->withErrors([
                'stock' => "Only {$resolved['available_stock']} unit(s) available for {$product->name}.",
            ]);
        }

        if ($sellerProfile) {
            $this->cancellationGuardService->validateCanCheckoutItems((int) $request->user()->id, [
                [
                    'product'        => $product,
                    'seller_profile' => $sellerProfile,
                ],
            ]);
        }

        $request->session()->put('checkout_buy_now', [
            'product_id'          => (int) $product->id,
            'quantity'            => (int) $validated['quantity'],
            'variant'             => $validated['variant'] ?? null,
            'sub_variant'         => $validated['sub_variant'] ?? null,
            'snapshot_unit_price' => (float) $resolved['discounted_unit_price'],
        ]);

        return redirect()->route('checkout.index', ['mode' => 'buy_now']);
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $mode = $request->query('mode', 'cart');

        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $selectedAddressId = $request->query('address_id');
        $selectedAddress = $selectedAddressId
            ? $addresses->firstWhere('id', (int) $selectedAddressId)
            : ($addresses->firstWhere('is_default', true) ?? $addresses->first());

        $rawLineItems = $this->collectCheckoutLineItems($request, $mode);

        if (empty($rawLineItems)) {
            if ($request->boolean('live_preview') || $request->wantsJson()) {
                return response()->json([
                    'success'  => false,
                    'redirect' => route('cart.index'),
                    'message'  => 'Please select at least one valid product to proceed to checkout.',
                ], 422);
            }

            return redirect()->route('cart.index')->withErrors([
                'cart' => 'Please select at least one valid product to proceed to checkout.',
            ]);
        }

        $this->cancellationGuardService->validateCanCheckoutItems((int) $user->id, $rawLineItems);

        $requestedStoreVouchers = (array) $request->query('store_vouchers', []);
        $requestedPlatformVoucher = trim((string) $request->query('platform_voucher_code', ''));
        $requestedShippingVoucher = trim((string) $request->query('shipping_voucher_code', ''));
        $useStorkiaCoins = $request->boolean('use_storkia_coins');
        $requestedCoinsToUse = $request->filled('coins_to_use') ? (int) $request->query('coins_to_use') : null;

        $checkoutSummary = $this->buildGroupedSellerPreview(
            rawLineItems: $rawLineItems,
            selectedAddress: $selectedAddress,
            buyerId: (int) $user->id,
            storeVoucherCodes: $requestedStoreVouchers,
            platformVoucherCode: $requestedPlatformVoucher,
            shippingVoucherCode: $requestedShippingVoucher,
            useStorkiaCoins: $useStorkiaCoins,
            strictVoucherErrors: false,
            requestedCoinsToUse: $requestedCoinsToUse
        );

        $buyerWallet = $this->roleWalletService->getOrCreateBuyerWallet($user);
        $buyerWalletBalance = round((float) $buyerWallet->available_balance, 2);

        if ($request->boolean('live_preview')) {
            $packagesPayload = [];
            foreach ($checkoutSummary['seller_packages'] as $pkg) {
                $spId = (int) $pkg['seller_profile']->id;
                $packagesPayload[$spId] = [
                    'subtotal'           => (float) $pkg['subtotal'],
                    'bundle_discount'    => (float) $pkg['bundle_discount'],
                    'store_discount'     => (float) $pkg['store_discount'],
                    'platform_discount'  => (float) $pkg['platform_discount'],
                    'shipping_discount'  => (float) $pkg['shipping_discount'],
                    'coins_used'         => (int) ($pkg['coins_used'] ?? 0),
                    'coins_discount'     => (float) ($pkg['coins_discount'] ?? 0),
                    'shipping_fee'       => (float) ($pkg['shipping']['shipping_fee'] ?? 0),
                    'net_subtotal'       => (float) $pkg['net_subtotal'],
                    'net_shipping_fee'   => (float) $pkg['net_shipping_fee'],
                    'total'              => (float) $pkg['total'],
                    'shipping_tier'      => (string) ($pkg['shipping']['tier'] ?? ''),
                    'is_serviceable'     => (bool) ($pkg['shipping']['is_serviceable'] ?? true),
                    'store_voucher_code' => $pkg['store_voucher']?->code ?? '',
                ];
            }

            return response()->json([
                'success'                => true,
                'is_address_serviceable' => (bool) $checkoutSummary['is_address_serviceable'],
                'buyer_wallet_balance'   => $buyerWalletBalance,
                'selected_address'       => $selectedAddress ? [
                    'id'           => (int) $selectedAddress->id,
                    'full_name'    => trim($selectedAddress->first_name . ' ' . $selectedAddress->last_name),
                    'phone_number' => ltrim((string) $selectedAddress->phone_number, '0'),
                    'full_address' => collect([
                        $selectedAddress->building_details,
                        $selectedAddress->street_address,
                        $selectedAddress->barangay,
                        $selectedAddress->municipality,
                        $selectedAddress->province,
                        $selectedAddress->postcode,
                    ])->filter()->implode(', '),
                ] : null,
                'packages'               => $packagesPayload,
                'summary'                => [
                    'totalItemsCount'         => (int) $checkoutSummary['total_items_count'],
                    'overallSubtotal'         => (float) $checkoutSummary['overall_subtotal'],
                    'overallShipping'         => (float) $checkoutSummary['overall_shipping'],
                    'overallStoreDiscount'    => (float) $checkoutSummary['overall_store_discount'],
                    'overallPlatformDiscount' => (float) $checkoutSummary['overall_platform_discount'],
                    'overallShippingDiscount' => (float) $checkoutSummary['overall_shipping_discount'],
                    'overallCoinsUsed'        => (int) $checkoutSummary['overall_coins_used'],
                    'overallCoinsDiscount'    => (float) $checkoutSummary['overall_coins_discount'],
                    'maxRedeemableCoins'      => (int) $checkoutSummary['max_redeemable_coins'],
                    'maxRedeemableDiscount'   => (float) $checkoutSummary['max_redeemable_discount'],
                    'buyerCoinsBalance'       => (int) $checkoutSummary['buyer_coins_balance'],
                    'overallTotal'            => (float) $checkoutSummary['overall_total'],
                    'appliedPlatformCode'     => $checkoutSummary['applied_platform_voucher']?->code ?? '',
                    'appliedShippingCode'     => $checkoutSummary['applied_shipping_voucher']?->code ?? '',
                ],
                'voucher_notices'        => array_values($checkoutSummary['voucher_notices']),
                'price_signature'        => $checkoutSummary['price_signature'],
            ]);
        }

        $availableVouchers = $this->voucherService->getBuyerAvailableVouchers((int) $user->id);
        $platformGcash = $this->resolvePlatformGcashSettings();
        $recentWalletTopups = Schema::hasTable('buyer_wallet_topups')
            ? BuyerWalletTopup::where('buyer_id', $user->id)->orderByDesc('created_at')->limit(5)->get()
            : collect();
        $idempotencyKey = (string) Str::uuid();

        return view('buyer.checkout', [
            'mode'                    => $mode,
            'addresses'               => $addresses,
            'selectedAddress'         => $selectedAddress,
            'isAddressServiceable'    => $checkoutSummary['is_address_serviceable'],
            'sellerPackages'          => $checkoutSummary['seller_packages'],
            'overallSubtotal'         => $checkoutSummary['overall_subtotal'],
            'overallShipping'         => $checkoutSummary['overall_shipping'],
            'overallStoreDiscount'    => $checkoutSummary['overall_store_discount'],
            'overallPlatformDiscount' => $checkoutSummary['overall_platform_discount'],
            'overallShippingDiscount' => $checkoutSummary['overall_shipping_discount'],
            'overallCoinsUsed'        => $checkoutSummary['overall_coins_used'],
            'overallCoinsDiscount'    => $checkoutSummary['overall_coins_discount'],
            'maxRedeemableCoins'      => $checkoutSummary['max_redeemable_coins'],
            'maxRedeemableDiscount'   => $checkoutSummary['max_redeemable_discount'],
            'buyerCoinsBalance'       => $checkoutSummary['buyer_coins_balance'],
            'useStorkiaCoins'         => $useStorkiaCoins,
            'buyerWalletBalance'      => $buyerWalletBalance,
            'platformGcash'           => $platformGcash,
            'recentWalletTopups'      => $recentWalletTopups,
            'overallTotal'            => $checkoutSummary['overall_total'],
            'appliedPlatformVoucher'  => $checkoutSummary['applied_platform_voucher'],
            'appliedShippingVoucher'  => $checkoutSummary['applied_shipping_voucher'],
            'voucherNotices'          => $checkoutSummary['voucher_notices'],
            'priceSignature'          => $checkoutSummary['price_signature'],
            'availableVouchers'       => $availableVouchers,
            'totalItemsCount'         => $checkoutSummary['total_items_count'],
            'selectedCartIds'         => $checkoutSummary['cart_ids'],
            'idempotencyKey'          => $idempotencyKey,
        ]);
    }

    public function store(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $validated = $request->validate([
            'user_address_id'       => ['required', 'integer', 'exists:user_addresses,id'],
            'mode'                  => ['required', 'in:cart,buy_now'],
            'cart_ids'              => ['nullable', 'array'],
            'cart_ids.*'            => ['integer'],
            'idempotency_key'       => ['required', 'string', 'max:64'],
            'price_signature'       => ['nullable', 'string'],
            'seller_notes'          => ['nullable', 'array'],
            'seller_notes.*'        => ['nullable', 'string', 'max:200'],
            'store_voucher_codes'   => ['nullable', 'array'],
            'store_voucher_codes.*' => ['nullable', 'string', 'max:25'],
            'platform_voucher_code' => ['nullable', 'string', 'max:25'],
            'shipping_voucher_code' => ['nullable', 'string', 'max:25'],
            'use_storkia_coins'     => ['nullable', 'boolean'],
            'coins_to_use'          => ['nullable', 'integer', 'min:0'],
            'payment_methods'       => ['required', 'array'],
            'payment_methods.*'     => ['required', 'in:COD,WALLET'],
        ], [
            'user_address_id.required' => 'Please select a delivery address.',
            'payment_methods.required' => 'Please select a payment method (COD or Storkia E-Wallet) for each store package.',
            'payment_methods.*.in'     => 'Please choose either Cash on Delivery (COD) or Storkia E-Wallet (WALLET).',
            'seller_notes.*.max'       => 'Note to seller cannot exceed 200 characters.',
        ]);

        $user = $request->user();

        $existingOrder = Order::where('idempotency_key', $validated['idempotency_key'])
            ->where('buyer_id', $user->id)
            ->first();

        if ($existingOrder) {
            return redirect()->route('checkout.success', $existingOrder->id);
        }

        $selectedAddress = $user->addresses()->findOrFail($validated['user_address_id']);

        if (! $this->shippingCalculatorService->isAddressServiceable($selectedAddress->province, $selectedAddress->municipality)) {
            return back()->withErrors([
                'address' => "Delivery to {$selectedAddress->municipality}, {$selectedAddress->province} is currently unserviceable by our partner logistics hubs. Please select a serviceable address.",
            ])->withInput();
        }

        $rawLineItems = $this->collectCheckoutLineItems($request, $validated['mode']);

        if (empty($rawLineItems)) {
            return redirect()->route('cart.index')->withErrors([
                'cart' => 'Your checkout items are empty or have already been processed.',
            ]);
        }

        $this->cancellationGuardService->validateCanCheckoutItems((int) $user->id, $rawLineItems);

        if (! empty($validated['price_signature'])) {
            $expectedPrices = json_decode((string) $validated['price_signature'], true);
            if (is_array($expectedPrices)) {
                foreach ($rawLineItems as $line) {
                    $sigKey = $this->buildLineSignatureKey(
                        $line['product']->id,
                        $line['variant_name'],
                        $line['sub_variant_name'],
                        $line['pricing_label'] ?? 'Regular'
                    );
                    if (isset($expectedPrices[$sigKey])) {
                        $expectedUnit = round((float) $expectedPrices[$sigKey], 2);
                        $currentUnit = round((float) $line['discounted_unit_price'], 2);
                        if (abs($expectedUnit - $currentUnit) > 0.009) {
                            return redirect()
                                ->route('checkout.index', array_filter([
                                    'mode'       => $validated['mode'],
                                    'address_id' => $selectedAddress->id,
                                    'cart_ids'   => implode(',', $validated['cart_ids'] ?? []),
                                ]))
                                ->withErrors([
                                    'price_changed' => "The price of \"{$line['product']->name}\" changed from ₱" . number_format($expectedUnit, 2) . " to ₱" . number_format($currentUnit, 2) . ". Please review the updated checkout total before placing your order.",
                                ]);
                        }
                    }
                }
            }
        }

        $deliverySnapshot = [
            'first_name'        => $selectedAddress->first_name,
            'last_name'         => $selectedAddress->last_name,
            'recipient_name'    => trim("{$selectedAddress->first_name} {$selectedAddress->last_name}"),
            'phone_number'      => '+63' . ltrim($selectedAddress->phone_number, '0'),
            'country'           => $selectedAddress->country ?? 'Philippines',
            'province_code'     => $selectedAddress->province_code,
            'province'          => $selectedAddress->province,
            'municipality_code' => $selectedAddress->municipality_code,
            'municipality'      => $selectedAddress->municipality,
            'barangay_code'     => $selectedAddress->barangay_code,
            'barangay'          => $selectedAddress->barangay,
            'postcode'          => $selectedAddress->postcode,
            'street_address'    => $selectedAddress->street_address,
            'building_details'  => $selectedAddress->building_details,
            'full_address'      => collect([
                $selectedAddress->building_details,
                $selectedAddress->street_address,
                $selectedAddress->barangay,
                $selectedAddress->municipality,
                $selectedAddress->province,
                $selectedAddress->postcode,
            ])->filter()->implode(', '),
        ];

        $notificationsToDispatch = [];

        $order = DB::transaction(function () use (
            $request,
            $user,
            $selectedAddress,
            $deliverySnapshot,
            $rawLineItems,
            $validated,
            &$notificationsToDispatch
        ) {
            $preview = $this->buildGroupedSellerPreview(
                rawLineItems: $rawLineItems,
                selectedAddress: $selectedAddress,
                buyerId: (int) $user->id,
                storeVoucherCodes: (array) ($validated['store_voucher_codes'] ?? []),
                platformVoucherCode: (string) ($validated['platform_voucher_code'] ?? ''),
                shippingVoucherCode: (string) ($validated['shipping_voucher_code'] ?? ''),
                useStorkiaCoins: $request->boolean('use_storkia_coins'),
                strictVoucherErrors: true,
                requestedCoinsToUse: isset($validated['coins_to_use']) ? (int) $validated['coins_to_use'] : null
            );

            $totalWalletRequired = 0.00;

            foreach ($preview['seller_packages'] as $pkg) {
                /** @var SellerProfile $sellerProfile */
                $sellerProfile = $pkg['seller_profile'];
                $spId = (int) $sellerProfile->id;
                $chosenMethod = strtoupper((string) ($validated['payment_methods'][$spId] ?? 'COD'));

                if (! in_array($chosenMethod, ['COD', 'WALLET'], true)) {
                    throw ValidationException::withMessages([
                        'payment_methods' => "Please select a valid payment method for {$sellerProfile->business_name}.",
                    ]);
                }

                if ($chosenMethod === 'WALLET') {
                    $totalWalletRequired = round($totalWalletRequired + (float) $pkg['total'], 2);
                }
            }

            if ($totalWalletRequired > 0) {
                $buyerWallet = $this->roleWalletService->getOrCreateBuyerWallet($user);
                $availableBalance = round((float) $buyerWallet->available_balance, 2);
                if ($availableBalance + 0.0001 < $totalWalletRequired) {
                    $shortfall = round($totalWalletRequired - $availableBalance, 2);
                    throw ValidationException::withMessages([
                        'payment_methods' => 'Insufficient Storkia E-Wallet balance (Available: ₱' . number_format($availableBalance, 2) . ', Required: ₱' . number_format($totalWalletRequired, 2) . '). Please top up ₱' . number_format($shortfall, 2) . ' via GCash or switch to Cash on Delivery (COD).',
                    ]);
                }
            }

            foreach ($rawLineItems as $line) {
                $this->inventoryStockService->deductStock(
                    $line['product']->id,
                    $line['variant_name'],
                    $line['sub_variant_name'],
                    $line['quantity']
                );

                if (! empty($line['super_deal_id']) && Schema::hasTable('super_deals')) {
                    DB::table('super_deals')
                        ->where('id', (int) $line['super_deal_id'])
                        ->increment('sold_quantity', (int) $line['quantity']);
                }
            }

            $allMethods = array_map('strtoupper', array_values($validated['payment_methods']));
            $parentMethod = count(array_unique($allMethods)) === 1 ? $allMethods[0] : 'MIXED';
            $allWallet = $parentMethod === 'WALLET';

            $orderCreatePayload = [
                'order_number'       => $this->generateUniqueOrderNumber(),
                'buyer_id'           => $user->id,
                'user_address_id'    => $selectedAddress->id,
                'address_snapshot'   => $deliverySnapshot,
                'payment_method'     => $parentMethod,
                'payment_status'     => $allWallet ? 'paid' : 'unpaid',
                'status'             => 'pending',
                'subtotal_amount'    => $preview['overall_net_subtotal'],
                'shipping_fee_total' => $preview['overall_net_shipping'],
                'total_amount'       => $preview['overall_total'],
                'idempotency_key'    => $validated['idempotency_key'],
            ];

            if (Schema::hasColumn('orders', 'coins_used_total')) {
                $orderCreatePayload['coins_used_total'] = (int) $preview['overall_coins_used'];
                $orderCreatePayload['coins_discount_total'] = (float) $preview['overall_coins_discount'];
                $orderCreatePayload['wallet_paid_total'] = $totalWalletRequired;
            }

            $parentOrder = Order::create($orderCreatePayload);

            $platformVoucherConsumed = false;
            $shippingVoucherConsumed = false;

            foreach ($preview['seller_packages'] as $pkg) {
                /** @var SellerProfile $sellerProfile */
                $sellerProfile = $pkg['seller_profile'];
                $spId = (int) $sellerProfile->id;
                $chosenMethod = strtoupper((string) ($validated['payment_methods'][$spId] ?? 'COD'));
                $isWalletPaid = $chosenMethod === 'WALLET';
                $buyerNote = trim((string) ($validated['seller_notes'][$spId] ?? ''));

                $pickupSnapshot = [
                    'business_name' => $sellerProfile->business_name,
                    'contact_no'    => $sellerProfile->contact_no,
                    'province'      => $sellerProfile->province,
                    'municipality'  => $sellerProfile->municipality,
                    'barangay'      => $sellerProfile->barangay,
                    'street'        => $sellerProfile->street,
                    'house_details' => $sellerProfile->house_details,
                    'full_address'  => $sellerProfile->full_address,
                ];

                $sellerOrder = SellerOrder::create([
                    'order_id'                  => $parentOrder->id,
                    'seller_order_number'       => $this->generateUniqueSellerOrderNumber(),
                    'buyer_id'                  => $user->id,
                    'seller_id'                 => $sellerProfile->user_id,
                    'seller_profile_id'         => $sellerProfile->id,
                    'logistics_profile_id'      => null,
                    'status'                    => 'pending_seller_acceptance',
                    'payment_status'            => $isWalletPaid ? 'paid' : 'unpaid',
                    'subtotal_amount'           => $pkg['net_subtotal'],
                    'shipping_fee'              => $pkg['net_shipping_fee'],
                    'total_amount'              => $pkg['total'],
                    'total_weight'              => $pkg['total_weight'],
                    'shipping_tier'             => $pkg['shipping']['tier'],
                    'pickup_address_snapshot'   => $pickupSnapshot,
                    'delivery_address_snapshot' => $deliverySnapshot,
                    'acceptance_deadline_at'    => now()->addHours(SellerOrder::ACCEPTANCE_DEADLINE_HOURS),
                    'stock_deducted'            => true,
                    'stock_restored'            => false,
                ]);

                foreach ($pkg['items'] as $itemData) {
                    $labelSuffix = ! empty($itemData['pricing_label']) && $itemData['pricing_label'] !== 'Regular' && $itemData['pricing_label'] !== 'Regular Price'
                        ? ' [' . $itemData['pricing_label'] . ']'
                        : '';

                    OrderItem::create([
                        'order_id'              => $parentOrder->id,
                        'seller_order_id'       => $sellerOrder->id,
                        'product_id'            => $itemData['product']->id,
                        'product_name'          => $itemData['product']->name . $labelSuffix,
                        'product_image'         => $itemData['image_path'],
                        'variant_id'            => $itemData['variant_id'],
                        'variant_name'          => $itemData['variant_name'],
                        'sub_variant_id'        => $itemData['sub_variant_id'],
                        'sub_variant_name'      => $itemData['sub_variant_name'],
                        'unit_price'            => $itemData['unit_price'],
                        'discount_percent'      => $itemData['discount_percent'],
                        'discounted_unit_price' => $itemData['discounted_unit_price'],
                        'unit_weight'           => $itemData['unit_weight'],
                        'quantity'              => $itemData['quantity'],
                        'line_total'            => $itemData['line_total'],
                        'line_weight'           => $itemData['line_weight'],
                    ]);
                }

                $walletTx = null;
                if ($isWalletPaid && (float) $pkg['total'] > 0) {
                    $walletTx = $this->roleWalletService->debitBuyerWalletForCheckout(
                        buyer: $user,
                        amount: (float) $pkg['total'],
                        sellerOrderId: (int) $sellerOrder->id,
                        sellerOrderNumber: $sellerOrder->seller_order_number
                    );
                }

                Payment::create([
                    'order_id'          => $parentOrder->id,
                    'seller_order_id'   => $sellerOrder->id,
                    'payment_reference' => $walletTx?->transaction_reference ?? ('PAY-' . strtoupper(Str::random(10))),
                    'method'            => $chosenMethod,
                    'status'            => $isWalletPaid ? 'paid' : 'unpaid',
                    'product_subtotal'  => $pkg['net_subtotal'],
                    'shipping_fee'      => $pkg['net_shipping_fee'],
                    'total_amount'      => $pkg['total'],
                    'collected_at'      => $isWalletPaid ? now() : null,
                ]);

                $pkgCoinsUsed = (int) ($pkg['coins_used'] ?? 0);
                $pkgCoinsDiscount = (float) ($pkg['coins_discount'] ?? 0.0);

                if ($pkgCoinsUsed > 0) {
                    $this->buyerRewardService->redeemCoinsForCheckoutOrder(
                        buyerId: (int) $user->id,
                        coinsToSpend: $pkgCoinsUsed,
                        sellerOrderId: (int) $sellerOrder->id,
                        sellerOrderNumber: $sellerOrder->seller_order_number
                    );
                }

                $extraPayload = [
                    'order_id'                  => $parentOrder->id,
                    'seller_order_id'           => $sellerOrder->id,
                    'buyer_note'                => $buyerNote !== '' ? substr($buyerNote, 0, 200) : null,
                    'store_voucher_id'          => $pkg['store_voucher']?->id,
                    'store_voucher_code'        => $pkg['store_voucher']?->code,
                    'store_voucher_discount'    => round($pkg['store_discount'] + ($pkg['bundle_discount'] ?? 0), 2),
                    'platform_voucher_id'       => $pkg['platform_discount'] > 0 ? $preview['applied_platform_voucher']?->id : null,
                    'platform_voucher_code'     => $pkg['platform_discount'] > 0 ? $preview['applied_platform_voucher']?->code : null,
                    'platform_voucher_discount' => round($pkg['platform_discount'], 2),
                    'shipping_voucher_id'       => $pkg['shipping_discount'] > 0 ? $preview['applied_shipping_voucher']?->id : null,
                    'shipping_voucher_code'     => $pkg['shipping_discount'] > 0 ? $preview['applied_shipping_voucher']?->code : null,
                    'shipping_voucher_discount' => $pkg['shipping_discount'],
                    'original_subtotal_amount'  => $pkg['subtotal'],
                    'original_shipping_fee'     => $pkg['shipping']['shipping_fee'],
                ];

                if (Schema::hasColumn('seller_order_extras', 'coins_used')) {
                    $extraPayload['coins_used'] = $pkgCoinsUsed;
                    $extraPayload['coins_discount_amount'] = $pkgCoinsDiscount;
                    $extraPayload['wallet_paid_amount'] = $isWalletPaid ? (float) $pkg['total'] : 0.00;
                }

                SellerOrderExtra::create($extraPayload);

                if ($pkg['store_voucher']) {
                    $this->voucherService->consumeVoucher($pkg['store_voucher'], (int) $user->id, $sellerOrder->id);
                }

                if (! $platformVoucherConsumed && $pkg['platform_discount'] > 0 && $preview['applied_platform_voucher']) {
                    $this->voucherService->consumeVoucher($preview['applied_platform_voucher'], (int) $user->id, $sellerOrder->id);
                    $platformVoucherConsumed = true;
                }

                if (! $shippingVoucherConsumed && $pkg['shipping_discount'] > 0 && $preview['applied_shipping_voucher']) {
                    $this->voucherService->consumeVoucher($preview['applied_shipping_voucher'], (int) $user->id, $sellerOrder->id);
                    $shippingVoucherConsumed = true;
                }

                OrderStatusHistory::create([
                    'order_id'        => $parentOrder->id,
                    'seller_order_id' => $sellerOrder->id,
                    'from_status'     => null,
                    'to_status'       => 'pending_seller_acceptance',
                    'actor_type'      => 'Buyer',
                    'actor_id'        => $user->id,
                    'remarks'         => $isWalletPaid
                        ? "Order placed and paid immediately via Storkia E-Wallet (Ref #{$walletTx?->transaction_reference}). Waiting for seller acceptance within 24 hours."
                        : 'Order placed via Cash on Delivery (COD). Waiting for seller acceptance within 24 hours.',
                ]);

                if ($sellerProfile->user) {
                    $notificationsToDispatch[] = [
                        'recipient'    => $sellerProfile->user,
                        'notification' => new OrderLifecycleNotification(
                            title: 'New Order Received (' . ($isWalletPaid ? 'Storkia E-Wallet Paid' : 'COD') . ')',
                            message: "Order #{$sellerOrder->seller_order_number} (₱" . number_format((float) $sellerOrder->total_amount, 2) . " via {$chosenMethod}) requires your review within 24 hours.",
                            type: 'info',
                            url: route('seller.orders.show', $sellerOrder->id),
                            orderId: $parentOrder->id,
                            sellerOrderId: $sellerOrder->id
                        ),
                    ];
                }
            }

            if ($validated['mode'] === 'cart' && ! empty($preview['cart_ids'])) {
                Cart::where('user_id', $user->id)
                    ->whereIn('id', $preview['cart_ids'])
                    ->delete();
            } else {
                session()->forget('checkout_buy_now');
            }

            return $parentOrder;
        });

        foreach ($notificationsToDispatch as $entry) {
            try {
                $entry['recipient']->notify($entry['notification']);
            } catch (\Throwable $e) {
            }
        }

        try {
            $user->notify(new OrderLifecycleNotification(
                title: 'Order Placed Successfully',
                message: "Your checkout #{$order->order_number} has been split into {$order->sellerOrders()->count()} seller order(s).",
                type: 'info',
                url: route('user.orders'),
                orderId: $order->id
            ));
        } catch (\Throwable $e) {
        }

        return redirect()->route('checkout.success', $order->id)
            ->with('success', 'Your order has been placed successfully!');
    }

    public function success(Request $request, Order $order)
    {
        if ((int) $order->buyer_id !== (int) $request->user()->id) {
            abort(403);
        }

        $order->load([
            'sellerOrders.items',
            'sellerOrders.sellerProfile',
            'sellerOrders.payment',
            'sellerOrders.paymentSubmission',
            'sellerOrders.extra',
        ]);

        return view('buyer.checkout-success', compact('order'));
    }

    protected function buildLineSignatureKey(int $productId, ?string $variantName, ?string $subVariantName, string $pricingLabel = 'Regular'): string
    {
        return $productId . ':' . strtolower(trim((string) $variantName)) . ':' . strtolower(trim((string) $subVariantName)) . ':' . strtolower(trim($pricingLabel));
    }

    protected function collectCheckoutLineItems(Request $request, string $mode): array
    {
        $user = $request->user();
        $lineItems = [];

        if ($mode === 'buy_now') {
            $buyNow = $request->session()->get('checkout_buy_now');
            if (! $buyNow || empty($buyNow['product_id'])) {
                return [];
            }

            $product = Product::with(['approval', 'user.sellerProfile'])->find($buyNow['product_id']);
            if (! $product || ! $product->user?->sellerProfile) {
                return [];
            }

            $this->superDealPricingService->assertStoreNotOnVacation($product->user->sellerProfile);

            $resolved = $this->inventoryStockService->resolveProductSelection(
                $product,
                $buyNow['variant'] ?? null,
                $buyNow['sub_variant'] ?? null
            );

            $qty = max(1, (int) ($buyNow['quantity'] ?? 1));

            $this->assertPerBuyerLimitNotExceeded((int) $user->id, $product, $qty);

            if ($resolved['available_stock'] < $qty) {
                throw ValidationException::withMessages([
                    'stock' => "Insufficient stock for \"{$product->name}\". Available: {$resolved['available_stock']}.",
                ]);
            }

            $splitLines = $this->superDealPricingService->buildSplitCheckoutLines(
                product: $product,
                requestedQty: $qty,
                baseUnitPrice: (float) $resolved['unit_price'],
                unitWeight: (float) $resolved['unit_weight'],
                variantName: $resolved['variant_name'],
                subVariantName: $resolved['sub_variant_name'],
                imagePath: $resolved['image_path']
            );

            foreach ($splitLines as $sLine) {
                $lineItems[] = [
                    'cart_id'               => null,
                    'product'               => $product,
                    'seller_profile'        => $product->user->sellerProfile,
                    'quantity'              => $sLine['quantity'],
                    'variant_id'            => $resolved['variant_id'],
                    'variant_name'          => $sLine['variant_name'],
                    'sub_variant_id'        => $resolved['sub_variant_id'],
                    'sub_variant_name'      => $sLine['sub_variant_name'],
                    'unit_price'            => $sLine['original_unit_price'],
                    'discount_percent'      => $sLine['original_unit_price'] > 0
                        ? round((1 - ($sLine['discounted_unit_price'] / $sLine['original_unit_price'])) * 100, 2)
                        : 0,
                    'discounted_unit_price' => $sLine['discounted_unit_price'],
                    'unit_weight'           => $sLine['unit_weight'],
                    'available_stock'       => $resolved['available_stock'],
                    'image_path'            => $sLine['image_path'],
                    'line_total'            => $sLine['line_total'],
                    'line_weight'           => $sLine['line_weight'],
                    'super_deal_id'         => $sLine['super_deal_id'],
                    'pricing_label'         => $sLine['pricing_label'],
                ];
            }

            return $lineItems;
        }

        $requestedIds = $request->input('cart_ids', []);
        if (is_string($requestedIds)) {
            $requestedIds = array_filter(explode(',', $requestedIds));
        }

        $cartQuery = Cart::with(['product.approval', 'product.user.sellerProfile'])
            ->where('user_id', $user->id)
            ->orderBy('id', 'asc');

        if (! empty($requestedIds)) {
            $cartQuery->whereIn('id', array_map('intval', (array) $requestedIds));
        }

        $cartRecords = $cartQuery->get();

        foreach ($cartRecords as $cartItem) {
            $product = $cartItem->product;
            if (! $product || ! $product->user?->sellerProfile) {
                continue;
            }

            $this->superDealPricingService->assertStoreNotOnVacation($product->user->sellerProfile);

            $resolved = $this->inventoryStockService->resolveProductSelection(
                $product,
                $cartItem->variant,
                $cartItem->sub_variant
            );

            $qty = max(1, (int) $cartItem->quantity);

            $this->assertPerBuyerLimitNotExceeded((int) $user->id, $product, $qty);

            if ($resolved['available_stock'] < $qty) {
                throw ValidationException::withMessages([
                    'stock' => "Insufficient stock for \"{$product->name}\". Available: {$resolved['available_stock']}.",
                ]);
            }

            $splitLines = $this->superDealPricingService->buildSplitCheckoutLines(
                product: $product,
                requestedQty: $qty,
                baseUnitPrice: (float) $resolved['unit_price'],
                unitWeight: (float) $resolved['unit_weight'],
                variantName: $resolved['variant_name'],
                subVariantName: $resolved['sub_variant_name'],
                imagePath: $resolved['image_path']
            );

            foreach ($splitLines as $sLine) {
                $lineItems[] = [
                    'cart_id'               => $cartItem->id,
                    'product'               => $product,
                    'seller_profile'        => $product->user->sellerProfile,
                    'quantity'              => $sLine['quantity'],
                    'variant_id'            => $resolved['variant_id'],
                    'variant_name'          => $sLine['variant_name'],
                    'sub_variant_id'        => $resolved['sub_variant_id'],
                    'sub_variant_name'      => $sLine['sub_variant_name'],
                    'unit_price'            => $sLine['original_unit_price'],
                    'discount_percent'      => $sLine['original_unit_price'] > 0
                        ? round((1 - ($sLine['discounted_unit_price'] / $sLine['original_unit_price'])) * 100, 2)
                        : 0,
                    'discounted_unit_price' => $sLine['discounted_unit_price'],
                    'unit_weight'           => $sLine['unit_weight'],
                    'available_stock'       => $resolved['available_stock'],
                    'image_path'            => $sLine['image_path'],
                    'line_total'            => $sLine['line_total'],
                    'line_weight'           => $sLine['line_weight'],
                    'super_deal_id'         => $sLine['super_deal_id'],
                    'pricing_label'         => $sLine['pricing_label'],
                ];
            }
        }

        return $lineItems;
    }

    protected function buildGroupedSellerPreview(
        array $rawLineItems,
        $selectedAddress,
        int $buyerId,
        array $storeVoucherCodes = [],
        string $platformVoucherCode = '',
        string $shippingVoucherCode = '',
        bool $useStorkiaCoins = false,
        bool $strictVoucherErrors = false,
        ?int $requestedCoinsToUse = null
    ): array {
        $grouped = [];
        $cartIds = [];
        $totalItemsCount = 0;
        $priceMap = [];

        foreach ($rawLineItems as $line) {
            if ($line['cart_id']) {
                $cartIds[] = $line['cart_id'];
            }
            $totalItemsCount += $line['quantity'];

            $sigKey = $this->buildLineSignatureKey(
                $line['product']->id,
                $line['variant_name'],
                $line['sub_variant_name'],
                $line['pricing_label'] ?? 'Regular'
            );
            $priceMap[$sigKey] = round((float) $line['discounted_unit_price'], 2);

            $sellerProfileId = $line['seller_profile']->id;
            if (! isset($grouped[$sellerProfileId])) {
                $grouped[$sellerProfileId] = [
                    'seller_profile' => $line['seller_profile'],
                    'items'          => [],
                    'subtotal'       => 0.0,
                    'total_weight'   => 0.0,
                ];
            }

            $grouped[$sellerProfileId]['items'][] = $line;
            $grouped[$sellerProfileId]['subtotal'] = round($grouped[$sellerProfileId]['subtotal'] + $line['line_total'], 2);
            $grouped[$sellerProfileId]['total_weight'] = round($grouped[$sellerProfileId]['total_weight'] + $line['line_weight'], 3);
        }

        $buyerProvince = $selectedAddress->province ?? '';
        $buyerMunicipality = $selectedAddress->municipality ?? '';
        $buyerBarangay = $selectedAddress->barangay ?? '';

        $isAddressServiceable = $selectedAddress
            ? $this->shippingCalculatorService->isAddressServiceable($buyerProvince, $buyerMunicipality)
            : false;

        $overallSubtotal = 0.0;
        $overallShipping = 0.0;
        $overallStoreDiscount = 0.0;
        $voucherNotices = [];
        $sellerPackages = [];

        foreach ($grouped as $sellerProfileId => $package) {
            /** @var SellerProfile $sellerProfile */
            $sellerProfile = $package['seller_profile'];

            $shipping = $this->shippingCalculatorService->calculateForSellerOrder(
                sellerProvince: $sellerProfile->province ?? '',
                sellerMunicipality: $sellerProfile->municipality ?? '',
                buyerProvince: $buyerProvince,
                buyerMunicipality: $buyerMunicipality,
                totalWeightKg: $package['total_weight'],
                sellerBarangay: $sellerProfile->barangay ?? '',
                buyerBarangay: $buyerBarangay
            );

            if ($selectedAddress && isset($shipping['is_serviceable']) && $shipping['is_serviceable'] === false) {
                $isAddressServiceable = false;
                $reasonMsg = $shipping['unserviceable_reason']
                    ?: "Shipping from {$sellerProfile->business_name} ({$sellerProfile->province}) to {$buyerProvince} is currently unavailable because no registered Storkia Logistics Partner covers both islands.";
                if ($strictVoucherErrors) {
                    throw ValidationException::withMessages([
                        'address' => $reasonMsg,
                    ]);
                }
                if (! in_array($reasonMsg, $voucherNotices, true)) {
                    $voucherNotices[] = $reasonMsg;
                }
            }

            $bundleResult = $this->superDealPricingService->calculateStoreBundleDiscount(
                sellerProfileId: (int) $sellerProfile->id,
                packageLines: $package['items']
            );
            $bundleDiscount = (float) ($bundleResult['bundle_discount'] ?? 0.0);
            $subtotalAfterBundle = max(0.0, round($package['subtotal'] - $bundleDiscount, 2));

            $appliedStoreVoucher = null;
            $storeDiscount = 0.0;
            $rawStoreCode = strtoupper(trim((string) ($storeVoucherCodes[$sellerProfileId] ?? '')));

            if ($rawStoreCode !== '') {
                $candidate = StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [$rawStoreCode])->first();
                if (! $candidate) {
                    if ($strictVoucherErrors) {
                        throw ValidationException::withMessages([
                            'voucher' => "Store voucher \"{$rawStoreCode}\" does not exist.",
                        ]);
                    }
                    $voucherNotices[] = "Voucher \"{$rawStoreCode}\" is invalid and was removed.";
                } else {
                    try {
                        $storeDiscount = $this->voucherService->calculateStoreVoucherDiscount(
                            voucher: $candidate,
                            buyerId: $buyerId,
                            sellerProfileId: (int) $sellerProfile->id,
                            packageItems: $package['items'],
                            packageSubtotal: $subtotalAfterBundle
                        );
                        $appliedStoreVoucher = $candidate;
                    } catch (ValidationException $e) {
                        if ($strictVoucherErrors) {
                            throw $e;
                        }
                        $voucherNotices[] = collect($e->errors())->flatten()->first() ?: "Voucher \"{$rawStoreCode}\" was removed.";
                    }
                }
            }

            $combinedSellerFundedDiscount = round($bundleDiscount + $storeDiscount, 2);

            $overallSubtotal = round($overallSubtotal + $package['subtotal'], 2);
            $overallShipping = round($overallShipping + $shipping['shipping_fee'], 2);
            $overallStoreDiscount = round($overallStoreDiscount + $combinedSellerFundedDiscount, 2);

            $sellerPackages[] = [
                'seller_profile'    => $sellerProfile,
                'items'             => $package['items'],
                'subtotal'          => $package['subtotal'],
                'bundle_discount'   => $bundleDiscount,
                'bundle_title'      => $bundleResult['bundle_title'],
                'store_voucher'     => $appliedStoreVoucher,
                'store_discount'    => $storeDiscount,
                'platform_discount' => 0.0,
                'shipping_discount' => 0.0,
                'coins_used'        => 0,
                'coins_discount'    => 0.0,
                'net_subtotal'      => max(0.0, round($package['subtotal'] - $combinedSellerFundedDiscount, 2)),
                'total_weight'      => $package['total_weight'],
                'shipping'          => $shipping,
                'net_shipping_fee'  => $shipping['shipping_fee'],
                'total'             => round(max(0.0, $package['subtotal'] - $combinedSellerFundedDiscount) + $shipping['shipping_fee'], 2),
            ];
        }

        $subtotalAfterStoreVouchers = max(0.0, round($overallSubtotal - $overallStoreDiscount, 2));

        $appliedPlatformVoucher = null;
        $overallPlatformDiscount = 0.0;
        $normPlatCode = strtoupper(trim($platformVoucherCode));

        if ($normPlatCode !== '') {
            $platCandidate = StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [$normPlatCode])
                ->where('scope', 'platform')
                ->first();

            if (! $platCandidate) {
                if ($strictVoucherErrors) {
                    throw ValidationException::withMessages([
                        'platform_voucher_code' => "Platform voucher \"{$normPlatCode}\" is invalid.",
                    ]);
                }
                $voucherNotices[] = "Platform voucher \"{$normPlatCode}\" is invalid and was removed.";
            } else {
                try {
                    $overallPlatformDiscount = $this->voucherService->calculatePlatformOrShippingDiscount(
                        voucher: $platCandidate,
                        buyerId: $buyerId,
                        overallMerchandiseSubtotal: $overallSubtotal,
                        overallShippingTotal: $overallShipping
                    );
                    $overallPlatformDiscount = min($subtotalAfterStoreVouchers, $overallPlatformDiscount);
                    $appliedPlatformVoucher = $platCandidate;
                } catch (ValidationException $e) {
                    if ($strictVoucherErrors) {
                        throw $e;
                    }
                    $voucherNotices[] = collect($e->errors())->flatten()->first() ?: "Platform voucher \"{$normPlatCode}\" was removed.";
                }
            }
        }

        $appliedShippingVoucher = null;
        $overallShippingDiscount = 0.0;
        $normShipCode = strtoupper(trim($shippingVoucherCode));

        if ($normShipCode !== '') {
            $shipCandidate = StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [$normShipCode])
                ->where('scope', 'shipping')
                ->first();

            if (! $shipCandidate) {
                if ($strictVoucherErrors) {
                    throw ValidationException::withMessages([
                        'shipping_voucher_code' => "Shipping voucher \"{$normShipCode}\" is invalid.",
                    ]);
                }
                $voucherNotices[] = "Shipping voucher \"{$normShipCode}\" is invalid and was removed.";
            } else {
                try {
                    $overallShippingDiscount = $this->voucherService->calculatePlatformOrShippingDiscount(
                        voucher: $shipCandidate,
                        buyerId: $buyerId,
                        overallMerchandiseSubtotal: $overallSubtotal,
                        overallShippingTotal: $overallShipping
                    );
                    $appliedShippingVoucher = $shipCandidate;
                } catch (ValidationException $e) {
                    if ($strictVoucherErrors) {
                        throw $e;
                    }
                    $voucherNotices[] = collect($e->errors())->flatten()->first() ?: "Shipping voucher \"{$normShipCode}\" was removed.";
                }
            }
        }

        $remainingPlatDiscount = $overallPlatformDiscount;
        $remainingShipDiscount = $overallShippingDiscount;
        $pkgCount = count($sellerPackages);
        $eligibleSubtotalsByIdx = [];

        foreach ($sellerPackages as $idx => &$pkg) {
            $isLast = ($idx === $pkgCount - 1);

            if ($overallPlatformDiscount > 0 && $subtotalAfterStoreVouchers > 0) {
                $platShare = $isLast
                    ? $remainingPlatDiscount
                    : min($pkg['net_subtotal'], round($overallPlatformDiscount * ($pkg['net_subtotal'] / $subtotalAfterStoreVouchers), 2));
                $pkg['platform_discount'] = max(0.0, $platShare);
                $remainingPlatDiscount = max(0.0, round($remainingPlatDiscount - $pkg['platform_discount'], 2));
            }

            if ($overallShippingDiscount > 0 && $overallShipping > 0) {
                $shipShare = $isLast
                    ? $remainingShipDiscount
                    : min($pkg['shipping']['shipping_fee'], round($overallShippingDiscount * ($pkg['shipping']['shipping_fee'] / $overallShipping), 2));
                $pkg['shipping_discount'] = max(0.0, $shipShare);
                $remainingShipDiscount = max(0.0, round($remainingShipDiscount - $pkg['shipping_discount'], 2));
            }

            $pkgSubAfterPlat = max(0.0, round($pkg['subtotal'] - $pkg['bundle_discount'] - $pkg['store_discount'] - $pkg['platform_discount'], 2));
            $eligibleSubtotalsByIdx[$idx] = $pkgSubAfterPlat;
        }
        unset($pkg);

        $subtotalAfterPlatformVouchers = max(0.0, round(array_sum($eligibleSubtotalsByIdx), 2));
        $coinAllowance = $this->buyerRewardService->calculateMaxRedeemableCoinsForCheckout($buyerId, $subtotalAfterPlatformVouchers);
        $maxRedeemableCoins = (int) $coinAllowance['max_redeemable_coins'];
        $maxRedeemableDiscount = (float) $coinAllowance['peso_discount'];
        $buyerCoinsBalance = (int) $coinAllowance['coins_balance'];

        $targetCoinsToUse = 0;
        if ($useStorkiaCoins && $maxRedeemableCoins >= BuyerRewardService::MIN_CHECKOUT_REDEEMABLE_COINS) {
            if ($requestedCoinsToUse !== null && $requestedCoinsToUse > 0) {
                $targetCoinsToUse = $requestedCoinsToUse < BuyerRewardService::MIN_CHECKOUT_REDEEMABLE_COINS
                    ? BuyerRewardService::MIN_CHECKOUT_REDEEMABLE_COINS
                    : min($requestedCoinsToUse, $maxRedeemableCoins);
            } else {
                $targetCoinsToUse = $maxRedeemableCoins;
            }
        }

        $coinAllocations = $this->buyerRewardService->allocateCoinsAcrossPackages($targetCoinsToUse, $eligibleSubtotalsByIdx);

        foreach ($sellerPackages as $idx => &$pkg) {
            $alloc = $coinAllocations[$idx] ?? ['coins_used' => 0, 'coins_discount' => 0.00];
            $pkg['coins_used'] = (int) $alloc['coins_used'];
            $pkg['coins_discount'] = (float) $alloc['coins_discount'];

            $pkg['net_subtotal'] = max(0.0, round($pkg['subtotal'] - $pkg['bundle_discount'] - $pkg['store_discount'] - $pkg['platform_discount'] - $pkg['coins_discount'], 2));
            $pkg['net_shipping_fee'] = max(0.0, round($pkg['shipping']['shipping_fee'] - $pkg['shipping_discount'], 2));
            $pkg['total'] = round($pkg['net_subtotal'] + $pkg['net_shipping_fee'], 2);
        }
        unset($pkg);

        $overallCoinsUsed = (int) collect($sellerPackages)->sum('coins_used');
        $overallCoinsDiscount = round((float) collect($sellerPackages)->sum('coins_discount'), 2);
        $overallNetSubtotal = round((float) collect($sellerPackages)->sum('net_subtotal'), 2);
        $overallNetShipping = round((float) collect($sellerPackages)->sum('net_shipping_fee'), 2);
        $overallTotal = round($overallNetSubtotal + $overallNetShipping, 2);

        return [
            'is_address_serviceable'    => $isAddressServiceable,
            'seller_packages'           => $sellerPackages,
            'overall_subtotal'          => $overallSubtotal,
            'overall_shipping'          => $overallShipping,
            'overall_store_discount'    => $overallStoreDiscount,
            'overall_platform_discount' => $overallPlatformDiscount,
            'overall_shipping_discount' => $overallShippingDiscount,
            'overall_coins_used'        => $overallCoinsUsed,
            'overall_coins_discount'    => $overallCoinsDiscount,
            'max_redeemable_coins'      => $maxRedeemableCoins,
            'max_redeemable_discount'   => $maxRedeemableDiscount,
            'buyer_coins_balance'       => $buyerCoinsBalance,
            'overall_net_subtotal'      => $overallNetSubtotal,
            'overall_net_shipping'      => $overallNetShipping,
            'overall_total'             => $overallTotal,
            'applied_platform_voucher'  => $appliedPlatformVoucher,
            'applied_shipping_voucher'  => $appliedShippingVoucher,
            'voucher_notices'           => $voucherNotices,
            'price_signature'           => json_encode($priceMap),
            'total_items_count'         => $totalItemsCount,
            'cart_ids'                  => array_values(array_unique($cartIds)),
        ];
    }

    protected function generateUniqueOrderNumber(): string
    {
        do {
            $candidate = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }

    protected function generateUniqueSellerOrderNumber(): string
    {
        do {
            $candidate = 'SO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (SellerOrder::where('seller_order_number', $candidate)->exists());

        return $candidate;
    }
}