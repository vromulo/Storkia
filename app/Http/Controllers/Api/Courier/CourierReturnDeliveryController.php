<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Api\Concerns\FormatsReturnShipments;
use App\Http\Controllers\Api\Concerns\StoresReturnPhotos;
use App\Http\Controllers\Controller;
use App\Models\ReturnShipment;
use App\Services\ReturnShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourierReturnDeliveryController extends Controller
{
    use FormatsReturnShipments, StoresReturnPhotos;

    protected const COMPLETED_LIMIT = 15;

    public function __construct(protected ReturnShipmentService $returnShipmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $courier = $request->user();
        $with = $this->returnShipmentRelations();

        $toCollect = ReturnShipment::with($with)
            ->where('current_courier_id', $courier->id)
            ->where('status', ReturnShipment::STATUS_COURIER_ASSIGNED)
            ->orderBy('courier_assigned_at')
            ->get();

        $outForDelivery = ReturnShipment::with($with)
            ->where('current_courier_id', $courier->id)
            ->where('status', ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY)
            ->orderBy('picked_up_from_hub_at')
            ->get();

        $completedQuery = ReturnShipment::where('current_courier_id', $courier->id)
            ->where('status', ReturnShipment::STATUS_DELIVERED_TO_SELLER);

        $completedCount = (clone $completedQuery)->count();

        $completed = (clone $completedQuery)
            ->with($with)
            ->orderByDesc('delivered_to_seller_at')
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
                'to_collect'       => $toCollect->map(fn (ReturnShipment $rs) => $this->formatReturnShipment($rs, 'courier'))->values()->all(),
                'out_for_delivery' => $outForDelivery->map(fn (ReturnShipment $rs) => $this->formatReturnShipment($rs, 'courier'))->values()->all(),
                'completed'        => $completed->map(fn (ReturnShipment $rs) => $this->formatReturnShipment($rs, 'courier'))->values()->all(),
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

        $updated->load($this->returnShipmentRelations());

        return response()->json([
            'status'  => 'success',
            'message' => "Delivery started for {$updated->tracking_number}. Take the parcel to the seller and confirm with a photo once it is handed over.",
            'data'    => [
                'return' => $this->formatReturnShipment($updated, 'courier'),
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

        $updated->load($this->returnShipmentRelations());

        return response()->json([
            'status'  => 'success',
            'message' => "Delivery confirmed for {$updated->tracking_number}. \u{20B1}" . number_format((float) $updated->courier_pay, 2) . ' was added to your E-Wallet for this return leg.',
            'data'    => [
                'return' => $this->formatReturnShipment($updated, 'courier'),
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

        $updated->load($this->returnShipmentRelations());

        return response()->json([
            'status'  => 'success',
            'message' => "Failed return delivery attempt recorded for {$updated->tracking_number}. The parcel was returned to the sorting hub queue for re-dispatch.",
            'data'    => [
                'return' => $this->formatReturnShipment($updated, 'courier'),
            ],
        ]);
    }
}