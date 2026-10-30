<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Models\RiderBarangayCoverage;
use App\Models\User;
use App\Services\AddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeManagementController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $profile->ensureMainHubExists();

        $isMainAccount = $user->isMainLogisticsAccount();
        $activeBranchHub = $user->isBranchLogisticsAccount() ? $user->branchLogisticsHub : null;

        $hubsQuery = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality');

        if (! $isMainAccount && $activeBranchHub) {
            $hubsQuery->where('id', $activeBranchHub->id);
        }

        $companyHubs = $hubsQuery->get();
        $eligibleHubIds = $companyHubs->pluck('id')->map(fn ($id) => (int) $id)->all();

        $selectedHubId = $request->query('hub_id');
        if (! $isMainAccount && $activeBranchHub) {
            // Branch Hub accounts are strictly locked to viewing only their own branch's registered employees
            $selectedHubId = (string) $activeBranchHub->id;
        } elseif ($selectedHubId !== null && $selectedHubId !== '' && ! in_array((int) $selectedHubId, $eligibleHubIds, true)) {
            $selectedHubId = null;
        }

        $tab = $request->query('tab', 'all');
        $search = trim((string) $request->query('search', ''));

        $couriersQuery = Courier::with([
            'currentHubAssignment.hub',
            'hubAssignments.hub',
            'pickupAssignments' => fn ($q) => $q->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub']),
        ])->where('logistics_profile_id', $profile->id);

        $ridersQuery = Rider::with([
            'currentHubAssignment.hub',
            'hubAssignments.hub',
            'barangayCoverages',
            'deliveryAssignments' => fn ($q) => $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']),
        ])->where('logistics_profile_id', $profile->id);

        if ($selectedHubId) {
            $couriersQuery->whereHas('currentHubAssignment', fn ($q) => $q->where('logistics_hub_id', (int) $selectedHubId)->where('is_current', true));
            $ridersQuery->whereHas('currentHubAssignment', fn ($q) => $q->where('logistics_hub_id', (int) $selectedHubId)->where('is_current', true));
        }

        if ($search !== '') {
            $couriersQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%")
                    ->orWhere('home_hub', 'like', "%{$search}%");
            });

            $ridersQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        // Strict First-In, First-Out (FIFO) ordering
        $couriers = $couriersQuery->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();
        $riders = $ridersQuery->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

        $hubBarangaysMap = [];
        foreach ($companyHubs as $h) {
            $hubBarangaysMap[$h->id] = $this->addressService->getBarangaysForLocation(
                provinceName: (string) $h->province,
                municipalityName: (string) $h->municipality,
                municipalityCode: $h->municipality_code
            );
        }

        return view('logistics.employees.index', compact(
            'profile',
            'isMainAccount',
            'activeBranchHub',
            'companyHubs',
            'selectedHubId',
            'tab',
            'search',
            'couriers',
            'riders',
            'hubBarangaysMap'
        ));
    }

    public function reassignCourier(Request $request, Courier $courier)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $courier->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'Unauthorized cross-company courier assignment.');
        }

        if ($user->isBranchLogisticsAccount()) {
            $branchHubId = (int) $user->branchLogisticsHub?->id;
            $currentHubId = (int) $courier->assigned_hub?->id;
            if ($currentHubId !== $branchHubId) {
                abort(403, 'Branch accounts can only manage employees assigned to their own hub.');
            }
        }

        $validated = $request->validate([
            'logistics_hub_id' => ['required', 'integer', 'exists:logistics_hubs,id'],
            'remarks'          => ['nullable', 'string', 'max:255'],
        ]);

        /** @var LogisticsHub $targetHub */
        $targetHub = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->findOrFail($validated['logistics_hub_id']);

        if ($user->isBranchLogisticsAccount() && (int) $targetHub->id !== (int) $user->branchLogisticsHub?->id) {
            abort(403, 'Branch accounts cannot reassign employees to other hubs.');
        }

        $hasActivePickup = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        if ($hasActivePickup && (int) $courier->assigned_hub?->id !== (int) $targetHub->id) {
            return back()->withErrors([
                'courier' => "Courier {$courier->full_name} currently has an active first-mile pickup in progress and cannot be moved to another hub until hub arrival is verified.",
            ]);
        }

        DB::transaction(function () use ($courier, $profile, $targetHub, $user, $validated) {
            LogisticsEmployeeHubAssignment::where('courier_id', $courier->id)
                ->where('is_current', true)
                ->update([
                    'is_current'    => false,
                    'unassigned_at' => now(),
                ]);

            LogisticsEmployeeHubAssignment::create([
                'logistics_profile_id' => $profile->id,
                'logistics_hub_id'     => $targetHub->id,
                'employee_type'        => 'courier',
                'courier_id'           => $courier->id,
                'municipality'         => $targetHub->municipality,
                'is_current'           => true,
                'assigned_by'          => $user->id,
                'assigned_at'          => now(),
                'remarks'              => $validated['remarks'] ?: "Assigned to {$targetHub->hub_name} ({$targetHub->municipality})",
            ]);

            $courier->update([
                'home_hub' => $targetHub->hub_name,
            ]);
        });

        return back()->with('success', "Courier {$courier->full_name} assigned to {$targetHub->hub_name} ({$targetHub->municipality}).");
    }

    public function reassignRider(Request $request, Rider $rider)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403, 'Unauthorized cross-company rider assignment.');
        }

        if ($user->isBranchLogisticsAccount()) {
            $branchHubId = (int) $user->branchLogisticsHub?->id;
            $currentHubId = (int) $rider->assigned_hub?->id;
            if ($currentHubId !== $branchHubId) {
                abort(403, 'Branch accounts can only manage riders assigned to their own hub.');
            }
        }

        $validated = $request->validate([
            'logistics_hub_id' => ['required', 'integer', 'exists:logistics_hubs,id'],
            'barangays'        => ['required', 'array', 'min:1'],
            'barangays.*'      => ['required', 'string', 'max:120'],
            'remarks'          => ['nullable', 'string', 'max:255'],
        ], [
            'barangays.required' => 'Please select at least one Barangay within the hub municipality.',
            'barangays.min'      => 'Please select at least one Barangay within the hub municipality.',
        ]);

        /** @var LogisticsHub $targetHub */
        $targetHub = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->findOrFail($validated['logistics_hub_id']);

        if ($user->isBranchLogisticsAccount() && (int) $targetHub->id !== (int) $user->branchLogisticsHub?->id) {
            abort(403, 'Branch accounts cannot reassign riders to another hub.');
        }

        $hasActiveDelivery = DeliveryAssignment::where('rider_id', $rider->id)
            ->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery'])
            ->exists();

        if ($hasActiveDelivery && (int) $rider->assigned_hub?->id !== (int) $targetHub->id) {
            return back()->withErrors([
                'rider' => "Rider {$rider->full_name} has an active delivery task in progress and cannot change hubs until completed.",
            ]);
        }

        $eligibleBarangays = collect($this->addressService->getBarangaysForLocation(
            provinceName: (string) $targetHub->province,
            municipalityName: (string) $targetHub->municipality,
            municipalityCode: $targetHub->municipality_code
        ));

        $validBarangayMap = [];
        foreach ($eligibleBarangays as $b) {
            $bName = trim((string) ($b['name'] ?? ''));
            if ($bName !== '') {
                $validBarangayMap[strtolower($bName)] = [
                    'name' => $bName,
                    'code' => (string) ($b['code'] ?? ''),
                ];
            }
        }

        $resolvedSelections = [];
        foreach ($validated['barangays'] as $submittedBrgy) {
            $key = strtolower(trim((string) $submittedBrgy));
            if (! isset($validBarangayMap[$key])) {
                throw ValidationException::withMessages([
                    'barangays' => "Barangay \"{$submittedBrgy}\" does not belong to {$targetHub->municipality}.",
                ]);
            }
            $resolvedSelections[$key] = $validBarangayMap[$key];
        }

        DB::transaction(function () use ($rider, $profile, $targetHub, $user, $validated, $resolvedSelections) {
            LogisticsEmployeeHubAssignment::where('rider_id', $rider->id)
                ->where('is_current', true)
                ->update([
                    'is_current'    => false,
                    'unassigned_at' => now(),
                ]);

            LogisticsEmployeeHubAssignment::create([
                'logistics_profile_id' => $profile->id,
                'logistics_hub_id'     => $targetHub->id,
                'employee_type'        => 'rider',
                'rider_id'             => $rider->id,
                'municipality'         => $targetHub->municipality,
                'is_current'           => true,
                'assigned_by'          => $user->id,
                'assigned_at'          => now(),
                'remarks'              => $validated['remarks'] ?: "Assigned to {$targetHub->hub_name} ({$targetHub->municipality})",
            ]);

            RiderBarangayCoverage::where('rider_id', $rider->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            foreach ($resolvedSelections as $item) {
                RiderBarangayCoverage::create([
                    'logistics_profile_id' => $profile->id,
                    'logistics_hub_id'     => $targetHub->id,
                    'rider_id'             => $rider->id,
                    'municipality_code'    => $targetHub->municipality_code,
                    'municipality'         => $targetHub->municipality,
                    'barangay_code'        => $item['code'] ?: null,
                    'barangay_name'        => $item['name'],
                    'is_active'            => true,
                    'assigned_by'          => $user->id,
                ]);
            }
        });

        return back()->with(
            'success',
            "Rider {$rider->full_name} assigned to {$targetHub->hub_name} covering: " . implode(', ', array_column($resolvedSelections, 'name')) . '.'
        );
    }

    public function toggleEmployeeStatus(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_type' => ['required', 'in:courier,rider'],
            'employee_id'   => ['required', 'integer'],
            'status'        => ['required', 'string'],
        ]);

        if ($validated['employee_type'] === 'courier') {
            /** @var Courier $courier */
            $courier = Courier::where('logistics_profile_id', $profile->id)->findOrFail($validated['employee_id']);

            if ($user->isBranchLogisticsAccount() && (int) $courier->assigned_hub?->id !== (int) $user->branchLogisticsHub?->id) {
                abort(403);
            }

            if (! in_array($validated['status'], ['active', 'off_duty', 'suspended'], true)) {
                abort(422, 'Invalid courier status.');
            }

            $courier->update(['status' => $validated['status']]);

            return back()->with('success', "Courier {$courier->full_name} status updated to {$validated['status']}.");
        }

        /** @var Rider $rider */
        $rider = Rider::where('logistics_profile_id', $profile->id)->findOrFail($validated['employee_id']);

        if ($user->isBranchLogisticsAccount() && (int) $rider->assigned_hub?->id !== (int) $user->branchLogisticsHub?->id) {
            abort(403);
        }

        if (! in_array($validated['status'], ['approved', 'rejected'], true)) {
            abort(422, 'Invalid rider status.');
        }

        $rider->update([
            'status'      => $validated['status'],
            'approved_at' => $validated['status'] === 'approved' ? now() : $rider->approved_at,
            'rejected_at' => $validated['status'] === 'rejected' ? now() : null,
        ]);

        $label = $validated['status'] === 'approved' ? 'Activated' : 'Deactivated';

        return back()->with('success', "Rider {$rider->full_name} account {$label}.");
    }
}