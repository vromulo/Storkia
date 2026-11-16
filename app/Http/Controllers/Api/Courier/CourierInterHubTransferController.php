<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\InterHubTransfer;
use App\Models\OrderStatusHistory;
use App\Models\ShipmentTracking;
use App\Models\WalletTransaction;
use App\Notifications\OrderLifecycleNotification;
use App\Services\CommissionCalculatorService;
use App\Services\CourierMobileSchemaService;
use App\Services\RealtimeBroadcastService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourierInterHubTransferController extends Controller
{
    public function __construct(
        protected CommissionCalculatorService $commissionCalculator,
        protected RoleWalletService $roleWalletService
    ) {}

    public function index(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        if (! Schema::hasTable('inter_hub_transfers')) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'counts'      => [
                        'active'    => 0,
                        'completed' => 0,
                    ],
                    'active'      => [],
                    'completed'   => [],
                ],
            ]);
        }

        $with = [
            'shipment.sellerOrder.items',
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.trackingLogs',
            'originHub',
            'destinationHub',
        ];

        $active = InterHubTransfer::with($with)
            ->where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'in_transit'])
            ->orderByRaw('COALESCE(assigned_at, created_at) ASC')
            ->orderBy('id', 'asc')
            ->get();

        $completedQuery = InterHubTransfer::where('courier_id', $courier->id)
            ->whereIn('status', ['arrived_destination_hub', 'completed']);

        $completedCount = (clone $completedQuery)->count();

        $completed = (clone $completedQuery)
            ->with($with)
            ->orderByDesc('arrived_destination_hub_at')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'counts'    => [
                    'active'    => $active->count(),
                    'completed' => $completedCount,
                ],
                'active'    => $active->map(fn (InterHubTransfer $t) => $this->formatTransferPayload($t, $courier))->values()->all(),
                'completed' => $completed->map(fn (InterHubTransfer $t) => $this->formatTransferPayload($t, $courier))->values()->all(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        if (! Schema::hasTable('inter_hub_transfers')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Inter-Hub transfer records are not initialized.',
            ], 404);
        }

        $transfer = InterHubTransfer::with([
            'shipment.sellerOrder.items',
            'shipment.sellerProfile',
            'shipment.logisticsProfile',
            'shipment.trackingLogs',
            'originHub',
            'destinationHub',
        ])->find($id);

        if (! $transfer || (int) $transfer->courier_id !== (int) $courier->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This Inter-Hub Linehaul Transfer is not assigned to your Courier account.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'transfer' => $this->formatTransferPayload($transfer, $courier),
            ],
        ]);
    }

    public function departOriginHub(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $transfer = InterHubTransfer::with(['shipment.sellerOrder', 'originHub', 'destinationHub'])->find($id);
        if (! $transfer || (int) $transfer->courier_id !== (int) $courier->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This Inter-Hub Linehaul Transfer is not assigned to your Courier account.',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        return DB::transaction(function () use ($transfer, $courier, $validated) {
            /** @var InterHubTransfer $locked */
            $locked = InterHubTransfer::with(['shipment.sellerOrder', 'originHub', 'destinationHub'])
                ->where('id', $transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'in_transit') {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Linehaul transfer is already in transit to the Destination Branch Hub.',
                    'data'    => [
                        'transfer' => $this->formatTransferPayload($locked->fresh(['shipment.sellerOrder.items', 'shipment.trackingLogs', 'originHub', 'destinationHub']), $courier),
                    ],
                ]);
            }

            if (! in_array($locked->status, ['assigned', 'accepted', 'awaiting_courier'], true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Cannot start linehaul departure because status is \"{$locked->status}\".",
                ], 422);
            }

            $locked->update([
                'status'                 => 'in_transit',
                'departed_origin_hub_at' => now(),
                'notes'                  => ! empty($validated['notes'])
                    ? trim(($locked->notes ? $locked->notes . ' | ' : '') . trim($validated['notes']))
                    : $locked->notes,
            ]);

            if ($locked->shipment) {
                $locked->shipment->update([
                    'status'                  => 'in_transit_to_destination_hub',
                    'inter_hub_dispatched_at' => $locked->shipment->inter_hub_dispatched_at ?? now(),
                ]);

                $origName = $locked->originHub?->hub_name ?? 'Origin Hub';
                $destName = $locked->destinationHub?->hub_name ?? 'Destination Branch Hub';

                ShipmentTracking::create([
                    'shipment_id'     => $locked->shipment->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'in_transit_to_destination_hub',
                    'title'           => 'Linehaul Courier Departed Origin Hub',
                    'description'     => "Courier {$courier->full_name} ({$courier->vehicle_type} - {$courier->plate_number}) collected the parcel at {$origName} and is in transit to {$destName}.",
                    'location'        => ($locked->originHub?->municipality ?? '') . ', ' . ($locked->originHub?->province ?? ''),
                    'actor_type'      => 'Courier',
                    'actor_id'        => $courier->id,
                    'occurred_at'     => now(),
                ]);
            }

            $courier->forceFill([
                'status'       => 'on_delivery',
                'is_online'    => true,
                'last_seen_at' => now(),
            ])->save();

            $fresh = $locked->fresh([
                'shipment.sellerOrder.items',
                'shipment.trackingLogs',
                'originHub',
                'destinationHub',
            ]);

            RealtimeBroadcastService::forInterHubTransfer($fresh, 'inter_hub_transfer.departed');
            if ($fresh->shipment) {
                RealtimeBroadcastService::forShipment($fresh->shipment, 'shipment.in_transit_to_destination_hub');
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Departure from Origin Hub confirmed! Transport the parcel to the Destination Branch Hub.',
                'data'    => [
                    'transfer' => $this->formatTransferPayload($fresh, $courier),
                ],
            ]);
        });
    }

    public function confirmDestinationArrival(Request $request, int $id): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $transfer = InterHubTransfer::with([
            'shipment.sellerOrder.payment',
            'shipment.logisticsProfile.user',
            'originHub',
            'destinationHub.branchUser',
        ])->find($id);

        if (! $transfer || (int) $transfer->courier_id !== (int) $courier->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This Inter-Hub Linehaul Transfer is not assigned to your Courier account.',
            ], 403);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        return DB::transaction(function () use ($transfer, $courier, $validated) {
            /** @var InterHubTransfer $locked */
            $locked = InterHubTransfer::with([
                'shipment.sellerOrder.payment',
                'shipment.logisticsProfile.user',
                'originHub',
                'destinationHub.branchUser',
            ])
                ->where('id', $transfer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, ['arrived_destination_hub', 'completed'], true)) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Arrival at Destination Branch Hub has already been recorded.',
                    'data'    => [
                        'transfer' => $this->formatTransferPayload($locked->fresh(['shipment.sellerOrder.items', 'shipment.trackingLogs', 'originHub', 'destinationHub']), $courier),
                    ],
                ]);
            }

            if (! in_array($locked->status, ['assigned', 'accepted', 'in_transit'], true)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Cannot record arrival because transfer status is \"{$locked->status}\".",
                ], 422);
            }

            $locked->update([
                'status'                     => 'arrived_destination_hub',
                'departed_origin_hub_at'     => $locked->departed_origin_hub_at ?? now(),
                'arrived_destination_hub_at' => now(),
                'notes'                      => ! empty($validated['notes'])
                    ? trim(($locked->notes ? $locked->notes . ' | ' : '') . trim($validated['notes']))
                    : $locked->notes,
            ]);

            $shipment = $locked->shipment;
            $destHub = $locked->destinationHub;
            $origHub = $locked->originHub;
            $destName = $destHub?->hub_name ?? 'Destination Branch Hub';
            $origName = $origHub?->hub_name ?? 'Origin Hub';

            $payInfo = $this->calculateLinehaulPay($locked);
            $payAmount = round((float) ($payInfo['total_pay'] ?? 15.00), 2);
            $peso = "\u{20B1}";

            if ($shipment) {
                ShipmentTracking::create([
                    'shipment_id'     => $shipment->id,
                    'seller_order_id' => $locked->seller_order_id,
                    'status'          => 'in_transit_to_destination_hub',
                    'title'           => 'Linehaul Courier Arrived at Destination Branch Hub — Pending Hub Verification',
                    'description'     => "Linehaul Courier {$courier->full_name} arrived and handed over the parcel from {$origName} at {$destName} ({$destHub?->municipality}, {$destHub?->province}).",
                    'location'        => ($destHub?->municipality ?? '') . ', ' . ($destHub?->province ?? ''),
                    'actor_type'      => 'Courier',
                    'actor_id'        => $courier->id,
                    'occurred_at'     => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'        => $shipment->order_id,
                    'seller_order_id' => $locked->seller_order_id,
                    'from_status'     => $shipment->sellerOrder?->status ?? 'at_logistics_hub',
                    'to_status'       => 'at_logistics_hub',
                    'actor_type'      => 'Courier',
                    'actor_id'        => $courier->id,
                    'remarks'         => "Linehaul Courier {$courier->full_name} handed over inter-hub transfer #{$locked->transfer_reference} at {$destName}.",
                ]);
            }

            $alreadyPaid = WalletTransaction::where('owner_type', 'Courier')
                ->where('owner_id', $courier->id)
                ->where('shipment_id', $locked->shipment_id)
                ->where('entry_type', 'inter_hub_linehaul_pay')
                ->exists();

            if (! $alreadyPaid && $payAmount > 0) {
                $method = $shipment?->sellerOrder?->effective_payment_method ?? 'COD';
                $this->roleWalletService->creditAvailableDirect(
                    ownerType: 'Courier',
                    ownerId: (int) $courier->id,
                    ownerName: $courier->full_name,
                    amount: $payAmount,
                    sellerOrderId: (int) $locked->seller_order_id,
                    shipmentId: (int) $locked->shipment_id,
                    entryType: 'inter_hub_linehaul_pay',
                    paymentMethod: $method,
                    description: "Inter-Hub Linehaul Transfer Pay ({$origName} → {$destName}) for Transfer #{$locked->transfer_reference}",
                    meta: array_merge($payInfo, [
                        'transfer_id'        => $locked->id,
                        'transfer_reference' => $locked->transfer_reference,
                    ])
                );
            }

            if (! $courier->hasActiveWorkAssignments(false) && $courier->status === 'on_delivery') {
                $courier->update(['status' => 'active']);
            }

            if ($destHub?->branchUser && $shipment) {
                try {
                    $destHub->branchUser->notify(new OrderLifecycleNotification(
                        title: "Linehaul Transfer Arrived at {$destName} (#{$shipment->tracking_number})",
                        message: "Courier {$courier->full_name} handed over Shipment #{$shipment->tracking_number} at {$destName}. Please confirm Destination Hub Arrival to assign a local Rider.",
                        type: 'info',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $shipment->order_id,
                        sellerOrderId: $shipment->seller_order_id,
                        shipmentId: $shipment->id
                    ));
                } catch (\Throwable $e) {
                }
            }

            if ($shipment?->logisticsProfile?->user) {
                try {
                    $shipment->logisticsProfile->user->notify(new OrderLifecycleNotification(
                        title: "Linehaul Transfer Arrived at {$destName} (#{$shipment->tracking_number})",
                        message: "Courier {$courier->full_name} delivered Inter-Hub Transfer #{$locked->transfer_reference} to {$destName}. Ready for Destination Hub verification.",
                        type: 'info',
                        url: route('logistics.shipments.show', $shipment->id),
                        orderId: $shipment->order_id,
                        sellerOrderId: $shipment->seller_order_id,
                        shipmentId: $shipment->id
                    ));
                } catch (\Throwable $e) {
                }
            }

            $fresh = $locked->fresh([
                'shipment.sellerOrder.items',
                'shipment.trackingLogs',
                'originHub',
                'destinationHub',
            ]);

            RealtimeBroadcastService::forInterHubTransfer($fresh, 'inter_hub_transfer.arrived');
            if ($fresh->shipment) {
                RealtimeBroadcastService::forShipment($fresh->shipment, 'shipment.inter_hub_arrived');
            }
            RealtimeBroadcastService::forWalletUpdate('Courier', (int) $courier->id, 'inter_hub_linehaul_pay.credited', [
                'inter_hub_transfer_id' => (int) $locked->id,
                'shipment_id'           => (int) $locked->shipment_id,
                'total_pay'             => $payAmount,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Handover at {$destName} recorded! {$peso}" . number_format($payAmount, 2) . ' Inter-Hub Linehaul Pay credited to your Available Courier E-Wallet.',
                'data'    => [
                    'transfer' => $this->formatTransferPayload($fresh, $courier),
                ],
            ]);
        });
    }

    protected function calculateLinehaulPay(InterHubTransfer $transfer): array
    {
        $origHub = $transfer->originHub;
        $destHub = $transfer->destinationHub;

        $origSnap = [
            'province'     => $origHub?->province ?? '',
            'municipality' => $origHub?->municipality ?? '',
            'barangay'     => $origHub?->barangay ?? '',
            'latitude'     => $origHub?->latitude,
            'longitude'    => $origHub?->longitude,
        ];
        $destSnap = [
            'province'     => $destHub?->province ?? '',
            'municipality' => $destHub?->municipality ?? '',
            'barangay'     => $destHub?->barangay ?? '',
            'latitude'     => $destHub?->latitude,
            'longitude'    => $destHub?->longitude,
        ];

        $weight = (float) ($transfer->shipment?->total_weight ?: 0.5);

        return $this->commissionCalculator->calculateDistanceScaledDriverPay(
            'pickup',
            $origSnap,
            $destSnap,
            $weight,
            true
        );
    }

    protected function formatTransferPayload(InterHubTransfer $transfer, ?Courier $courier = null): array
    {
        $shipment = $transfer->shipment;
        $order = $shipment?->sellerOrder;
        $origHub = $transfer->originHub;
        $destHub = $transfer->destinationHub;

        $origCoords = CourierMobileSchemaService::resolveCoordinatesForAddress([
            'province'     => $origHub?->province ?? '',
            'municipality' => $origHub?->municipality ?? '',
            'barangay'     => $origHub?->barangay ?? '',
            'latitude'     => $origHub?->latitude,
            'longitude'    => $origHub?->longitude,
        ], $origHub);

        $destCoords = CourierMobileSchemaService::resolveCoordinatesForAddress([
            'province'     => $destHub?->province ?? '',
            'municipality' => $destHub?->municipality ?? '',
            'barangay'     => $destHub?->barangay ?? '',
            'latitude'     => $destHub?->latitude,
            'longitude'    => $destHub?->longitude,
        ], $destHub);

        $distKm = (float) ($transfer->distance_km ?? 0);
        if ($distKm <= 0) {
            $distKm = round(
                CourierMobileSchemaService::calculateDistanceMeters(
                    (float) $origCoords['latitude'],
                    (float) $origCoords['longitude'],
                    (float) $destCoords['latitude'],
                    (float) $destCoords['longitude']
                ) / 1000.0,
                2
            );
        }

        $payInfo = $this->calculateLinehaulPay($transfer);
        $weightKg = round((float) ($shipment?->total_weight ?? 0.5), 3);

        $statusLabel = match ($transfer->status) {
            'assigned', 'awaiting_courier' => 'COLLECT AT ORIGIN HUB',
            'accepted', 'in_transit'       => 'IN TRANSIT TO DESTINATION HUB',
            'arrived_destination_hub'      => 'ARRIVED AT DESTINATION HUB (PENDING VERIFICATION)',
            'completed'                    => 'COMPLETED (VERIFIED AT DESTINATION HUB)',
            default                        => strtoupper(str_replace('_', ' ', (string) $transfer->status)),
        };

        $itemsList = ($order?->items ?? collect())->map(fn ($item) => [
            'id'           => (int) $item->id,
            'product_name' => (string) $item->product_name,
            'variant_name' => $item->variant_name,
            'quantity'     => (int) $item->quantity,
            'line_weight'  => (float) $item->line_weight,
        ])->values()->all();

        $events = ($shipment?->relationLoaded('trackingLogs') ? $shipment->trackingLogs : collect())
            ->map(fn ($log) => [
                'id'          => (int) $log->id,
                'status'      => (string) $log->status,
                'title'       => (string) $log->title,
                'description' => (string) ($log->description ?? ''),
                'location'    => (string) ($log->location ?? ''),
                'actor_type'  => (string) $log->actor_type,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'id'                         => (int) $transfer->id,
            'transfer_reference'         => (string) $transfer->transfer_reference,
            'shipment_id'                => (int) $transfer->shipment_id,
            'seller_order_id'            => (int) $transfer->seller_order_id,
            'tracking_number'            => (string) ($shipment?->tracking_number ?? 'TRK-N/A'),
            'seller_order_number'        => (string) ($order?->seller_order_number ?? 'SO-N/A'),
            'status'                     => (string) $transfer->status,
            'status_label'               => $statusLabel,
            'total_weight'               => $weightKg,
            'total_weight_formatted'     => number_format($weightKg, 2) . ' kg',
            'distance_km'                => $distKm,
            'distance_label'             => '~' . number_format($distKm, 1) . ' km',
            'courier_pay'                => round((float) ($payInfo['total_pay'] ?? 15.00), 2),
            'distance_tier'              => (string) ($payInfo['distance_tier'] ?? 'inter_provincial'),
            'is_paid'                    => in_array($transfer->status, ['arrived_destination_hub', 'completed'], true),
            'notes'                      => $transfer->notes,
            'assigned_at'                => $transfer->assigned_at?->toIso8601String(),
            'departed_origin_hub_at'     => $transfer->departed_origin_hub_at?->toIso8601String(),
            'arrived_destination_hub_at' => $transfer->arrived_destination_hub_at?->toIso8601String(),
            'origin_hub'                 => [
                'hub_id'       => (int) ($origHub?->id ?? $transfer->origin_hub_id),
                'hub_code'     => (string) ($origHub?->hub_code ?? 'HUB-ORIG'),
                'hub_name'     => (string) ($origHub?->hub_name ?? 'Origin Sorting Hub'),
                'contact_no'   => (string) ($origHub?->contact_no ?? ''),
                'barangay'     => (string) ($origHub?->barangay ?? ''),
                'municipality' => (string) ($origHub?->municipality ?? ''),
                'province'     => (string) ($origHub?->province ?? ''),
                'full_address' => (string) ($origHub?->full_address ?? ($origHub?->hub_name ?? 'Origin Hub')),
                'latitude'     => (float) $origCoords['latitude'],
                'longitude'    => (float) $origCoords['longitude'],
            ],
            'destination_hub'            => [
                'hub_id'       => (int) ($destHub?->id ?? $transfer->destination_hub_id),
                'hub_code'     => (string) ($destHub?->hub_code ?? 'HUB-DEST'),
                'hub_name'     => (string) ($destHub?->hub_name ?? 'Destination Branch Hub'),
                'contact_no'   => (string) ($destHub?->contact_no ?? ''),
                'barangay'     => (string) ($destHub?->barangay ?? ''),
                'municipality' => (string) ($destHub?->municipality ?? ''),
                'province'     => (string) ($destHub?->province ?? ''),
                'full_address' => (string) ($destHub?->full_address ?? ($destHub?->hub_name ?? 'Destination Hub')),
                'latitude'     => (float) $destCoords['latitude'],
                'longitude'    => (float) $destCoords['longitude'],
            ],
            'items'                      => $itemsList,
            'events'                     => $events,
        ];
    }
}