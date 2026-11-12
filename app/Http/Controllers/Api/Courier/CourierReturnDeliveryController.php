<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Api\Concerns\FormatsReturnShipments;
use App\Http\Controllers\Api\Concerns\StoresReturnPhotos;
use App\Http\Controllers\Controller;
use App\Models\ReturnShipment;
use App\Services\CommissionCalculatorService;
use App\Services\CourierMobileSchemaService;
use App\Services\ReturnShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourierReturnDeliveryController extends Controller
{
    use FormatsReturnShipments, StoresReturnPhotos;

    protected const COMPLETED_LIMIT = 20;

    public const FAILURE_REASONS = [
        'Seller store / warehouse closed',
        'Seller unreachable by phone',
        'Seller refused to receive return parcel',
        'Incorrect or incomplete seller address',
        'Vehicle breakdown or road closure',
        'Severe weather conditions',
        'Other',
    ];

    public function __construct(
        protected ReturnShipmentService $returnShipmentService,
        protected CommissionCalculatorService $commissionCalculator
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $courier = $request->user();
        $with = array_unique(array_merge($this->returnShipmentRelations(), [
            'returnRequest',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.sellerProfile',
            'events',
        ]));

        $toCollect = ReturnShipment::with($with)
            ->where('current_courier_id', $courier->id)
            ->where('status', ReturnShipment::STATUS_COURIER_ASSIGNED)
            ->orderBy('courier_assigned_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $outForDelivery = ReturnShipment::with($with)
            ->where('current_courier_id', $courier->id)
            ->where('status', ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY)
            ->orderBy('picked_up_from_hub_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $completedQuery = ReturnShipment::where('current_courier_id', $courier->id)
            ->where('status', ReturnShipment::STATUS_DELIVERED_TO_SELLER);

        $completedCount = (clone $completedQuery)->count();

        $completed = (clone $completedQuery)
            ->with($with)
            ->orderByDesc('delivered_to_seller_at')
            ->orderByDesc('id')
            ->limit(self::COMPLETED_LIMIT)
            ->get();

        $totalEarned = (float) ReturnShipment::where('current_courier_id', $courier->id)
            ->whereNotNull('courier_paid_at')
            ->sum('courier_pay');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'counts'           => [
                    'to_collect'       => $toCollect->count(),
                    'out_for_delivery' => $outForDelivery->count(),
                    'completed'        => $completedCount,
                ],
                'total_earned'     => round($totalEarned, 2),
                'max_attempts'     => ReturnShipment::MAX_DELIVERY_ATTEMPTS,
                'failure_reasons'  => self::FAILURE_REASONS,
                'to_collect'       => $toCollect->map(fn (ReturnShipment $rs) => $this->enrichCourierReturn($rs, $courier))->values()->all(),
                'out_for_delivery' => $outForDelivery->map(fn (ReturnShipment $rs) => $this->enrichCourierReturn($rs, $courier))->values()->all(),
                'completed'        => $completed->map(fn (ReturnShipment $rs) => $this->enrichCourierReturn($rs, $courier))->values()->all(),
            ],
        ]);
    }

    public function startDelivery(Request $request, int $id): JsonResponse
    {
        $courier = $request->user();
        $returnShipment = ReturnShipment::find($id);

        if (! $returnShipment || (int) $returnShipment->current_courier_id !== (int) $courier->id) {
            return $this->returnNotAssigned();
        }

        try {
            $updated = $this->returnShipmentService->courierStartDelivery($returnShipment, $courier);
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

        return response()->json([
            'status'  => 'success',
            'message' => "Return delivery started for {$updated->tracking_number}. Transport the parcel from the sorting hub to the Seller and confirm with a photo upon handover.",
            'data'    => [
                'return' => $this->enrichCourierReturn($updated, $courier),
            ],
        ]);
    }

    public function confirmDelivered(Request $request, int $id): JsonResponse
    {
        $courier = $request->user();
        $returnShipment = ReturnShipment::find($id);

        if (! $returnShipment || (int) $returnShipment->current_courier_id !== (int) $courier->id) {
            return $this->returnNotAssigned();
        }

        $validated = $request->validate([
            'delivery_photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'parcel_handed'  => ['accepted'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'delivery_photo.required'  => 'A clear photo of the parcel handed to the seller (JPG, PNG, or WEBP, max 5 MB) is required.',
            'delivery_photo.mimetypes' => 'The delivery photo must be a valid JPG, PNG, or WEBP image.',
            'delivery_photo.max'       => 'The delivery photo must not exceed 5 MB.',
            'parcel_handed.accepted'   => 'Please confirm that you handed the parcel to the seller.',
        ]);

        $path = $this->storeReturnPhoto($request->file('delivery_photo'), 'return_deliveries', 'rd', (int) $returnShipment->id);

        $recipient = trim((string) ($validated['recipient_name'] ?? ''));
        $notes = trim(($recipient !== '' ? "Received by {$recipient}. " : '') . trim((string) ($validated['notes'] ?? '')));

        try {
            $updated = $this->returnShipmentService->courierConfirmDelivered(
                $returnShipment,
                $courier,
                $path,
                $notes !== '' ? $notes : null
            );
        } catch (ValidationException $e) {
            $this->discardReturnPhotoIfUnused((int) $returnShipment->id, 'seller_delivery_photo', $path);

            return $this->returnFailureFrom($e);
        } catch (\Throwable $e) {
            $this->discardReturnPhotoIfUnused((int) $returnShipment->id, 'seller_delivery_photo', $path);

            throw $e;
        }

        $this->discardReturnPhotoIfUnused((int) $returnShipment->id, 'seller_delivery_photo', $path);

        $updated->load(array_unique(array_merge($this->returnShipmentRelations(), [
            'returnRequest',
            'sellerOrder.buyer',
            'sellerOrder.items',
            'sellerOrder.sellerProfile',
            'events',
        ])));

        return response()->json([
            'status'  => 'success',
            'message' => "Return delivery confirmed for {$updated->tracking_number}. \u{20B1}" . number_format((float) $updated->courier_pay, 2) . ' was credited to your Available E-Wallet.',
            'data'    => [
                'return' => $this->enrichCourierReturn($updated, $courier),
            ],
        ]);
    }

    public function reportFailed(Request $request, int $id): JsonResponse
    {
        $courier = $request->user();
        $returnShipment = ReturnShipment::find($id);

        if (! $returnShipment || (int) $returnShipment->current_courier_id !== (int) $courier->id) {
            return $this->returnNotAssigned();
        }

        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'min:5', 'max:150'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'failure_reason.required' => 'Please explain why the parcel could not be handed over to the seller.',
        ]);

        try {
            $updated = $this->returnShipmentService->courierReportDeliveryFailed(
                $returnShipment,
                $courier,
                $validated['failure_reason'],
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

        return response()->json([
            'status'  => 'success',
            'message' => "Failed return delivery attempt recorded for {$updated->tracking_number}. The parcel was returned to the sorting hub queue for re-dispatch.",
            'data'    => [
                'return' => $this->enrichCourierReturn($updated, $courier),
            ],
        ]);
    }

    protected function enrichCourierReturn(ReturnShipment $rs, $courier): array
    {
        $base = [];
        try {
            $base = $this->formatReturnShipment($rs, 'courier');
        } catch (\Throwable $e) {
            $base = [];
        }

        $req = $rs->returnRequest;
        $order = $rs->sellerOrder;
        $sellerProfile = $order?->sellerProfile;
        $hubSnap = (array) ($rs->hub_address_snapshot ?? []);
        $sellerSnap = (array) ($rs->return_address_snapshot ?? ($order?->pickup_address_snapshot ?? []));

        $hubCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($hubSnap, $courier?->assigned_hub);
        $sellerCoords = CourierMobileSchemaService::resolveCoordinatesForAddress($sellerSnap, $sellerProfile);

        $estimatedPay = (float) ($rs->courier_pay ?? 0);
        if ($estimatedPay <= 0) {
            $calc = $this->commissionCalculator->calculateDistanceScaledDriverPay(
                'pickup',
                $hubSnap,
                $sellerSnap,
                (float) ($rs->total_weight ?: 0.5)
            );
            $estimatedPay = round((float) ($calc['total_pay'] ?? 15.00), 2);
        }

        $sellerAddress = $sellerSnap['full_address']
            ?? $sellerProfile?->full_address
            ?? collect([
                $sellerSnap['house_details'] ?? $sellerProfile?->house_details,
                $sellerSnap['street'] ?? $sellerProfile?->street,
                $sellerSnap['barangay'] ?? $sellerProfile?->barangay,
                $sellerSnap['municipality'] ?? $sellerProfile?->municipality,
                $sellerSnap['province'] ?? $sellerProfile?->province,
            ])->filter()->implode(', ');

        $hubAddress = $hubSnap['full_address'] ?? ($courier?->assigned_hub?->full_address ?? collect([
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
            'seller_order_number'     => (string) ($order?->seller_order_number ?? 'SO-N/A'),
            'status'                  => (string) $rs->status,
            'status_label'            => strtoupper(str_replace('_', ' ', (string) $rs->status)),
            'total_weight'            => round((float) ($rs->total_weight ?? 0.5), 3),
            'total_weight_formatted'  => number_format((float) ($rs->total_weight ?? 0.5), 2) . ' kg',
            'courier_pay'             => round($estimatedPay, 2),
            'is_paid'                 => $rs->courier_paid_at !== null,
            'courier_paid_at'         => $rs->courier_paid_at?->toIso8601String(),
            'delivery_attempts_count' => (int) ($rs->delivery_attempts_count ?? 0),
            'max_attempts'            => ReturnShipment::MAX_DELIVERY_ATTEMPTS,
            'delivery_failure_reason' => $rs->delivery_failure_reason ?? null,
            'reason_category'         => (string) ($req?->reason_category ?? 'Return & Refund'),
            'reason'                  => (string) ($req?->reason ?? ''),
            'seller_delivery_photo'   => $rs->seller_delivery_photo ? asset('storage/' . ltrim($rs->seller_delivery_photo, '/')) : null,
            'courier_assigned_at'     => $rs->courier_assigned_at?->toIso8601String(),
            'picked_up_from_hub_at'   => $rs->picked_up_from_hub_at?->toIso8601String(),
            'delivered_to_seller_at'  => $rs->delivered_to_seller_at?->toIso8601String(),
            'hub'                     => [
                'hub_name'     => (string) ($hubSnap['hub_name'] ?? ($courier?->assigned_hub?->hub_name ?? 'Sorting Hub')),
                'contact_no'   => (string) ($hubSnap['contact_no'] ?? ($courier?->assigned_hub?->contact_no ?? '')),
                'barangay'     => (string) ($hubSnap['barangay'] ?? ($courier?->assigned_hub?->barangay ?? '')),
                'municipality' => (string) ($hubSnap['municipality'] ?? ($courier?->assigned_hub?->municipality ?? '')),
                'province'     => (string) ($hubSnap['province'] ?? ($courier?->assigned_hub?->province ?? '')),
                'full_address' => (string) $hubAddress,
                'latitude'     => (float) $hubCoords['latitude'],
                'longitude'    => (float) $hubCoords['longitude'],
            ],
            'seller'                  => [
                'business_name' => (string) ($sellerProfile?->business_name ?? ($sellerSnap['business_name'] ?? 'Seller Store')),
                'contact_no'    => (string) ($sellerSnap['contact_no'] ?? ($sellerProfile?->contact_no ?? '')),
                'barangay'      => (string) ($sellerSnap['barangay'] ?? ($sellerProfile?->barangay ?? '')),
                'municipality'  => (string) ($sellerSnap['municipality'] ?? ($sellerProfile?->municipality ?? '')),
                'province'      => (string) ($sellerSnap['province'] ?? ($sellerProfile?->province ?? '')),
                'full_address'  => (string) ($sellerAddress ?: 'Seller Facility Address'),
                'latitude'      => (float) $sellerCoords['latitude'],
                'longitude'     => (float) $sellerCoords['longitude'],
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