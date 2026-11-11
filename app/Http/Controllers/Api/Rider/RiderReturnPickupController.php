<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\Concerns\FormatsReturnShipments;
use App\Http\Controllers\Api\Concerns\StoresReturnPhotos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\ReturnPickupController as WebReturnPickupController;
use App\Models\ReturnShipment;
use App\Services\ReturnShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderReturnPickupController extends Controller
{
    use FormatsReturnShipments, StoresReturnPhotos;

    protected const COMPLETED_LIMIT = 15;

    public function __construct(protected ReturnShipmentService $returnShipmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $rider = $request->user();
        $with = $this->returnShipmentRelations();

        $toCollect = ReturnShipment::with($with)
            ->where('current_rider_id', $rider->id)
            ->where('status', ReturnShipment::STATUS_RIDER_ASSIGNED)
            ->orderBy('rider_assigned_at')
            ->get();

        $toHub = ReturnShipment::with($with)
            ->where('current_rider_id', $rider->id)
            ->where('status', ReturnShipment::STATUS_PICKED_UP_FROM_BUYER)
            ->orderBy('picked_up_from_buyer_at')
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
                'to_collect'      => $toCollect->map(fn (ReturnShipment $rs) => $this->formatReturnShipment($rs, 'rider'))->values()->all(),
                'to_hub'          => $toHub->map(fn (ReturnShipment $rs) => $this->formatReturnShipment($rs, 'rider'))->values()->all(),
                'completed'       => $completed->map(fn (ReturnShipment $rs) => $this->formatReturnShipment($rs, 'rider'))->values()->all(),
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

        $updated->load($this->returnShipmentRelations());

        return response()->json([
            'status'  => 'success',
            'message' => "Pickup confirmed for {$updated->tracking_number}. Bring the parcel to the sorting hub. You are paid for this leg once the hub confirms it.",
            'data'    => [
                'return' => $this->formatReturnShipment($updated, 'rider'),
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

        $updated->load($this->returnShipmentRelations());

        $closed = $updated->status === ReturnShipment::STATUS_CANCELLED;

        $message = $closed
            ? "Pickup failure reported for {$updated->tracking_number}. This was the last allowed attempt, so the return request was closed."
            : "Pickup failure reported for {$updated->tracking_number}. Logistics will assign a rider for another attempt.";

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => [
                'closed' => $closed,
                'return' => $this->formatReturnShipment($updated, 'rider'),
            ],
        ]);
    }
}