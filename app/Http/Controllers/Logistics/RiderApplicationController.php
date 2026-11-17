<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\RiderBarangayCoverage;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\PsgcService;
use App\Services\RealtimeBroadcastService;
use App\Services\RiderMobileSchemaService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class RiderApplicationController extends Controller
{
    public function __construct(
        protected PsgcService $psgc
    ) {}

    protected function resolveLogisticsProfile(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        return method_exists($user, 'effectiveLogisticsProfile')
            ? ($user->effectiveLogisticsProfile() ?? $user->logisticsProfile)
            : $user->logisticsProfile;
    }

    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile) {
            abort(403, 'Approved Logistics profile required.');
        }

        $activeHub = $user->activeLogisticsHub();
        $isBranchUser = $user->isBranchLogisticsAccount();
        $status = strtolower((string) $request->query('status', 'all'));
        $statusFilter = $status;
        $search = trim((string) $request->query('search', ''));

        $baseScope = Rider::where('logistics_profile_id', $profile->id);
        if ($isBranchUser && $activeHub) {
            $baseScope->whereHas('hubAssignments', function ($q) use ($activeHub) {
                $q->where('logistics_hub_id', $activeHub->id)
                  ->where('is_current', true);
            });
        }

        $counts = [
            'all'      => (clone $baseScope)->count(),
            'pending'  => (clone $baseScope)->where('status', 'pending')->count(),
            'approved' => (clone $baseScope)->where('status', 'approved')->count(),
            'rejected' => (clone $baseScope)->where('status', 'rejected')->count(),
        ];

        $query = (clone $baseScope)
            ->with(['currentHubAssignment.hub', 'activeBarangayCoverages'])
            ->withCount([
                'deliveryAssignments as active_deliveries_count' => function ($q) {
                    $q->whereIn('status', ['assigned', 'accepted', 'collected', 'out_for_delivery']);
                },
                'deliveryAssignments as completed_deliveries_count' => function ($q) {
                    $q->where('status', 'delivered');
                },
            ]);

        if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%");
            });
        }

        $applicants = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(20)
            ->withQueryString();

        $riders = $applicants;
        $applications = $applicants;

        $hubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('hub_name')
            ->get();

        return view('logistics.riders.applications', compact(
            'profile',
            'applicants',
            'applications',
            'riders',
            'status',
            'statusFilter',
            'search',
            'counts',
            'hubs',
            'activeHub',
            'isBranchUser'
        ));
    }

    public function create(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile) {
            abort(403);
        }

        return view('rider.auth.register');
    }

    public function store(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile) {
            abort(403);
        }

        $rawPhone = preg_replace('/\D/', '', (string) $request->input('contact_no', ''));
        if (str_starts_with($rawPhone, '63') && strlen($rawPhone) >= 12) {
            $rawPhone = substr($rawPhone, 2);
        }
        $cleanPhone = ltrim($rawPhone, '0');
        $normalizedPhone = '0' . $cleanPhone;

        $emailPrefix = strtolower(trim((string) $request->input('email_prefix', $request->input('email', ''))));
        $emailPrefix = preg_replace('/@.*$/', '', $emailPrefix);
        $fullEmail = $emailPrefix . '@storkia.shop';

        $licenseNoInput = strtoupper(trim((string) ($request->input('license_no', $request->input('license_number', '')))));

        $request->merge([
            'first_name'       => trim((string) $request->input('first_name', '')),
            'last_name'        => trim((string) $request->input('last_name', '')),
            'email_prefix'     => $emailPrefix,
            'email'            => $fullEmail,
            'contact_no_clean' => $cleanPhone,
            'contact_no'       => $normalizedPhone,
            'plate_number'     => strtoupper(trim((string) $request->input('plate_number', ''))),
            'license_no'       => $licenseNoInput,
        ]);

        $validated = $request->validate([
            'first_name'           => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'last_name'            => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'birthday'             => ['nullable', 'date', 'before:' . now()->subYears(18)->toDateString()],
            'email_prefix'         => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._\-]+$/'],
            'email'                => ['required', 'email', 'max:100', 'unique:riders,email', 'unique:couriers,email', 'unique:users,email'],
            'contact_no_clean'     => ['required', 'string', 'regex:/^9\d{9}$/'],
            'contact_no'           => ['required', 'string', 'unique:riders,contact_no', 'unique:couriers,contact_no'],
            'logistics_hub_id'     => ['nullable', 'integer', 'exists:logistics_hubs,id'],
            'vehicle_type'         => ['required', 'string', 'max:60'],
            'plate_number'         => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Z0-9\-\s]+$/', 'unique:riders,plate_number'],
            'license_no'           => ['required', 'string', 'min:6', 'max:35', 'regex:/^[A-Z0-9\-]+$/', 'unique:riders,license_no'],
            'license_expiry_date'  => ['nullable', 'date', 'after:today'],
            'license_file'         => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'drivers_license'      => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'or_cr_file'           => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'vehicle_or_cr'        => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'barangay_coverages'   => ['nullable', 'array'],
            'barangays'            => ['nullable', 'array'],
            'password'             => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $targetHub = null;
        if (! empty($validated['logistics_hub_id'])) {
            $targetHub = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
                ->where('id', (int) $validated['logistics_hub_id'])
                ->first();
        }
        if (! $targetHub) {
            $targetHub = $user->activeLogisticsHub() ?? $profile->ensureMainHubExists();
        }

        $licenseFile = $request->file('license_file') ?? $request->file('drivers_license');
        $orCrFile = $request->file('or_cr_file') ?? $request->file('vehicle_or_cr');

        $licensePath = $licenseFile ? $licenseFile->store('rider_documents/licenses', 'public') : '';
        $orCrPath = $orCrFile ? $orCrFile->store('rider_documents/or_cr', 'public') : '';

        $barangayList = $validated['barangay_coverages'] ?? $validated['barangays'] ?? [];

        $rider = DB::transaction(function () use ($profile, $user, $targetHub, $validated, $licensePath, $orCrPath, $barangayList) {
            $created = Rider::create([
                'logistics_profile_id'        => $profile->id,
                'first_name'                  => $validated['first_name'],
                'last_name'                   => $validated['last_name'],
                'birthday'                    => $validated['birthday'] ?? now()->subYears(22)->toDateString(),
                'email'                       => $validated['email'],
                'contact_no'                  => $validated['contact_no'],
                'password'                    => Hash::make($validated['password']),
                'vehicle_type'                => $validated['vehicle_type'],
                'plate_number'                => $validated['plate_number'],
                'license_no'                  => $validated['license_no'],
                'license_expiry_date'         => $validated['license_expiry_date'] ?? now()->addYears(3)->toDateString(),
                'license_path'                => $licensePath,
                'or_cr_path'                  => $orCrPath,
                'status'                      => 'approved',
                'approved_at'                 => now(),
                'is_online'                   => true,
                'vehicle_verification_status' => 'verified',
            ]);

            LogisticsEmployeeHubAssignment::create([
                'logistics_profile_id' => $profile->id,
                'logistics_hub_id'     => $targetHub->id,
                'employee_type'        => 'rider',
                'rider_id'             => $created->id,
                'municipality'         => $targetHub->municipality,
                'is_current'           => true,
                'assigned_by'          => $user->id,
                'assigned_at'          => now(),
                'remarks'              => 'Initial hub assignment upon Rider creation',
            ]);

            if (! empty($barangayList)) {
                foreach (array_unique($barangayList) as $brgyName) {
                    RiderBarangayCoverage::create([
                        'logistics_profile_id' => $profile->id,
                        'logistics_hub_id'     => $targetHub->id,
                        'rider_id'             => $created->id,
                        'municipality_code'    => $targetHub->municipality_code,
                        'municipality'         => $targetHub->municipality,
                        'barangay_name'        => trim((string) $brgyName),
                        'is_active'            => true,
                        'assigned_by'          => $user->id,
                    ]);
                }
            }

            return $created;
        });

        return redirect()
            ->route('logistics.rider-applications.index')
            ->with('success', "Last-Mile Rider {$rider->full_name} ({$rider->email}) created and assigned to {$targetHub->hub_name}!");
    }

    public function updateProfile(Request $request, Rider $rider)
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        if ($request->filled('plate_number')) {
            $request->merge(['plate_number' => strtoupper(trim((string) $request->input('plate_number')))]);
        }
        if ($request->filled('license_no')) {
            $request->merge(['license_no' => strtoupper(trim((string) $request->input('license_no')))]);
        }

        $validated = $request->validate([
            'avatar'              => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'vehicle_type'        => ['required', 'string', 'max:60'],
            'plate_number'        => [
                'required',
                'string',
                'min:4',
                'max:20',
                'regex:/^[A-Z0-9\-\s]+$/',
                Rule::unique('riders', 'plate_number')->ignore($rider->id),
            ],
            'license_no'          => [
                'required',
                'string',
                'min:6',
                'max:35',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('riders', 'license_no')->ignore($rider->id),
            ],
            'license_expiry_date' => ['required', 'date', 'after:today'],
            'license_file'        => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'or_cr_file'          => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'avatar.mimetypes'          => 'Rider profile photo must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'plate_number.unique'       => 'This plate number is already registered to another rider.',
            'license_no.unique'         => 'This driver\'s license number is already registered to another rider.',
            'license_expiry_date.after' => 'Driver\'s license expiry date must be in the future.',
        ]);

        $updates = [
            'vehicle_type'        => $validated['vehicle_type'],
            'plate_number'        => $validated['plate_number'],
            'license_no'          => $validated['license_no'],
            'license_expiry_date' => $validated['license_expiry_date'],
        ];

        if (Schema::hasColumn('riders', 'vehicle_verification_status')) {
            $updates['vehicle_verification_status'] = 'verified';
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'rider_avatars/rider_' . $rider->id . '_' . uniqid('', true) . '.webp';
            try {
                $manager = new ImageManager(new Driver());
                $encoded = $manager->read($file->getRealPath())->cover(400, 400)->toWebp(84);
                Storage::disk('public')->put($filename, (string) $encoded);
            } catch (\Throwable $e) {
                $filename = $file->store('rider_avatars', 'public');
            }

            if ($rider->avatar_path && Storage::disk('public')->exists($rider->avatar_path)) {
                Storage::disk('public')->delete($rider->avatar_path);
            }
            $updates['avatar_path'] = $filename;
        }

        if ($request->hasFile('license_file')) {
            $updates['license_path'] = $request->file('license_file')->store('rider_documents/licenses', 'public');
        }
        if ($request->hasFile('or_cr_file')) {
            $updates['or_cr_path'] = $request->file('or_cr_file')->store('rider_documents/or_cr', 'public');
        }

        $oldValues = [
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_number,
            'license_no'   => $rider->license_no,
            'avatar_path'  => $rider->avatar_path,
        ];

        $rider->update($updates);

        if (Schema::hasTable('rider_vehicle_updates')) {
            $updatePayload = [
                'status'     => 'approved',
                'remarks'    => 'Verified and updated by Logistics Hub (' . $user->full_name . ').',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('rider_vehicle_updates', 'reviewed_by')) {
                $updatePayload['reviewed_by'] = $user->id;
                $updatePayload['reviewed_at'] = now();
            }
            DB::table('rider_vehicle_updates')
                ->where('rider_id', $rider->id)
                ->where('status', 'pending_verification')
                ->update($updatePayload);
        }

        AuditLoggerService::log(
            action: 'rider_profile_updated_by_logistics',
            targetType: 'Rider',
            targetId: (int) $rider->id,
            targetReference: $rider->email,
            oldValues: $oldValues,
            newValues: $updates,
            reason: "Updated by Logistics ({$user->full_name}) — personal details locked"
        );

        RealtimeBroadcastService::send(
            channels: [
                'rider.' . $rider->id,
                'logistics.' . $profile->id,
            ],
            scope: 'rider',
            action: 'rider.profile.updated',
            payload: [
                'rider_id'                    => (int) $rider->id,
                'vehicle_verification_status' => 'verified',
            ]
        );

        return back()->with('success', "Rider {$rider->full_name}'s profile and vehicle details have been updated and verified.");
    }

    public function approve(Request $request, Rider $rider)
    {
        RiderMobileSchemaService::ensureTablesExist();

        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $oldStatus = $rider->status;
        $updates = [
            'status'      => 'approved',
            'approved_at' => now(),
            'rejected_at' => null,
            'is_online'   => true,
        ];
        if (Schema::hasColumn('riders', 'vehicle_verification_status')) {
            $updates['vehicle_verification_status'] = 'verified';
        }
        $rider->update($updates);

        if (Schema::hasTable('rider_vehicle_updates')) {
            $updatePayload = [
                'status'     => 'approved',
                'remarks'    => 'Approved by Logistics Hub.',
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('rider_vehicle_updates', 'reviewed_by')) {
                $updatePayload['reviewed_by'] = $user->id;
                $updatePayload['reviewed_at'] = now();
            }
            DB::table('rider_vehicle_updates')
                ->where('rider_id', $rider->id)
                ->where('status', 'pending_verification')
                ->update($updatePayload);
        }

        AuditLoggerService::log(
            action: 'rider_approved',
            targetType: 'Rider',
            targetId: (int) $rider->id,
            targetReference: $rider->email,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'approved'],
            reason: 'Approved by Logistics Hub'
        );

        try {
            $rider->notify(new OrderLifecycleNotification(
                title: 'Rider Account & Vehicle Verified!',
                message: "Your Rider account and vehicle documents have been verified and approved by {$profile->business_name}.",
                type: 'success',
                url: route('rider.dashboard')
            ));
        } catch (\Throwable $e) {
        }

        RealtimeBroadcastService::send(
            channels: [
                'rider.' . $rider->id,
                'logistics.' . $profile->id,
            ],
            scope: 'rider',
            action: 'rider.profile.updated',
            payload: [
                'rider_id'                    => (int) $rider->id,
                'status'                      => 'approved',
                'vehicle_verification_status' => 'verified',
            ]
        );

        return back()->with('success', "Rider {$rider->full_name} has been approved and activated.");
    }

    public function reject(Request $request, Rider $rider)
    {
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $rider->status;
        $rider->update([
            'status'      => 'rejected',
            'rejected_at' => now(),
            'is_online'   => false,
        ]);

        AuditLoggerService::log(
            action: 'rider_rejected',
            targetType: 'Rider',
            targetId: (int) $rider->id,
            targetReference: $rider->email,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'rejected'],
            reason: $validated['rejection_reason'] ?? 'Rejected by Logistics Hub'
        );

        RealtimeBroadcastService::send(
            channels: [
                'rider.' . $rider->id,
                'logistics.' . $profile->id,
            ],
            scope: 'rider',
            action: 'rider.profile.updated',
            payload: [
                'rider_id'  => (int) $rider->id,
                'status'    => 'rejected',
                'is_online' => false,
            ]
        );

        return back()->with('success', "Rider {$rider->full_name} status updated to Rejected.");
    }

    public function decideVehicleVerification(Request $request, Rider $rider)
    {
        RiderMobileSchemaService::ensureTablesExist();

        $user = $request->user();
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject,approved,rejected,verified'],
            'remarks'  => ['nullable', 'string', 'max:300'],
        ]);

        $isApprove = in_array(strtolower($validated['decision']), ['approve', 'approved', 'verified'], true);
        $newStatus = $isApprove ? 'verified' : 'rejected';
        $remarks = trim((string) ($validated['remarks'] ?? ''));

        if (Schema::hasColumn('riders', 'vehicle_verification_status')) {
            $rider->update([
                'vehicle_verification_status' => $newStatus,
            ]);
        }

        if (Schema::hasTable('rider_vehicle_updates')) {
            $updatePayload = [
                'status'     => $isApprove ? 'approved' : 'rejected',
                'remarks'    => $remarks !== '' ? $remarks : ($isApprove ? 'Approved by Logistics Hub.' : 'Rejected by Logistics Hub.'),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('rider_vehicle_updates', 'reviewed_by')) {
                $updatePayload['reviewed_by'] = $user->id;
                $updatePayload['reviewed_at'] = now();
            }
            DB::table('rider_vehicle_updates')
                ->where('rider_id', $rider->id)
                ->where('status', 'pending_verification')
                ->update($updatePayload);
        }

        try {
            $rider->notify(new OrderLifecycleNotification(
                title: $isApprove ? 'Vehicle & License Update Verified' : 'Vehicle & License Update Needs Attention',
                message: $isApprove
                    ? "Your updated vehicle and driver's license documents were verified by {$profile->business_name}."
                    : "Your vehicle/license update could not be verified" . ($remarks !== '' ? ": {$remarks}" : '. Please upload clear documents in the Rider app.'),
                type: $isApprove ? 'success' : 'warning',
                url: route('rider.dashboard')
            ));
        } catch (\Throwable $e) {
        }

        RealtimeBroadcastService::send(
            channels: [
                'rider.' . $rider->id,
                'logistics.' . $profile->id,
            ],
            scope: 'rider',
            action: 'rider.profile.updated',
            payload: [
                'rider_id'                    => (int) $rider->id,
                'vehicle_verification_status' => $newStatus,
            ]
        );

        return back()->with(
            'success',
            $isApprove
                ? "Rider {$rider->full_name}'s vehicle and documents marked as Verified."
                : "Rider {$rider->full_name}'s vehicle update marked as Rejected."
        );
    }

    public function resolveIncident(Request $request, int $incidentId)
    {
        RiderMobileSchemaService::ensureTablesExist();

        $profile = $this->resolveLogisticsProfile($request);
        if (! $profile || ! Schema::hasTable('rider_trip_incidents')) {
            abort(403);
        }

        $incident = DB::table('rider_trip_incidents')
            ->where('id', $incidentId)
            ->where('logistics_profile_id', $profile->id)
            ->first();

        if (! $incident) {
            abort(404);
        }

        DB::table('rider_trip_incidents')
            ->where('id', $incidentId)
            ->update([
                'status'     => 'resolved',
                'updated_at' => now(),
            ]);

        if ($incident->delivery_assignment_id && Schema::hasColumn('delivery_assignments', 'is_delayed')) {
            DB::table('delivery_assignments')
                ->where('id', $incident->delivery_assignment_id)
                ->update([
                    'is_delayed' => false,
                    'updated_at' => now(),
                ]);
        }

        RealtimeBroadcastService::send(
            channels: [
                'rider.' . (int) $incident->rider_id,
                'logistics.' . (int) $profile->id,
            ],
            scope: 'delivery',
            action: 'delivery.incident_resolved',
            payload: [
                'incident_id'            => $incidentId,
                'delivery_assignment_id' => (int) $incident->delivery_assignment_id,
                'shipment_id'            => (int) $incident->shipment_id,
            ]
        );

        return back()->with('success', 'Rider route incident marked as resolved.');
    }

    public function document(Request $request, Rider $rider, string $type)
    {
        $profile = $this->resolveLogisticsProfile($request);

        if (! $profile || (int) $rider->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $path = match ($type) {
            'license' => $rider->license_path,
            'or_cr'   => $rider->or_cr_path,
            default   => null,
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Rider document not found.');
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}