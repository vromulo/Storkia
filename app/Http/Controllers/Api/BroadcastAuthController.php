<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Courier;
use App\Models\InterHubTransfer;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class BroadcastAuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $channelName = trim((string) $request->input('channel_name', ''));
        $socketId = trim((string) $request->input('socket_id', ''));

        if ($channelName === '' || $socketId === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Both channel_name and socket_id are required for WebSocket authentication.',
            ], 422);
        }

        $actor = $this->resolveAuthenticatedActor($request);
        if (! $actor) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated WebSocket channel request.',
            ], 403);
        }

        $normalizedChannel = preg_replace('/^(private-|presence-)/', '', $channelName);

        if (! $this->isAuthorizedForChannel($actor, (string) $normalizedChannel)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden channel subscription.',
            ], 403);
        }

        $key = (string) config('broadcasting.connections.reverb.key', env('REVERB_APP_KEY', 'bn93mdlba1frvyhfe5gf'));
        $secret = (string) config('broadcasting.connections.reverb.secret', env('REVERB_APP_SECRET', 'storkia-reverb-secret'));

        if (str_starts_with($channelName, 'presence-')) {
            $channelData = json_encode([
                'user_id'   => (string) $this->actorKey($actor),
                'user_info' => [
                    'name' => $actor->full_name ?? $actor->name ?? $actor->email ?? 'Member',
                ],
            ]);
            $stringToSign = "{$socketId}:{$channelName}:{$channelData}";
            $signature = hash_hmac('sha256', $stringToSign, $secret);

            return response()->json([
                'auth'         => "{$key}:{$signature}",
                'channel_data' => $channelData,
            ]);
        }

        $stringToSign = "{$socketId}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $secret);

        return response()->json([
            'auth' => "{$key}:{$signature}",
        ]);
    }

    protected function resolveAuthenticatedActor(Request $request): ?object
    {
        if ($request->user('sanctum')) {
            return $request->user('sanctum');
        }

        foreach (['admin', 'courier', 'rider', 'web'] as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    return Auth::guard($guard)->user();
                }
            } catch (\Throwable $e) {
            }
        }

        return $request->user();
    }

    protected function actorKey(object $actor): string
    {
        $prefix = match (true) {
            $actor instanceof Admin   => 'admin',
            $actor instanceof Courier => 'courier',
            $actor instanceof Rider   => 'rider',
            default                   => 'user',
        };

        return $prefix . ':' . ($actor->id ?? 0);
    }

    protected function isAuthorizedForChannel(object $actor, string $channel): bool
    {
        if ($actor instanceof Admin) {
            return true;
        }

        if (preg_match('/^(chat|cart|support\.user|user)\.(\d+)$/', $channel, $m)) {
            return ($actor instanceof User) && (int) $actor->id === (int) $m[2];
        }

        if ($channel === 'support.admin' || $channel === 'admin' || $channel === 'admin.global') {
            return $actor instanceof Admin;
        }

        if (preg_match('/^seller\.(\d+)$/', $channel, $m)) {
            return ($actor instanceof User)
                && $actor->role === 'Seller'
                && (int) ($actor->sellerProfile?->id ?? 0) === (int) $m[1];
        }

        if (preg_match('/^logistics\.(\d+)$/', $channel, $m)) {
            if (! ($actor instanceof User) || $actor->role !== 'Logistics') {
                return false;
            }
            $effectiveId = (int) ($actor->effectiveLogisticsProfile()?->id ?? $actor->logisticsProfile?->id ?? 0);

            return $effectiveId === (int) $m[1];
        }

        if (preg_match('/^courier\.(\d+)$/', $channel, $m)) {
            return ($actor instanceof Courier) && (int) $actor->id === (int) $m[1];
        }

        if (preg_match('/^rider\.(\d+)$/', $channel, $m)) {
            return ($actor instanceof Rider) && (int) $actor->id === (int) $m[1];
        }

        if (preg_match('/^order\.(\d+)$/', $channel, $m)) {
            $orderId = (int) $m[1];
            $order = SellerOrder::with('shipment')->find($orderId);
            if (! $order) {
                return false;
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
                if (
                    (int) ($order->shipment?->current_courier_id ?? 0) === (int) $actor->id
                    || (int) ($order->shipment?->linehaul_courier_id ?? 0) === (int) $actor->id
                ) {
                    return true;
                }
                if ($order->shipment && $order->shipment->pickupAssignments()->where('courier_id', $actor->id)->exists()) {
                    return true;
                }
                if (Schema::hasTable('inter_hub_transfers') && InterHubTransfer::where('seller_order_id', $order->id)->where('courier_id', $actor->id)->exists()) {
                    return true;
                }
                if (Schema::hasTable('return_shipments') && ReturnShipment::where('seller_order_id', $order->id)->where('current_courier_id', $actor->id)->exists()) {
                    return true;
                }
            }

            return false;
        }

        if (preg_match('/^shipment\.(\d+)$/', $channel, $m)) {
            $shipment = Shipment::find((int) $m[1]);
            if (! $shipment) {
                return false;
            }

            if ($actor instanceof Courier) {
                return (int) $shipment->current_courier_id === (int) $actor->id
                    || (int) ($shipment->linehaul_courier_id ?? 0) === (int) $actor->id
                    || $shipment->pickupAssignments()->where('courier_id', $actor->id)->exists()
                    || (Schema::hasTable('inter_hub_transfers') && InterHubTransfer::where('shipment_id', $shipment->id)->where('courier_id', $actor->id)->exists())
                    || (Schema::hasTable('return_shipments') && ReturnShipment::where('shipment_id', $shipment->id)->where('current_courier_id', $actor->id)->exists());
            }

            if ($actor instanceof Rider) {
                return (int) $shipment->current_rider_id === (int) $actor->id
                    || $shipment->deliveryAssignments()->where('rider_id', $actor->id)->exists()
                    || (Schema::hasTable('return_shipments') && ReturnShipment::where('shipment_id', $shipment->id)->where('current_rider_id', $actor->id)->exists());
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
        }

        return false;
    }
}