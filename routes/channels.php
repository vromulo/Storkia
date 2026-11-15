<?php

use App\Models\Admin;
use App\Models\Courier;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Schema;

Broadcast::channel('chat.{id}', function ($user, $id) {
    return ($user instanceof User) && (int) $user->id === (int) $id;
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('cart.{id}', function ($user, $id) {
    return ($user instanceof User) && (int) $user->id === (int) $id;
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('user.{id}', function ($user, $id) {
    return ($user instanceof User) && (int) $user->id === (int) $id;
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('seller.{id}', function ($user, $id) {
    return ($user instanceof User)
        && $user->role === 'Seller'
        && (int) ($user->sellerProfile?->id ?? 0) === (int) $id;
}, ['guards' => ['web']]);

Broadcast::channel('logistics.{id}', function ($user, $id) {
    if (! ($user instanceof User) || $user->role !== 'Logistics') {
        return false;
    }
    $effectiveId = (int) ($user->effectiveLogisticsProfile()?->id ?? $user->logisticsProfile?->id ?? 0);

    return $effectiveId === (int) $id;
}, ['guards' => ['web']]);

Broadcast::channel('courier.{id}', function ($actor, $id) {
    return ($actor instanceof Courier) && (int) $actor->id === (int) $id;
}, ['guards' => ['courier', 'sanctum']]);

Broadcast::channel('rider.{id}', function ($actor, $id) {
    return ($actor instanceof Rider) && (int) $actor->id === (int) $id;
}, ['guards' => ['rider', 'sanctum']]);

Broadcast::channel('order.{id}', function ($actor, $id) {
    $order = SellerOrder::with('shipment')->find((int) $id);
    if (! $order) {
        return false;
    }

    if ($actor instanceof Admin) {
        return true;
    }

    if ($actor instanceof User) {
        if ((int) $order->buyer_id === (int) $actor->id || (int) $order->seller_id === (int) $actor->id) {
            return true;
        }
        if ($actor->role === 'Logistics') {
            $logId = (int) ($actor->effectiveLogisticsProfile()?->id ?? $actor->logisticsProfile?->id ?? 0);

            return $logId > 0 && (int) $order->logistics_profile_id === $logId;
        }
    }

    if ($actor instanceof Rider) {
        if ((int) ($order->shipment?->current_rider_id ?? 0) === (int) $actor->id) {
            return true;
        }
        if ($order->shipment && $order->shipment->deliveryAssignments()->where('rider_id', $actor->id)->exists()) {
            return true;
        }
        if (Schema::hasTable('return_shipments') && ReturnShipment::where('seller_order_id', $order->id)->where('current_rider_id', $actor->id)->exists()) {
            return true;
        }
    }

    if ($actor instanceof Courier) {
        if ((int) ($order->shipment?->current_courier_id ?? 0) === (int) $actor->id) {
            return true;
        }
        if ($order->shipment && $order->shipment->pickupAssignments()->where('courier_id', $actor->id)->exists()) {
            return true;
        }
        if (Schema::hasTable('return_shipments') && ReturnShipment::where('seller_order_id', $order->id)->where('current_courier_id', $actor->id)->exists()) {
            return true;
        }
    }

    return false;
}, ['guards' => ['web', 'admin', 'rider', 'courier', 'sanctum']]);

Broadcast::channel('shipment.{id}', function ($actor, $id) {
    $shipment = Shipment::find((int) $id);
    if (! $shipment) {
        return false;
    }

    if ($actor instanceof Admin) {
        return true;
    }

    if ($actor instanceof Courier) {
        return (int) $shipment->current_courier_id === (int) $actor->id
            || $shipment->pickupAssignments()->where('courier_id', $actor->id)->exists();
    }

    if ($actor instanceof Rider) {
        return (int) $shipment->current_rider_id === (int) $actor->id
            || $shipment->deliveryAssignments()->where('rider_id', $actor->id)->exists();
    }

    if ($actor instanceof User) {
        if ($actor->role === 'Logistics') {
            $logId = (int) ($actor->effectiveLogisticsProfile()?->id ?? $actor->logisticsProfile?->id ?? 0);

            return $logId > 0 && (int) $shipment->logistics_profile_id === $logId;
        }
        if ($actor->role === 'Seller') {
            return (int) ($actor->sellerProfile?->id ?? 0) === (int) $shipment->seller_profile_id;
        }

        return SellerOrder::where('id', $shipment->seller_order_id)
            ->where('buyer_id', $actor->id)
            ->exists();
    }

    return false;
}, ['guards' => ['web', 'admin', 'rider', 'courier', 'sanctum']]);

Broadcast::channel('support.user.{id}', function ($user, $id) {
    return ($user instanceof User) && (int) $user->id === (int) $id;
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('support.admin', function ($admin) {
    return $admin instanceof Admin;
}, ['guards' => ['admin']]);

Broadcast::channel('admin', function ($admin) {
    return $admin instanceof Admin;
}, ['guards' => ['admin']]);