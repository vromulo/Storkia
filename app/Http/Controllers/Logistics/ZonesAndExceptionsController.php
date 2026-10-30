<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\OrderStatusHistory;
use App\Models\PlatformAuditLog;
use App\Models\Rider;
use App\Models\SellerRtsClaim;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\FormalReportService;
use App\Services\OperationalSlaGuardService;
use App\Services\OrderCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ZonesAndExceptionsController extends Controller
{
    public function __construct(
        protected OrderCompletionService $orderCompletionService,
        protected OperationalSlaGuardService $slaGuardService,
        protected FormalReportService $formalReportService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $profile->id);
        $this->seedDefaultZonesIfEmpty((int) $profile->id);

        $rates = DB::table('logistics_shipping_rates')
            ->where(function ($q) use ($profile) {
                $q->where('logistics_profile_id', $profile->id)
                  ->orWhereNull('logistics_profile_id');
            })
            ->orderBy('id', 'asc')
            ->get();

        $activeShipments = Shipment::with(['sellerOrder.buyer', 'sellerProfile', 'currentRider'])
            ->where('logistics_profile_id', $profile->id)
            ->orderBy('created_at', 'asc')
            ->limit(30)
            ->get();

        // Short COD Remittance Discrepancies (LOG-COD-002 & RID-EARN-005)
        $shortRemittances = CodCollection::with(['rider', 'sellerOrder', 'shipment'])
            ->where('logistics_profile_id', $profile->id)
            ->whereNotNull('remitted_amount')
            ->whereRaw('remitted_amount < expected_amount')
            ->orderBy('created_at', 'asc')
            ->get();

        $recentAuditLogs = Schema::hasTable('platform_audit_logs')
            ? PlatformAuditLog::whereIn('target_type', ['Shipment', 'ShippingRate', 'Rider', 'CodRemittance'])
                ->orderByDesc('occurred_at')
                ->limit(15)
                ->get()
            : collect();

        return view('logistics.zones.index', compact(
            'profile',
            'rates',
            'activeShipments',
            'shortRemittances',
            'recentAuditLogs'
        ));
    }

    public function storeOrUpdateZoneRate(Request $request)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'rate_id'            => ['nullable', 'integer'],
            'zone_code'          => ['required', 'string', 'max:40'],
            'zone_name'          => ['required', 'string', 'max:120'],
            'origin_region'      => ['required', 'string', 'max:80'],
            'destination_region' => ['required', 'string', 'max:80'],
            'first_kg_rate'      => ['required', 'numeric', 'min:0.01', 'max:50000'],
            'additional_kg_rate' => ['required', 'numeric', 'min:0.01', 'max:50000'],
            'is_serviceable'     => ['required', 'boolean'],
            'reason'             => ['required', 'string', 'min:10', 'max:300'],
        ], [
            'first_kg_rate.min'      => 'First kg rate must be at least ₱0.01.',
            'additional_kg_rate.min' => 'Additional kg rate must be at least ₱0.01.',
            'reason.min'             => 'Audit reason must be between 10 and 300 characters.',
        ]);

        if (! empty($validated['rate_id'])) {
            $existing = DB::table('logistics_shipping_rates')->where('id', (int) $validated['rate_id'])->first();
            DB::table('logistics_shipping_rates')
                ->where('id', (int) $validated['rate_id'])
                ->update([
                    'zone_code'          => strtoupper(trim($validated['zone_code'])),
                    'zone_name'          => trim($validated['zone_name']),
                    'origin_region'      => trim($validated['origin_region']),
                    'destination_region' => trim($validated['destination_region']),
                    'first_kg_rate'      => round((float) $validated['first_kg_rate'], 2),
                    'additional_kg_rate' => round((float) $validated['additional_kg_rate'], 2),
                    'is_serviceable'     => (bool) $validated['is_serviceable'],
                    'updated_at'         => now(),
                ]);

            AuditLoggerService::log(
                action: 'shipping_rate_updated',
                targetType: 'ShippingRate',
                targetId: (int) $validated['rate_id'],
                targetReference: $validated['zone_name'],
                oldValues: $existing ? [
                    'first_kg_rate'      => (float) $existing->first_kg_rate,
                    'additional_kg_rate' => (float) $existing->additional_kg_rate,
                    'is_serviceable'     => (bool) $existing->is_serviceable,
                ] : null,
                newValues: [
                    'first_kg_rate'      => round((float) $validated['first_kg_rate'], 2),
                    'additional_kg_rate' => round((float) $validated['additional_kg_rate'], 2),
                    'is_serviceable'     => (bool) $validated['is_serviceable'],
                ],
                reason: $validated['reason']
            );

            return back()->with('success', "Shipping zone \"{$validated['zone_name']}\" updated and logged in the immutable Audit Trail.");
        }

        $newId = DB::table('logistics_shipping_rates')->insertGetId([
            'logistics_profile_id' => $profile->id,
            'zone_code'            => strtoupper(trim($validated['zone_code'])),
            'zone_name'            => trim($validated['zone_name']),
            'origin_region'        => trim($validated['origin_region']),
            'destination_region'   => trim($validated['destination_region']),
            'first_kg_rate'        => round((float) $validated['first_kg_rate'], 2),
            'additional_kg_rate'   => round((float) $validated['additional_kg_rate'], 2),
            'is_serviceable'       => (bool) $validated['is_serviceable'],
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        AuditLoggerService::log(
            action: 'shipping_zone_added',
            targetType: 'ShippingRate',
            targetId: $newId,
            targetReference: $validated['zone_name'],
            newValues: $validated,
            reason: $validated['reason']
        );

        return back()->with('success', "New delivery zone \"{$validated['zone_name']}\" added and logged.");
    }

    public function overrideShipmentStatus(Request $request, Shipment $shipment)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $shipment->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $validated = $request->validate([
            'new_status'     => ['required', 'in:received_at_hub,out_for_delivery,delivered,delivery_failed,returned_to_seller,lost,damaged'],
            'reason'         => ['required', 'string', 'min:10', 'max:300'],
            'evidence_photo' => ['required_if:new_status,damaged', 'nullable', 'file', 'mimetypes:image/jpeg,image/png', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'reason.required'            => 'A reason (10 to 300 characters) is strictly required for any status override or exception.',
            'reason.min'                 => 'The override reason must be at least 10 characters.',
            'evidence_photo.required_if' => 'A valid JPG or PNG photo (max 5 MB) is required when marking a parcel as Damaged.',
        ]);

        $photoPath = null;
        if ($request->hasFile('evidence_photo')) {
            $photoPath = $request->file('evidence_photo')->store('shipment_exceptions', 'public');
        }

        DB::transaction(function () use ($shipment, $user, $profile, $validated, $photoPath) {
            $locked = Shipment::with(['sellerOrder.order', 'sellerOrder.buyer', 'sellerOrder.seller', 'sellerOrder.payment', 'sellerOrder.extra'])
                ->where('id', $shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldShipmentStatus = $locked->status;
            $newStatus = $validated['new_status'];
            $sellerOrder = $locked->sellerOrder;
            $oldOrderStatus = $sellerOrder->status;

            $locked->update([
                'status'       => $newStatus,
                'delivered_at' => $newStatus === 'delivered' ? ($locked->delivered_at ?? now()) : $locked->delivered_at,
            ]);

            if ($newStatus === 'delivered') {
                $sellerOrder->update([
                    'status'                   => 'delivered',
                    'delivered_at'             => now(),
                    'confirmation_deadline_at' => now()->addHours(72),
                ]);
            } elseif (in_array($newStatus, ['lost', 'damaged'], true)) {
                $sellerOrder->update([
                    'status'                 => 'disputed',
                    'has_unresolved_dispute' => true,
                    'dispute_reason'         => strtoupper($newStatus) . ' Parcel Exception logged by Logistics: ' . trim($validated['reason']),
                    'dispute_reported_at'    => now(),
                ]);

                SellerRtsClaim::updateOrCreate(
                    ['seller_order_id' => $sellerOrder->id],
                    [
                        'shipment_id'             => $locked->id,
                        'seller_profile_id'       => $sellerOrder->seller_profile_id,
                        'logistics_profile_id'    => $profile->id,
                        'buyer_id'                => $sellerOrder->buyer_id,
                        'abandonment_reason'      => strtoupper($newStatus) . ': ' . trim($validated['reason']),
                        'rts_status'              => $newStatus,
                        'is_damaged_or_lost'      => true,
                        'claimed_product_expense' => $sellerOrder->subtotal_amount,
                        'claim_description'       => trim($validated['reason']),
                        'evidence_photo_path'     => $photoPath,
                        'reimbursement_status'    => 'pending_admin_review',
                    ]
                );
            } elseif ($newStatus === 'returned_to_seller') {
                $sellerOrder->update([
                    'status'         => 'returned_to_seller',
                    'payment_status' => 'cancelled',
                    'cancelled_at'   => now(),
                ]);
            }

            ShipmentTracking::create([
                'shipment_id'     => $locked->id,
                'seller_order_id' => $sellerOrder->id,
                'status'          => $newStatus,
                'title'           => 'Logistics Manual Status Override (' . strtoupper(str_replace('_', ' ', $newStatus)) . ')',
                'description'     => "Logistics overrode status from {$oldShipmentStatus} to {$newStatus}. Reason: {$validated['reason']}",
                'location'        => $profile->business_name,
                'actor_type'      => 'Logistics',
                'actor_id'        => $user->id,
                'occurred_at'     => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'        => $sellerOrder->order_id,
                'seller_order_id' => $sellerOrder->id,
                'from_status'     => $oldOrderStatus,
                'to_status'       => $sellerOrder->status,
                'actor_type'      => 'Logistics',
                'actor_id'        => $user->id,
                'remarks'         => "Logistics manual override ({$oldShipmentStatus} -> {$newStatus}): " . trim($validated['reason']),
            ]);

            AuditLoggerService::log(
                action: 'shipment_status_override',
                targetType: 'Shipment',
                targetId: (int) $locked->id,
                targetReference: $locked->tracking_number,
                oldValues: ['shipment_status' => $oldShipmentStatus, 'order_status' => $oldOrderStatus],
                newValues: ['shipment_status' => $newStatus, 'order_status' => $sellerOrder->status, 'photo' => $photoPath],
                reason: $validated['reason']
            );

            if ($sellerOrder->order) {
                $this->orderCompletionService->syncParentOrderStatus($sellerOrder->order);
            }

            if (in_array($newStatus, ['lost', 'damaged'], true)) {
                try {
                    $sellerOrder->buyer?->notify(new OrderLifecycleNotification(
                        title: 'Shipment Exception: Parcel Marked ' . ucfirst($newStatus),
                        message: "Tracking #{$locked->tracking_number} was reported as {$newStatus} by Logistics ({$validated['reason']}). A refund/claim process has been started.",
                        type: 'danger',
                        url: route('user.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $locked->id
                    ));

                    $sellerOrder->seller?->notify(new OrderLifecycleNotification(
                        title: 'Shipment Exception: Parcel Marked ' . ucfirst($newStatus),
                        message: "Tracking #{$locked->tracking_number} (Order #{$sellerOrder->seller_order_number}) was reported {$newStatus} by Logistics. An RTS reimbursement claim has been opened.",
                        type: 'danger',
                        url: route('seller.orders.show', $sellerOrder->id),
                        orderId: $sellerOrder->order_id,
                        sellerOrderId: $sellerOrder->id,
                        shipmentId: $locked->id
                    ));
                } catch (\Throwable $e) {
                }
            }
        });

        return back()->with('success', "Shipment #{$shipment->tracking_number} status updated to \"{$validated['new_status']}\" and permanently recorded in the Audit Trail.");
    }

    public function suspendRiderWithReason(Request $request, Rider $rider)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'Cannot manage another company\'s rider.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:300'],
        ], [
            'reason.required' => 'Please provide a suspension reason (10 to 300 characters).',
            'reason.min'      => 'Suspension reason must be at least 10 characters.',
        ]);

        $unassignedJobs = $this->slaGuardService->suspendRiderWithActiveJobHandling(
            rider: $rider,
            reason: $validated['reason'],
            actorType: 'Logistics',
            actorId: (int) $user->id,
            actorName: $user->full_name
        );

        return back()->with(
            'success',
            "Rider {$rider->full_name} suspended and set Offline. {$unassignedJobs} not-yet-picked-up job(s) were returned to the Unassigned queue."
        );
    }

    public function formalPdfReport(Request $request)
    {
        $profile = $request->user()->logisticsProfile;
        $report = $this->formalReportService->buildRoleReportPayload(
            role: 'Logistics',
            entityId: (int) $profile->id,
            entityName: $profile->business_name,
            request: $request
        );

        return view('reports.formal-pdf-report', compact('report'));
    }

    protected function seedDefaultZonesIfEmpty(int $logisticsProfileId): void
    {
        if (! Schema::hasTable('logistics_shipping_rates')) {
            return;
        }

        if (DB::table('logistics_shipping_rates')->count() > 0) {
            return;
        }

        $defaults = [
            [
                'logistics_profile_id' => $logisticsProfileId,
                'zone_code'            => 'NCR-LOCAL',
                'zone_name'            => 'Within Metro Manila (NCR) / Same Municipality',
                'origin_region'        => 'NCR',
                'destination_region'   => 'NCR',
                'first_kg_rate'        => 50.00,
                'additional_kg_rate'   => 15.00,
                'is_serviceable'       => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'logistics_profile_id' => $logisticsProfileId,
                'zone_code'            => 'VIS-LOCAL',
                'zone_name'            => 'Within Visayas / Same Province',
                'origin_region'        => 'Visayas',
                'destination_region'   => 'Visayas',
                'first_kg_rate'        => 60.00,
                'additional_kg_rate'   => 20.00,
                'is_serviceable'       => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'logistics_profile_id' => $logisticsProfileId,
                'zone_code'            => 'NCR-VIS',
                'zone_name'            => 'Inter-Island (NCR <-> Visayas / Provincial)',
                'origin_region'        => 'NCR',
                'destination_region'   => 'Visayas',
                'first_kg_rate'        => 120.00,
                'additional_kg_rate'   => 40.00,
                'is_serviceable'       => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
            [
                'logistics_profile_id' => $logisticsProfileId,
                'zone_code'            => 'BATANES-UNSERV',
                'zone_name'            => 'Itbayat, Batanes (Unserviceable Island Zone)',
                'origin_region'        => 'Any',
                'destination_region'   => 'Batanes',
                'first_kg_rate'        => 250.00,
                'additional_kg_rate'   => 80.00,
                'is_serviceable'       => false,
                'created_at'           => now(),
                'updated_at'           => now(),
            ],
        ];

        DB::table('logistics_shipping_rates')->insert($defaults);
    }
}