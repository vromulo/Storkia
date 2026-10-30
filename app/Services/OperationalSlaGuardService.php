<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OperationalSlaGuardService
{
    public const MAX_SLA_STRIKES = 3;
    public const DISPATCH_SLA_HOURS = 2;

    public function recordSellerSlaStrike(SellerProfile $sellerProfile, string $reason): void
    {
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

        return DB::transaction(function () use ($rider, $cleanReason, $actorType, $actorId, $actorName) {
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

            // LOG-RIDER-003 & A34: Return not-yet-picked-up jobs ('assigned', 'accepted') to the Unassigned queue;
            // parcels already picked up ('collected', 'out_for_delivery') stay with the rider.
            $uncollectedAssignments = DeliveryAssignment::with('shipment')
                ->where('rider_id', $rider->id)
                ->whereIn('status', ['assigned', 'accepted'])
                ->lockForUpdate()
                ->get();

            $unassignedCount = 0;

            foreach ($uncollectedAssignments as $assignment) {
                $assignment->update([
                    'status' => 'reassigned',
                    'notes'  => trim(($assignment->notes ? $assignment->notes . ' | ' : '') . "Auto-unassigned due to rider suspension: {$cleanReason}"),
                ]);

                if ($assignment->shipment && in_array($assignment->shipment->status, ['rider_assigned', 'scheduled_redelivery'], true)) {
                    $assignment->shipment->update([
                        'current_rider_id' => null,
                        'status'           => 'received_at_hub',
                    ]);

                    ShipmentTracking::create([
                        'shipment_id'     => $assignment->shipment->id,
                        'seller_order_id' => $assignment->seller_order_id,
                        'status'          => 'received_at_hub',
                        'title'           => 'Returned to Unassigned Hub Queue (Rider Suspended)',
                        'description'     => "Rider {$rider->full_name} was suspended before collecting the parcel ({$cleanReason}). Shipment returned to Unassigned queue.",
                        'location'        => $rider->assigned_hub?->hub_name ?? 'Sorting Hub',
                        'actor_type'      => $actorType,
                        'actor_id'        => $actorId,
                        'occurred_at'     => now(),
                    ]);

                    $unassignedCount++;
                }
            }

            AuditLoggerService::log(
                action: 'rider_suspended',
                targetType: 'Rider',
                targetId: (int) $rider->id,
                targetReference: $rider->full_name . ' (' . $rider->plate_number . ')',
                oldValues: ['status' => $oldStatus, 'is_online' => $oldOnline],
                newValues: ['status' => 'rejected', 'is_online' => false, 'jobs_returned_to_unassigned' => $unassignedCount],
                reason: $cleanReason,
                actorType: $actorType,
                actorId: $actorId,
                actorName: $actorName
            );

            return $unassignedCount;
        });
    }

    public function sweepOverdueEmployeeAssignments(int $logisticsProfileId): void
    {
        $cutoff = now()->subHours(self::DISPATCH_SLA_HOURS);

        // 1. Sweep Couriers who failed to accept/collect within 2 hours of assignment
        $overduePickups = PickupAssignment::with(['courier', 'shipment'])
            ->where('logistics_profile_id', $logisticsProfileId)
            ->where('status', 'assigned')
            ->where('assigned_at', '<=', $cutoff)
            ->limit(25)
            ->get();

        foreach ($overduePickups as $pickup) {
            DB::transaction(function () use ($pickup) {
                $courier = $pickup->courier;
                $shipment = $pickup->shipment;
                if (! $courier || ! $shipment) {
                    return;
                }

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

                if (Schema::hasColumn('couriers', 'sla_strikes')) {
                    $strikes = (int) $courier->sla_strikes + 1;
                    $shouldSuspend = $strikes >= self::MAX_SLA_STRIKES;

                    $courier->update([
                        'sla_strikes' => $strikes,
                        'status'      => $shouldSuspend ? 'suspended' : $courier->status,
                        'is_online'   => $shouldSuspend ? false : $courier->is_online,
                    ]);
                }
            });
        }

        // 2. Sweep Riders who failed to accept/collect within 2 hours of assignment
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
                $deliv->shipment->update([
                    'current_rider_id' => null,
                    'status'           => 'received_at_hub',
                ]);
            }
        }
    }
}