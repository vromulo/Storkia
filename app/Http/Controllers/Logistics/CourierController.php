<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\PickupAssignment;
use App\Services\AuditLoggerService;
use App\Services\StorkSchemaBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CourierController extends Controller
{
    public function index(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403, 'Approved Logistics profile required.');
        }

        $activeHub = $user->activeLogisticsHub();
        $isBranchUser = $user->isBranchLogisticsAccount();
        $status = strtolower((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $baseScope = Courier::where('logistics_profile_id', $profile->id);
        if ($isBranchUser && $activeHub) {
            $baseScope->whereHas('hubAssignments', function ($q) use ($activeHub) {
                $q->where('logistics_hub_id', $activeHub->id)
                  ->where('is_current', true);
            });
        }

        $counts = [
            'all'         => (clone $baseScope)->count(),
            'active'      => (clone $baseScope)->where('status', 'active')->count(),
            'on_delivery' => (clone $baseScope)->where('status', 'on_delivery')->count(),
            'off_duty'    => (clone $baseScope)->where('status', 'off_duty')->count(),
            'suspended'   => (clone $baseScope)->where('status', 'suspended')->count(),
        ];

        $query = (clone $baseScope)
            ->with(['currentHubAssignment.hub'])
            ->withCount([
                'pickupAssignments as active_pickups_count' => function ($q) {
                    $q->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_transit_to_hub']);
                },
                'pickupAssignments as completed_pickups_count' => function ($q) {
                    $q->where('status', 'completed');
                },
            ]);

        if ($status !== 'all' && in_array($status, ['active', 'on_delivery', 'off_duty', 'suspended'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('license_no', 'like', "%{$search}%")
                  ->orWhere('home_hub', 'like', "%{$search}%");
            });
        }

        $couriers = $query
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(20)
            ->withQueryString();

        $hubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('hub_name')
            ->get();

        return view('logistics.couriers.index', compact(
            'profile',
            'couriers',
            'status',
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

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $hubs = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
            ->where('status', 'active')
            ->orderByDesc('is_main_hub')
            ->orderBy('hub_name')
            ->get();

        return view('logistics.couriers.create-courier', compact('profile', 'hubs'));
    }

    public function store(Request $request)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile) {
            abort(403);
        }

        $rawPhone = preg_replace('/\D/', '', (string) $request->input('contact_no', ''));
        if (str_starts_with($rawPhone, '63') && strlen($rawPhone) >= 12) {
            $rawPhone = substr($rawPhone, 2);
        }
        $cleanPhone = ltrim($rawPhone, '0');
        $normalizedPhone = '0' . $cleanPhone;

        $emailPrefix = strtolower(trim((string) $request->input('email_prefix', '')));
        $emailPrefix = preg_replace('/@storkia\.shop$/i', '', $emailPrefix);
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
            'first_name'          => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'last_name'           => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}\s\'\-\.]+$/u'],
            'birthday'            => ['nullable', 'date', 'before:' . now()->subYears(18)->toDateString()],
            'email_prefix'        => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._\-]+$/'],
            'email'               => ['required', 'email', 'max:100', 'unique:couriers,email', 'unique:riders,email', 'unique:users,email'],
            'contact_no_clean'    => ['required', 'string', 'regex:/^9\d{9}$/'],
            'contact_no'          => ['required', 'string', 'unique:couriers,contact_no', 'unique:riders,contact_no'],
            'logistics_hub_id'    => ['nullable', 'integer', 'exists:logistics_hubs,id'],
            'vehicle_type'        => ['required', 'string', 'max:60'],
            'plate_number'        => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Z0-9\-\s]+$/', 'unique:couriers,plate_number'],
            'load_capacity'       => ['required', 'numeric', 'min:50', 'max:20000'],
            'license_no'          => ['required', 'string', 'min:6', 'max:35', 'regex:/^[A-Z0-9\-]+$/', 'unique:couriers,license_no'],
            'license_type'        => ['required', 'in:Professional'],
            'dl_codes'            => ['required', 'array', 'min:1'],
            'dl_codes.*'          => ['required', 'string', 'max:10'],
            'license_expiry_date' => ['required', 'date', 'after:today'],
            'license_file'        => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'or_cr_file'          => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'nbi_clearance_file'  => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'password'            => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
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

        $licensePath = $request->file('license_file')->store('courier_documents/licenses', 'public');
        $orCrPath = $request->file('or_cr_file')->store('courier_documents/or_cr', 'public');
        $nbiPath = $request->file('nbi_clearance_file')->store('courier_documents/nbi', 'public');

        $courier = DB::transaction(function () use ($profile, $user, $targetHub, $validated, $licensePath, $orCrPath, $nbiPath) {
            $created = Courier::create([
                'logistics_profile_id' => $profile->id,
                'first_name'           => $validated['first_name'],
                'last_name'            => $validated['last_name'],
                'birthday'             => $validated['birthday'] ?? now()->subYears(25)->toDateString(),
                'email'                => $validated['email'],
                'contact_no'           => $validated['contact_no'],
                'password'             => Hash::make($validated['password']),
                'vehicle_type'         => $validated['vehicle_type'],
                'plate_number'         => $validated['plate_number'],
                'load_capacity'        => (int) $validated['load_capacity'],
                'home_hub'             => $targetHub->hub_name,
                'license_no'           => $validated['license_no'],
                'license_type'         => $validated['license_type'],
                'dl_codes'             => array_values($validated['dl_codes']),
                'license_expiry_date'  => $validated['license_expiry_date'],
                'license_path'         => $licensePath,
                'or_cr_path'           => $orCrPath,
                'nbi_clearance_path'   => $nbiPath,
                'status'               => 'active',
                'is_online'            => true,
            ]);

            LogisticsEmployeeHubAssignment::create([
                'logistics_profile_id' => $profile->id,
                'logistics_hub_id'     => $targetHub->id,
                'employee_type'        => 'courier',
                'courier_id'           => $created->id,
                'municipality'         => $targetHub->municipality,
                'is_current'           => true,
                'assigned_by'          => $user->id,
                'assigned_at'          => now(),
                'remarks'              => 'Initial hub assignment upon Courier creation',
            ]);

            return $created;
        });

        return redirect()
            ->route('logistics.couriers.index')
            ->with('success', "First-Mile Courier {$courier->full_name} ({$courier->email}) created and assigned to {$targetHub->hub_name}!");
    }

    /**
     * Update an existing Courier's profile picture, vehicle/license details, or documents.
     * Name and personal details (first_name, last_name, birthday, email, contact_no) are strictly read-only.
     */
    public function updateProfile(Request $request, Courier $courier)
    {
        StorkSchemaBootstrapService::ensureTablesExist();

        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $courier->logistics_profile_id !== (int) $profile->id) {
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
                Rule::unique('couriers', 'plate_number')->ignore($courier->id),
            ],
            'load_capacity'       => ['required', 'integer', 'min:50', 'max:20000'],
            'license_no'          => [
                'required',
                'string',
                'min:6',
                'max:35',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('couriers', 'license_no')->ignore($courier->id),
            ],
            'license_expiry_date' => ['required', 'date', 'after:today'],
            'dl_codes'            => ['nullable', 'array', 'min:1'],
            'dl_codes.*'          => ['string', 'max:10'],
            'license_file'        => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'or_cr_file'          => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'nbi_clearance_file'  => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'avatar.mimetypes'          => 'Courier profile photo must be a valid JPG, PNG, or WEBP image (max 5 MB).',
            'plate_number.unique'       => 'This plate number is already registered to another courier.',
            'license_no.unique'         => 'This driver\'s license number is already registered to another courier.',
            'license_expiry_date.after' => 'Driver\'s license expiry date must be in the future.',
        ]);

        $updates = [
            'vehicle_type'        => $validated['vehicle_type'],
            'plate_number'        => $validated['plate_number'],
            'load_capacity'       => (int) $validated['load_capacity'],
            'license_no'          => $validated['license_no'],
            'license_expiry_date' => $validated['license_expiry_date'],
        ];

        if (! empty($validated['dl_codes'])) {
            $updates['dl_codes'] = array_values($validated['dl_codes']);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'courier_avatars/courier_' . $courier->id . '_' . uniqid('', true) . '.webp';
            try {
                $manager = new ImageManager(new Driver());
                $encoded = $manager->read($file->getRealPath())->cover(400, 400)->toWebp(84);
                Storage::disk('public')->put($filename, (string) $encoded);
            } catch (\Throwable $e) {
                $filename = $file->store('courier_avatars', 'public');
            }

            if ($courier->avatar_path && Storage::disk('public')->exists($courier->avatar_path)) {
                Storage::disk('public')->delete($courier->avatar_path);
            }
            $updates['avatar_path'] = $filename;
        }

        if ($request->hasFile('license_file')) {
            $updates['license_path'] = $request->file('license_file')->store('courier_documents/licenses', 'public');
        }
        if ($request->hasFile('or_cr_file')) {
            $updates['or_cr_path'] = $request->file('or_cr_file')->store('courier_documents/or_cr', 'public');
        }
        if ($request->hasFile('nbi_clearance_file')) {
            $updates['nbi_clearance_path'] = $request->file('nbi_clearance_file')->store('courier_documents/nbi', 'public');
        }

        $oldValues = [
            'vehicle_type'  => $courier->vehicle_type,
            'plate_number'  => $courier->plate_number,
            'load_capacity' => $courier->load_capacity,
            'license_no'    => $courier->license_no,
            'avatar_path'   => $courier->avatar_path,
        ];

        $courier->update($updates);

        AuditLoggerService::log(
            action: 'courier_profile_updated_by_logistics',
            targetType: 'Courier',
            targetId: (int) $courier->id,
            targetReference: $courier->email,
            oldValues: $oldValues,
            newValues: $updates,
            reason: "Updated by Logistics ({$user->full_name}) — personal details locked"
        );

        return back()->with('success', "Courier {$courier->full_name}'s profile and vehicle details have been updated.");
    }

    public function updateStatus(Request $request, Courier $courier)
    {
        $user = $request->user();
        $profile = $user->logisticsProfile;

        if (! $profile || (int) $courier->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $rawStatus = strtolower(trim((string) $request->input('status', '')));
        if ($rawStatus === 'inactive') {
            $rawStatus = 'off_duty';
        }
        $request->merge(['status' => $rawStatus]);

        $validated = $request->validate([
            'status' => ['required', 'in:active,on_delivery,off_duty,suspended'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $courier->status;
        $newStatus = $validated['status'];

        DB::transaction(function () use ($courier, $oldStatus, $newStatus, $validated) {
            $courier->update([
                'status'    => $newStatus,
                'is_online' => in_array($newStatus, ['active', 'on_delivery'], true),
            ]);

            if ($newStatus === 'suspended') {
                PickupAssignment::where('courier_id', $courier->id)
                    ->whereIn('status', ['assigned', 'accepted'])
                    ->update([
                        'status' => 'reassigned',
                        'notes'  => 'Courier status changed to ' . $newStatus,
                    ]);
            }

            AuditLoggerService::log(
                action: 'courier_status_updated',
                targetType: 'Courier',
                targetId: (int) $courier->id,
                targetReference: $courier->email,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus],
                reason: $validated['reason'] ?? "Courier status changed from {$oldStatus} to {$newStatus}"
            );
        });

        return back()->with('success', "Courier {$courier->full_name} status updated to " . ucwords(str_replace('_', ' ', $newStatus)) . '.');
    }

    public function document(Request $request, Courier $courier, string $type)
    {
        $profile = $request->user()?->logisticsProfile;

        if (! $profile || (int) $courier->logistics_profile_id !== (int) $profile->id) {
            abort(403);
        }

        $path = match ($type) {
            'license' => $courier->license_path,
            'or_cr'   => $courier->or_cr_path,
            'nbi'     => $courier->nbi_clearance_path,
            default   => null,
        };

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Courier document not found.');
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}