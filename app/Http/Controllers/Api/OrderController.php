<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsReturnRequests;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\DeliveryChatMessage;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerCancellationGuardService;
use App\Services\BuyerRewardService;
use App\Services\CourierMobileSchemaService;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class OrderController extends Controller
{
    use FormatsReturnRequests;

    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected InventoryStockService $inventoryStockService,
        protected VoucherService $voucherService,
        protected BuyerCancellationGuardService $cancellationGuardService,
        protected ?RoleWalletService $roleWalletService = null,
        protected ?BuyerRewardService $buyerRewardService = null
    ) {
        $this->roleWalletService = $roleWalletService ?? app(RoleWalletService::class);
        $this->buyerRewardService = $buyerRewardService ?? app(BuyerRewardService::class);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->orderCompletionService->runLifecycleMaintenance(buyerId: (int) $user->id);

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

        $baseQuery = SellerOrder::where('buyer_id', $user->id);

        $counts = [
            'all'        => (clone $baseQuery)->count(),
            'unpaid'     => (clone $baseQuery)->whereIn('payment_status', ['unpaid', 'flagged'])->whereNotIn('status', ['rejected', 'cancelled', 'returned_to_seller'])->count(),
            'processing' => (clone $baseQuery)->whereIn('status', ['pending_seller_acceptance', 'pending_buyer_response', 'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned'])->count(),
            'shipped'    => (clone $baseQuery)->whereIn('status', ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivery_failed'])->count(),
            'to_confirm' => (clone $baseQuery)->where('status', 'delivered')->count(),
            'to_rate'    => (clone $baseQuery)->where('status', 'completed')->whereDoesntHave('serviceRating')->count(),
            'completed'  => (clone $baseQuery)->where('status', 'completed')->whereHas('serviceRating')->count(),
            'returns'    => (clone $baseQuery)->whereIn('status', ['disputed', 'rejected', 'cancelled', 'returned_to_seller'])->count(),
        ];

        $relations = [
            'order',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'shipment.latestDeliveryProof',
            'shipment.packages',
            'payment',
            'paymentSubmission',
            'extra',
            'serviceRating',
            'itemReviews',
        ];
        if (Schema::hasTable('return_requests')) {
            $relations[] = 'returnRequest.returnShipment.events';
            $relations[] = 'returnRequest.returnShipment.rider';
        }

        $query = SellerOrder::with($relations)->where('buyer_id', $user->id);

        match ($statusTab) {
            'unpaid'     => $query->whereIn('payment_status', ['unpaid', 'flagged'])->whereNotIn('status', ['rejected', 'cancelled', 'returned_to_seller']),
            'processing' => $query->whereIn('status', ['pending_seller_acceptance', 'pending_buyer_response', 'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned']),
            'shipped'    => $query->whereIn('status', ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivery_failed']),
            'to_confirm' => $query->where('status', 'delivered'),
            'to_rate'    => $query->where('status', 'completed')->whereDoesntHave('serviceRating'),
            'completed', 'review' => $query->where('status', 'completed')->whereHas('serviceRating'),
            'returns'    => $query->whereIn('status', ['disputed', 'rejected', 'cancelled', 'returned_to_seller']),
            default      => null,
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('seller_order_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('sellerProfile', fn ($sq) => $sq->where('business_name', 'like', "%{$search}%"))
                    ->orWhereHas('items', fn ($iq) => $iq->where('product_name', 'like', "%{$search}%"));
            });
        }

        $sellerOrders = $query
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'rejected', 'returned_to_seller') THEN 1 ELSE 0 END ASC")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $sellerOrders->getCollection()->transform(function (SellerOrder $so) {
            return $this->appendComputedOrderAttributes($so);
        });

        $cancellationQuota = $this->cancellationGuardService->getBuyerQuotaSummary((int) $user->id);
        $isIdentityVerified = $user->identityVerification?->status === 'verified';

        return response()->json([
            'success'              => true,
            'data'                 => $sellerOrders,
            'counts'               => $counts,
            'cancellation_quota'   => $cancellationQuota,
            'is_identity_verified' => $isIdentityVerified,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->orderCompletionService->runLifecycleMaintenance(buyerId: (int) $user->id);
        $sellerOrder->refresh();

        $relations = [
            'order',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'payment',
            'paymentSubmission',
            'extra',
            'serviceRating',
            'itemReviews',
            'statusHistories',
            'shipment.packages',
            'shipment.trackingLogs',
            'shipment.currentCourier',
            'shipment.currentRider',
            'shipment.deliveryAttempts',
            'shipment.deliveryProofs',
        ];
        if (Schema::hasTable('return_requests')) {
            $relations[] = 'returnRequest.returnShipment.events';
            $relations[] = 'returnRequest.returnShipment.rider';
            $relations[] = 'returnRequest.sellerOrder.sellerProfile';
        }

        $sellerOrder->load($relations);
        $this->appendComputedOrderAttributes($sellerOrder);

        $unreadDeliveryChatCount = 0;
        if (Schema::hasTable('delivery_chat_messages')) {
            $unreadDeliveryChatCount = DeliveryChatMessage::where('seller_order_id', $sellerOrder->id)
                ->where('sender_type', 'rider')
                ->where('is_read', false)
                ->count();
        }
        $sellerOrder->setAttribute('unread_delivery_chat_count', $unreadDeliveryChatCount);

        $cancellationQuota = $this->cancellationGuardService->getBuyerQuotaSummary((int) $user->id);
        $isIdentityVerified = $user->identityVerification?->status === 'verified';

        return response()->json([
            'success'                    => true,
            'data'                       => $sellerOrder,
            'unread_delivery_chat_count' => $unreadDeliveryChatCount,
            'cancellation_quota'         => $cancellationQuota,
            'is_identity_verified'       => $isIdentityVerified,
        ]);
    }

    public function invoice(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::with([
            'order',
            'buyer',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'payment',
            'extra',
            'shipment',
        ])->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $extra = $sellerOrder->extra;
        $origSub = (float) ($extra?->original_subtotal_amount ?: $sellerOrder->subtotal_amount);
        $storeDisc = (float) ($extra?->store_voucher_discount ?? 0);
        $rawPlatDisc = (float) ($extra?->platform_voucher_discount ?? 0);
        $coinsDisc = (float) ($extra?->coins_discount_amount ?? 0);
        $netSub = (float) $sellerOrder->subtotal_amount;

        $cleanPlatformDiscount = 0.0;
        if ($extra && ! empty($extra->platform_voucher_code)) {
            if ($origSub > 0 && abs(round($origSub - $storeDisc - $rawPlatDisc - $coinsDisc, 2) - round($netSub, 2)) < 0.02) {
                $cleanPlatformDiscount = round($rawPlatDisc, 2);
            } else {
                $cleanPlatformDiscount = max(0.0, round($rawPlatDisc - $coinsDisc, 2));
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'invoice_number'             => 'INV-' . str_replace('SO-', '', $sellerOrder->seller_order_number),
                'seller_order_id'            => (int) $sellerOrder->id,
                'seller_order_number'        => $sellerOrder->seller_order_number,
                'order_number'               => $sellerOrder->order?->order_number,
                'issued_at'                  => ($sellerOrder->completed_at ?? $sellerOrder->delivered_at ?? $sellerOrder->created_at)?->timezone('Asia/Manila')->format('M d, Y h:i A'),
                'status'                     => $sellerOrder->status,
                'status_label'               => $sellerOrder->status_label,
                'payment_method'             => $sellerOrder->effective_payment_method,
                'payment_status'             => $sellerOrder->payment_status,
                'payment_reference'          => $sellerOrder->payment?->payment_reference,
                'printable_url'              => route('user.orders.invoice', $sellerOrder->id),
                'seller'                     => [
                    'business_name' => $sellerOrder->sellerProfile?->business_name ?? 'Seller Store',
                    'address'       => $sellerOrder->pickup_address_snapshot['full_address'] ?? '',
                    'contact_no'    => $sellerOrder->pickup_address_snapshot['contact_no'] ?? '',
                ],
                'buyer'                      => [
                    'recipient_name' => $sellerOrder->delivery_address_snapshot['recipient_name'] ?? $user->full_name,
                    'phone_number'   => $sellerOrder->delivery_address_snapshot['phone_number'] ?? $user->contact_no,
                    'address'        => $sellerOrder->delivery_address_snapshot['full_address'] ?? '',
                ],
                'items'                      => $sellerOrder->items->map(fn ($item) => [
                    'id'                    => (int) $item->id,
                    'product_name'          => $item->product_name,
                    'variant'               => implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name])) ?: null,
                    'quantity'              => (int) $item->quantity,
                    'unit_price'            => (float) $item->unit_price,
                    'discounted_unit_price' => (float) $item->discounted_unit_price,
                    'line_total'            => (float) $item->line_total,
                ])->values(),
                'original_subtotal'          => $origSub,
                'store_voucher_discount'     => $storeDisc,
                'platform_voucher_discount'  => $cleanPlatformDiscount,
                'coins_used'                 => (int) ($extra?->coins_used ?? 0),
                'coins_discount_amount'      => $coinsDisc,
                'net_subtotal'               => $netSub,
                'original_shipping_fee'      => (float) ($extra?->original_shipping_fee ?: $sellerOrder->shipping_fee),
                'shipping_voucher_discount'  => (float) ($extra?->shipping_voucher_discount ?? 0),
                'net_shipping_fee'           => (float) $sellerOrder->shipping_fee,
                'total_amount'               => (float) $sellerOrder->total_amount,
            ],
        ]);
    }

    public function liveTracking(Request $request, $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $sellerOrder = SellerOrder::with([
            'order.userAddress',
            'shipment.currentRider',
            'shipment.currentCourier',
            'shipment.logisticsProfile',
            'sellerProfile',
        ])->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $shipment = $sellerOrder->shipment;
        if (! $shipment) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'has_shipment'    => false,
                    'status'          => $sellerOrder->status,
                    'status_label'    => $sellerOrder->status_label,
                    'is_live_active'  => false,
                    'driver'          => null,
                    'destination'     => null,
                    'sorting_hub'     => null,
                    'distance_meters' => null,
                ],
            ]);
        }

        $delivSnap = (array) ($shipment->delivery_address_snapshot ?? $sellerOrder->delivery_address_snapshot ?? []);
        $hubSnap = (array) ($shipment->hub_address_snapshot ?? []);
        $pickupSnap = (array) ($shipment->pickup_address_snapshot ?? $sellerOrder->pickup_address_snapshot ?? []);

        $destCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($delivSnap, $sellerOrder->order?->userAddress);
        $hubCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($hubSnap, $shipment->logisticsProfile);
        $sellerCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($pickupSnap, $sellerOrder->sellerProfile);

        $driverPayload = null;
        $distanceMeters = null;
        $isLiveActive = false;

        if ($shipment->currentRider && in_array($shipment->status, ['rider_assigned', 'out_for_delivery', 'delivery_failed'], true)) {
            $rider = $shipment->currentRider;
            $latestLoc = Schema::hasTable('rider_locations')
                ? DB::table('rider_locations')->where('rider_id', $rider->id)->orderByDesc('recorded_at')->first()
                : null;

            $lat = $latestLoc?->latitude ?? $rider->current_latitude ?? $hubCoords['latitude'];
            $lng = $latestLoc?->longitude ?? $rider->current_longitude ?? $hubCoords['longitude'];

            if ($lat !== null && $lng !== null) {
                $distanceMeters = CourierMobileSchemaService::calculateDistanceMeters(
                    (float) $lat,
                    (float) $lng,
                    (float) $destCoords['latitude'],
                    (float) $destCoords['longitude']
                );
            }

            $isLiveActive = $shipment->status === 'out_for_delivery';
            $driverPayload = [
                'role'         => 'Rider',
                'id'           => (int) $rider->id,
                'name'         => $rider->full_name,
                'contact_no'   => $rider->contact_no,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'is_online'    => (bool) ($rider->is_online ?? true),
                'latitude'     => (float) $lat,
                'longitude'    => (float) $lng,
                'speed'        => $latestLoc?->speed !== null ? (float) $latestLoc->speed : null,
                'heading'      => $latestLoc?->heading !== null ? (float) $latestLoc->heading : null,
                'updated_at'   => $latestLoc?->recorded_at ?? $rider->location_updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        } elseif ($shipment->currentCourier && in_array($shipment->status, ['courier_assigned', 'picked_up_from_seller', 'in_transit_to_hub'], true)) {
            $courier = $shipment->currentCourier;
            $latestLoc = Schema::hasTable('courier_locations')
                ? DB::table('courier_locations')->where('courier_id', $courier->id)->orderByDesc('recorded_at')->first()
                : null;

            $lat = $latestLoc?->latitude ?? $courier->current_latitude ?? $sellerCoords['latitude'];
            $lng = $latestLoc?->longitude ?? $courier->current_longitude ?? $sellerCoords['longitude'];

            if ($lat !== null && $lng !== null) {
                $distanceMeters = CourierMobileSchemaService::calculateDistanceMeters(
                    (float) $lat,
                    (float) $lng,
                    (float) $hubCoords['latitude'],
                    (float) $hubCoords['longitude']
                );
            }

            $isLiveActive = in_array($shipment->status, ['picked_up_from_seller', 'in_transit_to_hub'], true);
            $driverPayload = [
                'role'         => 'Courier',
                'id'           => (int) $courier->id,
                'name'         => $courier->full_name,
                'contact_no'   => $courier->contact_no,
                'vehicle_type' => $courier->vehicle_type,
                'plate_number' => $courier->plate_number,
                'is_online'    => (bool) ($courier->is_online ?? true),
                'latitude'     => (float) $lat,
                'longitude'    => (float) $lng,
                'speed'        => $latestLoc?->speed !== null ? (float) $latestLoc->speed : null,
                'heading'      => $latestLoc?->heading !== null ? (float) $latestLoc->heading : null,
                'updated_at'   => $latestLoc?->recorded_at ?? $courier->location_updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'has_shipment'    => true,
                'tracking_number' => $shipment->tracking_number,
                'status'          => $shipment->status,
                'status_label'    => $shipment->status_label,
                'is_live_active'  => $isLiveActive,
                'driver'          => $driverPayload,
                'distance_meters' => $distanceMeters,
                'eta_minutes'     => $distanceMeters !== null ? max(2, (int) ceil($distanceMeters / 400)) : null,
                'origin_seller'   => [
                    'name'      => $pickupSnap['business_name'] ?? ($sellerOrder->sellerProfile?->business_name ?? 'Seller'),
                    'address'   => $pickupSnap['full_address'] ?? '',
                    'latitude'  => $sellerCoords['latitude'],
                    'longitude' => $sellerCoords['longitude'],
                ],
                'sorting_hub'     => [
                    'name'      => $hubSnap['hub_name'] ?? ($shipment->logisticsProfile?->business_name ?? 'Sorting Hub'),
                    'address'   => $hubSnap['full_address'] ?? '',
                    'latitude'  => $hubCoords['latitude'],
                    'longitude' => $hubCoords['longitude'],
                ],
                'destination'     => [
                    'recipient' => $delivSnap['recipient_name'] ?? 'Buyer',
                    'address'   => $delivSnap['full_address'] ?? '',
                    'latitude'  => $destCoords['latitude'],
                    'longitude' => $destCoords['longitude'],
                ],
            ],
        ]);
    }

    public function deliveryChatMessages(Request $request, $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $sellerOrder = SellerOrder::with('shipment.currentRider')->findOrFail($id);
        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $shipment = $sellerOrder->shipment;
        if (! $shipment || ! Schema::hasTable('delivery_chat_messages')) {
            return response()->json([
                'success'  => true,
                'can_send' => false,
                'rider'    => null,
                'messages' => [],
            ]);
        }

        DeliveryChatMessage::where('seller_order_id', $sellerOrder->id)
            ->where('sender_type', 'rider')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $messages = DeliveryChatMessage::where('seller_order_id', $sellerOrder->id)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (DeliveryChatMessage $m) => [
                'id'             => (int) $m->id,
                'sender_type'    => $m->sender_type,
                'message'        => $m->message,
                'attachment_url' => $m->attachment_url,
                'is_read'        => (bool) $m->is_read,
                'created_at'     => $m->created_at?->toIso8601String(),
            ]);

        $canSend = $shipment->current_rider_id !== null
            && in_array($shipment->status, ['rider_assigned', 'out_for_delivery', 'delivery_failed'], true);

        return response()->json([
            'success'  => true,
            'can_send' => $canSend,
            'rider'    => $shipment->currentRider ? [
                'id'           => (int) $shipment->currentRider->id,
                'name'         => $shipment->currentRider->full_name,
                'contact_no'   => $shipment->currentRider->contact_no,
                'vehicle_type' => $shipment->currentRider->vehicle_type,
                'plate_number' => $shipment->currentRider->plate_number,
            ] : null,
            'messages' => $messages,
        ]);
    }

    public function sendDeliveryChatMessage(Request $request, $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $sellerOrder = SellerOrder::with(['shipment.currentRider', 'shipment.deliveryAssignments'])->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $shipment = $sellerOrder->shipment;
        if (
            ! $shipment
            || ! $shipment->currentRider
            || ! in_array($shipment->status, ['rider_assigned', 'out_for_delivery', 'delivery_failed'], true)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery chat is only active while a Rider is assigned and delivering your parcel.',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $activeAssignment = $shipment->deliveryAssignments
            ->where('rider_id', $shipment->current_rider_id)
            ->sortByDesc('id')
            ->first();

        $chatMsg = DeliveryChatMessage::create([
            'shipment_id'            => $shipment->id,
            'seller_order_id'        => $sellerOrder->id,
            'delivery_assignment_id' => $activeAssignment?->id,
            'buyer_id'               => $user->id,
            'rider_id'               => $shipment->current_rider_id,
            'sender_type'            => 'buyer',
            'message'                => trim($validated['message']),
            'is_read'                => false,
        ]);

        try {
            $shipment->currentRider->notify(new OrderLifecycleNotification(
                title: "Buyer Delivery Note (#{$shipment->tracking_number})",
                message: "{$user->full_name}: \"" . substr($chatMsg->message, 0, 120) . '"',
                type: 'info',
                url: '/rider/dashboard',
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id,
                shipmentId: $shipment->id
            ));
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent to Rider.',
            'data'    => [
                'id'          => (int) $chatMsg->id,
                'sender_type' => $chatMsg->sender_type,
                'message'     => $chatMsg->message,
                'created_at'  => $chatMsg->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function cancelOrder(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'min:3', 'max:150'],
            'cancel_notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'cancel_reason.required' => 'Please select a reason for cancelling this order.',
        ]);

        $this->cancellationGuardService->validateCanCancelOrder($sellerOrder, (int) $user->id);

        $refundedWalletAmount = 0.00;

        DB::transaction(function () use ($sellerOrder, $user, $validated, &$refundedWalletAmount) {
            $locked = SellerOrder::with(['order', 'payment', 'paymentSubmission', 'extra', 'items'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'cancelled') {
                return;
            }

            if (! $locked->canBuyerCancelPreShipment()) {
                throw ValidationException::withMessages([
                    'cancel' => 'This order can no longer be cancelled because the seller has already marked it Ready for Pickup or handed it over.',
                ]);
            }

            $prevStatus = $locked->status;
            $wasWalletPaid = strtoupper((string) $locked->effective_payment_method) === 'WALLET'
                || (float) ($locked->extra?->wallet_paid_amount ?? 0) > 0;

            $locked->update([
                'status'           => 'cancelled',
                'payment_status'   => 'cancelled',
                'cancelled_at'     => now(),
                'rejection_reason' => 'Cancelled by Buyer: ' . trim($validated['cancel_reason']),
            ]);

            $locked->payment?->update([
                'status' => 'cancelled',
            ]);

            $locked->paymentSubmission?->update([
                'status' => 'cancelled_by_buyer',
            ]);

            $extra = $locked->extra ?: SellerOrderExtra::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
            ]);

            $extra->update([
                'buyer_cancel_reason'   => trim($validated['cancel_reason']),
                'buyer_cancel_notes'    => ! empty($validated['cancel_notes']) ? trim($validated['cancel_notes']) : null,
                'cancelled_by_buyer_id' => $user->id,
            ]);

            if ($wasWalletPaid) {
                $refundedWalletAmount = (float) ($extra->wallet_paid_amount > 0 ? $extra->wallet_paid_amount : $locked->total_amount);
                $this->roleWalletService?->refundBuyerWalletForOrder(
                    $locked,
                    $refundedWalletAmount,
                    'Pre-Shipment Order Cancellation Refund'
                );
            }

            $this->inventoryStockService->restoreStockForSellerOrder($locked);
            $this->voucherService->restoreVouchersForSellerOrder($locked);
            $this->buyerRewardService?->restoreCoinsForSellerOrder($locked);
            $this->cancellationGuardService->recordOrderCancellation($locked, (int) $user->id, $validated['cancel_reason']);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $prevStatus,
                'to_status'       => 'cancelled',
                'actor_type'      => 'Buyer',
                'actor_id'        => $user->id,
                'remarks'         => 'Buyer cancelled order: ' . trim($validated['cancel_reason'])
                    . (! empty($validated['cancel_notes']) ? ' (' . trim($validated['cancel_notes']) . ')' : '')
                    . ($refundedWalletAmount > 0 ? ' ₱' . number_format($refundedWalletAmount, 2) . ' refunded immediately to Buyer E-Wallet.' : ''),
            ]);

            if ($locked->order) {
                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            }
        });

        try {
            $sellerOrder->seller?->notify(new OrderLifecycleNotification(
                title: 'Order Cancelled by Buyer',
                message: "Buyer cancelled Order #{$sellerOrder->seller_order_number}. Reason: {$validated['cancel_reason']}. Reserved stock has been restored.",
                type: 'warning',
                url: route('seller.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        $walletMsg = $refundedWalletAmount > 0
            ? ' ₱' . number_format($refundedWalletAmount, 2) . ' has been refunded immediately to your Storkia E-Wallet.'
            : '';

        return response()->json([
            'success' => true,
            'message' => "Order #{$sellerOrder->seller_order_number} has been cancelled. Reserved product stock, coins, and any applied vouchers have been returned.{$walletMsg}",
        ]);
    }

    public function switchToCod(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($sellerOrder, $user) {
            $locked = SellerOrder::with(['order', 'payment', 'paymentSubmission', 'seller'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_buyer_response') {
                throw ValidationException::withMessages([
                    'order' => 'This order is not awaiting a payment switch response.',
                ]);
            }

            if ($locked->paymentSubmission) {
                $locked->paymentSubmission->update([
                    'status'             => 'switched_to_cod',
                    'switched_to_cod'    => true,
                    'switched_to_cod_at' => now(),
                ]);
            }

            $locked->payment?->update([
                'method' => 'COD',
                'status' => 'unpaid',
            ]);

            $locked->update([
                'status'                 => 'pending_seller_acceptance',
                'payment_status'         => 'unpaid',
                'acceptance_deadline_at' => now()->addHours(SellerOrder::ACCEPTANCE_DEADLINE_HOURS),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => 'pending_buyer_response',
                'to_status'       => 'pending_seller_acceptance',
                'actor_type'      => 'Buyer',
                'actor_id'        => $user->id,
                'remarks'         => 'Buyer reviewed flagged payment details and confirmed continuing the transaction by switching payment method to Cash on Delivery (COD).',
            ]);

            if ($locked->order) {
                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            }
        });

        try {
            $sellerOrder->seller?->notify(new OrderLifecycleNotification(
                title: 'Buyer Switched Order to Cash on Delivery (COD)',
                message: "Buyer confirmed switching Order #{$sellerOrder->seller_order_number} to Cash on Delivery (COD). It is now back in your New Orders queue for acceptance.",
                type: 'info',
                url: route('seller.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'message' => "Order #{$sellerOrder->seller_order_number} has been switched to Cash on Delivery (COD) and returned to the seller for acceptance!",
        ]);
    }

    public function cancelFlaggedPaymentOrder(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($sellerOrder, $user) {
            $locked = SellerOrder::with(['order', 'payment', 'paymentSubmission', 'extra'])
                ->where('id', $sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_buyer_response') {
                throw ValidationException::withMessages([
                    'order' => 'This order is no longer in Pending Buyer Response status.',
                ]);
            }

            $locked->paymentSubmission?->update([
                'status' => 'cancelled_by_buyer',
            ]);

            $locked->payment?->update([
                'status' => 'cancelled',
            ]);

            $locked->update([
                'status'           => 'cancelled',
                'payment_status'   => 'cancelled',
                'cancelled_at'     => now(),
                'rejection_reason' => 'Cancelled immediately by Buyer after online payment was flagged.',
            ]);

            $extra = $locked->extra ?: SellerOrderExtra::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
            ]);

            $extra->update([
                'buyer_cancel_reason'   => 'Cancelled after flagged online payment',
                'cancelled_by_buyer_id' => $user->id,
            ]);

            $this->inventoryStockService->restoreStockForSellerOrder($locked);
            $this->voucherService->restoreVouchersForSellerOrder($locked);
            $this->buyerRewardService?->restoreCoinsForSellerOrder($locked);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => 'pending_buyer_response',
                'to_status'       => 'cancelled',
                'actor_type'      => 'Buyer',
                'actor_id'        => $user->id,
                'remarks'         => 'Buyer chose to cancel the order immediately instead of switching to COD.',
            ]);

            if ($locked->order) {
                $this->orderCompletionService->syncParentOrderStatus($locked->order);
            }
        });

        try {
            $sellerOrder->seller?->notify(new OrderLifecycleNotification(
                title: 'Flagged Payment Order Cancelled by Buyer',
                message: "Buyer chose to cancel Order #{$sellerOrder->seller_order_number}. Reserved product stock has been restored.",
                type: 'warning',
                url: route('seller.orders.show', $sellerOrder->id),
                orderId: $sellerOrder->order_id,
                sellerOrderId: $sellerOrder->id
            ));
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'message' => "Order #{$sellerOrder->seller_order_number} has been cancelled immediately. Reserved stock, coins, and any applied vouchers have been restored.",
        ]);
    }

    public function confirmReceipt(Request $request, $id): JsonResponse
    {
        $sellerOrder = SellerOrder::findOrFail($id);
        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->orderCompletionService->confirmSellerOrder(
            sellerOrder: $sellerOrder,
            actorType: 'Buyer',
            actorId: $request->user()->id,
            isAutoCompleted: false
        );

        return response()->json([
            'success' => true,
            'message' => "Order #{$sellerOrder->seller_order_number} confirmed and completed! You can now rate the product, condition, seller, logistics, and rider.",
        ]);
    }

    public function reportDispute(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $latestVerification = $user->identityVerification;
        if (! $latestVerification || $latestVerification->status !== 'verified') {
            throw ValidationException::withMessages([
                'identity_verification' => 'Valid ID Verification Required: You must have an approved Valid Government ID on your account to request a Return & Refund. Please complete Identity Verification in your Account Management settings first.',
            ]);
        }

        $rawRefundPhone = preg_replace('/\D/', '', (string) $request->input('refund_account_number'));
        if (str_starts_with($rawRefundPhone, '63') && strlen($rawRefundPhone) === 12) {
            $rawRefundPhone = '0' . substr($rawRefundPhone, 2);
        } elseif (strlen($rawRefundPhone) === 10 && str_starts_with($rawRefundPhone, '9')) {
            $rawRefundPhone = '0' . $rawRefundPhone;
        }
        $request->merge(['refund_account_number' => $rawRefundPhone]);

        $validated = $request->validate([
            'dispute_reason_category' => ['required', 'string', 'max:120'],
            'dispute_reason'          => ['required', 'string', 'min:10', 'max:1000'],
            'refund_method'           => ['required', 'in:GCASH,WALLET'],
            'refund_account_name'     => ['required_if:refund_method,GCASH', 'nullable', 'string', 'max:120'],
            'refund_account_number'   => ['required_if:refund_method,GCASH', 'nullable', 'regex:/^09\d{9}$/'],
            'evidence_photos'         => ['required', 'array', 'min:1', 'max:5'],
            'evidence_photos.*'       => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $storedPhotos = [];
        $manager = ImageManager::usingDriver(Driver::class);
        foreach ($request->file('evidence_photos', []) as $file) {
            $filename = uniqid('dispute_') . '_' . time() . '.webp';
            $path = 'dispute_evidence/' . $filename;
            $img = $manager->decode($file->getRealPath());
            $img->scaleDown(width: 1200, height: 1200);
            $encoded = $img->encode(new WebpEncoder(quality: 85));
            Storage::disk('public')->put($path, $encoded->toString());
            $storedPhotos[] = $path;
        }

        $refundSummary = $validated['refund_method'] === 'GCASH'
            ? "Refund via GCash ({$validated['refund_account_name']} - {$validated['refund_account_number']})"
            : 'Instant Refund via Storkia Buyer E-Wallet';

        $fullReason = '[' . trim($validated['dispute_reason_category']) . ' | ' . $refundSummary . '] ' . trim($validated['dispute_reason']);

        $this->orderCompletionService->reportDeliveryProblem(
            sellerOrder: $sellerOrder,
            buyerId: $user->id,
            reason: $fullReason
        );

        $extra = SellerOrderExtra::firstOrCreate(
            ['seller_order_id' => $sellerOrder->id],
            ['order_id' => $sellerOrder->order_id]
        );

        $extra->update([
            'dispute_reason_category' => trim($validated['dispute_reason_category']),
            'dispute_evidence_photos' => $storedPhotos,
            'refund_method'           => $validated['refund_method'],
            'refund_account_name'     => $validated['refund_method'] === 'GCASH' ? trim((string) $validated['refund_account_name']) : $user->full_name,
            'refund_account_number'   => $validated['refund_method'] === 'GCASH' ? $validated['refund_account_number'] : null,
            'refund_amount'           => $sellerOrder->total_amount,
            'refund_status'           => 'pending_approval',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your Valid-ID-verified Return & Refund request and photo evidence have been submitted. Automatic order completion is now paused while under review.',
        ]);
    }

    public function escalateDispute(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $extra = SellerOrderExtra::where('seller_order_id', $sellerOrder->id)->first();
        if (! $extra || $extra->seller_return_decision !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Only return requests that were declined by the seller can be escalated to Admin.',
            ], 422);
        }

        if ($extra->is_escalated_to_admin) {
            return response()->json([
                'success' => false,
                'message' => 'This return request has already been escalated to Admin.',
            ], 422);
        }

        $extra->update([
            'is_escalated_to_admin' => true,
            'escalated_at'          => now(),
        ]);

        OrderStatusHistory::create([
            'order_id'        => $sellerOrder->order_id,
            'seller_order_id' => $sellerOrder->id,
            'from_status'     => 'disputed',
            'to_status'       => 'disputed',
            'actor_type'      => 'Buyer',
            'actor_id'        => $user->id,
            'remarks'         => 'Buyer escalated declined return/refund request to Storkia Admin for final resolution.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your refund dispute has been escalated to Storkia Admin for final review.',
        ]);
    }

    public function buyAgain(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $sellerOrder = SellerOrder::with('items.product')->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $addedCount = 0;

        foreach ($sellerOrder->items as $item) {
            $product = Product::with('approval')->find($item->product_id);
            if (! $product || $product->approval?->status !== 'Approved') {
                continue;
            }

            try {
                $resolved = $this->inventoryStockService->resolveProductSelection(
                    $product,
                    $item->variant_name,
                    $item->sub_variant_name
                );

                if ($resolved['available_stock'] < 1) {
                    continue;
                }

                $qtyToAdd = min((int) $item->quantity, (int) $resolved['available_stock']);

                $existingCart = Cart::where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->where('variant', $item->variant_name)
                    ->where('sub_variant', $item->sub_variant_name)
                    ->first();

                if ($existingCart) {
                    $existingCart->update([
                        'quantity' => min((int) $resolved['available_stock'], (int) $existingCart->quantity + $qtyToAdd),
                    ]);
                } else {
                    Cart::create([
                        'user_id'     => $user->id,
                        'product_id'  => $product->id,
                        'variant'     => $item->variant_name,
                        'sub_variant' => $item->sub_variant_name,
                        'quantity'    => $qtyToAdd,
                    ]);
                }

                $addedCount++;
            } catch (\Throwable $e) {
            }
        }

        if ($addedCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'The items in this order are currently out of stock or unavailable for reorder.',
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'added_count' => $addedCount,
            'message'     => "{$addedCount} item(s) from Order #{$sellerOrder->seller_order_number} have been added to your Shopping Cart!",
        ]);
    }

    public function proofImage(Request $request, DeliveryProof $proof)
    {
        $proof->loadMissing(['sellerOrder', 'shipment.sellerOrder']);
        $sellerOrder = $proof->sellerOrder ?? $proof->shipment?->sellerOrder;
        $user = $request->user();

        if (
            ! $user ||
            ! $sellerOrder ||
            (
                (int) $sellerOrder->buyer_id !== (int) $user->id &&
                (int) $sellerOrder->seller_id !== (int) $user->id
            )
        ) {
            abort(403);
        }

        $rawPath = ltrim((string) $proof->photo_path, '/');
        if (str_starts_with($rawPath, 'storage/')) {
            $rawPath = substr($rawPath, strlen('storage/'));
        }
        if (str_starts_with($rawPath, 'public/')) {
            $rawPath = substr($rawPath, strlen('public/'));
        }

        $fullPath = null;
        if ($rawPath !== '') {
            if (Storage::disk('public')->exists($rawPath)) {
                $fullPath = Storage::disk('public')->path($rawPath);
            } elseif (Storage::disk('local')->exists($rawPath)) {
                $fullPath = Storage::disk('local')->path($rawPath);
            } elseif (file_exists(public_path('storage/' . $rawPath))) {
                $fullPath = public_path('storage/' . $rawPath);
            }
        }

        if (! $fullPath || ! file_exists($fullPath)) {
            abort(404, 'Delivery proof photo not found.');
        }

        $mimeType = mime_content_type($fullPath) ?: 'image/webp';

        return response()->file($fullPath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    protected function appendComputedOrderAttributes(SellerOrder $so): SellerOrder
    {
        $so->setAttribute('status_label', $so->status_label);
        $so->setAttribute('effective_payment_method', $so->effective_payment_method);
        $so->setAttribute('is_rated_by_buyer', $so->isRatedByBuyer());
        $so->setAttribute('can_buyer_cancel_pre_shipment', $so->canBuyerCancelPreShipment());

        if ($so->relationLoaded('paymentSubmission') && $so->paymentSubmission) {
            $so->paymentSubmission->setAttribute('screenshot_url', $so->paymentSubmission->screenshot_url);
        }

        $formattedReturn = null;
        if ($so->relationLoaded('returnRequest') && $so->returnRequest) {
            try {
                $formattedReturn = $this->formatReturnRequest($so->returnRequest);
            } catch (\Throwable $e) {
                $formattedReturn = null;
            }
        }
        $so->setAttribute('formatted_return_request', $formattedReturn);

        $hasUnresolvedDispute = $so->status === 'disputed'
            || ($formattedReturn !== null && ! in_array($formattedReturn['status'] ?? '', ['resolved', 'rejected', 'closed'], true));
        $so->setAttribute('has_unresolved_dispute', $hasUnresolvedDispute);

        return $so;
    }
}