<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\LogisticsProfile;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\User;
use App\Services\ReturnShipmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnShipmentController extends Controller
{
    protected const TAB_STATUSES = [
        'needs_rider'  => [ReturnShipment::STATUS_AWAITING_RIDER, ReturnShipment::STATUS_PICKUP_FAILED],
        'rider_pickup' => [ReturnShipment::STATUS_RIDER_ASSIGNED],
        'to_hub'       => [ReturnShipment::STATUS_PICKED_UP_FROM_BUYER],
        'at_hub'       => [ReturnShipment::STATUS_RECEIVED_AT_HUB],
        'to_seller'    => [ReturnShipment::STATUS_COURIER_ASSIGNED, ReturnShipment::STATUS_OUT_FOR_RETURN_DELIVERY],
        'delivered'    => [ReturnShipment::STATUS_DELIVERED_TO_SELLER],
        'cancelled'    => [ReturnShipment::STATUS_CANCELLED],
    ];

    public function __construct(protected ReturnShipmentService $returnShipmentService)
    {
    }

    public function index(Request $request): View
    {
        [$user, $profile] = $this->resolveProfile($request);
        $activeBranchHub = $user->isBranchLogisticsAccount() ? $user->branchLogisticsHub : null;

        $statusTab = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = $this->scopedQuery((int) $profile->id, $activeBranchHub?->id);

        $counts = ['all' => (clone $baseQuery)->count()];
        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = (clone $baseQuery)->whereIn('status', $statuses)->count();
        }

        $query = (clone $baseQuery)->with([
            'returnRequest',
            'sellerOrder.buyer',
            'sellerOrder.sellerProfile',
            'rider',
            'courier',
        ]);

        if (isset(self::TAB_STATUSES[$statusTab])) {
            $query->whereIn('status', self::TAB_STATUSES[$statusTab]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('returnRequest', fn ($rq) => $rq->where('request_number', 'like', "%{$search}%"))
                    ->orWhereHas('sellerOrder', fn ($sq) => $sq->where('seller_order_number', 'like', "%{$search}%"))
                    ->orWhereHas('sellerOrder.sellerProfile', fn ($sp) => $sp->where('business_name', 'like', "%{$search}%"));
            });
        }

        $returnShipments = $query
            ->orderByRaw(
                "CASE WHEN status IN ('delivered_to_seller', 'cancelled') THEN 1 ELSE 0 END ASC"
            )
            ->orderByRaw(
                "CASE WHEN status NOT IN ('delivered_to_seller', 'cancelled') THEN created_at END ASC"
            )
            ->orderByRaw(
                "CASE WHEN status NOT IN ('delivered_to_seller', 'cancelled') THEN id END ASC"
            )
            ->orderByRaw(
                "CASE WHEN status IN ('delivered_to_seller', 'cancelled') THEN COALESCE(delivered_to_seller_at, cancelled_at, updated_at) END DESC"
            )
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('logistics.returns.index', compact(
            'profile',
            'activeBranchHub',
            'returnShipments',
            'statusTab',
            'search',
            'counts'
        ));
    }

    public function show(Request $request, ReturnShipment $returnShipment): View
    {
        [$user, $profile] = $this->resolveProfile($request);
        $this->authorizeShipment($user, $profile, $returnShipment);

        $returnShipment->load([
            'returnRequest',
            'sellerOrder.order',
            'sellerOrder.items',
            'sellerOrder.buyer',
            'sellerOrder.sellerProfile',
            'rider',
            'courier',
            'events',
        ]);

        $availableRiders = collect();
        $availableCouriers = collect();

        if (in_array($returnShipment->status, [
            ReturnShipment::STATUS_AWAITING_RIDER,
            ReturnShipment::STATUS_PICKUP_FAILED,
            ReturnShipment::STATUS_RIDER_ASSIGNED,
        ], true)) {
            $riders = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
                ->where('logistics_profile_id', $profile->id)
                ->where('status', 'approved')
                ->orderBy('first_name')
                ->get();
            $availableRiders = $this->returnShipmentService->eligibleRiders($returnShipment, $riders);
        }

        if (in_array($returnShipment->status, [
            ReturnShipment::STATUS_RECEIVED_AT_HUB,
            ReturnShipment::STATUS_COURIER_ASSIGNED,
        ], true)) {
            $couriers = Courier::with('currentHubAssignment.hub')
                ->where('logistics_profile_id', $profile->id)
                ->whereIn('status', ['active', 'Active', 'on_delivery'])
                ->orderBy('first_name')
                ->get();
            $availableCouriers = $this->returnShipmentService->eligibleCouriers($returnShipment, $couriers);
        }

        return view('logistics.returns.show', [
            'profile'           => $profile,
            'returnShipment'    => $returnShipment,
            'returnRequest'     => $returnShipment->returnRequest,
            'sellerOrder'       => $returnShipment->sellerOrder,
            'availableRiders'   => $availableRiders,
            'availableCouriers' => $availableCouriers,
            'maxPickupAttempts' => ReturnShipment::MAX_PICKUP_ATTEMPTS,
        ]);
    }

    public function assignRider(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        [$user, $profile] = $this->resolveProfile($request);
        $this->authorizeShipment($user, $profile, $returnShipment);

        $validated = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:riders,id'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ], [
            'rider_id.required' => 'Please choose a rider for the return pickup.',
            'rider_id.exists'   => 'The selected rider could not be found.',
        ]);

        $updated = $this->returnShipmentService->assignRider(
            $returnShipment,
            (int) $validated['rider_id'],
            (int) $profile->id,
            (int) $user->id,
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('logistics.returns.show', $returnShipment->id)
            ->with('success', "Rider {$updated->rider?->full_name} will collect return {$updated->tracking_number} from the buyer.");
    }

    public function receiveAtHub(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        [$user, $profile] = $this->resolveProfile($request);
        $this->authorizeShipment($user, $profile, $returnShipment);

        $validated = $request->validate([
            'parcel_checked' => ['accepted'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'parcel_checked.accepted' => 'Please confirm that you physically checked the returned parcel.',
        ]);

        $updated = $this->returnShipmentService->receiveAtHub(
            $returnShipment,
            (int) $profile->id,
            (int) $user->id,
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('logistics.returns.show', $returnShipment->id)
            ->with('success', "Return {$updated->tracking_number} was received at the sorting hub. The rider was paid for the pickup leg. You can now assign a courier to bring it back to the seller.");
    }

    public function assignCourier(Request $request, ReturnShipment $returnShipment): RedirectResponse
    {
        [$user, $profile] = $this->resolveProfile($request);
        $this->authorizeShipment($user, $profile, $returnShipment);

        $validated = $request->validate([
            'courier_id' => ['required', 'integer', 'exists:couriers,id'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ], [
            'courier_id.required' => 'Please choose a courier for the return delivery.',
            'courier_id.exists'   => 'The selected courier could not be found.',
        ]);

        $updated = $this->returnShipmentService->assignCourier(
            $returnShipment,
            (int) $validated['courier_id'],
            (int) $profile->id,
            (int) $user->id,
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('logistics.returns.show', $returnShipment->id)
            ->with('success', "Courier {$updated->courier?->full_name} will bring return {$updated->tracking_number} back to the seller.");
    }

    protected function resolveProfile(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403, 'Your logistics hub must be approved to manage returns.');
        }

        return [$user, $profile];
    }

    protected function authorizeShipment(User $user, LogisticsProfile $profile, ReturnShipment $returnShipment): void
    {
        abort_unless(
            (int) $returnShipment->logistics_profile_id === (int) $profile->id,
            403,
            'This return is not assigned to your logistics company.'
        );

        if ($user->isBranchLogisticsAccount() && $user->branchLogisticsHub) {
            $hubId = $returnShipment->hub_address_snapshot['logistics_hub_id']
                ?? $returnShipment->shipment?->packages()->first()?->logistics_hub_id;

            if ($hubId && (int) $hubId !== (int) $user->branchLogisticsHub->id) {
                abort(403, 'This return is assigned to another hub branch.');
            }
        }
    }

    protected function scopedQuery(int $profileId, ?int $branchHubId = null): Builder
    {
        $query = ReturnShipment::where('logistics_profile_id', $profileId);

        if ($branchHubId) {
            $query->where(function ($q) use ($branchHubId) {
                $q->where('hub_address_snapshot->logistics_hub_id', $branchHubId)
                    ->orWhereHas('shipment.packages', fn ($pq) => $pq->where('logistics_hub_id', $branchHubId));
            });
        }

        return $query;
    }
}