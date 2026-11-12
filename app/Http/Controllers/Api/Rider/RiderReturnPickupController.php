<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\Concerns\FormatsReturnShipments;
use App\Http\Controllers\Api\Concerns\StoresReturnPhotos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\ReturnPickupController as WebReturnPickupController;
use App\Models\ReturnShipment;
use App\Services\CommissionCalculatorService;
use App\Services\CourierMobileSchemaService;
use App\Services\ReturnShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderReturnPickupController extends Controller
{
    use FormatsReturnShipments, StoresReturnPhotos;

    protected const COMPLETED_LIMIT = 20;

    public function __construct(
        protected ReturnShipmentService $returnShipmentService,
        protected CommissionCalculatorService $commissionCalculator
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $rider = $request->user();
        $with = array_unique(array_merge($this->returnShipmentRelations(), [
            'returnRequest',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.sellerProfile',
            'sellerOrder.order.userAddress',
            'events',
        ]));

        $toCollect = ReturnShipment::with($with)
            ->where('current_rider_id', $rider->id)
            ->where('status', ReturnShipment::STATUS_RIDER_ASSIGNED)
            ->orderBy('rider_assigned_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $toHub = ReturnShipment::with($with)
            ->where('current_rider_id', $rider->id)
            ->where('status', ReturnShipment::STATUS_PICKED_UP_FROM_BUYER)
            ->orderBy('picked_up_from_buyer_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $completedQuery = ReturnShipment::where('current_rider_id', $rider->id)
            ->whereIn('status', [
                ReturnShipment::STATUS_RECEIVED_AT_HUB,
                ReturnShipment::STATUS_COURIER_ASSIGNED,
                ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
                ReturnShipment::STATUS_DELIVERED_TO_SELLER,
            ]);

        $completedCount = (clone $completedQuery)->count();

        $completed = (clone $completedQuery)
            ->with($with)
            ->orderByDesc('arrived_at_hub_at')
            ->orderByDesc('id')
            ->limit(self::COMPLETED_LIMIT)
            ->get();

        $totalEarned = (float) ReturnShipment::where('current_rider_id', $rider->id)
            ->whereNotNull('rider_paid_at')
            ->sum('rider_pay');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'counts'          => [
                    'to_collect' => $toCollect->count(),
                    'to_hub'     => $toHub->count(),
                    'completed'  => $completedCount,
                ],
                'total_earned'    => round($totalEarned, 2),
                'max_attempts'    => ReturnShipment::MAX_PICKUP_ATTEMPTS,
                'failure_reasons' => WebReturnPickupController::FAILURE_REASONS,
                'to_collect'      => $toCollect->map(fn (ReturnShipment $rs) => $this->enrichReturnShipment($rs, $rider))->values()->all(),
                'to_hub'          => $toHub->map(fn (ReturnShipment $rs) => $this->enrichReturnShipment($rs, $rider))->values()->all(),
                'completed'       => $completed->map(fn (ReturnShipment $rs) => $this->enrichReturnShipment($rs, $rider))->values()->all(),
            ],
        ]);
    }

    public function confirmPickup(Request $request, int $id): JsonResponse
    {
        $rider = $request->user();
        $returnShipment = ReturnShipment::find($id);

        if (! $returnShipment || (int) $returnShipment->current_rider_id !== (int) $rider->id) {
            return $this->returnNotAssigned();
        }

        $validated = $request->validate([
            'pickup_photo'   => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'parcel_checked' => ['accepted'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'pickup_photo.required'   => 'A clear photo of the parcel you collected (JPG, PNG, or WEBP, max 5 MB) is required.',
            'pickup_photo.mimetypes'  => 'The pickup photo must be a valid JPG, PNG, or WEBP image.',
            'pickup_photo.max'        => 'The pickup photo must not exceed 5 MB.',
            'parcel_checked.accepted' => 'Please confirm that you collected the parcel from the buyer.',
        ]);

        $path = $this->storeReturnPhoto($request->file('pickup_photo'), 'return_pickups', 'rp', (int) $returnShipment->id);

        try {
            $updated = $this->returnShipmentService->riderConfirmPickup(
                $returnShipment,
                $rider,
                $path,
                $validated['notes'] ?? null
            );
        } catch (ValidationException $e) {
            $this->discardReturnPhoto($path);

            return $this->returnFailureFrom($e);
        } catch (\Throwable $e) {
            $this->discardReturnPhoto($path);

            throw $e;
        }

        $updated->load(array_unique(array_merge($this->returnShipmentRelations(), [
            'returnRequest',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.sellerProfile',
            'events',
        ])));

        return response()->json([
            'status'  => 'success',
            'message' => "Pickup confirmed for {$updated->tracking_number}. Bring the parcel to the sorting hub. You are paid for this leg once the hub confirms it.",
            'data'    => [
                'return' => $this->enrichReturnShipment($updated, $rider),
            ],
        ]);
    }

    public function reportFailed(Request $request, int $id): JsonResponse
    {
        $rider = $request->user();
        $returnShipment = ReturnShipment::find($id);

        if (! $returnShipment || (int) $returnShipment->current_rider_id !== (int) $rider->id) {
            return $this->returnNotAssigned();
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(WebReturnPickupController::FAILURE_REASONS)],
            'notes'  => ['nullable', 'required_if:reason,Other', 'string', 'min:5', 'max:300'],
        ], [
            'reason.required'   => 'Please choose why the parcel could not be collected.',
            'reason.in'         => 'Please choose one of the listed reasons.',
            'notes.required_if' => 'Please describe what happened when you choose "Other".',
            'notes.min'         => 'Please describe what happened (at least 5 characters).',
        ]);

        try {
            $updated = $this->returnShipmentService->riderReportPickupFailed(
                $returnShipment,
                $rider,
                $validated['reason'],
                $validated['notes'] ?? null
            );
        } catch (ValidationException $e) {
            return $this->returnFailureFrom($e);
        }

        $updated->load(array_unique(array_merge($this->returnShipmentRelations(), [
            'returnRequest',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.sellerProfile',
            'events',
        ])));

        $closed = $updated->status === ReturnShipment::STATUS_CANCELLED;

        $message = $closed
            ? "Pickup failure reported for {$updated->tracking_number}. This was the last allowed attempt, so the return request was closed."
            : "Pickup failure reported for {$updated->tracking_number}. Logistics will assign a rider for another attempt.";

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => [
                'closed' => $closed,
                'return' => $this->enrichReturnShipment($updated, $rider),
            ],
        ]);
    }

    protected function enrichReturnShipment(ReturnShipment $rs, $rider): array
    {
        $base = [];
        try {
            $base = $this->formatReturnShipment($rs, 'rider');
        } catch (\Throwable $e) {
            $base = [];
        }

        $req = $rs->returnRequest;
        $order = $rs->sellerOrder;
        $pickupSnap = (array) ($rs->pickup_address_snapshot ?? ($order?->delivery_address_snapshot ?? []));
        $hubSnap = (array) ($rs->hub_address_snapshot ?? []);
        $sellerSnap = (array) ($rs->return_address_snapshot ?? ($order?->pickup_address_snapshot ?? []));

        $buyerCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($pickupSnap, $order?->order?->userAddress);
        $hubCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($hubSnap, $rider?->assigned_hub);

        $estimatedPay = (float) ($rs->rider_pay ?? 0);
        if ($estimatedPay <= 0) {
            $calc = $this->commissionCalculator->calculateDistanceScaledDriverPay(
                'delivery',
                $pickupSnap,
                $hubSnap,
                (float) ($rs->total_weight ?: 0.5)
            );
            $estimatedPay = round((float) ($calc['total_pay'] ?? 22.50), 2);
        }

        $buyerAddress = $pickupSnap['full_address'] ?? collect([
            $pickupSnap['building_details'] ?? ($pickupSnap['house_details'] ?? null),
            $pickupSnap['street_address'] ?? ($pickupSnap['street'] ?? null),
            $pickupSnap['barangay'] ?? null,
            $pickupSnap['municipality'] ?? null,
            $pickupSnap['province'] ?? null,
        ])->filter()->implode(', ');

        $hubAddress = $hubSnap['full_address'] ?? ($rider?->assigned_hub?->full_address ?? collect([
            $hubSnap['barangay'] ?? null,
            $hubSnap['municipality'] ?? null,
            $hubSnap['province'] ?? null,
        ])->filter()->implode(', '));

        return array_merge($base, [
            'id'                      => (int) $rs->id,
            'tracking_number'         => (string) $rs->tracking_number,
            'return_request_id'       => (int) $rs->return_request_id,
            'request_number'          => (string) ($req?->request_number ?? 'RET-' . $rs->return_request_id),
            'seller_order_id'         => (int) $rs->seller_order_id,
            'seller_order_number'     => (string) ($order?->seller_order_number ?? 'N/A'),
            'status'                  => (string) $rs->status,
            'status_label'            => strtoupper(str_replace('_', ' ', (string) $rs->status)),
            'total_weight'            => round((float) ($rs->total_weight ?? 0.5), 3),
            'rider_pay'               => round($estimatedPay, 2),
            'is_paid'                 => $rs->rider_paid_at !== null,
            'rider_paid_at'           => $rs->rider_paid_at?->toIso8601String(),
            'pickup_attempts_count'   => (int) ($rs->pickup_attempts_count ?? 0),
            'max_attempts'            => ReturnShipment::MAX_PICKUP_ATTEMPTS,
            'pickup_failure_reason'   => $rs->pickup_failure_reason,
            'reason_category'         => (string) ($req?->reason_category ?? 'Return Request'),
            'reason'                  => (string) ($req?->reason ?? ''),
            'buyer_pickup_photo_url'  => $rs->buyer_pickup_photo ? asset('storage/' . ltrim($rs->buyer_pickup_photo, '/')) : null,
            'rider_assigned_at'       => $rs->rider_assigned_at?->toIso8601String(),
            'picked_up_from_buyer_at' => $rs->picked_up_from_buyer_at?->toIso8601String(),
            'arrived_at_hub_at'       => $rs->arrived_at_hub_at?->toIso8601String(),
            'buyer'                   => [
                'recipient_name' => (string) ($pickupSnap['recipient_name'] ?? ($order?->buyer?->full_name ?? 'Buyer')),
                'phone_number'   => (string) ($pickupSnap['phone_number'] ?? ($order?->buyer?->contact_no ?? '')),
                'barangay'       => (string) ($pickupSnap['barangay'] ?? ''),
                'municipality'   => (string) ($pickupSnap['municipality'] ?? ''),
                'province'       => (string) ($pickupSnap['province'] ?? ''),
                'full_address'   => (string) $buyerAddress,
                'latitude'       => (float) $buyerCoords['latitude'],
                'longitude'      => (float) $buyerCoords['longitude'],
            ],
            'hub'                     => [
                'hub_name'     => (string) ($hubSnap['hub_name'] ?? ($rider?->assigned_hub?->hub_name ?? 'Sorting Hub')),
                'contact_no'   => (string) ($hubSnap['contact_no'] ?? ($rider?->assigned_hub?->contact_no ?? '')),
                'barangay'     => (string) ($hubSnap['barangay'] ?? ($rider?->assigned_hub?->barangay ?? '')),
                'municipality' => (string) ($hubSnap['municipality'] ?? ($rider?->assigned_hub?->municipality ?? '')),
                'province'     => (string) ($hubSnap['province'] ?? ($rider?->assigned_hub?->province ?? '')),
                'full_address' => (string) $hubAddress,
                'latitude'     => (float) $hubCoords['latitude'],
                'longitude'    => (float) $hubCoords['longitude'],
            ],
            'seller'                  => [
                'business_name' => (string) ($order?->sellerProfile?->business_name ?? ($sellerSnap['business_name'] ?? 'Seller Store')),
                'municipality'  => (string) ($sellerSnap['municipality'] ?? ''),
                'province'      => (string) ($sellerSnap['province'] ?? ''),
            ],
            'items'                   => ($order?->items ?? collect())->map(fn ($item) => [
                'id'           => (int) $item->id,
                'product_name' => (string) $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity'     => (int) $item->quantity,
                'line_weight'  => (float) $item->line_weight,
            ])->values()->all(),
        ]);
    }
}