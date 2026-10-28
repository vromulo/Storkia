<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\RiderBarangayCoverage;
use App\Models\User;
use App\Services\AddressService;
use App\Services\InternalStorkiaEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RiderApplicationController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    public function index(Request $request)
    {
        $profile = $request->user()->logisticsProfile;
        if (! $profile) {
            abort(403, 'Your logistics hub must be approved to manage riders.');
        }

        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = Rider::with(['currentHubAssignment.hub', 'barangayCoverages'])
            ->where('logistics_profile_id', $profile->id);

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        $applicants = $query->latest('id')->paginate(10)->withQueryString();

        $counts = [
            'all'      => Rider::where('logistics_profile_id', $profile->id)->count(),
            'pending'  => Rider::where('logistics_profile_id', $profile->id)->where('status', 'pending')->count(),
            'approved' => Rider::where('logistics_profile_id', $profile->id)->where('status', 'approved')->count(),
            'rejected' => Rider::where('logistics_profile_id', $profile->id)->where('status', 'rejected')->count(),
        ];

        return view('logistics.riders.applications', compact('applicants', 'counts', 'status', 'search', 'profile'));
    }

    public function create(Request $request)
    {
        $profile = $request->user()->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $hubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('municipality')
            ->get();

        if ($hubs->isEmpty()) {
            $profile->ensureMainHubExists();
            $hubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)->get();
        }

        return view('logistics.riders.create-rider', compact('profile', 'hubs'));
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->logisticsProfile;
        if (! $profile) {
            abort(403);
        }

        $normalizedPrefix = InternalStorkiaEmailService::normalizePrefix($request->input('email_prefix'));
        $request->merge(['email_prefix' => $normalizedPrefix]);

        $validated = $request->validate([
            'email_prefix'          => InternalStorkiaEmailService::prefixValidationRules(),
            'logistics_hub_id'      => ['required', 'integer', 'exists:logistics_hubs,id'],
            'barangays'             => ['required', 'array', 'min:1'],
            'barangays.*'           => ['required', 'string', 'max:120'],
            'first_name'            => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'last_name'             => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z\s]+$/'],
            'birthday'              => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],
            'contact_no'            => ['required', 'regex:/^9\d{9}$/'],
            'license_no'            => ['required', 'string', 'regex:/^[A-Za-z]\d{2}-\d{2}-\d{6}$/'],
            'license_expiry_date'   => ['required', 'date', 'after_or_equal:' . now()->format('Y-m-d')],
            'vehicle_type'          => ['required', 'string'],
            'plate_number'          => ['required', 'string', 'min:6', 'max:15'],
            'drivers_license'       => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'vehicle_or_cr'         => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'password'              => ['required', 'string', 'min:8', 'max:64'],
            'password_confirmation' => ['required', 'same:password'],
        ], array_merge(
            InternalStorkiaEmailService::prefixValidationMessages('email_prefix'),
            [
                'logistics_hub_id.required' => 'Please select a company hub for the rider.',
                'barangays.required'        => 'Please select at least one Barangay coverage for the rider.',
                'barangays.min'             => 'Please select at least one Barangay coverage for the rider.',
            ]
        ));

        /** @var LogisticsHub $targetHub */
        $targetHub = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->findOrFail($validated['logistics_hub_id']);

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

        $resolvedBarangays = [];
        foreach ($validated['barangays'] as $submittedBrgy) {
            $key = strtolower(trim((string) $submittedBrgy));
            if (! isset($validBarangayMap[$key])) {
                throw ValidationException::withMessages([
                    'barangays' => "Barangay \"{$submittedBrgy}\" does not belong to {$targetHub->municipality}.",
                ]);
            }
            $resolvedBarangays[$key] = $validBarangayMap[$key];
        }

        $fullEmail = InternalStorkiaEmailService::buildEmail($validated['email_prefix']);

        if (InternalStorkiaEmailService::emailExists($fullEmail)) {
            throw ValidationException::withMessages([
                'email_prefix' => "The email {$fullEmail} is already registered.",
            ]);
        }

        $formattedContact = '+63' . ltrim(preg_replace('/\D/', '', $validated['contact_no']), '0');

        DB::transaction(function () use ($profile, $targetHub, $user, $validated, $fullEmail, $formattedContact, $resolvedBarangays) {
            $licensePath = $request->file('drivers_license')->store('rider_documents/licenses', 'public');
            $orCrPath = $request->file('vehicle_or_cr')->store('rider_documents/or_cr', 'public');

            $rider = Rider::create([
                'logistics_profile_id' => $profile->id,
                'first_name'           => ucwords(strtolower(trim($validated['first_name']))),
                'last_name'            => ucwords(strtolower(trim($validated['last_name']))),
                'birthday'             => $validated['birthday'],
                'email'                => $fullEmail,
                'email_verified_at'    => now(),
                'contact_no'           => $formattedContact,
                'license_no'           => strtoupper(trim($validated['license_no'])),
                'license_expiry_date'  => $validated['license_expiry_date'],
                'vehicle_type'         => $validated['vehicle_type'],
                'plate_number'         => strtoupper(trim($validated['plate_number'])),
                'license_path'         => $licensePath,
                'or_cr_path'           => $orCrPath,
                'password'             => Hash::make($validated['password']),
                'status'               => 'approved',
                'approved_at'          => now(),
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
                'remarks'              => 'Created directly by Logistics hub',
            ]);

            foreach ($resolvedBarangays as $item) {
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

        return redirect()
            ->route('logistics.rider-applications.index')
            ->with('success', "Rider account {$fullEmail} created and assigned successfully!");
    }

    public function approve(Request $request, Rider $rider)
    {
        $profile = $request->user()->logisticsProfile;
        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $rider->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        return back()->with('success', "Rider application for {$rider->full_name} has been approved.");
    }

    public function reject(Request $request, Rider $rider)
    {
        $profile = $request->user()->logisticsProfile;
        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $rider->update([
            'status'      => 'rejected',
            'rejected_at' => now(),
            'approved_at' => null,
        ]);

        return back()->with('success', "Rider application for {$rider->full_name} has been rejected.");
    }

    public function document(Request $request, Rider $rider, string $type)
    {
        $profile = $request->user()->logisticsProfile;
        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $path = match ($type) {
            'license' => $rider->license_path,
            'or_cr'   => $rider->or_cr_path,
            default   => null,
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Document not found.');
        }

        return Storage::disk('public')->response($path);
    }
}