<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\ReturnShipment;
use App\Services\ReturnShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Rider side of Return & Refund (first leg of the parcel's trip back):
 *
 *   GET  /rider/returns                              -> my return pickups
 *   POST /rider/returns/{returnShipment}/confirm-pickup  (photo required)
 *   POST /rider/returns/{returnShipment}/report-failed
 *
 * The Rider is paid for this leg by the system when the sorting hub confirms the parcel
 * (ReturnShipmentService::receiveAtHub), not here.
 */
class ReturnPickupController extends Controller
{
    /** Reasons a Rider can pick when the parcel could not be collected. */
    public const FAILURE_REASONS = [
        'Buyer not at the address',
        'Buyer unreachable by phone',
        'Buyer refused to hand over the parcel',
        'Parcel not ready or not packed',
        'Wrong or incomplete address',
        'Unsafe location or bad weather',
        'Other',
    ];

    public function __construct(protected ReturnShipmentService $returnShipmentService)
    {
    }

    public function index(): View
    {
        $rider = Auth::guard('rider')->user();
        $rider->loadMissing('logisticsProfile');

        $with = ['returnRequest', 'sellerOrder.order', 'sellerOrder.items', 'sellerOrder.sellerProfile'];

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

        $completedStatuses = [
            ReturnShipment::STATUS_RECEIVED_AT_HUB,
            ReturnShipment::STATUS_COURIER_ASSIGNED,
            ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY,
            ReturnShipment::STATUS_DELIVERED_TO_SELLER,
        ];

        $completedQuery = ReturnShipment::where('current_rider_id', $rider->id)
            ->whereIn('status', $completedStatuses);

        $completedCount = (clone $completedQuery)->count();

        $completed = (clone $completedQuery)
            ->with(['returnRequest', 'sellerOrder'])
            ->orderByDesc('arrived_at_hub_at')
            ->limit(15)
            ->get();

        $totalEarned = (float) ReturnShipment::where('current_rider_id', $rider->id)
            ->whereNotNull('rider_paid_at')
            ->sum('rider_pay');

        return view('rider.returns', [
            'rider'          => $rider,
            'toCollect'      => $toCollect,
            'toHub'          => $toHub,
            'completed'      => $completed,
            'completedCount' => $completedCount,
            'totalEarned'    => $totalEarned,
            'failureReasons' => self::FAILURE_REASONS,
            'maxAttempts'    => ReturnShipment::MAX_PICKUP_ATTEMPTS,
        ]);
    }

    public function confirmPickup(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        $rider = Auth::guard('rider')->user();

        if ((int) $returnShipment->current_rider_id !== (int) $rider->id) {
            abort(403, 'This return is not assigned to you.');
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

        $file = $request->file('pickup_photo');
        $path = 'return_pickups/rp_' . $returnShipment->id . '_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1600, 1600)->toWebp(82);
            Storage::disk('public')->put($path, (string) $encoded);
        } catch (\Throwable $e) {
            $path = $file->store('return_pickups', 'public');
        }

        try {
            $updated = $this->returnShipmentService->riderConfirmPickup(
                $returnShipment,
                $rider,
                $path,
                $validated['notes'] ?? null
            );
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        return redirect()
            ->route('rider.returns.index')
            ->with('success', "Pickup confirmed for {$updated->tracking_number}. Bring the parcel to the sorting hub. You are paid for this leg once the hub confirms it.");
    }

    public function reportFailed(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        $rider = Auth::guard('rider')->user();

        if ((int) $returnShipment->current_rider_id !== (int) $rider->id) {
            abort(403, 'This return is not assigned to you.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:' . implode(',', self::FAILURE_REASONS)],
            'notes'  => ['nullable', 'required_if:reason,Other', 'string', 'min:5', 'max:300'],
        ], [
            'reason.required'    => 'Please choose why the parcel could not be collected.',
            'reason.in'          => 'Please choose one of the listed reasons.',
            'notes.required_if'  => 'Please describe what happened when you choose "Other".',
            'notes.min'          => 'Please describe what happened (at least 5 characters).',
        ]);

        $updated = $this->returnShipmentService->riderReportPickupFailed(
            $returnShipment,
            $rider,
            $validated['reason'],
            $validated['notes'] ?? null
        );

        if ($updated->status === ReturnShipment::STATUS_CANCELLED) {
            return redirect()
                ->route('rider.returns.index')
                ->with('success', "Pickup failure reported for {$updated->tracking_number}. This was the last allowed attempt, so the return request was closed.");
        }

        return redirect()
            ->route('rider.returns.index')
            ->with('success', "Pickup failure reported for {$updated->tracking_number}. Logistics will assign a rider for another attempt.");
    }
}