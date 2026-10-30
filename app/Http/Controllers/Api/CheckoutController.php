<?php

namespace App\Http\Controllers\Api;

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
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        protected BuyerCancellationGuardService $cancellationGuardService
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode'                    => ['required', 'in:cart,buy_now'],
            'address_id'              => ['nullable', 'integer', 'exists:user_addresses,id'],
            'cart_ids'                => ['required_if:mode,cart', 'array'],
            'cart_ids.*'              => ['integer'],
            'store_vouchers'          => ['nullable', 'array'],
            'store_vouchers.*'        => ['nullable', 'string', 'max:25'],
            'platform_voucher_code'   => ['nullable', 'string', 'max:25'],
            'shipping_voucher_code'   => ['nullable', 'string', 'max:25'],
            'voucher_code'            => ['nullable', 'string', 'max:25'],
            'product_id'              => ['required_if:mode,buy_now', 'integer', 'exists:products,id'],
            'quantity'                => ['required_if:mode,buy_now', 'integer', 'min:1', 'max:1000000'],
            'variant'                 => ['nullable', 'string'],
            'sub_variant'             => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $selectedAddress = isset($validated['address_id'])
            ? $addresses->firstWhere('id', (int) $validated['address_id'])
            : ($addresses->firstWhere('is_default', true) ?? $addresses->first());

        $rawLineItems = $this->collectCheckoutLineItems($validated, $user);

        if (empty($rawLineItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one valid product to proceed to checkout.',
            ], 400);
        }

        $this->cancellationGuardService->validateCanCheckoutItems((int) $user->id, $rawLineItems);

        $storeVouchers = (array) ($validated['store_vouchers'] ?? []);
        $platformVoucherCode = trim((string) ($validated['platform_voucher_code'] ?? ''));
        $shippingVoucherCode = trim((string) ($validated['shipping_voucher_code'] ?? ''));

        if (! empty($validated['voucher_code']) && $platformVoucherCode === '' && $shippingVoucherCode === '' && empty($storeVouchers)) {
            $legacyCode = strtoupper(trim((string) $validated['voucher_code']));
            $candidate = StorkVoucher::whereRaw('UPPER(TRIM(code)) = ?', [$legacyCode])->first();
            if ($candidate) {
                if ($candidate->scope === 'platform') {
                    $platformVoucherCode = $legacyCode;
                } elseif ($candidate->scope === 'shipping') {
                    $shippingVoucherCode = $legacyCode;
                } elseif ($candidate->scope === 'store' && $candidate->seller_profile_id) {
                    $storeVouchers[$candidate->seller_profile_id] = $legacyCode;
                }
            }
        }

        $checkoutSummary = $this->buildGroupedSellerPreview(
            rawLineItems: $rawLineItems,
            selectedAddress: $selectedAddress,
            buyerId: (int) $user->id,
            storeVoucherCodes: $storeVouchers,
            platformVoucherCode: $platformVoucherCode,
            shippingVoucherCode: $shippingVoucherCode,
            strictVoucherErrors: false
        );

        $availableVouchers = $this->voucherService->getBuyerAvailableVouchers((int) $user->id)
            ->map(function (StorkVoucher $v) {
                return array_merge($v->toArray(), [
                    'badge_label' => $v->badge_label,
                ]);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'mode'                      => $validated['mode'],
                'addresses'                 => $addresses,
                'selected_address'          => $selectedAddress,
                'is_address_serviceable'    => $checkoutSummary['is_address_serviceable'],
                'seller_packages'           => $checkoutSummary['seller_packages'],
                'overall_subtotal'          => $checkoutSummary['overall_subtotal'],
                'overall_shipping'          => $checkoutSummary['overall_shipping'],
                'overall_store_discount'    => $checkoutSummary['overall_store_discount'],
                'overall_platform_discount' => $checkoutSummary['overall_platform_discount'],
                'overall_shipping_discount' => $checkoutSummary['overall_shipping_discount'],
                'overall_net_subtotal'      => $checkoutSummary['overall_net_subtotal'],
                'overall_net_shipping'      => $checkoutSummary['overall_net_shipping'],
                'overall_total'             => $checkoutSummary['overall_total'],
                'applied_platform_voucher'  => $checkoutSummary['applied_platform_voucher']
                    ? array_merge($checkoutSummary['applied_platform_voucher']->toArray(), [
                        'badge_label' => $checkoutSummary['applied_platform_voucher']->badge_label,
                    ])
                    : null,
                'applied_shipping_voucher'  => $checkoutSummary['applied_shipping_voucher']
                    ? array_merge($checkoutSummary['applied_shipping_voucher']->toArray(), [
                        'badge_label' => $checkoutSummary['applied_shipping_voucher']->badge_label,
                    ])
                    : null,
                'voucher_notices'           => $checkoutSummary['voucher_notices'],
                'price_signature'           => $checkoutSummary['price_signature'],
                'available_vouchers'        => $availableVouchers,
                'total_items'               => $checkoutSummary['total_items_count'],
                'cart_ids'                  => $checkoutSummary['cart_ids'],
                'idempotency_key'           => (string) Str::uuid(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeMultipartArrays($request, [
            'cart_ids',
            'seller_notes',
            'store_voucher_codes',
            'payment_methods',
            'gcash_sender_number',
            'gcash_paid_at',
            'gcash_reference_number',
            'gcash_reference_code',
        ]);

        $validated = $request->validate([
            'user_address_id'        => ['required', 'integer', 'exists:user_addresses,id'],
            'mode'                   => ['required', 'in:cart,buy_now'],
            'cart_ids'               => ['required_if:mode,cart', 'array'],
            'cart_ids.*'             => ['integer'],
            'idempotency_key'        => ['required', 'string', 'max:64'],
            'price_signature'        => ['nullable', 'string'],
            'seller_notes'           => ['nullable', 'array'],
            'seller_notes.*'         => ['nullable', 'string', 'max:200'],
            'store_voucher_codes'    => ['nullable', 'array'],
            'store_voucher_codes.*'  => ['nullable', 'string', 'max:25'],
            'platform_voucher_code'  => ['nullable', 'string', 'max:25'],
            'shipping_voucher_code'  => ['nullable', 'string', 'max:25'],
            'payment_methods'        => ['nullable', 'array'],
            'payment_methods.*'      => ['required', 'in:COD,GCASH'],
            'product_id'             => ['required_if:mode,buy_now', 'integer', 'exists:products,id'],
            'quantity'               => ['required_if:mode,buy_now', 'integer', 'min:1', 'max:1000000'],
            'variant'                => ['nullable', 'string'],
            'sub_variant'            => ['nullable', 'string'],
        ], [
            'user_address_id.required' => 'Please select a delivery address.',
            'seller_notes.*.max'       => 'Note to seller cannot exceed 200 characters.',
        ]);

        $user = $request->user();

        $existingOrder = Order::with([
            'sellerOrders.items',
            'sellerOrders.sellerProfile',
            'sellerOrders.payment',
            'sellerOrders.paymentSubmission',
            'sellerOrders.extra',
        ])
            ->where('idempotency_key', $validated['idempotency_key'])
            ->where('buyer_id', $user->id)
            ->first();

        if ($existingOrder) {
            return response()->json([
                'success'  => true,
                'message'  => 'Order placed successfully.',
                'order_id' => $existingOrder->id,
                'data'     => $existingOrder,
            ]);
        }

        $selectedAddress = $user->addresses()->findOrFail($validated['user_address_id']);

        if (! $this->shippingCalculatorService->isAddressServiceable($selectedAddress->province, $selectedAddress->municipality)) {
            throw ValidationException::withMessages([
                'address' => "Delivery to {$selectedAddress->municipality}, {$selectedAddress->province} is currently unserviceable by our partner logistics hubs. Please select a serviceable address.",
            ]);
        }

        $rawLineItems = $this->collectCheckoutLineItems($validated, $user);

        if (empty($rawLineItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Your checkout items are empty or have already been processed.',
            ], 400);
        }

        $this->cancellationGuardService->validateCanCheckoutItems((int) $user->id, $rawLineItems);

        if (! empty($validated['price_signature'])) {
            $expectedPrices = json_decode((string) $validated['price_signature'], true);
            if (is_array($expectedPrices)) {
                foreach ($rawLineItems as $line) {
                    $sigKey = $this->buildLineSignatureKey(
                        $line['product']->id,
                        $line['variant_name'],
                        $line['sub_variant_name']
                    );
                    if (isset($expectedPrices[$sigKey])) {
                        $expectedUnit = round((float) $expectedPrices[$sigKey], 2);
                        $currentUnit = round((float) $line['discounted_unit_price'], 2);
                        if (abs($expectedUnit - $currentUnit) > 0.009) {
                            throw ValidationException::withMessages([
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

            $paymentMethodsInput = (array) ($validated['payment_methods'] ?? []);
            $seenRefNumbers = [];
            $preparedGcashData = [];

            foreach ($preview['seller_packages'] as $pkg) {
                /** @var SellerProfile $sellerProfile */
                $sellerProfile = $pkg['seller_profile_model'];
                $spId = (int) $sellerProfile->id;
                $chosenMethod = strtoupper((string) ($paymentMethodsInput[$spId] ?? 'COD'));

                if (! in_array($chosenMethod, ['COD', 'GCASH'], true)) {
                    throw ValidationException::withMessages([
                        'payment_methods' => "Please select a valid payment method for {$sellerProfile->business_name}.",
                    ]);
                }

                if ($chosenMethod === 'GCASH') {
                    $gcashAcc = $sellerProfile->gcashAccount;
                    if (! $gcashAcc || ! $gcashAcc->isCompliantForCheckout()) {
                        throw ValidationException::withMessages([
                            'payment_methods' => "Online Payment (GCash) is not enabled for {$sellerProfile->business_name}. Please select Cash on Delivery (COD).",
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

                    if (
                        in_array($refNumber, $seenRefNumbers, true)
                        || OrderPaymentSubmission::whereRaw('UPPER(TRIM(reference_number)) = ?', [$refNumber])->exists()
                    ) {
                        throw ValidationException::withMessages([
                            "gcash_reference_number.{$spId}" => "GCash Reference Number \"{$refNumber}\" has already been used. Each online payment must have a unique reference number.",
                        ]);
                    }
                    $seenRefNumbers[] = $refNumber;

                    $file = $request->file("gcash_screenshot.{$spId}") ?? $request->file("gcash_screenshot_{$spId}");
                    if (! $file) {
                        throw ValidationException::withMessages([
                            "gcash_screenshot.{$spId}" => "Please upload your GCash payment screenshot for {$sellerProfile->business_name}.",
                        ]);
                    }

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
            }

            $resolvedMethods = [];
            foreach ($preview['seller_packages'] as $pkg) {
                $spId = (int) $pkg['seller_profile_model']->id;
                $resolvedMethods[] = strtoupper((string) ($paymentMethodsInput[$spId] ?? 'COD'));
            }
            $parentMethod = count(array_unique($resolvedMethods)) === 1
                ? ($resolvedMethods[0] ?? 'COD')
                : 'MIXED';

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
                $sellerProfile = $pkg['seller_profile_model'];
                $spId = (int) $sellerProfile->id;
                $chosenMethod = strtoupper((string) ($paymentMethodsInput[$spId] ?? 'COD'));
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

                foreach ($pkg['raw_items'] as $itemData) {
                    OrderItem::create([
                        'order_id'              => $parentOrder->id,
                        'seller_order_id'       => $sellerOrder->id,
                        'product_id'            => $itemData['product']->id,
                        'product_name'          => $itemData['product']->name,
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

                /** @var StorkVoucher|null $storeVoucherModel */
                $storeVoucherModel = $pkg['store_voucher_model'];

                SellerOrderExtra::create([
                    'order_id'                  => $parentOrder->id,
                    'seller_order_id'           => $sellerOrder->id,
                    'buyer_note'                => $buyerNote !== '' ? substr($buyerNote, 0, 200) : null,
                    'store_voucher_id'          => $storeVoucherModel?->id,
                    'store_voucher_code'        => $storeVoucherModel?->code,
                    'store_voucher_discount'    => $pkg['store_discount'],
                    'platform_voucher_id'       => $pkg['platform_discount'] > 0 ? $preview['applied_platform_voucher']?->id : null,
                    'platform_voucher_code'     => $pkg['platform_discount'] > 0 ? $preview['applied_platform_voucher']?->code : null,
                    'platform_voucher_discount' => $pkg['platform_discount'],
                    'shipping_voucher_id'       => $pkg['shipping_discount'] > 0 ? $preview['applied_shipping_voucher']?->id : null,
                    'shipping_voucher_code'     => $pkg['shipping_discount'] > 0 ? $preview['applied_shipping_voucher']?->code : null,
                    'shipping_voucher_discount' => $pkg['shipping_discount'],
                    'original_subtotal_amount'  => $pkg['subtotal'],
                    'original_shipping_fee'     => $pkg['shipping']['shipping_fee'],
                ]);

                if ($storeVoucherModel) {
                    $this->voucherService->consumeVoucher($storeVoucherModel, (int) $user->id, $sellerOrder->id);
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

        $order->load([
            'sellerOrders.items',
            'sellerOrders.sellerProfile',
            'sellerOrders.payment',
            'sellerOrders.paymentSubmission',
            'sellerOrders.extra',
        ]);

        foreach ($order->sellerOrders as $so) {
            $so->setAttribute('status_label', $so->status_label);
            $so->setAttribute('effective_payment_method', $so->effective_payment_method);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Your order has been placed successfully!',
            'order_id' => $order->id,
            'data'     => $order,
        ]);
    }

    protected function normalizeMultipartArrays(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            $val = $request->input($key);
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    $request->merge([$key => $decoded]);
                }
            }
        }
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

    protected function buildLineSignatureKey(int $productId, ?string $variantName, ?string $subVariantName): string
    {
        return $productId . ':' . strtolower(trim((string) $variantName)) . ':' . strtolower(trim((string) $subVariantName));
    }

    protected function collectCheckoutLineItems(array $validated, $user): array
    {
        $lineItems = [];

        if ($validated['mode'] === 'buy_now') {
            $product = Product::with(['approval', 'sellerProfile', 'user.sellerProfile.gcashAccount'])
                ->find($validated['product_id']);
            $sellerProfile = $product?->user?->sellerProfile ?? $product?->sellerProfile;

            if (! $product || ! $sellerProfile) {
                return [];
            }

            if ((bool) ($sellerProfile->is_on_vacation ?? false)) {
                throw ValidationException::withMessages([
                    'vacation' => "{$sellerProfile->business_name} is currently on vacation and not accepting orders.",
                ]);
            }

            $resolved = $this->inventoryStockService->resolveProductSelection(
                $product,
                $validated['variant'] ?? null,
                $validated['sub_variant'] ?? null
            );

            $qty = max(1, (int) ($validated['quantity'] ?? 1));
            $perBuyerLimit = (int) ($product->per_buyer_limit ?? 0);
            if ($perBuyerLimit > 0 && $qty > $perBuyerLimit) {
                throw ValidationException::withMessages([
                    'quantity' => "You can purchase at most {$perBuyerLimit} unit(s) of \"{$product->name}\".",
                ]);
            }

            if ($resolved['available_stock'] < $qty) {
                throw ValidationException::withMessages([
                    'stock' => "Insufficient stock for \"{$product->name}\". Available: {$resolved['available_stock']}.",
                ]);
            }

            $lineItems[] = $this->formatLineItem(null, $product, $sellerProfile, $resolved, $qty);

            return $lineItems;
        }

        $cartRecords = Cart::with(['product.approval', 'product.sellerProfile', 'product.user.sellerProfile.gcashAccount'])
            ->where('user_id', $user->id)
            ->whereIn('id', array_map('intval', (array) ($validated['cart_ids'] ?? [])))
            ->orderBy('id', 'asc')
            ->get();

        foreach ($cartRecords as $cartItem) {
            $product = $cartItem->product;
            $sellerProfile = $product?->user?->sellerProfile ?? $product?->sellerProfile;
            if (! $product || ! $sellerProfile) {
                continue;
            }

            if ((bool) ($sellerProfile->is_on_vacation ?? false)) {
                throw ValidationException::withMessages([
                    'vacation' => "{$sellerProfile->business_name} is currently on vacation and not accepting orders.",
                ]);
            }

            $resolved = $this->inventoryStockService->resolveProductSelection(
                $product,
                $cartItem->variant,
                $cartItem->sub_variant
            );

            $qty = max(1, (int) $cartItem->quantity);
            $perBuyerLimit = (int) ($product->per_buyer_limit ?? 0);
            if ($perBuyerLimit > 0 && $qty > $perBuyerLimit) {
                throw ValidationException::withMessages([
                    'quantity' => "You can purchase at most {$perBuyerLimit} unit(s) of \"{$product->name}\".",
                ]);
            }

            if ($resolved['available_stock'] < $qty) {
                throw ValidationException::withMessages([
                    'stock' => "Insufficient stock for \"{$product->name}\". Available: {$resolved['available_stock']}.",
                ]);
            }

            $lineItems[] = $this->formatLineItem($cartItem->id, $product, $sellerProfile, $resolved, $qty);
        }

        return $lineItems;
    }

    protected function formatLineItem($cartId, Product $product, SellerProfile $sellerProfile, array $resolved, int $qty): array
    {
        return [
            'cart_id'               => $cartId,
            'product'               => $product,
            'seller_profile'        => $sellerProfile,
            'quantity'              => $qty,
            'variant_id'            => $resolved['variant_id'],
            'variant_name'          => $resolved['variant_name'],
            'sub_variant_id'        => $resolved['sub_variant_id'],
            'sub_variant_name'      => $resolved['sub_variant_name'],
            'unit_price'            => $resolved['unit_price'],
            'discount_percent'      => $resolved['discount_percent'],
            'discounted_unit_price' => $resolved['discounted_unit_price'],
            'unit_weight'           => $resolved['unit_weight'],
            'available_stock'       => $resolved['available_stock'],
            'image_path'            => $resolved['image_path'],
            'line_total'            => round($resolved['discounted_unit_price'] * $qty, 2),
            'line_weight'           => round($resolved['unit_weight'] * $qty, 3),
        ];
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
                $line['sub_variant_name']
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

            $appliedStoreVoucher = null;
            $storeDiscount = 0.0;
            $rawStoreCode = strtoupper(trim((string) ($storeVoucherCodes[$sellerProfileId] ?? ($storeVoucherCodes[(string) $sellerProfileId] ?? ''))));

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
                            packageSubtotal: (float) $package['subtotal']
                        );
                        $appliedStoreVoucher = $candidate;
                    } catch (ValidationException $e) {
                        if ($strictVoucherErrors) {
                            throw $e;
                        }
                        $voucherNotices[] = collect($e->errors())->flatten()->first()
                            ?: "Voucher \"{$rawStoreCode}\" was removed.";
                    }
                }
            }

            $overallSubtotal = round($overallSubtotal + $package['subtotal'], 2);
            $overallShipping = round($overallShipping + $shipping['shipping_fee'], 2);
            $overallStoreDiscount = round($overallStoreDiscount + $storeDiscount, 2);

            $gcashAccount = $sellerProfile->gcashAccount;
            $gcashBlocked = (bool) ($sellerProfile->gcash_blocked_for_arrears ?? false);
            $gcashEnabled = ! $gcashBlocked && $gcashAccount !== null && $gcashAccount->isCompliantForCheckout();

            $serializedItems = array_map(function (array $line) {
                return [
                    'cart_id'               => $line['cart_id'],
                    'product_id'            => $line['product']->id,
                    'product'               => [
                        'id'   => $line['product']->id,
                        'name' => $line['product']->name,
                    ],
                    'quantity'              => $line['quantity'],
                    'variant_id'            => $line['variant_id'],
                    'variant_name'          => $line['variant_name'],
                    'sub_variant_id'        => $line['sub_variant_id'],
                    'sub_variant_name'      => $line['sub_variant_name'],
                    'unit_price'            => $line['unit_price'],
                    'discount_percent'      => $line['discount_percent'],
                    'discounted_unit_price' => $line['discounted_unit_price'],
                    'unit_weight'           => $line['unit_weight'],
                    'available_stock'       => $line['available_stock'],
                    'image_path'            => $line['image_path'],
                    'line_total'            => $line['line_total'],
                    'line_weight'           => $line['line_weight'],
                ];
            }, $package['items']);

            $sellerPackages[] = [
                'seller_profile_model' => $sellerProfile,
                'seller_profile'       => $sellerProfile->toArray(),
                'gcash_enabled'        => $gcashEnabled,
                'gcash_account'        => $gcashEnabled ? [
                    'id'            => $gcashAccount->id,
                    'account_name'  => $gcashAccount->account_name,
                    'gcash_number'  => $gcashAccount->gcash_number,
                    'qr_image_path' => $gcashAccount->qr_image_path,
                    'qr_image_url'  => $gcashAccount->qr_image_url,
                ] : null,
                'raw_items'            => $package['items'],
                'items'                => $serializedItems,
                'subtotal'             => $package['subtotal'],
                'store_voucher_model'  => $appliedStoreVoucher,
                'store_voucher'        => $appliedStoreVoucher ? array_merge($appliedStoreVoucher->toArray(), [
                    'badge_label' => $appliedStoreVoucher->badge_label,
                ]) : null,
                'store_discount'       => $storeDiscount,
                'platform_discount'    => 0.0,
                'shipping_discount'    => 0.0,
                'net_subtotal'         => max(0.0, round($package['subtotal'] - $storeDiscount, 2)),
                'total_weight'         => $package['total_weight'],
                'shipping'             => $shipping,
                'net_shipping_fee'     => $shipping['shipping_fee'],
                'total'                => round(max(0.0, $package['subtotal'] - $storeDiscount) + $shipping['shipping_fee'], 2),
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
                    $voucherNotices[] = collect($e->errors())->flatten()->first()
                        ?: "Platform voucher \"{$normPlatCode}\" was removed.";
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
                    $voucherNotices[] = collect($e->errors())->flatten()->first()
                        ?: "Shipping voucher \"{$normShipCode}\" was removed.";
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

            $pkg['net_subtotal'] = max(0.0, round($pkg['subtotal'] - $pkg['store_discount'] - $pkg['platform_discount'], 2));
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