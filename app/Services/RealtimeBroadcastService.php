<?php

namespace App\Services;

use App\Events\StorkiaRealtimeUpdated;
use App\Models\DeliveryAssignment;
use App\Models\PickupAssignment;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\SellerOrder;
use App\Models\Shipment;

class RealtimeBroadcastService
{
    /**
     * Broadcast a real-time update to one or more private/public channels.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public static function send(
        array $channels,
        string $scope,
        string $action,
        array $payload = [],
        array $publicChannels = []
    ): void {
        $cleanPrivate = array_values(array_unique(array_filter($channels)));
        $cleanPublic = array_values(array_unique(array_filter($publicChannels)));

        if (empty($cleanPrivate) && empty($cleanPublic)) {
            return;
        }

        try {
            event(new StorkiaRealtimeUpdated(
                channelNames: $cleanPrivate,
                scope: $scope,
                action: $action,
                payload: $payload,
                publicChannels: $cleanPublic
            ));
        } catch (\Throwable $e) {
            // Prevent broadcast transport issues from interrupting database transactions
        }
    }

    public static function forSellerOrder(SellerOrder $sellerOrder, string $action = 'updated', array $extra = []): void
    {
        $sellerOrder->loadMissing(['shipment', 'sellerProfile']);

        $channels = [
            'admin.global',
            'order.' . $sellerOrder->id,
        ];

        if ($sellerOrder->buyer_id) {
            $channels[] = 'user.' . $sellerOrder->buyer_id;
        }

        if ($sellerOrder->seller_id) {
            $channels[] = 'user.' . $sellerOrder->seller_id;
        }

        if ($sellerOrder->seller_profile_id) {
            $channels[] = 'seller.' . $sellerOrder->seller_profile_id;
        }

        if ($sellerOrder->logistics_profile_id) {
            $channels[] = 'logistics.' . $sellerOrder->logistics_profile_id;
        }

        $shipment = $sellerOrder->shipment;
        if ($shipment) {
            $channels[] = 'shipment.' . $shipment->id;
            if ($shipment->current_courier_id) {
                $channels[] = 'courier.' . $shipment->current_courier_id;
            }
            if ($shipment->current_rider_id) {
                $channels[] = 'rider.' . $shipment->current_rider_id;
            }
        }

        self::send(
            channels: $channels,
            scope: 'order',
            action: $action,
            payload: array_merge([
                'order_id'            => (int) $sellerOrder->order_id,
                'seller_order_id'     => (int) $sellerOrder->id,
                'seller_order_number' => (string) $sellerOrder->seller_order_number,
                'status'              => (string) $sellerOrder->status,
                'status_label'        => (string) $sellerOrder->status_label,
                'payment_status'      => (string) $sellerOrder->payment_status,
                'shipment_id'         => $shipment ? (int) $shipment->id : null,
                'shipment_status'     => $shipment ? (string) $shipment->status : null,
                'tracking_number'     => $shipment ? (string) $shipment->tracking_number : null,
            ], $extra)
        );
    }

    public static function forShipment(Shipment $shipment, string $action = 'updated', array $extra = []): void
    {
        $shipment->loadMissing(['sellerOrder']);
        $sellerOrder = $shipment->sellerOrder;

        $channels = [
            'admin.global',
            'shipment.' . $shipment->id,
        ];

        if ($shipment->logistics_profile_id) {
            $channels[] = 'logistics.' . $shipment->logistics_profile_id;
        }

        if ($shipment->seller_profile_id) {
            $channels[] = 'seller.' . $shipment->seller_profile_id;
        }

        if ($shipment->current_courier_id) {
            $channels[] = 'courier.' . $shipment->current_courier_id;
        }

        if ($shipment->current_rider_id) {
            $channels[] = 'rider.' . $shipment->current_rider_id;
        }

        if ($sellerOrder) {
            $channels[] = 'order.' . $sellerOrder->id;
            if ($sellerOrder->buyer_id) {
                $channels[] = 'user.' . $sellerOrder->buyer_id;
            }
            if ($sellerOrder->seller_id) {
                $channels[] = 'user.' . $sellerOrder->seller_id;
            }
        }

        self::send(
            channels: $channels,
            scope: 'shipment',
            action: $action,
            payload: array_merge([
                'shipment_id'       => (int) $shipment->id,
                'seller_order_id'   => (int) $shipment->seller_order_id,
                'tracking_number'   => (string) $shipment->tracking_number,
                'status'            => (string) $shipment->status,
                'status_label'      => (string) $shipment->status_label,
                'order_status'      => $sellerOrder ? (string) $sellerOrder->status : null,
                'current_courier_id'=> $shipment->current_courier_id ? (int) $shipment->current_courier_id : null,
                'current_rider_id'  => $shipment->current_rider_id ? (int) $shipment->current_rider_id : null,
            ], $extra)
        );
    }

    public static function forPickupAssignment(PickupAssignment $assignment, string $action = 'updated', array $extra = []): void
    {
        $assignment->loadMissing(['shipment.sellerOrder']);
        $shipment = $assignment->shipment;
        $sellerOrder = $shipment?->sellerOrder;

        $channels = [
            'admin.global',
        ];

        if ($assignment->courier_id) {
            $channels[] = 'courier.' . $assignment->courier_id;
        }

        if ($assignment->logistics_profile_id) {
            $channels[] = 'logistics.' . $assignment->logistics_profile_id;
        }

        if ($shipment) {
            $channels[] = 'shipment.' . $shipment->id;
            if ($shipment->seller_profile_id) {
                $channels[] = 'seller.' . $shipment->seller_profile_id;
            }
        }

        if ($sellerOrder) {
            $channels[] = 'order.' . $sellerOrder->id;
            if ($sellerOrder->buyer_id) {
                $channels[] = 'user.' . $sellerOrder->buyer_id;
            }
            if ($sellerOrder->seller_id) {
                $channels[] = 'user.' . $sellerOrder->seller_id;
            }
        }

        self::send(
            channels: $channels,
            scope: 'pickup',
            action: $action,
            payload: array_merge([
                'pickup_assignment_id' => (int) $assignment->id,
                'shipment_id'          => (int) $assignment->shipment_id,
                'seller_order_id'      => (int) $assignment->seller_order_id,
                'courier_id'           => (int) $assignment->courier_id,
                'status'               => (string) $assignment->status,
                'shipment_status'      => $shipment ? (string) $shipment->status : null,
                'tracking_number'      => $shipment ? (string) $shipment->tracking_number : null,
            ], $extra)
        );
    }

    public static function forDeliveryAssignment(DeliveryAssignment $assignment, string $action = 'updated', array $extra = []): void
    {
        $assignment->loadMissing(['shipment', 'sellerOrder']);
        $shipment = $assignment->shipment;
        $sellerOrder = $assignment->sellerOrder ?? $shipment?->sellerOrder;

        $channels = [
            'admin.global',
        ];

        if ($assignment->rider_id) {
            $channels[] = 'rider.' . $assignment->rider_id;
        }

        if ($assignment->logistics_profile_id) {
            $channels[] = 'logistics.' . $assignment->logistics_profile_id;
        }

        if ($shipment) {
            $channels[] = 'shipment.' . $shipment->id;
            if ($shipment->seller_profile_id) {
                $channels[] = 'seller.' . $shipment->seller_profile_id;
            }
        }

        if ($sellerOrder) {
            $channels[] = 'order.' . $sellerOrder->id;
            if ($sellerOrder->buyer_id) {
                $channels[] = 'user.' . $sellerOrder->buyer_id;
            }
            if ($sellerOrder->seller_id) {
                $channels[] = 'user.' . $sellerOrder->seller_id;
            }
        }

        self::send(
            channels: $channels,
            scope: 'delivery',
            action: $action,
            payload: array_merge([
                'delivery_assignment_id' => (int) $assignment->id,
                'shipment_id'            => (int) $assignment->shipment_id,
                'seller_order_id'        => (int) $assignment->seller_order_id,
                'rider_id'               => (int) $assignment->rider_id,
                'status'                 => (string) $assignment->status,
                'shipment_status'        => $shipment ? (string) $shipment->status : null,
                'tracking_number'        => $shipment ? (string) $shipment->tracking_number : null,
            ], $extra)
        );
    }

    public static function forReturnRequest(ReturnRequest $returnRequest, string $action = 'updated', array $extra = []): void
    {
        $returnRequest->loadMissing(['returnShipment', 'sellerOrder']);
        $rs = $returnRequest->returnShipment;

        $channels = [
            'admin.global',
        ];

        if ($returnRequest->buyer_id) {
            $channels[] = 'user.' . $returnRequest->buyer_id;
        }

        if ($returnRequest->seller_id) {
            $channels[] = 'user.' . $returnRequest->seller_id;
        }

        if ($returnRequest->seller_profile_id) {
            $channels[] = 'seller.' . $returnRequest->seller_profile_id;
        }

        if ($returnRequest->seller_order_id) {
            $channels[] = 'order.' . $returnRequest->seller_order_id;
        }

        if ($rs) {
            if ($rs->logistics_profile_id) {
                $channels[] = 'logistics.' . $rs->logistics_profile_id;
            }
            if ($rs->current_rider_id) {
                $channels[] = 'rider.' . $rs->current_rider_id;
            }
            if ($rs->current_courier_id) {
                $channels[] = 'courier.' . $rs->current_courier_id;
            }
        }

        self::send(
            channels: $channels,
            scope: 'return',
            action: $action,
            payload: array_merge([
                'return_request_id'  => (int) $returnRequest->id,
                'request_number'     => (string) $returnRequest->request_number,
                'seller_order_id'    => (int) $returnRequest->seller_order_id,
                'status'             => (string) $returnRequest->status,
                'return_shipment_id' => $rs ? (int) $rs->id : null,
                'shipment_status'    => $rs ? (string) $rs->status : null,
            ], $extra)
        );
    }

    public static function forReturnShipment(ReturnShipment $returnShipment, string $action = 'updated', array $extra = []): void
    {
        $returnShipment->loadMissing(['returnRequest', 'sellerOrder']);
        $req = $returnShipment->returnRequest;

        $channels = [
            'admin.global',
        ];

        if ($returnShipment->logistics_profile_id) {
            $channels[] = 'logistics.' . $returnShipment->logistics_profile_id;
        }

        if ($returnShipment->seller_profile_id) {
            $channels[] = 'seller.' . $returnShipment->seller_profile_id;
        }

        if ($returnShipment->current_rider_id) {
            $channels[] = 'rider.' . $returnShipment->current_rider_id;
        }

        if ($returnShipment->current_courier_id) {
            $channels[] = 'courier.' . $returnShipment->current_courier_id;
        }

        if ($returnShipment->seller_order_id) {
            $channels[] = 'order.' . $returnShipment->seller_order_id;
        }

        if ($req) {
            if ($req->buyer_id) {
                $channels[] = 'user.' . $req->buyer_id;
            }
            if ($req->seller_id) {
                $channels[] = 'user.' . $req->seller_id;
            }
        }

        self::send(
            channels: $channels,
            scope: 'return_shipment',
            action: $action,
            payload: array_merge([
                'return_shipment_id' => (int) $returnShipment->id,
                'return_request_id'  => (int) $returnShipment->return_request_id,
                'seller_order_id'    => (int) $returnShipment->seller_order_id,
                'tracking_number'    => (string) $returnShipment->tracking_number,
                'status'             => (string) $returnShipment->status,
                'current_rider_id'   => $returnShipment->current_rider_id ? (int) $returnShipment->current_rider_id : null,
                'current_courier_id' => $returnShipment->current_courier_id ? (int) $returnShipment->current_courier_id : null,
            ], $extra)
        );
    }

    public static function forDriverLocation(
        string $driverType,
        int $driverId,
        ?int $logisticsProfileId,
        float $latitude,
        float $longitude,
        ?float $accuracy = null,
        ?float $speed = null,
        ?float $heading = null,
        ?int $shipmentId = null,
        ?int $sellerOrderId = null,
        ?int $assignmentId = null
    ): void {
        $normType = strtolower($driverType) === 'courier' ? 'courier' : 'rider';
        $channels = [
            $normType . '.' . $driverId,
        ];

        if ($logisticsProfileId) {
            $channels[] = 'logistics.' . $logisticsProfileId;
        }

        if ($shipmentId) {
            $channels[] = 'shipment.' . $shipmentId;
        }

        if ($sellerOrderId) {
            $channels[] = 'order.' . $sellerOrderId;
            $buyerId = SellerOrder::where('id', $sellerOrderId)->value('buyer_id');
            if ($buyerId) {
                $channels[] = 'user.' . (int) $buyerId;
            }
        }

        self::send(
            channels: $channels,
            scope: 'location',
            action: $normType . '.location.updated',
            payload: [
                'driver_type'     => $normType,
                'driver_id'       => $driverId,
                'assignment_id'   => $assignmentId,
                'shipment_id'     => $shipmentId,
                'seller_order_id' => $sellerOrderId,
                'latitude'        => round($latitude, 7),
                'longitude'       => round($longitude, 7),
                'accuracy'        => $accuracy !== null ? round($accuracy, 2) : null,
                'speed'           => $speed !== null ? round($speed, 2) : null,
                'heading'         => $heading !== null ? round($heading, 2) : null,
                'recorded_at'     => now()->toIso8601String(),
            ]
        );
    }

    public static function forDeliveryChat(
        int $shipmentId,
        int $sellerOrderId,
        int $buyerId,
        int $riderId,
        array $messagePayload
    ): void {
        self::send(
            channels: [
                'user.' . $buyerId,
                'rider.' . $riderId,
                'order.' . $sellerOrderId,
                'shipment.' . $shipmentId,
            ],
            scope: 'delivery_chat',
            action: 'delivery_chat.message',
            payload: [
                'shipment_id'     => $shipmentId,
                'seller_order_id' => $sellerOrderId,
                'buyer_id'        => $buyerId,
                'rider_id'        => $riderId,
                'message'         => $messagePayload,
            ]
        );
    }

    public static function forWalletUpdate(
        string $ownerType,
        int $ownerId,
        string $action = 'wallet.updated',
        array $extra = []
    ): void {
        $channels = ['admin.global'];
        $norm = strtolower(trim($ownerType));

        if ($norm === 'buyer' || $norm === 'user') {
            $channels[] = 'user.' . $ownerId;
        } elseif ($norm === 'seller') {
            $channels[] = 'seller.' . $ownerId;
            $userId = \App\Models\SellerProfile::where('id', $ownerId)->value('user_id');
            if ($userId) {
                $channels[] = 'user.' . (int) $userId;
            }
        } elseif ($norm === 'logistics') {
            $channels[] = 'logistics.' . $ownerId;
            $userId = \App\Models\LogisticsProfile::where('id', $ownerId)->value('user_id');
            if ($userId) {
                $channels[] = 'user.' . (int) $userId;
            }
        } elseif ($norm === 'rider') {
            $channels[] = 'rider.' . $ownerId;
        } elseif ($norm === 'courier') {
            $channels[] = 'courier.' . $ownerId;
        }

        self::send(
            channels: $channels,
            scope: 'wallet',
            action: $action,
            payload: array_merge([
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
            ], $extra)
        );
    }

    public static function forNotification(object $notifiable, array $notificationData): void
    {
        $channels = [];

        if ($notifiable instanceof \App\Models\User) {
            $channels[] = 'user.' . $notifiable->id;
            if (strtolower((string) $notifiable->role) === 'admin') {
                $channels[] = 'admin.global';
            }
            if ($notifiable->sellerProfile) {
                $channels[] = 'seller.' . $notifiable->sellerProfile->id;
            }
            if ($notifiable->logisticsProfile) {
                $channels[] = 'logistics.' . $notifiable->logisticsProfile->id;
            }
        } elseif ($notifiable instanceof \App\Models\Rider) {
            $channels[] = 'rider.' . $notifiable->id;
        } elseif ($notifiable instanceof \App\Models\Courier) {
            $channels[] = 'courier.' . $notifiable->id;
        }

        if (empty($channels)) {
            return;
        }

        self::send(
            channels: $channels,
            scope: 'notification',
            action: 'notification.created',
            payload: $notificationData
        );
    }

    public static function forCatalogUpdate(int $productId, ?int $sellerProfileId = null, string $action = 'product.updated'): void
    {
        $channels = ['admin.global'];
        if ($sellerProfileId) {
            $channels[] = 'seller.' . $sellerProfileId;
        }

        self::send(
            channels: $channels,
            scope: 'catalog',
            action: $action,
            payload: [
                'product_id'        => $productId,
                'seller_profile_id' => $sellerProfileId,
            ],
            publicChannels: ['marketplace']
        );
    }

    public static function forCartUpdated(int $userId, string $action = 'cart.updated', array $extra = []): void
    {
        self::send(
            channels: [
                'user.' . $userId,
                'cart.' . $userId,
            ],
            scope: 'cart',
            action: $action,
            payload: array_merge(['user_id' => $userId], $extra)
        );
    }
}