<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Voucher;
use App\Notifications\OrderLifecycleNotification;
use App\Services\InventoryStockService;
use App\Services\ShippingCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected InventoryStockService $inventoryStockService,
        protected ShippingCalculatorService $shippingCalculatorService
    ) {}

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'mode'            => ['required', 'in:cart,buy_now'],
            'address_id'      => ['nullable', 'integer', 'exists:user_addresses,id'],
            'cart_ids'        => ['required_if:mode,cart', 'array'],
            'cart_ids.*'      => ['integer'],
            'voucher_code'    => ['nullable', 'string'],
            // Buy now payload
            'product_id'      => ['required_if:mode,buy_now', 'integer', 'exists:products,id'],
            'quantity'        => ['required_if:mode,buy_now', 'integer', 'min:1'],
            'variant'         => ['nullable', 'string'],
            'sub_variant'     => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $addresses = $user->addresses()->orderByDesc('is_default')->latest('id')->get();
        $selectedAddress = isset($validated['address_id']) 
            ? $addresses->firstWhere('id', (int) $validated['address_id'])
            : ($addresses->firstWhere('is_default', true) ?? $addresses->first());

        $rawLineItems = $this->collectCheckoutLineItems($request, $validated, $user);

        if (empty($rawLineItems)) {
            return response()->json(['success' => false, 'message' => 'No items available for checkout.'], 400);
        }

        $checkoutSummary = $this->buildGroupedSellerPreview($rawLineItems, $selectedAddress);

        $voucher = null;
        $voucherDiscount = 0.0;
        if (!empty($validated['voucher_code'])) {
            $voucher = Voucher::where('code', trim($validated['voucher_code']))
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($voucher) {
                if (isset($voucher->discount_amount) && $voucher->discount_amount > 0) {
                    $voucherDiscount = (float) $voucher->discount_amount;
                } elseif (isset($voucher->discount_percent) && $voucher->discount_percent > 0) {
                    $voucherDiscount = round(($checkoutSummary['overall_subtotal'] * $voucher->discount_percent) / 100, 2);
                } elseif (isset($voucher->value) && $voucher->value > 0) {
                    $voucherDiscount = (isset($voucher->type) && $voucher->type === 'percent')
                        ? round(($checkoutSummary['overall_subtotal'] * $voucher->value) / 100, 2)
                        : (float) $voucher->value;
                }
                $voucherDiscount = min($voucherDiscount, $checkoutSummary['overall_subtotal']);
            }
        }

        $overallTotal = max(0, round($checkoutSummary['overall_subtotal'] + $checkoutSummary['overall_shipping'] - $voucherDiscount, 2));

        return response()->json([
            'success' => true,
            'data' => [
                'mode'             => $validated['mode'],
                'selected_address' => $selectedAddress,
                'seller_packages'  => $checkoutSummary['seller_packages'],
                'overall_subtotal' => $checkoutSummary['overall_subtotal'],
                'overall_shipping' => $checkoutSummary['overall_shipping'],
                'voucher'          => $voucher,
                'voucher_discount' => $voucherDiscount,
                'overall_total'    => $overallTotal,
                'total_items'      => $checkoutSummary['total_items_count'],
                'cart_ids'         => $checkoutSummary['cart_ids'],
                'idempotency_key'  => (string) Str::uuid(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_address_id' => ['required', 'integer', 'exists:user_addresses,id'],
            'mode'            => ['required', 'in:cart,buy_now'],
            'cart_ids'        => ['required_if:mode,cart', 'array'],
            'cart_ids.*'      => ['integer'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'voucher_code'    => ['nullable', 'string'],
            // Buy now payload
            'product_id'      => ['required_if:mode,buy_now', 'integer', 'exists:products,id'],
            'quantity'        => ['required_if:mode,buy_now', 'integer', 'min:1'],
            'variant'         => ['nullable', 'string'],
            'sub_variant'     => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $existingOrder = Order::where('idempotency_key', $validated['idempotency_key'])
            ->where('buyer_id', $user->id)
            ->first();

        if ($existingOrder) {
            return response()->json(['success' => true, 'order_id' => $existingOrder->id]);
        }

        $selectedAddress = $user->addresses()->findOrFail($validated['user_address_id']);
        $rawLineItems = $this->collectCheckoutLineItems($request, $validated, $user);

        if (empty($rawLineItems)) {
            return response()->json(['success' => false, 'message' => 'Items are empty or already processed.'], 400);
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

        $order = DB::transaction(function () use ($user, $selectedAddress, $deliverySnapshot, $rawLineItems, $validated, &$notificationsToDispatch) {
            $preview = $this->buildGroupedSellerPreview($rawLineItems, $selectedAddress);

            foreach ($rawLineItems as $line) {
                $this->inventoryStockService->deductStock(
                    $line['product']->id,
                    $line['variant_name'],
                    $line['sub_variant_name'],
                    $line['quantity']
                );
            }

            $voucherDiscount = 0.0;
            if (!empty($validated['voucher_code'])) {
                $voucher = Voucher::where('code', trim($validated['voucher_code']))
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->first();

                if ($voucher) {
                    if (isset($voucher->discount_amount) && $voucher->discount_amount > 0) {
                        $voucherDiscount = (float) $voucher->discount_amount;
                    } elseif (isset($voucher->discount_percent) && $voucher->discount_percent > 0) {
                        $voucherDiscount = round(($preview['overall_subtotal'] * $voucher->discount_percent) / 100, 2);
                    } elseif (isset($voucher->value) && $voucher->value > 0) {
                        $voucherDiscount = (isset($voucher->type) && $voucher->type === 'percent')
                            ? round(($preview['overall_subtotal'] * $voucher->value) / 100, 2)
                            : (float) $voucher->value;
                    }
                    $voucherDiscount = min($voucherDiscount, $preview['overall_subtotal']);
                }
            }

            $finalTotal = max(0, round($preview['overall_subtotal'] + $preview['overall_shipping'] - $voucherDiscount, 2));

            $parentOrder = Order::create([
                'order_number'       => $this->generateUniqueOrderNumber(),
                'buyer_id'           => $user->id,
                'user_address_id'    => $selectedAddress->id,
                'address_snapshot'   => $deliverySnapshot,
                'payment_method'     => 'COD',
                'payment_status'     => 'unpaid',
                'status'             => 'pending',
                'subtotal_amount'    => $preview['overall_subtotal'],
                'shipping_fee_total' => $preview['overall_shipping'],
                'total_amount'       => $finalTotal,
                'idempotency_key'    => $validated['idempotency_key'],
            ]);

            foreach ($preview['seller_packages'] as $pkg) {
                $sellerProfile = $pkg['seller_profile'];

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
                    'status'                    => 'pending_seller_acceptance',
                    'payment_status'            => 'unpaid',
                    'subtotal_amount'           => $pkg['subtotal'],
                    'shipping_fee'              => $pkg['shipping']['shipping_fee'],
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

                Payment::create([
                    'order_id'          => $parentOrder->id,
                    'seller_order_id'   => $sellerOrder->id,
                    'payment_reference' => 'PAY-' . strtoupper(Str::random(10)),
                    'method'            => 'COD',
                    'status'            => 'unpaid',
                    'product_subtotal'  => $pkg['subtotal'],
                    'shipping_fee'      => $pkg['shipping']['shipping_fee'],
                    'total_amount'      => $pkg['total'],
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $parentOrder->id,
                    'seller_order_id' => $sellerOrder->id,
                    'to_status'       => 'pending_seller_acceptance',
                    'actor_type'      => 'Buyer',
                    'actor_id'        => $user->id,
                    'remarks'         => 'Order placed via Cash on Delivery (COD).',
                ]);

                if ($sellerProfile->user) {
                    $notificationsToDispatch[] = [
                        'recipient' => $sellerProfile->user,
                        'notification' => new OrderLifecycleNotification(
                            'New Order Received',
                            "Order #{$sellerOrder->seller_order_number} requires your acceptance.",
                            'info',
                            route('seller.orders.show', $sellerOrder->id),
                            $parentOrder->id,
                            $sellerOrder->id
                        ),
                    ];
                }
            }

            if ($validated['mode'] === 'cart' && !empty($preview['cart_ids'])) {
                Cart::where('user_id', $user->id)->whereIn('id', $preview['cart_ids'])->delete();
            }

            return $parentOrder;
        });

        foreach ($notificationsToDispatch as $entry) {
            try { $entry['recipient']->notify($entry['notification']); } catch (\Throwable $e) {}
        }

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'order_id' => $order->id
        ]);
    }

    protected function collectCheckoutLineItems(Request $request, array $validated, $user): array
    {
        $lineItems = [];

        if ($validated['mode'] === 'buy_now') {
            $product = Product::with(['approval', 'sellerProfile'])->find($validated['product_id']);
            if (!$product || !$product->sellerProfile) return [];

            $resolved = $this->inventoryStockService->resolveProductSelection($product, $validated['variant'] ?? null, $validated['sub_variant'] ?? null);
            $qty = max(1, (int) $validated['quantity']);

            if ($resolved['available_stock'] < $qty) {
                throw ValidationException::withMessages(['stock' => "Insufficient stock for \"{$product->name}\"."]);
            }

            $lineItems[] = $this->formatLineItem(null, $product, $resolved, $qty);
            return $lineItems;
        }

        $cartRecords = Cart::with(['product.approval', 'product.sellerProfile'])
            ->where('user_id', $user->id)
            ->whereIn('id', $validated['cart_ids'])
            ->get();

        foreach ($cartRecords as $cartItem) {
            $product = $cartItem->product;
            if (!$product || !$product->sellerProfile) continue;

            $resolved = $this->inventoryStockService->resolveProductSelection($product, $cartItem->variant, $cartItem->sub_variant);
            $qty = max(1, (int) $cartItem->quantity);

            if ($resolved['available_stock'] < $qty) {
                throw ValidationException::withMessages(['stock' => "Insufficient stock for \"{$product->name}\"."]);
            }

            $lineItems[] = $this->formatLineItem($cartItem->id, $product, $resolved, $qty);
        }

        return $lineItems;
    }

    protected function formatLineItem($cartId, $product, $resolved, $qty): array
    {
        return [
            'cart_id'               => $cartId,
            'product'               => $product,
            'seller_profile'        => $product->sellerProfile,
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

    protected function buildGroupedSellerPreview(array $rawLineItems, $selectedAddress): array
    {
        $grouped = [];
        $cartIds = [];
        $totalItemsCount = 0;

        foreach ($rawLineItems as $line) {
            if ($line['cart_id']) $cartIds[] = $line['cart_id'];
            $totalItemsCount += $line['quantity'];

            $sellerProfileId = $line['seller_profile']->id;
            if (!isset($grouped[$sellerProfileId])) {
                $grouped[$sellerProfileId] = ['seller_profile' => $line['seller_profile'], 'items' => [], 'subtotal' => 0.0, 'total_weight' => 0.0];
            }

            $grouped[$sellerProfileId]['items'][] = $line;
            $grouped[$sellerProfileId]['subtotal'] = round($grouped[$sellerProfileId]['subtotal'] + $line['line_total'], 2);
            $grouped[$sellerProfileId]['total_weight'] = round($grouped[$sellerProfileId]['total_weight'] + $line['line_weight'], 3);
        }

        $buyerProvince = $selectedAddress->province ?? '';
        $buyerMunicipality = $selectedAddress->municipality ?? '';

        $overallSubtotal = 0.0;
        $overallShipping = 0.0;
        $sellerPackages = [];

        foreach ($grouped as $sellerProfileId => $package) {
            $sellerProfile = $package['seller_profile'];

            $shipping = $this->shippingCalculatorService->calculateForSellerOrder(
                sellerProvince: $sellerProfile->province ?? '',
                sellerMunicipality: $sellerProfile->municipality ?? '',
                buyerProvince: $buyerProvince,
                buyerMunicipality: $buyerMunicipality,
                totalWeightKg: $package['total_weight']
            );

            $packageTotal = round($package['subtotal'] + $shipping['shipping_fee'], 2);

            $overallSubtotal = round($overallSubtotal + $package['subtotal'], 2);
            $overallShipping = round($overallShipping + $shipping['shipping_fee'], 2);

            $sellerPackages[] = [
                'seller_profile' => $sellerProfile,
                'items'          => $package['items'],
                'subtotal'       => $package['subtotal'],
                'total_weight'   => $package['total_weight'],
                'shipping'       => $shipping,
                'total'          => $packageTotal,
            ];
        }

        return [
            'seller_packages'   => $sellerPackages,
            'overall_subtotal'  => $overallSubtotal,
            'overall_shipping'  => $overallShipping,
            'overall_total'     => round($overallSubtotal + $overallShipping, 2),
            'total_items_count' => $totalItemsCount,
            'cart_ids'          => array_values(array_unique($cartIds)),
        ];
    }

    protected function generateUniqueOrderNumber(): string {
        do { $candidate = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)); } while (Order::where('order_number', $candidate)->exists());
        return $candidate;
    }

    protected function generateUniqueSellerOrderNumber(): string {
        do { $candidate = 'SO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)); } while (SellerOrder::where('seller_order_number', $candidate)->exists());
        return $candidate;
    }
}