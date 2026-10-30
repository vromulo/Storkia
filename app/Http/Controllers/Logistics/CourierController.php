<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\AddressService;
use App\Services\InternalStorkiaEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CourierController extends Controller
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
            abort(403, 'Your logistics hub must be approved to manage couriers.');
        }

        $isBranchAccount = $user->isBranchLogisticsAccount();
        $branchHub = $isBranchAccount ? $user->branchLogisticsHub : null;

        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $baseQuery = Courier::where('logistics_profile_id', $profile->id);

        if ($isBranchAccount && $branchHub) {
            $baseQuery->whereHas('currentHubAssignment', function ($q) use ($branchHub) {
                $q->where('logistics_hub_id', $branchHub->id)->where('is_current', true);
            });
        }

        $query = (clone $baseQuery)->with(['currentHubAssignment.hub']);

        if (in_array($status, ['active', 'on_delivery', 'off_duty', 'suspended'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('contact_no', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_type', 'like', "%{$search}%");
            });
        }

        $couriers = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        $counts = [
            'all'         => (clone $baseQuery)->count(),
            'active'      => (clone $baseQuery)->where('status', 'active')->count(),
            'on_delivery' => (clone $baseQuery)->where('status', 'on_delivery')->count(),
            'off_duty'    => (clone $baseQuery)->where('status', 'off_duty')->count(),
            'suspended'   => (clone $baseQuery)->where('status', 'suspended')->count(),
        ];

        return view('logistics.couriers.index', compact(
            'couriers',
            'counts',
            'status',
            'search',
            'profile',
            'isBranchAccount',
            'branchHub'
        ));
    }

    public function create(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403, 'Your logistics hub must be approved to add couriers.');
        }

        $profile->ensureMainHubExists();

        $isBranchAccount = $user->isBranchLogisticsAccount();
        $lockedBranchHub = $isBranchAccount ? $user->branchLogisticsHub : null;

        if ($isBranchAccount && $lockedBranchHub) {
            // Branch Hub accounts can ONLY register couriers to their own Branch Hub (read-only)
            $hubs = collect([$lockedBranchHub]);
        } else {
            // Main Hub accounts can choose among their own company hubs (Main Hub + their Branch Hubs)
            $hubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
                ->where('status', 'active')
                ->orderByDesc('is_main_hub')
                ->orderBy('municipality')
                ->get();
        }

        return view('logistics.couriers.create-courier', compact(
            'profile',
            'hubs',
            'isBranchAccount',
            'lockedBranchHub'
        ));
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $isBranchAccount = $user->isBranchLogisticsAccount();
        $lockedBranchHub = $isBranchAccount ? $user->branchLogisticsHub : null;

        if ($isBranchAccount && $lockedBranchHub) {
            $request->merge(['logistics_hub_id' => $lockedBranchHub->id]);
        }

        $normalizedPrefix = InternalStorkiaEmailService::normalizePrefix($request->input('email_prefix'));
        $request->merge(['email_prefix' => $normalizedPrefix]);

        $validated = $request->validate([
            'email_prefix'          => InternalStorkiaEmailService::prefixValidationRules(),
            'logistics_hub_id'      => ['required', 'integer', 'exists:logistics_hubs,id'],
            'first_name'            => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'             => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'birthday'              => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'contact_no'            => ['required', 'regex:/^9\d{9}$/'],
            'license_no'            => ['required', 'string', 'size:13', 'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/'],
            'license_type'          => ['required', 'in:Professional'],
            'dl_codes'              => ['required', 'array', 'min:1'],
            'license_expiry_date'   => ['required', 'date', 'after_or_equal:' . now()->format('Y-m-d')],
            'vehicle_type'          => ['required', 'string'],
            'plate_number'          => ['required', 'string', 'min:6', 'max:15'],
            'load_capacity'         => ['required', 'integer', 'min:50', 'max:20000'],
            'drivers_license'       => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'vehicle_or_cr'         => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'nbi_clearance'         => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password'              => ['required', 'string', 'min:8', 'max:64'],
            'password_confirmation' => ['required', 'same:password'],
        ], array_merge(
            InternalStorkiaEmailService::prefixValidationMessages('email_prefix'),
            [
                'logistics_hub_id.required' => 'Please select a company hub for the courier.',
                'license_no.size'           => 'Driver\'s license number must follow format A00-00-000000.',
                'license_no.regex'          => 'Driver\'s license number must follow format A00-00-000000.',
            ]
        ));

        // Strictly verify the chosen hub belongs to THIS Logistics company (and matches Branch Hub if branch account)
        $hubQuery = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active');

        if ($isBranchAccount && $lockedBranchHub) {
            $hubQuery->where('id', $lockedBranchHub->id);
        }

        /** @var LogisticsHub|null $targetHub */
        $targetHub = $hubQuery->find($validated['logistics_hub_id']);

        if (! $targetHub) {
            throw ValidationException::withMessages([
                'logistics_hub_id' => 'You can only assign couriers to an active hub belonging to your own logistics account.',
            ]);
        }

        $fullEmail = InternalStorkiaEmailService::buildEmail($validated['email_prefix']);

        if (InternalStorkiaEmailService::emailExists($fullEmail)) {
            throw ValidationException::withMessages([
                'email_prefix' => "The email {$fullEmail} is already registered.",
            ]);
        }

        $formattedContact = '+63' . ltrim(preg_replace('/\D/', '', $validated['contact_no']), '0');

        DB::transaction(function () use ($request, $profile, $targetHub, $user, $validated, $fullEmail, $formattedContact) {
            $licensePath = $request->file('drivers_license')->store('courier_documents/licenses', 'public');
            $orCrPath = $request->file('vehicle_or_cr')->store('courier_documents/or_cr', 'public');
            $nbiPath = $request->file('nbi_clearance')->store('courier_documents/nbi_clearances', 'public');

            $courier = Courier::create([
                'logistics_profile_id' => $profile->id,
                'first_name'           => ucwords(strtolower(trim($validated['first_name']))),
                'last_name'            => ucwords(strtolower(trim($validated['last_name']))),
                'birthday'             => $validated['birthday'],
                'email'                => $fullEmail,
                'email_verified_at'    => now(),
                'contact_no'           => $formattedContact,
                'license_no'           => strtoupper(trim($validated['license_no'])),
                'license_type'         => $validated['license_type'],
                'dl_codes'             => array_values($validated['dl_codes']),
                'license_expiry_date'  => $validated['license_expiry_date'],
                'vehicle_type'         => $validated['vehicle_type'],
                'plate_number'         => strtoupper(trim($validated['plate_number'])),
                'load_capacity'        => (int) $validated['load_capacity'],
                'home_hub'             => $targetHub->hub_name,
                'license_path'         => $licensePath,
                'or_cr_path'           => $orCrPath,
                'nbi_clearance_path'   => $nbiPath,
                'password'             => Hash::make($validated['password']),
                'status'               => 'active',
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
                'remarks'              => "Assigned to {$targetHub->hub_name} ({$targetHub->municipality})",
            ]);
        });

        return redirect()
            ->route('logistics.couriers.index')
            ->with('success', "Courier account {$fullEmail} created and assigned to {$targetHub->hub_name} ({$targetHub->municipality})!");
    }

    public function updateStatus(Request $request, Courier $courier)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile || (int) $courier->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        if ($user->isBranchLogisticsAccount() && (int) $courier->assigned_hub?->id !== (int) $user->branchLogisticsHub?->id) {
            abort(403, 'Branch accounts can only manage couriers assigned to their own Branch Hub.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:active,on_delivery,off_duty,suspended'],
        ]);

        $courier->update(['status' => $validated['status']]);

        return back()->with('success', "Status for courier {$courier->full_name} has been updated.");
    }

    public function document(Request $request, Courier $courier, string $type)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile || (int) $courier->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        if ($user->isBranchLogisticsAccount() && (int) $courier->assigned_hub?->id !== (int) $user->branchLogisticsHub?->id) {
            abort(403);
        }

        $path = match ($type) {
            'license' => $courier->license_path,
            'or_cr'   => $courier->or_cr_path,
            'nbi'     => $courier->nbi_clearance_path,
            default   => null,
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Document not found.');
        }

        return Storage::disk('public')->response($path);
    }
}