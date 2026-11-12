<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\PickupController as WebPickupController;
use App\Models\Courier;
use App\Models\OrderServiceRating;
use App\Models\PickupAssignment;
use App\Models\ReturnShipment;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\CourierMobileSchemaService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CourierNotificationController extends Controller
{
    public function __construct(
        protected RoleWalletService $roleWalletService
    ) {}

    public function badges(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $baseQuery = PickupAssignment::where('courier_id', $courier->id)
            ->where('logistics_profile_id', $courier->logistics_profile_id)
            ->whereHas('shipment', fn ($q) => $q->where('status', '!=', 'awaiting_seller_drop_off'));

        $unacceptedCount = (clone $baseQuery)->where('status', 'assigned')->count();
        $acceptedCount = (clone $baseQuery)->where('status', 'accepted')->count();
        $inTransitCount = (clone $baseQuery)->whereIn('status', ['picked_up', 'in_transit_to_hub'])->count();

        $pendingReturnDeliveries = Schema::hasTable('return_shipments')
            ? ReturnShipment::where('current_courier_id', $courier->id)
                ->whereIn('status', [
                    ReturnShipment::STATUS_COURIER_ASSIGNED,
                    ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
                ])
                ->count()
            : 0;

        $totalPickupsBadge = $unacceptedCount + $acceptedCount + $inTransitCount + $pendingReturnDeliveries;
        $unreadNotificationsCount = $courier->unreadNotifications()->count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'unaccepted_pickups_count'   => $unacceptedCount,
                'accepted_pickups_count'     => $acceptedCount,
                'in_transit_pickups_count'   => $inTransitCount,
                'pending_return_deliveries'  => $pendingReturnDeliveries,
                'total_pickups_badge'        => $totalPickupsBadge,
                'pickups_count'              => $totalPickupsBadge,
                'unread_notifications_count' => $unreadNotificationsCount,
                'unread_count'               => $unreadNotificationsCount,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $notifications = $courier->notifications()
            ->orderByDesc('created_at')
            ->paginate(30);

        $items = collect($notifications->items())->map(function ($notif) use ($courier) {
            $data = is_array($notif->data) ? $notif->data : [];
            $shipmentId = $data['shipment_id'] ?? null;
            $sellerOrderId = $data['seller_order_id'] ?? null;

            $pickupAssignmentId = $data['pickup_assignment_id'] ?? null;
            if (! $pickupAssignmentId && ($shipmentId || $sellerOrderId)) {
                $matchedAssignment = PickupAssignment::where('courier_id', $courier->id)
                    ->when($shipmentId, fn ($q) => $q->where('shipment_id', (int) $shipmentId))
                    ->when(! $shipmentId && $sellerOrderId, fn ($q) => $q->where('seller_order_id', (int) $sellerOrderId))
                    ->latest('id')
                    ->first();
                $pickupAssignmentId = $matchedAssignment?->id;
            }

            $data['pickup_assignment_id'] = $pickupAssignmentId;

            return [
                'id'                   => (string) $notif->id,
                'type'                 => (string) $notif->type,
                'data'                 => $data,
                'pickup_assignment_id' => $pickupAssignmentId,
                'read_at'              => $notif->read_at?->toIso8601String(),
                'created_at'           => $notif->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'unread_count'  => $courier->unreadNotifications()->count(),
                'notifications' => [
                    'data'         => $items,
                    'current_page' => $notifications->currentPage(),
                    'last_page'    => $notifications->lastPage(),
                    'total'        => $notifications->total(),
                ],
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $notification = $courier->notifications()->where('id', $id)->first();
        if ($notification && $notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification marked as read.',
            'data'    => [
                'unread_count' => $courier->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $courier->unreadNotifications->markAsRead();

        return response()->json([
            'status'  => 'success',
            'message' => 'All notifications marked as read.',
            'data'    => [
                'unread_count' => 0,
            ],
        ]);
    }

    public function earningsAndWallet(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $wallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        $nowManila = now()->timezone('Asia/Manila');
        $todayStart = $nowManila->copy()->startOfDay();
        $weekStart = $nowManila->copy()->startOfWeek();
        $monthStart = $nowManila->copy()->startOfMonth();

        $positiveTxQuery = WalletTransaction::where('owner_type', 'Courier')
            ->where('owner_id', $courier->id)
            ->where('amount', '>', 0);

        $todayEarned = round((float) (clone $positiveTxQuery)->where('created_at', '>=', $todayStart)->sum('amount'), 2);
        $weekEarned = round((float) (clone $positiveTxQuery)->where('created_at', '>=', $weekStart)->sum('amount'), 2);
        $monthEarned = round((float) (clone $positiveTxQuery)->where('created_at', '>=', $monthStart)->sum('amount'), 2);

        $peso = "\u{20B1}";

        $transactions = WalletTransaction::with(['sellerOrder', 'shipment'])
            ->where('owner_type', 'Courier')
            ->where('owner_id', $courier->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (WalletTransaction $tx) use ($peso) {
                return [
                    'id'                      => (int) $tx->id,
                    'transaction_reference'   => (string) $tx->transaction_reference,
                    'entry_type'              => (string) $tx->entry_type,
                    'payment_method'          => (string) $tx->payment_method,
                    'amount'                  => round((float) $tx->amount, 2),
                    'amount_formatted'        => ($tx->amount >= 0 ? '+' . $peso : '-' . $peso) . number_format(abs((float) $tx->amount), 2),
                    'available_balance_after' => round((float) $tx->available_balance_after, 2),
                    'status'                  => (string) $tx->status,
                    'description'             => (string) $tx->description,
                    'tracking_number'         => $tx->shipment?->tracking_number,
                    'seller_order_number'     => $tx->sellerOrder?->seller_order_number,
                    'created_at'              => $tx->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $withdrawals = Schema::hasTable('withdrawal_requests')
            ? WithdrawalRequest::where('requester_type', 'Courier')
                ->where('requester_id', $courier->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (WithdrawalRequest $w) => [
                    'id'                     => (int) $w->id,
                    'withdrawal_reference'   => (string) $w->withdrawal_reference,
                    'requested_amount'       => round((float) $w->requested_amount, 2),
                    'payout_method'          => (string) $w->payout_method,
                    'account_name'           => (string) $w->account_name,
                    'account_number'         => (string) $w->account_number,
                    'status'                 => (string) $w->status,
                    'rejection_reason'       => $w->rejection_reason,
                    'payout_proof_reference' => $w->payout_proof_reference,
                    'created_at'             => $w->created_at?->toIso8601String(),
                    'paid_at'                => $w->paid_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        $recentCompletedPickups = PickupAssignment::with(['shipment.sellerProfile', 'sellerOrder'])
            ->where('courier_id', $courier->id)
            ->whereIn('status', ['in_transit_to_hub', 'completed', 'declined', 'reassigned'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(function (PickupAssignment $p) use ($peso) {
                $pay = ($p->shipment && in_array($p->status, ['in_transit_to_hub', 'completed'], true))
                    ? WebPickupController::calculateFirstMileCourierPay($p->shipment)
                    : ['total_pay' => 0.00, 'distance_tier' => ucfirst($p->status)];

                return [
                    'id'                  => (int) $p->id,
                    'tracking_number'     => $p->shipment?->tracking_number ?? 'TRK-N/A',
                    'seller_order_number' => $p->sellerOrder?->seller_order_number ?? 'SO-N/A',
                    'seller_name'         => $p->shipment?->sellerProfile?->business_name ?? 'Seller',
                    'status'              => (string) $p->status,
                    'distance_tier'       => (string) $pay['distance_tier'],
                    'pay_amount'          => round((float) $pay['total_pay'], 2),
                    'pay_formatted'       => $peso . number_format((float) $pay['total_pay'], 2),
                    'updated_at'          => $p->updated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'wallet' => [
                    'available_balance' => round((float) $wallet->available_balance, 2),
                    'pending_balance'   => round((float) $wallet->pending_balance, 2),
                    'total_earned'      => round((float) $wallet->total_earned, 2),
                    'total_withdrawn'   => round((float) $wallet->total_withdrawn, 2),
                    'today_earned'      => $todayEarned,
                    'week_earned'       => $weekEarned,
                    'month_earned'      => $monthEarned,
                ],
                'transactions'      => $transactions,
                'withdrawals'       => $withdrawals,
                'completed_pickups' => $recentCompletedPickups,
            ],
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();
        $peso = "\u{20B1}";

        $validated = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:100', 'max:500000'],
            'payout_method'    => ['nullable', 'string'],
            'account_name'     => ['required', 'string', 'min:2', 'max:120'],
            'account_number'   => ['required', 'string', 'min:10', 'max:25'],
            'idempotency_key'  => ['nullable', 'string', 'max:64'],
        ], [
            'requested_amount.min' => "Minimum withdrawal request amount is {$peso}100.00.",
        ]);

        $withdrawal = $this->roleWalletService->submitWithdrawalRequest(
            ownerType: 'Courier',
            ownerId: (int) $courier->id,
            ownerName: $courier->full_name,
            amount: (float) $validated['requested_amount'],
            payoutMethod: 'GCASH',
            accountName: $validated['account_name'],
            accountNumber: $validated['account_number'],
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        $wallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        return response()->json([
            'status'  => 'success',
            'message' => "Withdrawal Request #{$withdrawal->withdrawal_reference} for {$peso}" . number_format((float) $withdrawal->requested_amount, 2) . ' via GCash submitted to Storkia Admin for approval!',
            'data'    => [
                'withdrawal' => [
                    'id'                   => (int) $withdrawal->id,
                    'withdrawal_reference' => (string) $withdrawal->withdrawal_reference,
                    'requested_amount'     => round((float) $withdrawal->requested_amount, 2),
                    'payout_method'        => (string) $withdrawal->payout_method,
                    'account_name'         => (string) $withdrawal->account_name,
                    'account_number'       => (string) $withdrawal->account_number,
                    'status'               => (string) $withdrawal->status,
                ],
                'wallet' => [
                    'available_balance' => round((float) $wallet->available_balance, 2),
                    'pending_balance'   => round((float) $wallet->pending_balance, 2),
                    'total_earned'      => round((float) $wallet->total_earned, 2),
                    'total_withdrawn'   => round((float) $wallet->total_withdrawn, 2),
                ],
            ],
        ]);
    }

    public function ratings(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $pickupOrderIds = PickupAssignment::where('courier_id', $courier->id)
            ->pluck('seller_order_id')
            ->unique()
            ->values();

        $ratingsList = Schema::hasTable('order_service_ratings') && $pickupOrderIds->isNotEmpty()
            ? OrderServiceRating::with(['buyer', 'sellerOrder', 'sellerProfile'])
                ->whereIn('seller_order_id', $pickupOrderIds)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get()
                ->map(fn (OrderServiceRating $r) => [
                    'id'                      => (int) $r->id,
                    'seller_order_number'     => $r->sellerOrder?->seller_order_number ?? 'N/A',
                    'seller_name'             => $r->sellerProfile?->business_name ?? 'Seller',
                    'logistics_hub_rating'    => (int) ($r->logistics_hub_rating ?? 5),
                    'parcel_condition_rating' => (int) ($r->parcel_condition_rating ?? 5),
                    'review_comment'          => $r->review_comment,
                    'created_at'              => $r->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        $avgRating = Schema::hasTable('order_service_ratings') && $pickupOrderIds->isNotEmpty()
            ? round((float) OrderServiceRating::whereIn('seller_order_id', $pickupOrderIds)->avg('logistics_hub_rating'), 2)
            : 5.00;

        $totalRatings = Schema::hasTable('order_service_ratings') && $pickupOrderIds->isNotEmpty()
            ? OrderServiceRating::whereIn('seller_order_id', $pickupOrderIds)->count()
            : 0;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'avg_rating'    => $totalRatings > 0 ? $avgRating : 5.00,
                'total_ratings' => $totalRatings,
                'ratings'       => $ratingsList,
            ],
        ]);
    }
}