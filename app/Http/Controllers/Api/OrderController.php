<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Services\OrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(buyerId: (int) $user->id);

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = SellerOrder::with([
            'order', 'items', 'sellerProfile', 'logisticsProfile', 
            'shipment.latestDeliveryProof', 'shipment.packages', 'payment'
        ])->where('buyer_id', $user->id);

        match ($statusTab) {
            'unpaid'     => $query->where('payment_status', 'unpaid')->whereNotIn('status', ['rejected', 'cancelled', 'returned_to_seller']),
            'processing' => $query->whereIn('status', ['pending_seller_acceptance', 'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned']),
            'shipped'    => $query->whereIn('status', ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivery_failed']),
            'review'     => $query->whereIn('status', ['delivered', 'completed']),
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
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $sellerOrders
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $sellerOrder = SellerOrder::with([
            'order', 'items', 'sellerProfile', 'logisticsProfile', 'payment',
            'statusHistories', 'shipment.packages', 'shipment.trackingLogs',
            'shipment.currentCourier', 'shipment.currentRider', 'shipment.deliveryAttempts',
            'shipment.deliveryProofs'
        ])->findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(buyerId: (int) $request->user()->id);
        $sellerOrder->refresh();

        return response()->json([
            'success' => true,
            'data' => $sellerOrder
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
            'message' => "Order #{$sellerOrder->seller_order_number} confirmed."
        ]);
    }

    public function reportDispute(Request $request, $id): JsonResponse
    {
        $sellerOrder = SellerOrder::findOrFail($id);

        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'dispute_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->orderCompletionService->reportDeliveryProblem(
            sellerOrder: $sellerOrder,
            buyerId: $request->user()->id,
            reason: $validated['dispute_reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Delivery issue reported. Order completion paused.'
        ]);
    }
}