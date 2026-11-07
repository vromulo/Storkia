<?php

namespace App\Http\Controllers\Courier;

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
 * Courier side of Return & Refund (last leg of the parcel's trip back: Sorting Hub -> Seller):
 *
 *   GET  /courier/returns                                   -> my return deliveries
 *   POST /courier/returns/{returnShipment}/start-delivery   (parcel collected at the hub)
 *   POST /courier/returns/{returnShipment}/confirm-delivered (photo required)
 *
 * The Courier is paid for this leg by the system the moment delivery is confirmed
 * (ReturnShipmentService::courierConfirmDelivered). After that the seller is asked to
 * confirm receipt, and the refund continues from there.
 */
class ReturnDeliveryController extends Controller
{
    public function __construct(protected ReturnShipmentService $returnShipmentService)
    {
    }

    public function index(): View
    {
        $courier = Auth::guard('courier')->user();
        $courier->loadMissing('logisticsProfile');

        $with = ['returnRequest', 'sellerOrder.order', 'sellerOrder.items', 'sellerOrder.sellerProfile'];

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
            ->with(['returnRequest', 'sellerOrder.sellerProfile'])
            ->orderByDesc('delivered_to_seller_at')
            ->limit(15)
            ->get();

        $totalEarned = (float) ReturnShipment::where('current_courier_id', $courier->id)
            ->whereNotNull('courier_paid_at')
            ->sum('courier_pay');

        return view('courier.returns', [
            'courier'        => $courier,
            'toCollect'      => $toCollect,
            'outForDelivery' => $outForDelivery,
            'completed'      => $completed,
            'completedCount' => $completedCount,
            'totalEarned'    => $totalEarned,
        ]);
    }

    public function startDelivery(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        $courier = Auth::guard('courier')->user();

        if ((int) $returnShipment->current_courier_id !== (int) $courier->id) {
            abort(403, 'This return is not assigned to you.');
        }

        $updated = $this->returnShipmentService->courierStartDelivery($returnShipment, $courier);

        return redirect()
            ->route('courier.returns.index')
            ->with('success', "Delivery started for {$updated->tracking_number}. Take the parcel to the seller and confirm with a photo once it is handed over.");
    }

    public function confirmDelivered(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        $courier = Auth::guard('courier')->user();

        if ((int) $returnShipment->current_courier_id !== (int) $courier->id) {
            abort(403, 'This return is not assigned to you.');
        }

        $validated = $request->validate([
            'delivery_photo'  => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'recipient_name'  => ['nullable', 'string', 'max:120'],
            'parcel_handed'   => ['accepted'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ], [
            'delivery_photo.required'  => 'A clear photo of the parcel handed to the seller (JPG, PNG, or WEBP, max 5 MB) is required.',
            'delivery_photo.mimetypes' => 'The delivery photo must be a valid JPG, PNG, or WEBP image.',
            'delivery_photo.max'       => 'The delivery photo must not exceed 5 MB.',
            'parcel_handed.accepted'   => 'Please confirm that you handed the parcel to the seller.',
        ]);

        $file = $request->file('delivery_photo');
        $path = 'return_deliveries/rd_' . $returnShipment->id . '_' . uniqid('', true) . '.webp';

        try {
            $manager = new ImageManager(new Driver());
            $encoded = $manager->read($file->getRealPath())->scaleDown(1600, 1600)->toWebp(82);
            Storage::disk('public')->put($path, (string) $encoded);
        } catch (\Throwable $e) {
            $path = $file->store('return_deliveries', 'public');
        }

        $recipient = trim((string) ($validated['recipient_name'] ?? ''));
        $notes = trim(($recipient !== '' ? "Received by {$recipient}. " : '') . trim((string) ($validated['notes'] ?? '')));

        try {
            $updated = $this->returnShipmentService->courierConfirmDelivered(
                $returnShipment,
                $courier,
                $path,
                $notes !== '' ? $notes : null
            );
        } catch (\Throwable $e) {
            $this->discardPhotoIfUnused($returnShipment->id, $path);

            throw $e;
        }

        // Repeat submit of an already-delivered return: the new photo was not used.
        $this->discardPhotoIfUnused($returnShipment->id, $path);

        return redirect()
            ->route('courier.returns.index')
            ->with('success', "Delivery confirmed for {$updated->tracking_number}. ₱" . number_format((float) $updated->courier_pay, 2) . ' was added to your E-Wallet for this return leg.');
    }

    /**
     * Keep the uploaded photo only when the shipment really points to it.
     * (If the delivery committed but a later step failed, the photo must stay.)
     */
    protected function discardPhotoIfUnused(int $returnShipmentId, string $path): void
    {
        $stored = ReturnShipment::whereKey($returnShipmentId)->value('seller_delivery_photo');

        if ($stored !== $path) {
            Storage::disk('public')->delete($path);
        }
    }
}