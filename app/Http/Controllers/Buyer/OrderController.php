<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProof;
use App\Models\OrderStatusHistory;
use App\Models\SellerOrder;
use App\Models\SellerOrderExtra;
use App\Notifications\OrderLifecycleNotification;
use App\Services\BuyerCancellationGuardService;
use App\Services\InventoryStockService;
use App\Services\OrderCompletionService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class OrderController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected InventoryStockService $inventoryStockService,
        protected VoucherService $voucherService,
        protected BuyerCancellationGuardService $cancellationGuardService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(buyerId: (int) $user->id);

        $statusTab = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = SellerOrder::where('buyer_id', $user->id);

        $counts = [
            'all'        => (clone $baseQuery)->count(),
            'unpaid'     => (clone $baseQuery)->whereIn('payment_status', ['unpaid', 'flagged'])->whereNotIn('status', ['rejected', 'cancelled', 'returned_to_seller'])->count(),
            'processing' => (clone $baseQuery)->whereIn('status', ['pending_seller_acceptance', 'pending_buyer_response', 'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned'])->count(),
            'shipped'    => (clone $baseQuery)->whereIn('status', ['picked_up', 'at_logistics_hub', 'out_for_delivery', 'delivery_failed'])->count(),
            'review'     => (clone $baseQuery)->whereIn('status', ['delivered', 'completed'])->count(),
            'returns'    => (clone $baseQuery)->whereIn('status', ['disputed', 'rejected', 'cancelled', 'returned_to_seller'])->count(),
        ];

        $query = SellerOrder::with([
            'order',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'shipment.latestDeliveryProof',
            'shipment.packages',
            'payment',
            'paymentSubmission',
            'extra',
        ])->where('buyer_id', $user->id);

        match ($statusTab) {
            'unpaid'     => $query->whereIn('payment_status', ['unpaid', 'flagged'])->whereNotIn('status', ['rejected', 'cancelled', 'returned_to_seller']),
            'processing' => $query->whereIn('status', ['pending_seller_acceptance', 'pending_buyer_response', 'accepted', 'preparing', 'ready_for_pickup', 'pickup_assigned']),
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $cancellationQuota = $this->cancellationGuardService->getBuyerQuotaSummary((int) $user->id);

        return view('buyer.option.orders.index', compact(
            'sellerOrders',
            'statusTab',
            'search',
            'counts',
            'cancellationQuota'
        ));
    }

    public function show(Request $request, SellerOrder $sellerOrder)
    {
        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            abort(403);
        }

        $this->orderCompletionService->autoCompleteExpiredDeliveredOrders(buyerId: (int) $request->user()->id);
        $sellerOrder->refresh();

        $sellerOrder->load([
            'order',
            'items',
            'sellerProfile',
            'logisticsProfile',
            'payment',
            'paymentSubmission',
            'extra',
            'statusHistories',
            'shipment.packages',
            'shipment.trackingLogs',
            'shipment.currentCourier',
            'shipment.currentRider',
            'shipment.deliveryAttempts',
            'shipment.deliveryProofs',
        ]);

        $cancellationQuota = $this->cancellationGuardService->getBuyerQuotaSummary((int) $request->user()->id);

        return view('buyer.option.orders.show', compact('sellerOrder', 'cancellationQuota'));
    }

    public function switchToCod(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            abort(403);
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
                'remarks'         => 'Buyer reviewed flagged GCash payment details and confirmed continuing the transaction by switching payment method to Cash on Delivery (COD).',
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

        return back()->with('success', "Order #{$sellerOrder->seller_order_number} has been switched to Cash on Delivery (COD) and returned to the seller for acceptance!");
    }

    public function cancelFlaggedPaymentOrder(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            abort(403);
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
                'rejection_reason' => 'Cancelled immediately by Buyer after GCash online payment was flagged by Seller.',
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

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => 'pending_buyer_response',
                'to_status'       => 'cancelled',
                'actor_type'      => 'Buyer',
                'actor_id'        => $user->id,
                'remarks'         => 'Buyer chose to cancel the order immediately instead of switching to COD after online payment was flagged.',
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

        return back()->with('success', "Order #{$sellerOrder->seller_order_number} has been cancelled immediately. Reserved stock and any applied vouchers have been restored.");
    }

    public function cancelOrder(Request $request, SellerOrder $sellerOrder)
    {
        $user = $request->user();
        if ((int) $sellerOrder->buyer_id !== (int) $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'min:3', 'max:150'],
            'cancel_notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'cancel_reason.required' => 'Please select a reason for cancelling this order.',
        ]);

        $this->cancellationGuardService->validateCanCancelOrder($sellerOrder, (int) $user->id);

        DB::transaction(function () use ($sellerOrder, $user, $validated) {
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
                'buyer_cancel_notes'    => $validated['cancel_notes'] ? trim($validated['cancel_notes']) : null,
                'cancelled_by_buyer_id' => $user->id,
            ]);

            $this->inventoryStockService->restoreStockForSellerOrder($locked);
            $this->voucherService->restoreVouchersForSellerOrder($locked);
            $this->cancellationGuardService->recordOrderCancellation($locked, (int) $user->id, $validated['cancel_reason']);

            OrderStatusHistory::create([
                'order_id'        => $locked->order_id,
                'seller_order_id' => $locked->id,
                'from_status'     => $prevStatus,
                'to_status'       => 'cancelled',
                'actor_type'      => 'Buyer',
                'actor_id'        => $user->id,
                'remarks'         => 'Buyer cancelled order: ' . trim($validated['cancel_reason']) . ($validated['cancel_notes'] ? ' (' . trim($validated['cancel_notes']) . ')' : ''),
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

        return back()->with('success', "Order #{$sellerOrder->seller_order_number} has been cancelled. Reserved product stock and any applied vouchers have been returned.");
    }

    public function confirmReceipt(Request $request, SellerOrder $sellerOrder)
    {
        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            abort(403);
        }

        $this->orderCompletionService->confirmSellerOrder(
            sellerOrder: $sellerOrder,
            actorType: 'Buyer',
            actorId: $request->user()->id,
            isAutoCompleted: false
        );

        return back()->with('success', "Thank you! Order #{$sellerOrder->seller_order_number} has been confirmed and completed.");
    }

    public function reportDispute(Request $request, SellerOrder $sellerOrder)
    {
        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'dispute_reason_category' => ['required', 'string', 'max:120'],
            'dispute_reason'          => ['required', 'string', 'min:10', 'max:1000'],
            'evidence_photos'         => ['required', 'array', 'min:1', 'max:5'],
            'evidence_photos.*'       => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'dispute_reason_category.required' => 'Please select a return/dispute reason category.',
            'dispute_reason.required'          => 'Please describe the delivery issue.',
            'dispute_reason.min'               => 'Please provide at least 10 characters explaining the issue.',
            'evidence_photos.required'         => 'Please upload 1 to 5 photo evidence files.',
            'evidence_photos.min'              => 'Please upload at least 1 evidence photo.',
            'evidence_photos.max'              => 'You may upload a maximum of 5 evidence photos.',
            'evidence_photos.*.max'            => 'Each evidence photo must not exceed 5MB.',
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

        $fullReason = '[' . trim($validated['dispute_reason_category']) . '] ' . trim($validated['dispute_reason']);

        $this->orderCompletionService->reportDeliveryProblem(
            sellerOrder: $sellerOrder,
            buyerId: $request->user()->id,
            reason: $fullReason
        );

        $extra = SellerOrderExtra::firstOrCreate(
            ['seller_order_id' => $sellerOrder->id],
            ['order_id' => $sellerOrder->order_id]
        );

        $extra->update([
            'dispute_reason_category' => trim($validated['dispute_reason_category']),
            'dispute_evidence_photos' => $storedPhotos,
        ]);

        return back()->with('success', 'Your return/dispute request and photo evidence have been submitted. Automatic order completion is now paused while under review.');
    }

    public function escalateDispute(Request $request, SellerOrder $sellerOrder)
    {
        if ((int) $sellerOrder->buyer_id !== (int) $request->user()->id) {
            abort(403);
        }

        $extra = SellerOrderExtra::where('seller_order_id', $sellerOrder->id)->first();

        if (! $extra || $extra->seller_return_decision !== 'rejected') {
            return back()->withErrors([
                'dispute' => 'Only return requests that were declined by the seller can be escalated to Admin.',
            ]);
        }

        if ($extra->is_escalated_to_admin) {
            return back()->withErrors([
                'dispute' => 'This return request has already been escalated to Admin.',
            ]);
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
            'actor_id'        => $request->user()->id,
            'remarks'         => 'Buyer escalated declined return request to Storkia Admin for final resolution.',
        ]);

        return back()->with('success', 'Your dispute has been escalated to Storkia Admin for final review.');
    }

    public function proofImage(Request $request, DeliveryProof $proof)
    {
        $proof->loadMissing('sellerOrder');
        $sellerOrder = $proof->sellerOrder;

        $authorized = false;

        if (Auth::guard('admin')->check()) {
            $authorized = true;
        } elseif (Auth::guard('rider')->check() && (int) Auth::guard('rider')->id() === (int) $proof->rider_id) {
            $authorized = true;
        } elseif ($request->user()) {
            $user = $request->user();
            if (
                (int) $sellerOrder->buyer_id === (int) $user->id ||
                (int) $sellerOrder->seller_id === (int) $user->id ||
                ($user->logisticsProfile && (int) $sellerOrder->logistics_profile_id === (int) $user->logisticsProfile->id)
            ) {
                $authorized = true;
            }
        }

        if (! $authorized) {
            abort(403);
        }

        $disk = Storage::disk('local')->exists($proof->photo_path) ? 'local' : 'public';

        if (! Storage::disk($disk)->exists($proof->photo_path)) {
            abort(404, 'Delivery proof photo not found.');
        }

        return Storage::disk($disk)->response($proof->photo_path);
    }
}