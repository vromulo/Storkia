<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\SellerProfile;
use App\Models\ShipmentTracking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperationalSlaGuardService
{
    public const MAX_SLA_STRIKES = 3;
    public const DISPATCH_SLA_HOURS = 2;

    public function recordSellerSlaStrike(SellerProfile $sellerProfile, string $reason, bool $isUnserviceableAreaException = false): void
    {
        if ($isUnserviceableAreaException) {
            AuditLoggerService::log(
                action: 'seller_unserviceable_rejection_exempt',
                targetType: 'SellerProfile',
                targetId: (int) $sellerProfile->id,
                targetReference: $sellerProfile->business_name,
                oldValues: [
                    'sla_strikes'    => (int) ($sellerProfile->sla_strikes ?? 0),
                    'is_on_vacation' => (bool) ($sellerProfile->is_on_vacation ?? false),
                ],
                newValues: [
                    'sla_strikes'    => (int) ($sellerProfile->sla_strikes ?? 0),
                    'is_on_vacation' => (bool) ($sellerProfile->is_on_vacation ?? false),
                ],
                reason: 'Exempt from SLA violation strike (Unserviceable Area / No Available Fleet): ' . $reason,
                actorType: 'System',
                actorName: 'Operational SLA Guard'
            );
            return;
        }

        if (! Schema::hasColumn('seller_profiles', 'sla_strikes')) {
            return;
        }

        $newStrikes = (int) $sellerProfile->sla_strikes + 1;
        $autoVacation = $newStrikes >= self::MAX_SLA_STRIKES;

        $oldState = [
            'sla_strikes'    => (int) $sellerProfile->sla_strikes,
            'is_on_vacation' => (bool) $sellerProfile->is_on_vacation,
        ];

        $sellerProfile->update([
            'sla_strikes'    => $newStrikes,
            'is_on_vacation' => $autoVacation ? true : (bool) $sellerProfile->is_on_vacation,
        ]);

        AuditLoggerService::log(
            action: $autoVacation ? 'seller_auto_vacation_sla_breach' : 'seller_sla_strike_recorded',
            targetType: 'SellerProfile',
            targetId: (int) $sellerProfile->id,
            targetReference: $sellerProfile->business_name,
            oldValues: $oldState,
            newValues: [
                'sla_strikes'    => $newStrikes,
                'is_on_vacation' => (bool) $sellerProfile->is_on_vacation,
            ],
            reason: $reason,
            actorType: 'System',
            actorName: 'Operational SLA Guard'
        );
    }

    public function suspendCourierWithActiveJobHandling(
        Courier $courier,
        string $reason,
        string $actorType = 'Logistics',
        ?int $actorId = null,
        ?string $actorName = null
    ): int {
        $cleanReason = trim($reason);
        if (strlen($cleanReason) < 10 || strlen($cleanReason) > 300) {
            throw ValidationException::withMessages([
                'reason' => 'Suspension reason is required and must be between 10 and 300 characters.',
            ]);
        }

        $unassignedCount = DB::transaction(function () use ($courier, $cleanReason, $actorType, $actorId, $actorName) {
            $oldStatus = $courier->status;
            $oldOnline = (bool) ($courier->is_online ?? false);

            $updateData = [
                'status' => 'suspended',
            ];
            if (Schema::hasColumn('couriers', 'is_online')) {
                $updateData['is_online'] = false;
            }
            $courier->update($updateData);

            $uncollectedPickups = PickupAssignment::with(['shipment.sellerOrder'])
                ->where('courier_id', $courier->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->lockForUpdate()
                ->get();

            $count = 0;

            foreach ($uncollectedPickups as $assignment) {
                $assignment->update([
                    'status' => 'reassigned',
                    'notes'  => trim(($assignment->notes ? $assignment->notes . ' | ' : '') . "Auto-unassigned due to courier suspension: {$cleanReason}"),
                ]);

                if ($assignment->shipment && $assignment->shipment->status === 'courier_assigned') {
                    $assignment->shipment->update([
                        'current_courier_id' => null,
                        'status'             => 'pickup_requested',
                    ]);

                    if ($assignment->shipment->sellerOrder && $assignment->shipment->sellerOrder->status === 'pickup_assigned') {
                        $assignment->shipment->sellerOrder->update([
                            'status' => 'ready_for_pickup',
                        ]);
                    }

                    ShipmentTracking::create([
                        'shipment_id'     => $assignment->shipment->id,
                        'seller_order_id' => $assignment->seller_order_id,
                        'status'          => 'pickup_requested',
                        'title'           => 'Returned to Unassigned Pickup Queue (Courier Suspended)',
                        'description'     => "Courier {$courier->full_name} was suspended before collecting the parcel ({$cleanReason}). Shipment returned to Unassigned Pickup queue.",
                        'location'        => $courier->assigned_hub?->hub_name ?? 'Sorting Hub',
                        'actor_type'      => $actorType,
                        'actor_id'        => $actorId,
                        'occurred_at'     => now(),
                    ]);

                    $count++;
                }
            }

            AuditLoggerService::log(
                action: 'courier_suspended',
                targetType: 'Courier',
                targetId: (int) $courier->id,
                targetReference: $courier->full_name . ' (' . $courier->plate_number . ')',
                oldValues: ['status' => $oldStatus, 'is_online' => $oldOnline],
                newValues: ['status' => 'suspended', 'is_online' => false, 'jobs_returned_to_unassigned' => $count],
                reason: $cleanReason,
                actorType: $actorType,
                actorId: $actorId,
                actorName: $actorName
            );

            return $count;
        });

        try {
            app(OrderCompletionService::class)->autoCancelUnserviceablePendingOrders();
        } catch (\Throwable $e) {
        }

        return $unassignedCount;
    }

    public function suspendRiderWithActiveJobHandling(
        Rider $rider,
        string $reason,
        string $actorType = 'Logistics',
        ?int $actorId = null,
        ?string $actorName = null
    ): int {
        $cleanReason = trim($reason);
        if (strlen($cleanReason) < 10 || strlen($cleanReason) > 300) {
            throw ValidationException::withMessages([
                'reason' => 'Suspension reason is required and must be between 10 and 300 characters.',
            ]);
        }

        $unassignedCount = DB::transaction(function () use ($rider, $cleanReason, $actorType, $actorId, $actorName) {
            $oldStatus = $rider->status;
            $oldOnline = (bool) ($rider->is_online ?? false);

            $updateData = [
                'status'      => 'rejected',
                'rejected_at' => now(),
            ];
            if (Schema::hasColumn('riders', 'is_online')) {
                $updateData['is_online'] = false;
            }
            $rider->update($updateData);

            $uncollectedAssignments = DeliveryAssignment::with('shipment')
                ->where('rider_id', $rider->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->lockForUpdate()
                ->get();

            $count = 0;

            foreach ($uncollectedAssignments as $assignment) {
                $assignment->update([
                    'status' => 'reassigned',
                    'notes'  => trim(($assignment->notes ? $assignment->notes . ' | ' : '') . "Auto-unassigned due to rider suspension: {$cleanReason}"),
                ]);

                if ($assignment->shipment && in_array($assignment->shipment->status, ['rider_assigned', 'scheduled_redelivery'], true)) {
                    $restoredStatus = $assignment->shipment->needsInterHubTransfer()
                        ? 'received_at_destination_hub'
                        : 'received_at_hub';

                    $assignment->shipment->update([
                        'current_rider_id' => null,
                        'status'           => $restoredStatus,
                    ]);

                    ShipmentTracking::create([
                        'shipment_id'     => $assignment->shipment->id,
                        'seller_order_id' => $assignment->seller_order_id,
                        'status'          => $restoredStatus,
                        'title'           => 'Returned to Unassigned Hub Queue (Rider Suspended)',
                        'description'     => "Rider {$rider->full_name} was suspended before collecting the parcel ({$cleanReason}). Shipment returned to Unassigned queue.",
                        'location'        => $rider->assigned_hub?->hub_name ?? 'Sorting Hub',
                        'actor_type'      => $actorType,
                        'actor_id'        => $actorId,
                        'occurred_at'     => now(),
                    ]);

                    $count++;
                }
            }

            AuditLoggerService::log(
                action: 'rider_suspended',
                targetType: 'Rider',
                targetId: (int) $rider->id,
                targetReference: $rider->full_name . ' (' . $rider->plate_number . ')',
                oldValues: ['status' => $oldStatus, 'is_online' => $oldOnline],
                newValues: ['status' => 'rejected', 'is_online' => false, 'jobs_returned_to_unassigned' => $count],
                reason: $cleanReason,
                actorType: $actorType,
                actorId: $actorId,
                actorName: $actorName
            );

            return $count;
        });

        try {
            app(OrderCompletionService::class)->autoCancelUnserviceablePendingOrders();
        } catch (\Throwable $e) {
        }

        return $unassignedCount;
    }

    public function sweepOverdueEmployeeAssignments(int $logisticsProfileId): void
    {
        $throttleKey = 'storkia_sla_sweep_hub_' . $logisticsProfileId;
        if (! Cache::add($throttleKey, true, now()->addSeconds(90))) {
            return;
        }

        $cutoff = now()->subHours(self::DISPATCH_SLA_HOURS);

        $overduePickups = PickupAssignment::with(['courier', 'shipment'])
            ->where('logistics_profile_id', $logisticsProfileId)
            ->where('status', 'assigned')
            ->where('assigned_at', '<=', $cutoff)
            ->limit(25)
            ->get();

        foreach ($overduePickups as $pickup) {
            $courier = $pickup->courier;
            $shipment = $pickup->shipment;
            if (! $courier || ! $shipment) {
                continue;
            }

            if (Schema::hasColumn('couriers', 'sla_strikes')) {
                $strikes = (int) $courier->sla_strikes + 1;
                $courier->update(['sla_strikes' => $strikes]);

                if ($strikes >= self::MAX_SLA_STRIKES) {
                    $this->suspendCourierWithActiveJobHandling(
                        courier: $courier,
                        reason: 'Automated SLA Suspension: Courier missed 3 scheduled pickup assignments.',
                        actorType: 'System',
                        actorName: 'Operational SLA Guard'
                    );
                    continue;
                }
            }

            DB::transaction(function () use ($pickup, $courier, $shipment) {
                $pickup->update([
                    'status' => 'reassigned',
                    'notes'  => 'SLA Missed: Courier failed to accept pickup within 2 hours.',
                ]);

                if ((int) $shipment->current_courier_id === (int) $courier->id && $shipment->status === 'courier_assigned') {
                    $shipment->update([
                        'current_courier_id' => null,
                        'status'             => 'pickup_requested',
                    ]);
                }
            });
        }

        $overdueDeliveries = DeliveryAssignment::with(['rider', 'shipment'])
            ->where('logistics_profile_id', $logisticsProfileId)
            ->where('status', 'assigned')
            ->where('assigned_at', '<=', $cutoff)
            ->limit(25)
            ->get();

        foreach ($overdueDeliveries as $deliv) {
            $rider = $deliv->rider;
            if (! $rider) {
                continue;
            }

            if (Schema::hasColumn('riders', 'sla_strikes')) {
                $strikes = (int) $rider->sla_strikes + 1;
                $rider->update(['sla_strikes' => $strikes]);

                if ($strikes >= self::MAX_SLA_STRIKES) {
                    $this->suspendRiderWithActiveJobHandling(
                        rider: $rider,
                        reason: 'Automated SLA Suspension: Rider missed 3 scheduled dispatch assignments.',
                        actorType: 'System',
                        actorName: 'Operational SLA Guard'
                    );
                    continue;
                }
            }

            $deliv->update([
                'status' => 'reassigned',
                'notes'  => 'SLA Missed: Rider failed to accept delivery task within 2 hours.',
            ]);

            if ($deliv->shipment && (int) $deliv->shipment->current_rider_id === (int) $rider->id) {
                $restoredStatus = $deliv->shipment->needsInterHubTransfer()
                    ? 'received_at_destination_hub'
                    : 'received_at_hub';

                $deliv->shipment->update([
                    'current_rider_id' => null,
                    'status'           => $restoredStatus,
                ]);
            }
        }
    }
}