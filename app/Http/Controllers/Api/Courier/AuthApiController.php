<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\PickupAssignment;
use App\Models\Rider;
use App\Notifications\OrderLifecycleNotification;
use App\Services\AuditLoggerService;
use App\Services\CourierMobileSchemaService;
use App\Services\OperationalSlaGuardService;
use App\Services\RoleWalletService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AuthApiController extends Controller
{
    public function __construct(
        protected RoleWalletService $roleWalletService,
        protected OperationalSlaGuardService $slaGuardService
    ) {}

    public function login(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        /** @var Courier|null $courier */
        $courier = Courier::with(['logisticsProfile', 'currentHubAssignment.hub'])
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        if (! $courier || ! Hash::check($validated['password'], $courier->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The provided credentials do not match our First-Mile Courier records.',
            ], 401);
        }

        $normalizedStatus = strtolower(trim((string) $courier->status));
        if (in_array($normalizedStatus, ['suspended', 'inactive', 'rejected'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your Courier account is currently suspended by your Logistics Hub. You cannot sign in or go On Duty.',
                'errors'  => [
                    'email' => ['Your Courier account is currently suspended.'],
                ],
            ], 403);
        }

        if ($courier->logistics_profile_id) {
            $this->slaGuardService->sweepOverdueEmployeeAssignments((int) $courier->logistics_profile_id);
            $courier->refresh();
        }

        $hasActiveDelivery = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        $safeStatus = $hasActiveDelivery ? 'on_delivery' : 'active';

        $courier->forceFill([
            'status'       => $safeStatus,
            'last_seen_at' => now(),
        ])->save();

        $deviceName = trim((string) ($validated['device_name'] ?? 'Storkia-Courier-Android'));
        $token = $courier->createToken($deviceName, ['courier', 'role:courier'])->plainTextToken;

        $payload = $this->formatCourierPayload($courier);

        return response()->json([
            'status'       => 'success',
            'message'      => "Welcome back, {$courier->first_name}!",
            'access_token' => $token,
            'token'        => $token,
            'user'         => $payload,
            'data'         => [
                'access_token' => $token,
                'token'        => $token,
                'user'         => $payload,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Courier|null $courier */
        $courier = $request->user();

        if ($courier) {
            $hasActivePickups = PickupAssignment::where('courier_id', $courier->id)
                ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
                ->exists();

            if (! $hasActivePickups && Schema::hasColumn('couriers', 'is_online')) {
                $courier->forceFill([
                    'is_online'    => false,
                    'last_seen_at' => now(),
                ])->save();
            }

            $courier->currentAccessToken()?->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Signed out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();
        $courier->loadMissing(['logisticsProfile', 'currentHubAssignment.hub']);

        $this->checkStaleDutyTimeout($courier);

        $courier->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $payload = $this->formatCourierPayload($courier);

        return response()->json([
            'status' => 'success',
            'user'   => $payload,
            'data'   => [
                'user' => $payload,
            ],
        ]);
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $normalizedStatus = strtolower(trim((string) $courier->status));
        if (in_array($normalizedStatus, ['suspended', 'inactive', 'rejected'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your Courier account is suspended. You cannot go On Duty or accept pickup assignments.',
            ], 403);
        }

        $validated = $request->validate([
            'is_online'         => ['required', 'boolean'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'          => ['nullable', 'numeric', 'min:0'],
            'checklist'         => ['nullable', 'array'],
            'tires_ok'          => ['nullable', 'boolean'],
            'lights_ok'         => ['nullable', 'boolean'],
            'brakes_ok'         => ['nullable', 'boolean'],
            'fuel_ok'           => ['nullable', 'boolean'],
            'cargo_lock_ok'     => ['nullable', 'boolean'],
            'notes'             => ['nullable', 'string', 'max:300'],
            'require_checklist' => ['nullable', 'boolean'],
        ]);

        $wantsOnline = (bool) $validated['is_online'];

        if (! $wantsOnline) {
            $activeInProgress = PickupAssignment::where('courier_id', $courier->id)
                ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
                ->first();

            if ($activeInProgress) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You cannot go Off Duty while you have an active pickup or package in transit. Please complete hub handover first.',
                ], 422);
            }

            $courier->forceFill([
                'is_online'    => false,
                'status'       => 'active',
                'last_seen_at' => now(),
            ])->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'You are now Off Duty and will not receive new pickup assignments.',
                'data'    => [
                    'is_online' => false,
                    'status'    => 'off_duty',
                    'user'      => $this->formatCourierPayload($courier->fresh(['logisticsProfile', 'currentHubAssignment.hub'])),
                ],
            ]);
        }

        if (! isset($validated['latitude']) || ! isset($validated['longitude'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Location access is required to go On Duty. Please enable GPS location permission in Settings.',
                'errors'  => [
                    'location' => ['Location permission and current GPS coordinates are required to go On Duty.'],
                ],
            ], 422);
        }

        $hasChecklistInput = $request->has('checklist')
            || $request->hasAny(['tires_ok', 'lights_ok', 'brakes_ok', 'fuel_ok', 'cargo_lock_ok']);

        if ($hasChecklistInput || $request->boolean('require_checklist')) {
            $checklistResponse = $this->processChecklistSubmission($request, $courier);
            if ($checklistResponse !== null) {
                return $checklistResponse;
            }
        }

        $hasActiveTask = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        $nextStatus = $hasActiveTask ? 'on_delivery' : 'active';

        DB::transaction(function () use ($courier, $validated, $nextStatus) {
            $updateFields = [
                'is_online'    => true,
                'status'       => $nextStatus,
                'last_seen_at' => now(),
            ];

            if (Schema::hasColumn('couriers', 'current_latitude')) {
                $updateFields['current_latitude'] = round((float) $validated['latitude'], 7);
                $updateFields['current_longitude'] = round((float) $validated['longitude'], 7);
                $updateFields['location_accuracy'] = isset($validated['accuracy']) ? round((float) $validated['accuracy'], 2) : 10.0;
                $updateFields['location_updated_at'] = now();
            }

            $courier->forceFill($updateFields)->save();

            if (Schema::hasTable('courier_locations')) {
                DB::table('courier_locations')->insert([
                    'courier_id'           => $courier->id,
                    'pickup_assignment_id' => null,
                    'latitude'             => round((float) $validated['latitude'], 7),
                    'longitude'            => round((float) $validated['longitude'], 7),
                    'accuracy'             => isset($validated['accuracy']) ? round((float) $validated['accuracy'], 2) : 10.0,
                    'speed'                => 0.0,
                    'heading'              => 0.0,
                    'recorded_at'          => now(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        });

        $courier->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'You are now On Duty and ready for first-mile pickup assignments.',
            'data'    => [
                'is_online' => true,
                'status'    => $courier->status,
                'user'      => $this->formatCourierPayload($courier->load(['logisticsProfile', 'currentHubAssignment.hub'])),
            ],
        ]);
    }

    public function submitPreTripChecklist(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $normalizedStatus = strtolower(trim((string) $courier->status));
        if (in_array($normalizedStatus, ['suspended', 'inactive', 'rejected'], true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your Courier account is suspended and cannot go On Duty.',
            ], 403);
        }

        if (! $request->filled('latitude') || ! $request->filled('longitude')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Location permission is required to complete the pre-trip checklist and go On Duty.',
                'errors'  => [
                    'location' => ['Location access is required to go On Duty.'],
                ],
            ], 422);
        }

        $failureResponse = $this->processChecklistSubmission($request, $courier);
        if ($failureResponse !== null) {
            return $failureResponse;
        }

        $hasActiveTask = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        $courier->forceFill([
            'is_online'                => true,
            'status'                   => $hasActiveTask ? 'on_delivery' : 'active',
            'last_seen_at'             => now(),
            'current_latitude'         => round((float) $request->input('latitude'), 7),
            'current_longitude'        => round((float) $request->input('longitude'), 7),
            'location_updated_at'      => now(),
            'last_checklist_passed_at' => now(),
        ])->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pre-trip safety checklist passed! You are now On Duty.',
            'data'    => [
                'is_online' => true,
                'user'      => $this->formatCourierPayload($courier->fresh(['logisticsProfile', 'currentHubAssignment.hub'])),
            ],
        ]);
    }

    protected function processChecklistSubmission(Request $request, Courier $courier): ?JsonResponse
    {
        $source = is_array($request->input('checklist')) ? $request->input('checklist') : $request->all();

        $requiredKeys = [
            'tires_ok'      => 'Tires',
            'lights_ok'     => 'Lights',
            'brakes_ok'     => 'Brakes',
            'fuel_ok'       => 'Fuel',
            'cargo_lock_ok' => 'Cargo door lock',
        ];

        $missingLabels = [];
        $failedLabels = [];
        $boolValues = [];

        foreach ($requiredKeys as $key => $label) {
            if (! array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
                $missingLabels[] = $label;
                continue;
            }
            $val = filter_var($source[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val === null) {
                $missingLabels[] = $label;
                continue;
            }
            $boolValues[$key] = $val;
            if (! $val) {
                $failedLabels[] = $label;
            }
        }

        if (! empty($missingLabels)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please answer every pre-trip checklist item before submitting (' . implode(', ', $missingLabels) . ').',
                'errors'  => [
                    'checklist' => ['Please answer every item on the pre-trip checklist.'],
                ],
            ], 422);
        }

        $notes = trim((string) ($source['notes'] ?? $request->input('notes', '')));

        if (! empty($failedLabels)) {
            if (mb_strlen($notes) < 10 || mb_strlen($notes) > 300) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'A note between 10 and 300 characters is required when any checklist item is marked Not OK (' . implode(', ', $failedLabels) . ').',
                    'errors'  => [
                        'notes' => ['Please enter a note (10 to 300 characters) explaining the issue with: ' . implode(', ', $failedLabels) . '.'],
                    ],
                ], 422);
            }

            if (Schema::hasTable('courier_shift_checklists')) {
                DB::table('courier_shift_checklists')->insert([
                    'courier_id'           => $courier->id,
                    'logistics_profile_id' => $courier->logistics_profile_id,
                    'tires_ok'             => $boolValues['tires_ok'],
                    'lights_ok'            => $boolValues['lights_ok'],
                    'brakes_ok'            => $boolValues['brakes_ok'],
                    'fuel_ok'              => $boolValues['fuel_ok'],
                    'cargo_lock_ok'        => $boolValues['cargo_lock_ok'],
                    'all_passed'           => false,
                    'failed_items_summary' => implode(', ', $failedLabels),
                    'notes'                => $notes,
                    'latitude'             => $request->filled('latitude') ? round((float) $request->input('latitude'), 7) : null,
                    'longitude'            => $request->filled('longitude') ? round((float) $request->input('longitude'), 7) : null,
                    'submitted_at'         => now(),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            $courier->forceFill([
                'is_online'    => false,
                'status'       => 'active',
                'last_seen_at' => now(),
            ])->save();

            $logisticsUser = $courier->logisticsProfile?->user;
            if ($logisticsUser) {
                try {
                    $logisticsUser->notify(new OrderLifecycleNotification(
                        title: "Courier Pre-Trip Checklist Issue ({$courier->full_name})",
                        message: "Courier {$courier->full_name} ({$courier->plate_number}) reported Not OK on: " . implode(', ', $failedLabels) . ". Note: \"{$notes}\". Courier remains Off Duty.",
                        type: 'warning',
                        url: route('logistics.couriers.index')
                    ));
                } catch (\Throwable $e) {
                }
            }

            AuditLoggerService::log(
                action: 'courier_pretrip_checklist_failed',
                targetType: 'Courier',
                targetId: (int) $courier->id,
                targetReference: $courier->full_name . ' (' . $courier->plate_number . ')',
                oldValues: ['is_online' => (bool) $courier->is_online],
                newValues: ['is_online' => false, 'failed_items' => $failedLabels],
                reason: $notes,
                actorType: 'Courier',
                actorId: (int) $courier->id,
                actorName: $courier->full_name
            );

            return response()->json([
                'status'       => 'warning',
                'all_passed'   => false,
                'is_online'    => false,
                'failed_items' => $failedLabels,
                'message'      => 'Pre-trip issue reported to Logistics (' . implode(', ', $failedLabels) . '). You will remain Off Duty until the vehicle issue is resolved.',
                'data'         => [
                    'is_online' => false,
                    'user'      => $this->formatCourierPayload($courier->fresh(['logisticsProfile', 'currentHubAssignment.hub'])),
                ],
            ]);
        }

        if (Schema::hasTable('courier_shift_checklists')) {
            DB::table('courier_shift_checklists')->insert([
                'courier_id'           => $courier->id,
                'logistics_profile_id' => $courier->logistics_profile_id,
                'tires_ok'             => true,
                'lights_ok'            => true,
                'brakes_ok'            => true,
                'fuel_ok'              => true,
                'cargo_lock_ok'        => true,
                'all_passed'           => true,
                'failed_items_summary' => null,
                'notes'                => $notes !== '' ? $notes : null,
                'latitude'             => $request->filled('latitude') ? round((float) $request->input('latitude'), 7) : null,
                'longitude'            => $request->filled('longitude') ? round((float) $request->input('longitude'), 7) : null,
                'submitted_at'         => now(),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        if (Schema::hasColumn('couriers', 'last_checklist_passed_at')) {
            $courier->forceFill(['last_checklist_passed_at' => now()])->save();
        }

        return null;
    }

    public function updateVehicleAndDocuments(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $hasActiveTrip = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        if ($hasActiveTrip) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vehicle details and compliance documents cannot be modified while an active pickup task is in progress.',
            ], 422);
        }

        if ($request->filled('plate_number')) {
            $request->merge([
                'plate_number' => strtoupper(trim((string) $request->input('plate_number'))),
            ]);
        }

        $validated = $request->validate([
            'vehicle_type'        => ['nullable', 'string', 'max:60'],
            'plate_number'        => [
                'nullable',
                'string',
                'min:4',
                'max:20',
                'regex:/^[A-Z0-9\-\s]+$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($courier) {
                    $normalizedPlate = strtoupper(preg_replace('/[\s\-]+/', '', trim((string) $value)));
                    if ($normalizedPlate === '') {
                        return;
                    }

                    $existsInCouriers = Courier::where('id', '!=', $courier->id)
                        ->whereRaw("UPPER(REPLACE(REPLACE(TRIM(plate_number), ' ', ''), '-', '')) = ?", [$normalizedPlate])
                        ->exists();

                    $existsInRiders = Rider::whereRaw("UPPER(REPLACE(REPLACE(TRIM(plate_number), ' ', ''), '-', '')) = ?", [$normalizedPlate])
                        ->exists();

                    if ($existsInCouriers || $existsInRiders) {
                        $fail("Plate number \"{$value}\" is already registered to another Courier or Rider across Storkia.");
                    }
                },
            ],
            'load_capacity'       => ['nullable', 'integer', 'min:50', 'max:20000'],
            'license_expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
            'avatar_file'         => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'license_file'        => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
            'or_cr_file'          => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
            'nbi_clearance_file'  => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
            'remarks'             => ['nullable', 'string', 'max:300'],
        ], [
            'plate_number.regex'                 => 'Plate number may only contain uppercase letters, numbers, spaces, and hyphens.',
            'license_expiry_date.after_or_equal' => 'Driver\'s license expiry date cannot be in the past. Please enter today\'s date or a future expiry date.',
            'load_capacity.min'                  => 'Vehicle load capacity must be at least 50 kg.',
            'load_capacity.max'                  => 'Vehicle load capacity cannot exceed 20,000 kg.',
            'license_file.mimetypes'             => 'Driver\'s License must be a valid JPG, PNG, WEBP, or PDF file (checked by file content).',
            'license_file.mimes'                 => 'Driver\'s License must have a .jpg, .jpeg, .png, .webp, or .pdf extension.',
            'license_file.max'                   => 'Driver\'s License file size must not exceed 5.0 MB (5,120 KB).',
            'or_cr_file.mimetypes'               => 'Vehicle OR/CR must be a valid JPG, PNG, WEBP, or PDF file (checked by file content).',
            'or_cr_file.mimes'                   => 'Vehicle OR/CR must have a .jpg, .jpeg, .png, .webp, or .pdf extension.',
            'or_cr_file.max'                     => 'Vehicle OR/CR file size must not exceed 5.0 MB (5,120 KB).',
            'nbi_clearance_file.mimetypes'       => 'NBI Clearance must be a valid JPG, PNG, WEBP, or PDF file (checked by file content).',
            'nbi_clearance_file.mimes'           => 'NBI Clearance must have a .jpg, .jpeg, .png, .webp, or .pdf extension.',
            'nbi_clearance_file.max'             => 'NBI Clearance file size must not exceed 5.0 MB (5,120 KB).',
        ]);

        $previousPlate = $courier->plate_number;
        $previousCapacity = (int) $courier->load_capacity;

        $avatarPath = $courier->avatar_path;
        $licensePath = $courier->license_path;
        $orCrPath = $courier->or_cr_path;
        $nbiPath = $courier->nbi_clearance_path;
        $flaggedForReverification = false;

        if ($request->hasFile('avatar_file') && Schema::hasColumn('couriers', 'avatar_path')) {
            $avatarPath = $request->file('avatar_file')->store('courier_avatars', 'public');
        }
        if ($request->hasFile('license_file')) {
            $licensePath = $request->file('license_file')->store('courier_documents/licenses', 'public');
            $flaggedForReverification = true;
        }
        if ($request->hasFile('or_cr_file')) {
            $orCrPath = $request->file('or_cr_file')->store('courier_documents/or_cr', 'public');
            $flaggedForReverification = true;
        }
        if ($request->hasFile('nbi_clearance_file')) {
            $nbiPath = $request->file('nbi_clearance_file')->store('courier_documents/nbi', 'public');
            $flaggedForReverification = true;
        }

        $newPlate = $validated['plate_number'] ?? $courier->plate_number;
        $newCapacity = isset($validated['load_capacity']) ? (int) $validated['load_capacity'] : (int) $courier->load_capacity;
        $newVehicleType = $validated['vehicle_type'] ?? $courier->vehicle_type;
        $newExpiry = $validated['license_expiry_date'] ?? $courier->license_expiry_date?->toDateString();

        if (
            $newPlate !== $previousPlate
            || $newCapacity !== $previousCapacity
            || $newVehicleType !== $courier->vehicle_type
        ) {
            $flaggedForReverification = true;
        }

        DB::transaction(function () use (
            $courier,
            $previousPlate,
            $previousCapacity,
            $newPlate,
            $newCapacity,
            $newVehicleType,
            $newExpiry,
            $avatarPath,
            $licensePath,
            $orCrPath,
            $nbiPath,
            $flaggedForReverification,
            $validated
        ) {
            $updateData = [
                'vehicle_type'        => $newVehicleType,
                'plate_number'        => $newPlate,
                'load_capacity'       => $newCapacity,
                'license_expiry_date' => $newExpiry,
                'license_path'        => $licensePath,
                'or_cr_path'          => $orCrPath,
                'nbi_clearance_path'  => $nbiPath,
            ];

            if (Schema::hasColumn('couriers', 'avatar_path')) {
                $updateData['avatar_path'] = $avatarPath;
            }

            if (Schema::hasColumn('couriers', 'vehicle_verification_status') && $flaggedForReverification) {
                $updateData['vehicle_verification_status'] = 'pending_verification';
            }

            $courier->update($updateData);

            if (Schema::hasTable('courier_vehicle_updates') && $flaggedForReverification) {
                DB::table('courier_vehicle_updates')->insert([
                    'courier_id'             => $courier->id,
                    'logistics_profile_id'   => $courier->logistics_profile_id,
                    'previous_plate_number'  => $previousPlate,
                    'new_plate_number'       => $newPlate,
                    'vehicle_type'           => $newVehicleType,
                    'previous_load_capacity' => $previousCapacity,
                    'new_load_capacity'      => $newCapacity,
                    'license_expiry_date'    => $newExpiry,
                    'license_path'           => $licensePath,
                    'or_cr_path'             => $orCrPath,
                    'nbi_clearance_path'     => $nbiPath,
                    'status'                 => 'pending_verification',
                    'remarks'                => $validated['remarks'] ?? 'Updated via Storkia Courier Mobile App',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }

            AuditLoggerService::log(
                action: 'courier_vehicle_or_documents_updated',
                targetType: 'Courier',
                targetId: (int) $courier->id,
                targetReference: $courier->full_name . ' (' . $newPlate . ')',
                oldValues: [
                    'plate_number'  => $previousPlate,
                    'load_capacity' => $previousCapacity,
                ],
                newValues: [
                    'plate_number'                => $newPlate,
                    'load_capacity'               => $newCapacity,
                    'license_expiry_date'         => $newExpiry,
                    'vehicle_verification_status' => $flaggedForReverification ? 'pending_verification' : 'verified',
                ],
                reason: $validated['remarks'] ?? 'Courier updated vehicle/documents on mobile app.',
                actorType: 'Courier',
                actorId: (int) $courier->id,
                actorName: $courier->full_name
            );
        });

        if ($flaggedForReverification && $courier->logisticsProfile?->user) {
            try {
                $courier->logisticsProfile->user->notify(new OrderLifecycleNotification(
                    title: "Courier Vehicle/Document Update Pending Verification ({$courier->full_name})",
                    message: "Courier {$courier->full_name} updated vehicle/documents (Plate: {$newPlate}, Capacity: " . number_format($newCapacity) . " kg). Flagged for re-verification.",
                    type: 'info',
                    url: route('logistics.couriers.index')
                ));
            } catch (\Throwable $e) {
            }
        }

        $courier->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => $flaggedForReverification
                ? 'Profile, vehicle details, and documents saved and flagged for Logistics re-verification.'
                : 'Profile details updated successfully.',
            'data'    => [
                'user' => $this->formatCourierPayload($courier->load(['logisticsProfile', 'currentHubAssignment.hub'])),
            ],
        ]);
    }

    public function viewDocument(Request $request, string $type)
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $path = match ($type) {
            'license'                  => $courier->license_path,
            'or_cr'                    => $courier->or_cr_path,
            'nbi', 'nbi_clearance'     => $courier->nbi_clearance_path,
            default                    => null,
        };

        if (! $path) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Requested document record is not available.',
            ], 404);
        }

        $existsPublic = Storage::disk('public')->exists($path);
        $existsLocal = Storage::disk('local')->exists($path);

        if ($request->boolean('download') || $request->boolean('raw')) {
            if ($existsPublic) {
                return response()->file(Storage::disk('public')->path($path));
            }
            if ($existsLocal) {
                return response()->file(Storage::disk('local')->path($path));
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'type'      => $type,
                'path'      => $path,
                'url'       => asset('storage/' . ltrim($path, '/')),
                'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                'exists'    => $existsPublic || $existsLocal,
            ],
        ]);
    }

    protected function checkStaleDutyTimeout(Courier $courier): void
    {
        if (! $courier->is_online) {
            return;
        }

        $hasActiveTask = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
            ->exists();

        if ($hasActiveTask) {
            return;
        }

        $locTime = $courier->location_updated_at ? Carbon::parse($courier->location_updated_at) : null;
        $seenTime = $courier->last_seen_at ? Carbon::parse($courier->last_seen_at) : null;

        $lastActivity = match (true) {
            $locTime && $seenTime => $locTime->gt($seenTime) ? $locTime : $seenTime,
            $locTime !== null     => $locTime,
            default               => $seenTime,
        };

        if ($lastActivity && $lastActivity->lt(now()->subMinutes(30))) {
            $courier->forceFill([
                'is_online' => false,
                'status'    => 'active',
            ])->save();
        }
    }

    public function formatCourierPayload(Courier $courier): array
    {
        $courier->loadMissing(['logisticsProfile', 'currentHubAssignment.hub']);

        $assignedHub = $courier->assigned_hub;
        $logisticsProfile = $courier->logisticsProfile;
        $hubCoords = CourierMobileSchemaService::resolveCoordinatesForAddress(
            [
                'province'     => $assignedHub?->province ?? $logisticsProfile?->province,
                'municipality' => $assignedHub?->municipality ?? $logisticsProfile?->municipality,
                'barangay'     => $assignedHub?->barangay ?? $logisticsProfile?->barangay,
            ],
            $assignedHub ?? $logisticsProfile
        );

        $wallet = $this->roleWalletService->getOrCreateWallet('Courier', (int) $courier->id, $courier->full_name);

        $licenseNumber = $courier->license_no ?? ($courier->getAttributes()['license_number'] ?? 'N/A');
        $dlCodes = is_array($courier->dl_codes)
            ? $courier->dl_codes
            : (json_decode((string) $courier->dl_codes, true) ?: ['B', 'B2', 'C']);

        $statusRaw = strtolower(trim((string) $courier->status));
        $isSuspended = in_array($statusRaw, ['suspended', 'inactive', 'rejected'], true);
        $isOnline = (bool) $courier->is_online && ! $isSuspended;

        $statusLabel = match (true) {
            $isSuspended                  => 'SUSPENDED',
            $statusRaw === 'on_delivery'  => 'ON PICKUP RUN',
            $isOnline                     => 'ACTIVE (ON DUTY)',
            default                       => 'OFF DUTY',
        };

        return [
            'id'                          => (int) $courier->id,
            'first_name'                  => (string) $courier->first_name,
            'last_name'                   => (string) $courier->last_name,
            'full_name'                   => $courier->full_name,
            'email'                       => (string) $courier->email,
            'contact_no'                  => (string) ($courier->contact_no ?? ''),
            'avatar_url'                  => $courier->avatar_url,
            'birthday'                    => $courier->birthday?->format('Y-m-d'),
            'status'                      => $isSuspended ? 'suspended' : ($isOnline ? $statusRaw : 'off_duty'),
            'status_label'                => $statusLabel,
            'is_online'                   => $isOnline,
            'is_suspended'                => $isSuspended,
            'sla_strikes'                 => (int) ($courier->sla_strikes ?? 0),
            'license_no'                  => (string) $licenseNumber,
            'license_number'              => (string) $licenseNumber,
            'license_type'                => (string) ($courier->license_type ?: 'Professional'),
            'dl_codes'                    => array_values($dlCodes),
            'dl_codes_formatted'          => implode(', ', $dlCodes),
            'license_expiry_date'         => $courier->license_expiry_date?->format('Y-m-d'),
            'license_expiry_formatted'    => $courier->license_expiry_date?->format('m/d/Y'),
            'vehicle_type'                => (string) ($courier->vehicle_type ?? 'Van'),
            'plate_number'                => (string) ($courier->plate_number ?? ''),
            'load_capacity'               => (int) ($courier->load_capacity ?? 800),
            'load_capacity_formatted'     => number_format((int) ($courier->load_capacity ?? 800)) . ' kg',
            'vehicle_verification_status' => (string) ($courier->vehicle_verification_status ?? 'verified'),
            'last_checklist_passed_at'    => $courier->last_checklist_passed_at
                ? Carbon::parse($courier->last_checklist_passed_at)->toIso8601String()
                : null,
            'home_hub'                    => (string) ($assignedHub?->hub_name ?? $courier->home_hub ?? 'Main Hub'),
            'assigned_municipality'       => $courier->assigned_municipality,
            'current_latitude'            => $courier->current_latitude !== null ? (float) $courier->current_latitude : null,
            'current_longitude'           => $courier->current_longitude !== null ? (float) $courier->current_longitude : null,
            'location_updated_at'         => $courier->location_updated_at
                ? Carbon::parse($courier->location_updated_at)->toIso8601String()
                : null,
            'documents'                   => [
                'license' => [
                    'title'     => "Professional Driver's License",
                    'path'      => $courier->license_path,
                    'url'       => $courier->license_path ? asset('storage/' . ltrim($courier->license_path, '/')) : null,
                    'extension' => strtolower(pathinfo((string) $courier->license_path, PATHINFO_EXTENSION)) ?: 'jpg',
                ],
                'or_cr' => [
                    'title'     => 'Vehicle Official Receipt & Certificate of Registration (OR/CR)',
                    'path'      => $courier->or_cr_path,
                    'url'       => $courier->or_cr_path ? asset('storage/' . ltrim($courier->or_cr_path, '/')) : null,
                    'extension' => strtolower(pathinfo((string) $courier->or_cr_path, PATHINFO_EXTENSION)) ?: 'pdf',
                ],
                'nbi_clearance' => [
                    'title'     => 'NBI Clearance',
                    'path'      => $courier->nbi_clearance_path,
                    'url'       => $courier->nbi_clearance_path ? asset('storage/' . ltrim($courier->nbi_clearance_path, '/')) : null,
                    'extension' => strtolower(pathinfo((string) $courier->nbi_clearance_path, PATHINFO_EXTENSION)) ?: 'pdf',
                ],
            ],
            'logistics_company'           => $logisticsProfile ? [
                'id'            => (int) $logisticsProfile->id,
                'business_name' => (string) $logisticsProfile->business_name,
                'contact_no'    => (string) ($logisticsProfile->contact_no ?? ''),
                'province'      => (string) ($logisticsProfile->province ?? ''),
                'municipality'  => (string) ($logisticsProfile->municipality ?? ''),
                'full_address'  => $logisticsProfile->full_address,
                'logo_url'      => $logisticsProfile->logo_path ? asset('storage/' . $logisticsProfile->logo_path) : null,
            ] : null,
            'assigned_hub'                => $assignedHub ? [
                'id'             => (int) $assignedHub->id,
                'hub_code'       => (string) $assignedHub->hub_code,
                'hub_name'       => (string) $assignedHub->hub_name,
                'is_main_hub'    => (bool) $assignedHub->is_main_hub,
                'display_type'   => $assignedHub->display_type,
                'contact_no'     => (string) ($assignedHub->contact_no ?: ($logisticsProfile?->contact_no ?? '')),
                'province'       => (string) $assignedHub->province,
                'municipality'   => (string) $assignedHub->municipality,
                'barangay'       => (string) ($assignedHub->barangay ?? ''),
                'full_address'   => $assignedHub->full_address,
                'latitude'       => $hubCoords['latitude'],
                'longitude'      => $hubCoords['longitude'],
                'is_approximate' => $hubCoords['is_approximate'],
            ] : null,
            'wallet'                      => [
                'available_balance' => round((float) $wallet->available_balance, 2),
                'pending_balance'   => round((float) $wallet->pending_balance, 2),
                'total_earned'      => round((float) $wallet->total_earned, 2),
                'total_withdrawn'   => round((float) $wallet->total_withdrawn, 2),
            ],
        ];
    }
}