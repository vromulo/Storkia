<?php

namespace App\Observers;

use App\Models\BuyerBanAppeal;
use App\Models\BuyerVoucherClaim;
use App\Models\BuyerWalletTopup;
use App\Models\Cart;
use App\Models\CodRemittance;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryChatMessage;
use App\Models\IdentityVerification;
use App\Models\LogisticsAdminRemittance;
use App\Models\LogisticsApplication;
use App\Models\OrderItemReview;
use App\Models\PickupAssignment;
use App\Models\Product;
use App\Models\ProductApproval;
use App\Models\ProductQuestion;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\SellerReport;
use App\Models\Shipment;
use App\Models\StorkVoucher;
use App\Models\UserAddress;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\RealtimeBroadcastService;

class RealtimeLifecycleObserver
{
    public function created(object $model): void
    {
        $this->dispatchRealtimeForModel($model, 'created');
    }

    public function updated(object $model): void
    {
        $this->dispatchRealtimeForModel($model, 'updated');
    }

    public function deleted(object $model): void
    {
        $this->dispatchRealtimeForModel($model, 'deleted');
    }

    protected function dispatchRealtimeForModel(object $model, string $lifecycleVerb): void
    {
        try {
            if ($model instanceof SellerOrder) {
                RealtimeBroadcastService::forSellerOrder($model, "order.{$lifecycleVerb}");
                return;
            }

            if ($model instanceof Shipment) {
                RealtimeBroadcastService::forShipment($model, "shipment.{$lifecycleVerb}");
                return;
            }

            if ($model instanceof PickupAssignment) {
                RealtimeBroadcastService::forPickupAssignment($model, "pickup.{$lifecycleVerb}");
                return;
            }

            if ($model instanceof DeliveryAssignment) {
                RealtimeBroadcastService::forDeliveryAssignment($model, "delivery.{$lifecycleVerb}");
                return;
            }

            if ($model instanceof ReturnRequest) {
                RealtimeBroadcastService::forReturnRequest($model, "return.{$lifecycleVerb}");
                return;
            }

            if ($model instanceof ReturnShipment) {
                RealtimeBroadcastService::forReturnShipment($model, "return_shipment.{$lifecycleVerb}");
                return;
            }

            if ($model instanceof DeliveryChatMessage) {
                RealtimeBroadcastService::forDeliveryChat(
                    shipmentId: (int) $model->shipment_id,
                    sellerOrderId: (int) $model->seller_order_id,
                    buyerId: (int) $model->buyer_id,
                    riderId: (int) $model->rider_id,
                    messagePayload: [
                        'id' => (int) $model->id,
                        'delivery_assignment_id' => $model->delivery_assignment_id ? (int) $model->delivery_assignment_id : null,
                        'sender_type' => (string) $model->sender_type,
                        'message' => (string) ($model->message ?? ''),
                        'attachment_url' => $model->attachment_path ? asset('storage/' . ltrim($model->attachment_path, '/')) : null,
                        'is_read' => (bool) $model->is_read,
                        'created_at' => $model->created_at?->toIso8601String(),
                        'created_at_formatted' => $model->created_at?->timezone('Asia/Manila')->format('M d, h:i A'),
                    ]
                );
                return;
            }

            if ($model instanceof WalletTransaction) {
                RealtimeBroadcastService::forWalletUpdate(
                    ownerType: (string) $model->owner_type,
                    ownerId: (int) $model->owner_id,
                    action: "wallet.transaction.{$lifecycleVerb}",
                    extra: [
                        'transaction_reference' => (string) $model->transaction_reference,
                        'entry_type' => (string) $model->entry_type,
                        'amount' => (float) $model->amount,
                        'status' => (string) $model->status,
                    ]
                );
                return;
            }

            if ($model instanceof WithdrawalRequest) {
                RealtimeBroadcastService::forWalletUpdate(
                    ownerType: (string) $model->requester_type,
                    ownerId: (int) $model->requester_id,
                    action: "withdrawal.{$lifecycleVerb}",
                    extra: [
                        'withdrawal_id' => (int) $model->id,
                        'withdrawal_reference' => (string) $model->withdrawal_reference,
                        'status' => (string) $model->status,
                        'requested_amount' => (float) $model->requested_amount,
                    ]
                );
                return;
            }

            if ($model instanceof BuyerWalletTopup) {
                RealtimeBroadcastService::forWalletUpdate(
                    ownerType: 'Buyer',
                    ownerId: (int) $model->buyer_id,
                    action: "wallet.topup.{$lifecycleVerb}",
                    extra: [
                        'topup_id' => (int) $model->id,
                        'topup_reference' => (string) $model->topup_reference,
                        'status' => (string) $model->status,
                        'approved_amount' => $model->approved_amount !== null ? (float) $model->approved_amount : null,
                    ]
                );
                return;
            }

            if ($model instanceof LogisticsAdminRemittance) {
                RealtimeBroadcastService::forWalletUpdate(
                    ownerType: 'Logistics',
                    ownerId: (int) $model->logistics_profile_id,
                    action: "logistics_remittance.{$lifecycleVerb}",
                    extra: [
                        'remittance_id' => (int) $model->id,
                        'remittance_reference' => (string) $model->remittance_reference,
                        'status' => (string) $model->status,
                    ]
                );
                return;
            }

            if ($model instanceof CodRemittance) {
                RealtimeBroadcastService::send(
                    channels: array_filter([
                        'admin',
                        $model->logistics_profile_id ? 'logistics.' . (int) $model->logistics_profile_id : null,
                        $model->rider_id ? 'rider.' . (int) $model->rider_id : null,
                    ]),
                    scope: 'cod_remittance',
                    action: "cod_remittance.{$lifecycleVerb}",
                    payload: [
                        'cod_remittance_id' => (int) $model->id,
                        'remittance_reference' => (string) $model->remittance_reference,
                        'status' => (string) $model->status,
                    ]
                );
                return;
            }

            if ($model instanceof CourierLocation) {
                $courier = Courier::select(['id', 'logistics_profile_id'])->find($model->courier_id);
                $assignment = $model->pickup_assignment_id
                    ? PickupAssignment::select(['id', 'shipment_id', 'seller_order_id'])->find($model->pickup_assignment_id)
                    : PickupAssignment::select(['id', 'shipment_id', 'seller_order_id'])
                        ->where('courier_id', $model->courier_id)
                        ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
                        ->orderByDesc('updated_at')
                        ->first();

                RealtimeBroadcastService::forDriverLocation(
                    driverType: 'courier',
                    driverId: (int) $model->courier_id,
                    logisticsProfileId: $courier?->logistics_profile_id ? (int) $courier->logistics_profile_id : null,
                    latitude: (float) $model->latitude,
                    longitude: (float) $model->longitude,
                    accuracy: $model->accuracy !== null ? (float) $model->accuracy : null,
                    speed: $model->speed !== null ? (float) $model->speed : null,
                    heading: $model->heading !== null ? (float) $model->heading : null,
                    shipmentId: $assignment?->shipment_id ? (int) $assignment->shipment_id : null,
                    sellerOrderId: $assignment?->seller_order_id ? (int) $assignment->seller_order_id : null,
                    assignmentId: $assignment?->id ? (int) $assignment->id : null
                );
                return;
            }

            if ($model instanceof RiderLocation) {
                $rider = Rider::select(['id', 'logistics_profile_id'])->find($model->rider_id);
                $assignment = $model->delivery_assignment_id
                    ? DeliveryAssignment::select(['id', 'shipment_id', 'seller_order_id'])->find($model->delivery_assignment_id)
                    : DeliveryAssignment::select(['id', 'shipment_id', 'seller_order_id'])
                        ->where('rider_id', $model->rider_id)
                        ->whereIn('status', ['accepted', 'collected', 'out_for_delivery'])
                        ->orderByDesc('updated_at')
                        ->first();

                RealtimeBroadcastService::forDriverLocation(
                    driverType: 'rider',
                    driverId: (int) $model->rider_id,
                    logisticsProfileId: $rider?->logistics_profile_id ? (int) $rider->logistics_profile_id : null,
                    latitude: (float) $model->latitude,
                    longitude: (float) $model->longitude,
                    accuracy: $model->accuracy !== null ? (float) $model->accuracy : null,
                    speed: $model->speed !== null ? (float) $model->speed : null,
                    heading: $model->heading !== null ? (float) $model->heading : null,
                    shipmentId: $assignment?->shipment_id ? (int) $assignment->shipment_id : null,
                    sellerOrderId: $assignment?->seller_order_id ? (int) $assignment->seller_order_id : null,
                    assignmentId: $assignment?->id ? (int) $assignment->id : null
                );
                return;
            }

            if ($model instanceof Product || $model instanceof ProductApproval) {
                $userId = $model->user_id ?? null;
                $channels = ['admin', 'public:marketplace'];
                if ($userId) {
                    $channels[] = 'user.' . (int) $userId;
                    $sellerProfileId = SellerProfile::where('user_id', $userId)->value('id');
                    if ($sellerProfileId) {
                        $channels[] = 'seller.' . (int) $sellerProfileId;
                    }
                }
                RealtimeBroadcastService::send(
                    channels: $channels,
                    scope: 'catalog',
                    action: "product.{$lifecycleVerb}",
                    payload: [
                        'product_id' => (int) ($model instanceof Product ? $model->id : $model->product_id),
                    ]
                );
                return;
            }

            if ($model instanceof StorkVoucher) {
                $channels = ['admin', 'public:marketplace'];
                if ($model->seller_profile_id) {
                    $channels[] = 'seller.' . (int) $model->seller_profile_id;
                }
                if ($model->created_by) {
                    $channels[] = 'user.' . (int) $model->created_by;
                }
                RealtimeBroadcastService::send(
                    channels: $channels,
                    scope: 'voucher',
                    action: "voucher.{$lifecycleVerb}",
                    payload: [
                        'voucher_id' => (int) $model->id,
                        'code' => (string) $model->code,
                        'scope' => (string) $model->scope,
                    ]
                );
                return;
            }

            if ($model instanceof BuyerVoucherClaim) {
                if ($model->buyer_id) {
                    RealtimeBroadcastService::send(
                        channels: ['user.' . (int) $model->buyer_id],
                        scope: 'voucher',
                        action: "voucher_claim.{$lifecycleVerb}",
                        payload: [
                            'voucher_id' => (int) $model->voucher_id,
                            'buyer_id' => (int) $model->buyer_id,
                        ]
                    );
                }
                return;
            }

            if ($model instanceof OrderItemReview || $model instanceof ProductQuestion) {
                $channels = ['public:marketplace'];
                if (! empty($model->buyer_id)) {
                    $channels[] = 'user.' . (int) $model->buyer_id;
                }
                if (! empty($model->seller_profile_id)) {
                    $channels[] = 'seller.' . (int) $model->seller_profile_id;
                    $sellerUserId = SellerProfile::where('id', $model->seller_profile_id)->value('user_id');
                    if ($sellerUserId) {
                        $channels[] = 'user.' . (int) $sellerUserId;
                    }
                }
                RealtimeBroadcastService::send(
                    channels: $channels,
                    scope: 'catalog',
                    action: class_basename($model) . ".{$lifecycleVerb}",
                    payload: [
                        'id' => (int) $model->id,
                        'product_id' => (int) ($model->product_id ?? 0),
                    ]
                );
                return;
            }

            if ($model instanceof UserAddress) {
                if ($model->user_id) {
                    RealtimeBroadcastService::send(
                        channels: ['user.' . (int) $model->user_id],
                        scope: 'address',
                        action: "address.{$lifecycleVerb}",
                        payload: [
                            'address_id' => (int) $model->id,
                            'user_id' => (int) $model->user_id,
                        ]
                    );
                }
                return;
            }

            if ($model instanceof Cart) {
                if ($model->user_id) {
                    RealtimeBroadcastService::send(
                        channels: ['user.' . (int) $model->user_id, 'cart.' . (int) $model->user_id],
                        scope: 'cart',
                        action: "cart.{$lifecycleVerb}",
                        payload: [
                            'cart_id' => (int) $model->id,
                            'user_id' => (int) $model->user_id,
                            'product_id' => (int) $model->product_id,
                            'quantity' => $lifecycleVerb === 'deleted' ? 0 : (int) $model->quantity,
                        ]
                    );
                }
                return;
            }

            if (
                $model instanceof SellerApplication ||
                $model instanceof LogisticsApplication ||
                $model instanceof IdentityVerification ||
                $model instanceof SellerReport ||
                $model instanceof BuyerBanAppeal
            ) {
                $channels = ['admin'];
                if (! empty($model->user_id)) {
                    $channels[] = 'user.' . (int) $model->user_id;
                }
                if (! empty($model->buyer_id)) {
                    $channels[] = 'user.' . (int) $model->buyer_id;
                }
                if (! empty($model->reporter_id)) {
                    $channels[] = 'user.' . (int) $model->reporter_id;
                }
                if (! empty($model->seller_profile_id)) {
                    $channels[] = 'seller.' . (int) $model->seller_profile_id;
                }
                RealtimeBroadcastService::send(
                    channels: $channels,
                    scope: 'compliance',
                    action: class_basename($model) . ".{$lifecycleVerb}",
                    payload: [
                        'id' => (int) $model->id,
                        'status' => (string) ($model->status ?? ''),
                    ]
                );
            }
        } catch (\Throwable $e) {
        }
    }
}