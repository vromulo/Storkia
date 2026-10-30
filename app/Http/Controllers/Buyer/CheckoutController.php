<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentSubmission;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Models\SellerProfile;
use App\Models\StorkVoucher;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerCancellationGuardService;
use App\Services\InventoryStockService;
use App\Services\ShippingCalculatorService;
use App\Services\SuperDealPricingService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class CheckoutController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected ShippingCalculatorService $shippingCalculatorService,
        protected VoucherService $voucherService,
        protected BuyerCancellationGuardService $cancellationGuardService,
        protected SuperDealPricingService $superDealPricingService
    ) {}

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

        $this->superDealPricingService->assertStoreNotOnVacation($product->user?->sellerProfile);
        $this->assertPerBuyerLimitNotExceeded((int) $request->user()->id, $product, (int) $validated['quantity']);

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

        if ($product->user?->sellerProfile) {
            $this->cancellationGuardService->validateCanCheckoutItems((int) $request->user()->id, [
                [
                    'product'        => $product,
                    'seller_profile' => $product->user->sellerProfile,
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
        $user = $request->user();
        $mode = $request->query('mode', 'cart');

        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $selectedAddressId = $request->query('address_id');

        $selectedAddress = $selectedAddressId
            ? $addresses->firstWhere('id', (int) $selectedAddressId)
            : ($addresses->firstWhere('is_default', true) ?? $addresses->first());

        $rawLineItems = $this->collectCheckoutLineItems($request, $mode);

        if (empty($rawLineItems)) {
            return redirect()->route('cart.index')->withErrors([
                'cart' => 'Please select at least one valid product to proceed to checkout.',
            ]);
        }

        $this->cancellationGuardService->validateCanCheckoutItems((int) $user->id, $rawLineItems);

        $requestedStoreVouchers = (array) $request->query('store_vouchers', []);
        $requestedPlatformVoucher = trim((string) $request->query('platform_voucher_code', ''));
        $requestedShippingVoucher = trim((string) $request->query('shipping_voucher_code', ''));

        $checkoutSummary = $this->buildGroupedSellerPreview(
            rawLineItems: $rawLineItems,
            selectedAddress: $selectedAddress,
            buyerId: (int) $user->id,
            storeVoucherCodes: $requestedStoreVouchers,
            platformVoucherCode: $requestedPlatformVoucher,
            shippingVoucherCode: $requestedShippingVoucher,
            strictVoucherErrors: false
        );

        $availableVouchers = $this->voucherService->getBuyerAvailableVouchers((int) $user->id);
        $idempotencyKey = (string) Str::uuid();

        return view('buyer.checkout', [
            'mode'                     => $mode,
            'addresses'                => $addresses,
            'selectedAddress'          => $selectedAddress,
            'isAddressServiceable'     => $checkoutSummary['is_address_serviceable'],
            'sellerPackages'           => $checkoutSummary['seller_packages'],
            'overallSubtotal'          => $checkoutSummary['overall_subtotal'],
            'overallShipping'          => $checkoutSummary['overall_shipping'],
            'overallStoreDiscount'     => $checkoutSummary['overall_store_discount'],
            'overallPlatformDiscount'  => $checkoutSummary['overall_platform_discount'],
            'overallShippingDiscount'  => $checkoutSummary['overall_shipping_discount'],
            'overallTotal'             => $checkoutSummary['overall_total'],
            'appliedPlatformVoucher'   => $checkoutSummary['applied_platform_voucher'],
            'appliedShippingVoucher'   => $checkoutSummary['applied_shipping_voucher'],
            'voucherNotices'           => $checkoutSummary['voucher_notices'],
            'priceSignature'           => $checkoutSummary['price_signature'],
            'availableVouchers'        => $availableVouchers,
            'totalItemsCount'          => $checkoutSummary['total_items_count'],
            'selectedCartIds'          => $checkoutSummary['cart_ids'],
            'idempotencyKey'           => $idempotencyKey,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_address_id'         => ['required', 'integer', 'exists:user_addresses,id'],
            'mode'                    => ['required', 'in:cart,buy_now'],
            'cart_ids'                => ['nullable', 'array'],
            'cart_ids.*'              => ['integer'],
            'idempotency_key'         => ['required', 'string', 'max:64'],
            'price_signature'         => ['nullable', 'string'],
            'seller_notes'            => ['nullable', 'array'],
            'seller_notes.*'          => ['nullable', 'string', 'max:200'],
            'store_voucher_codes'     => ['nullable', 'array'],
            'store_voucher_codes.*'   => ['nullable', 'string', 'max:25'],
            'platform_voucher_code'   => ['nullable', 'string', 'max:25'],
            'shipping_voucher_code'   => ['nullable', 'string', 'max:25'],
            'payment_methods'         => ['required', 'array'],
            'payment_methods.*'       => ['required', 'in:COD,GCASH'],
            'gcash_sender_number'     => ['nullable', 'array'],
            'gcash_paid_at'           => ['nullable', 'array'],
            'gcash_reference_number'  => ['nullable', 'array'],
            'gcash_reference_code'    => ['nullable', 'array'],
            'gcash_screenshot'        => ['nullable', 'array'],
        ], [
            'user_address_id.required' => 'Please select a delivery address.',
            'payment_methods.required' => 'Please select a payment method for each store package.',
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
                strictVoucherErrors: true
            );

            $seenRefNumbers = [];
            $preparedGcashData = [];

            foreach ($preview['seller_packages'] as $pkg) {
                /** @var SellerProfile $sellerProfile */
                $sellerProfile = $pkg['seller_profile'];
                $spId = (int) $sellerProfile->id;
                $chosenMethod = strtoupper((string) ($validated['payment_methods'][$spId] ?? 'COD'));

                if (! in_array($chosenMethod, ['COD', 'GCASH'], true)) {
                    throw ValidationException::withMessages([
                        'payment_methods' => "Please select a valid payment method for {$sellerProfile->business_name}.",
                    ]);
                }

                if ($chosenMethod === 'GCASH') {
                    $gcashAcc = $sellerProfile->gcashAccount;
                    $blockedForArrears = (bool) ($sellerProfile->gcash_blocked_for_arrears ?? false);

                    if ($blockedForArrears || ! $gcashAcc || ! $gcashAcc->isCompliantForCheckout()) {
                        throw ValidationException::withMessages([
                            'payment_methods' => "Online Payment (GCash) is currently unavailable for {$sellerProfile->business_name}. Please select Cash on Delivery (COD).",
                        ]);
                    }

                    $rawSender = preg_replace('/\D/', '', (string) ($request->input("gcash_sender_number.{$spId}") ?? ''));
                    if (str_starts_with($rawSender, '63') && strlen($rawSender) === 12) {
                        $rawSender = '0' . substr($rawSender, 2);
                    } elseif (strlen($rawSender) === 10 && str_starts_with($rawSender, '9')) {
                        $rawSender = '0' . $rawSender;
                    }

                    if (! preg_match('/^09\d{9}$/', $rawSender)) {
                        throw ValidationException::withMessages([
                            "gcash_sender_number.{$spId}" => "Please enter a valid 11-digit Sender GCash mobile number for {$sellerProfile->business_name}.",
                        ]);
                    }

                    $paidAtRaw = trim((string) ($request->input("gcash_paid_at.{$spId}") ?? ''));
                    if ($paidAtRaw === '') {
                        throw ValidationException::withMessages([
                            "gcash_paid_at.{$spId}" => "Please enter the GCash payment date and time for {$sellerProfile->business_name}.",
                        ]);
                    }

                    try {
                        $paidAt = Carbon::parse($paidAtRaw);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            "gcash_paid_at.{$spId}" => "Invalid GCash payment date and time for {$sellerProfile->business_name}.",
                        ]);
                    }

                    $refNumber = strtoupper(trim((string) ($request->input("gcash_reference_number.{$spId}") ?? '')));
                    $refCode = strtoupper(trim((string) ($request->input("gcash_reference_code.{$spId}") ?? '')));

                    if (strlen($refNumber) < 4 || strlen($refNumber) > 80) {
                        throw ValidationException::withMessages([
                            "gcash_reference_number.{$spId}" => "Please enter a valid GCash Reference Number (4–80 characters) for {$sellerProfile->business_name}.",
                        ]);
                    }

                    if (strlen($refCode) < 2 || strlen($refCode) > 80) {
                        throw ValidationException::withMessages([
                            "gcash_reference_code.{$spId}" => "Please enter the GCash Reference Code for {$sellerProfile->business_name}.",
                        ]);
                    }

                    if (in_array($refNumber, $seenRefNumbers, true) || OrderPaymentSubmission::whereRaw('UPPER(TRIM(reference_number)) = ?', [$refNumber])->exists()) {
                        throw ValidationException::withMessages([
                            "gcash_reference_number.{$spId}" => "GCash Reference Number \"{$refNumber}\" has already been used. Each online payment must have a unique reference number.",
                        ]);
                    }
                    $seenRefNumbers[] = $refNumber;

                    if (! $request->hasFile("gcash_screenshot.{$spId}")) {
                        throw ValidationException::withMessages([
                            "gcash_screenshot.{$spId}" => "Please upload your GCash payment screenshot for {$sellerProfile->business_name}.",
                        ]);
                    }

                    $file = $request->file("gcash_screenshot.{$spId}");
                    if (! $file->isValid() || $file->getSize() > 5 * 1024 * 1024) {
                        throw ValidationException::withMessages([
                            "gcash_screenshot.{$spId}" => "The GCash payment screenshot for {$sellerProfile->business_name} must be a valid image under 5MB.",
                        ]);
                    }

                    $preparedGcashData[$spId] = [
                        'seller_gcash_name'   => $gcashAcc->account_name,
                        'seller_gcash_number' => $gcashAcc->gcash_number,
                        'sender_gcash_number' => $rawSender,
                        'paid_at'             => $paidAt,
                        'reference_number'    => $refNumber,
                        'reference_code'      => $refCode,
                        'screenshot_file'     => $file,
                    ];
                }
            }

            foreach ($rawLineItems as $line) {
                $this->inventoryStockService->deductStock(
                    $line['product']->id,
                    $line['variant_name'],
                    $line['sub_variant_name'],
                    $line['quantity']
                );

                // Increment sold_quantity on Super Deal slot if applicable (SEL-FLASH-003)
                if (! empty($line['super_deal_id']) && Schema::hasTable('super_deals')) {
                    DB::table('super_deals')
                        ->where('id', (int) $line['super_deal_id'])
                        ->increment('sold_quantity', (int) $line['quantity']);
                }
            }

            $allMethods = array_map('strtoupper', array_values($validated['payment_methods']));
            $parentMethod = count(array_unique($allMethods)) === 1 ? $allMethods[0] : 'MIXED';

            $parentOrder = Order::create([
                'order_number'       => $this->generateUniqueOrderNumber(),
                'buyer_id'           => $user->id,
                'user_address_id'    => $selectedAddress->id,
                'address_snapshot'   => $deliverySnapshot,
                'payment_method'     => $parentMethod,
                'payment_status'     => 'unpaid',
                'status'             => 'pending',
                'subtotal_amount'    => $preview['overall_net_subtotal'],
                'shipping_fee_total' => $preview['overall_net_shipping'],
                'total_amount'       => $preview['overall_total'],
                'idempotency_key'    => $validated['idempotency_key'],
            ]);

            $platformVoucherConsumed = false;
            $shippingVoucherConsumed = false;

            foreach ($preview['seller_packages'] as $pkg) {
                /** @var SellerProfile $sellerProfile */
                $sellerProfile = $pkg['seller_profile'];
                $spId = (int) $sellerProfile->id;
                $chosenMethod = strtoupper((string) ($validated['payment_methods'][$spId] ?? 'COD'));
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
                    'payment_status'            => $chosenMethod === 'GCASH' ? 'submitted' : 'unpaid',
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

                $paymentRecord = Payment::create([
                    'order_id'          => $parentOrder->id,
                    'seller_order_id'   => $sellerOrder->id,
                    'payment_reference' => $chosenMethod === 'GCASH'
                        ? $preparedGcashData[$spId]['reference_number']
                        : ('PAY-' . strtoupper(Str::random(10))),
                    'method'            => $chosenMethod,
                    'status'            => $chosenMethod === 'GCASH' ? 'submitted' : 'unpaid',
                    'product_subtotal'  => $pkg['net_subtotal'],
                    'shipping_fee'      => $pkg['net_shipping_fee'],
                    'total_amount'      => $pkg['total'],
                ]);

                SellerOrderExtra::create([
                    'order_id'                  => $parentOrder->id,
                    'seller_order_id'           => $sellerOrder->id,
                    'buyer_note'                => $buyerNote !== '' ? substr($buyerNote, 0, 200) : null,
                    'store_voucher_id'          => $pkg['store_voucher']?->id,
                    'store_voucher_code'        => $pkg['store_voucher']?->code,
                    'store_voucher_discount'    => round($pkg['store_discount'] + ($pkg['bundle_discount'] ?? 0), 2),
                    'platform_voucher_id'       => $pkg['platform_discount'] > 0 ? $preview['applied_platform_voucher']?->id : null,
                    'platform_voucher_code'     => $pkg['platform_discount'] > 0 ? $preview['applied_platform_voucher']?->code : null,
                    'platform_voucher_discount' => $pkg['platform_discount'],
                    'shipping_voucher_id'       => $pkg['shipping_discount'] > 0 ? $preview['applied_shipping_voucher']?->id : null,
                    'shipping_voucher_code'     => $pkg['shipping_discount'] > 0 ? $preview['applied_shipping_voucher']?->code : null,
                    'shipping_voucher_discount' => $pkg['shipping_discount'],
                    'original_subtotal_amount'  => $pkg['subtotal'],
                    'original_shipping_fee'     => $pkg['shipping']['shipping_fee'],
                ]);

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

                if ($chosenMethod === 'GCASH' && isset($preparedGcashData[$spId])) {
                    $gData = $preparedGcashData[$spId];
                    $webpPath = $this->convertScreenshotToWebp($gData['screenshot_file'], 'gcash_payment_screenshots');

                    OrderPaymentSubmission::create([
                        'order_id'            => $parentOrder->id,
                        'seller_order_id'     => $sellerOrder->id,
                        'payment_id'          => $paymentRecord->id,
                        'buyer_id'            => $user->id,
                        'seller_profile_id'   => $sellerProfile->id,
                        'seller_gcash_name'   => $gData['seller_gcash_name'],
                        'seller_gcash_number' => $gData['seller_gcash_number'],
                        'sender_gcash_number' => $gData['sender_gcash_number'],
                        'paid_at'             => $gData['paid_at'],
                        'reference_number'    => $gData['reference_number'],
                        'reference_code'      => $gData['reference_code'],
                        'screenshot_path'     => $webpPath,
                        'submitted_amount'    => $pkg['total'],
                        'status'              => 'submitted',
                    ]);
                }

                OrderStatusHistory::create([
                    'order_id'        => $parentOrder->id,
                    'seller_order_id' => $sellerOrder->id,
                    'from_status'     => null,
                    'to_status'       => 'pending_seller_acceptance',
                    'actor_type'      => 'Buyer',
                    'actor_id'        => $user->id,
                    'remarks'         => $chosenMethod === 'GCASH'
                        ? "Order placed via Online Payment (GCash Ref #{$preparedGcashData[$spId]['reference_number']}). Waiting for seller review and acceptance within 24 hours."
                        : 'Order placed via Cash on Delivery (COD). Waiting for seller acceptance within 24 hours.',
                ]);

                if ($sellerProfile->user) {
                    $notificationsToDispatch[] = [
                        'recipient'    => $sellerProfile->user,
                        'notification' => new OrderLifecycleNotification(
                            title: 'New Order Received (' . $chosenMethod . ')',
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

    protected function convertScreenshotToWebp($file, string $folder): string
    {
        $filename = uniqid('gcash_') . '_' . time() . '.webp';
        $path = $folder . '/' . $filename;

        $manager = ImageManager::usingDriver(Driver::class);
        $image = $manager->decode($file->getRealPath());
        $image->scaleDown(width: 1200, height: 1200);
        $encoded = $image->encode(new WebpEncoder(quality: 85));

        Storage::disk('public')->put($path, $encoded->toString());

        return $path;
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

            $product = Product::with(['approval', 'user.sellerProfile.gcashAccount'])->find($buyNow['product_id']);
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

        $cartQuery = Cart::with(['product.approval', 'product.user.sellerProfile.gcashAccount'])
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
        bool $strictVoucherErrors = false
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
            $sellerProfile->loadMissing('gcashAccount');

            $shipping = $this->shippingCalculatorService->calculateForSellerOrder(
                sellerProvince: $sellerProfile->province ?? '',
                sellerMunicipality: $sellerProfile->municipality ?? '',
                buyerProvince: $buyerProvince,
                buyerMunicipality: $buyerMunicipality,
                totalWeightKg: $package['total_weight']
            );

            // Apply Bundle Deal Discount automatically if qualifying items are present (SEL-FLASH-007 / 008)
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

            $gcashAccount = $sellerProfile->gcashAccount;
            $blockedForArrears = (bool) ($sellerProfile->gcash_blocked_for_arrears ?? false);
            $gcashEnabled = ! $blockedForArrears && $gcashAccount !== null && $gcashAccount->isCompliantForCheckout();

            $sellerPackages[] = [
                'seller_profile'     => $sellerProfile,
                'gcash_enabled'      => $gcashEnabled,
                'gcash_account'      => $gcashEnabled ? $gcashAccount : null,
                'items'              => $package['items'],
                'subtotal'           => $package['subtotal'],
                'bundle_discount'    => $bundleDiscount,
                'bundle_title'       => $bundleResult['bundle_title'],
                'store_voucher'      => $appliedStoreVoucher,
                'store_discount'     => $storeDiscount,
                'platform_discount'  => 0.0,
                'shipping_discount'  => 0.0,
                'net_subtotal'       => max(0.0, round($package['subtotal'] - $combinedSellerFundedDiscount, 2)),
                'total_weight'       => $package['total_weight'],
                'shipping'           => $shipping,
                'net_shipping_fee'   => $shipping['shipping_fee'],
                'total'              => round(max(0.0, $package['subtotal'] - $combinedSellerFundedDiscount) + $shipping['shipping_fee'], 2),
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

            $pkg['net_subtotal'] = max(0.0, round($pkg['subtotal'] - $pkg['bundle_discount'] - $pkg['store_discount'] - $pkg['platform_discount'], 2));
            $pkg['net_shipping_fee'] = max(0.0, round($pkg['shipping']['shipping_fee'] - $pkg['shipping_discount'], 2));
            $pkg['total'] = round($pkg['net_subtotal'] + $pkg['net_shipping_fee'], 2);
        }
        unset($pkg);

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