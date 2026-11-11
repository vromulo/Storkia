<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Api\Concerns\IssuesApiTokens;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Rider;
use App\Models\RiderLocation;
use App\Services\AuditLoggerService;
use App\Services\RiderMobileSchemaService;
use App\Services\StorkSchemaBootstrapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthApiController extends Controller
{
    use IssuesApiTokens;

    public function login(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $normalizedEmail = strtolower(trim((string) $request->input('email')));

        /** @var Rider|null $rider */
        $rider = Rider::with(['logisticsProfile', 'currentHubAssignment.hub', 'activeBarangayCoverages'])
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        if (! $rider || ! Hash::check((string) $request->input('password'), $rider->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials provided.',
            ], 401);
        }

        $allowedStatuses = (array) config('security.allowed_statuses.rider', ['approved', 'active']);

        if (! in_array($rider->status, $allowedStatuses, true)) {
            $msg = $rider->status === 'pending'
                ? 'Your Rider account is currently pending approval by your Logistics company.'
                : 'Your Rider account has been suspended or restricted. Please contact your Logistics hub.';

            return response()->json([
                'status'       => 'error',
                'account_code' => $rider->status,
                'message'      => $msg,
            ], 403);
        }

        $rider->update([
            'last_seen_at' => now(),
        ]);

        $token = $this->issueApiToken($rider, 'rider', 'rider_auth_token');

        return response()->json([
            'status'       => 'success',
            'message'      => 'Login successful',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $this->formatRiderProfile($rider->fresh(['logisticsProfile', 'currentHubAssignment.hub', 'activeBarangayCoverages'])),
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Rider|null $rider */
        $rider = $request->user();

        if ($rider instanceof Rider) {
            $updates = ['last_seen_at' => now()];
            if (Schema::hasColumn('riders', 'is_online')) {
                $updates['is_online'] = false;
            }
            $rider->update($updates);
            $rider->currentAccessToken()?->delete();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        if ($request->filled('rider_id') && (int) $request->query('rider_id') !== (int) $rider->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden: You cannot view another rider\'s profile.',
            ], 403);
        }

        $rider->loadMissing(['logisticsProfile', 'currentHubAssignment.hub', 'activeBarangayCoverages']);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatRiderProfile($rider),
        ]);
    }

    public function submitPreTripChecklist(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'tires_ok'       => ['required', 'boolean'],
            'lights_ok'      => ['required', 'boolean'],
            'brakes_ok'      => ['required', 'boolean'],
            'fuel_ok'        => ['required', 'boolean'],
            'helmet_gear_ok' => ['nullable', 'boolean'],
            'thermal_bag_ok' => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:300'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $checks = [
            'Tires'        => (bool) $validated['tires_ok'],
            'Lights'       => (bool) $validated['lights_ok'],
            'Brakes'       => (bool) $validated['brakes_ok'],
            'Fuel'         => (bool) $validated['fuel_ok'],
            'Helmet/Gear'  => (bool) ($validated['helmet_gear_ok'] ?? true),
            'Thermal Bag'  => (bool) ($validated['thermal_bag_ok'] ?? true),
        ];

        $failedItems = array_keys(array_filter($checks, fn ($passed) => ! $passed));
        $allPassed = empty($failedItems);
        $failedSummary = $allPassed ? null : ('Failed: ' . implode(', ', $failedItems));

        $checklistId = DB::table('rider_shift_checklists')->insertGetId([
            'rider_id'             => $rider->id,
            'logistics_profile_id' => $rider->logistics_profile_id,
            'tires_ok'             => $checks['Tires'],
            'lights_ok'            => $checks['Lights'],
            'brakes_ok'            => $checks['Brakes'],
            'fuel_ok'              => $checks['Fuel'],
            'helmet_gear_ok'       => $checks['Helmet/Gear'],
            'thermal_bag_ok'       => $checks['Thermal Bag'],
            'all_passed'           => $allPassed,
            'failed_items_summary' => $failedSummary,
            'notes'                => ! empty($validated['notes']) ? trim($validated['notes']) : null,
            'latitude'             => isset($validated['latitude']) ? round((float) $validated['latitude'], 7) : null,
            'longitude'            => isset($validated['longitude']) ? round((float) $validated['longitude'], 7) : null,
            'submitted_at'         => now(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        if ($allPassed && Schema::hasColumn('riders', 'last_checklist_passed_at')) {
            $rider->update(['last_checklist_passed_at' => now()]);
        } elseif (! $allPassed && Schema::hasColumn('riders', 'is_online')) {
            $rider->update(['is_online' => false]);
        }

        return response()->json([
            'status'  => $allPassed ? 'success' : 'warning',
            'message' => $allPassed
                ? 'Pre-shift safety checklist passed! You are cleared for last-mile delivery dispatch.'
                : "Safety check flagged ({$failedSummary}). Please resolve vehicle issues before going Online.",
            'data'    => [
                'checklist_id'             => $checklistId,
                'all_passed'               => $allPassed,
                'failed_items_summary'     => $failedSummary,
                'last_checklist_passed_at' => $rider->fresh()->last_checklist_passed_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateAvailability(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $allowedStatuses = (array) config('security.allowed_statuses.rider', ['approved', 'active']);
        if (! in_array($rider->status, $allowedStatuses, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your Rider account is suspended or not approved and cannot go Online.',
            ], 403);
        }

        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $wantsOnline = (bool) $validated['is_online'];

        if ($wantsOnline && (! isset($validated['latitude']) || ! isset($validated['longitude']))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Location permission and current GPS coordinates are required to go Online.',
                'errors'  => [
                    'location' => ['Location access is required before going Online. Please enable Location in your phone Settings.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($rider, $wantsOnline, $validated) {
            $updates = [
                'is_online'    => $wantsOnline,
                'last_seen_at' => now(),
            ];

            if (isset($validated['latitude'], $validated['longitude'])) {
                $updates['current_latitude'] = round((float) $validated['latitude'], 7);
                $updates['current_longitude'] = round((float) $validated['longitude'], 7);
                $updates['location_accuracy'] = isset($validated['accuracy']) ? round((float) $validated['accuracy'], 2) : null;
                $updates['location_updated_at'] = now();

                if (Schema::hasTable('rider_locations')) {
                    RiderLocation::create([
                        'rider_id'               => $rider->id,
                        'delivery_assignment_id' => null,
                        'latitude'               => $updates['current_latitude'],
                        'longitude'              => $updates['current_longitude'],
                        'accuracy'               => $updates['location_accuracy'],
                        'recorded_at'            => now(),
                    ]);
                }
            }

            $rider->update($updates);
        });

        $rider->refresh()->loadMissing(['logisticsProfile', 'currentHubAssignment.hub', 'activeBarangayCoverages']);

        return response()->json([
            'status'  => 'success',
            'message' => $wantsOnline
                ? 'You are now Online and ready to receive delivery assignments.'
                : 'You are now Offline. Any already collected packages remain in your active list.',
            'data'    => $this->formatRiderProfile($rider),
        ]);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'latitude'               => ['required', 'numeric', 'between:-90,90'],
            'longitude'              => ['required', 'numeric', 'between:-180,180'],
            'accuracy'               => ['nullable', 'numeric', 'min:0'],
            'speed'                  => ['nullable', 'numeric', 'min:0'],
            'heading'                => ['nullable', 'numeric', 'between:0,360'],
            'delivery_assignment_id' => ['nullable', 'integer'],
            'recorded_at'            => ['nullable', 'date'],
            'gps_enabled'            => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('gps_enabled', $validated) && $validated['gps_enabled'] === false) {
            $rider->update([
                'is_online'    => false,
                'last_seen_at' => now(),
            ]);

            return response()->json([
                'status'  => 'warning',
                'message' => 'Device GPS location was turned off. You have been marked Offline until location is re-enabled.',
                'data'    => ['is_online' => false],
            ]);
        }

        $assignmentId = $validated['delivery_assignment_id'] ?? null;
        if ($assignmentId !== null) {
            $ownsAssignment = $rider->deliveryAssignments()->where('id', $assignmentId)->exists();
            if (! $ownsAssignment) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unauthorized delivery assignment reference for location update.',
                ], 403);
            }
        }

        $lat = round((float) $validated['latitude'], 7);
        $lng = round((float) $validated['longitude'], 7);
        $acc = isset($validated['accuracy']) ? round((float) $validated['accuracy'], 2) : null;
        $recordedAt = ! empty($validated['recorded_at']) ? Carbon::parse($validated['recorded_at']) : now();

        if ($rider->location_updated_at && $recordedAt->lt($rider->location_updated_at)) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Ignored outdated location packet.',
                'data'    => [
                    'current_latitude'    => (float) $rider->current_latitude,
                    'current_longitude'   => (float) $rider->current_longitude,
                    'location_updated_at' => $rider->location_updated_at?->toIso8601String(),
                ],
            ]);
        }

        $recentlyLogged = Schema::hasTable('rider_locations')
            && RiderLocation::where('rider_id', $rider->id)
                ->where('recorded_at', '>=', now()->subSeconds(5))
                ->exists();

        $rider->update([
            'current_latitude'    => $lat,
            'current_longitude'   => $lng,
            'location_accuracy'   => $acc,
            'location_updated_at' => now(),
            'last_seen_at'        => now(),
        ]);

        if (! $recentlyLogged && Schema::hasTable('rider_locations')) {
            RiderLocation::create([
                'rider_id'               => $rider->id,
                'delivery_assignment_id' => $assignmentId,
                'latitude'               => $lat,
                'longitude'              => $lng,
                'accuracy'               => $acc,
                'speed'                  => isset($validated['speed']) ? round((float) $validated['speed'], 2) : null,
                'heading'                => isset($validated['heading']) ? round((float) $validated['heading'], 2) : null,
                'recorded_at'            => $recordedAt,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Location updated.',
            'data'    => [
                'current_latitude'    => $lat,
                'current_longitude'   => $lng,
                'location_accuracy'   => $acc,
                'location_updated_at' => now()->toIso8601String(),
                'is_online'           => (bool) $rider->is_online,
            ],
        ]);
    }

    public function batchUpdateLocations(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'locations'                            => ['required', 'array', 'min:1', 'max:100'],
            'locations.*.latitude'                 => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude'                => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy'                 => ['nullable', 'numeric', 'min:0'],
            'locations.*.speed'                    => ['nullable', 'numeric', 'min:0'],
            'locations.*.heading'                  => ['nullable', 'numeric', 'between:0,360'],
            'locations.*.delivery_assignment_id'   => ['nullable', 'integer'],
            'locations.*.recorded_at'              => ['nullable', 'date'],
        ]);

        $ownedAssignmentIds = $rider->deliveryAssignments()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rows = [];
        $latestPoint = null;

        foreach ($validated['locations'] as $pt) {
            $asgId = isset($pt['delivery_assignment_id']) ? (int) $pt['delivery_assignment_id'] : null;
            if ($asgId !== null && ! in_array($asgId, $ownedAssignmentIds, true)) {
                $asgId = null;
            }

            $ts = ! empty($pt['recorded_at']) ? Carbon::parse($pt['recorded_at']) : now();
            $lat = round((float) $pt['latitude'], 7);
            $lng = round((float) $pt['longitude'], 7);
            $acc = isset($pt['accuracy']) ? round((float) $pt['accuracy'], 2) : null;

            $rows[] = [
                'rider_id'               => $rider->id,
                'delivery_assignment_id' => $asgId,
                'latitude'               => $lat,
                'longitude'              => $lng,
                'accuracy'               => $acc,
                'speed'                  => isset($pt['speed']) ? round((float) $pt['speed'], 2) : null,
                'heading'                => isset($pt['heading']) ? round((float) $pt['heading'], 2) : null,
                'recorded_at'            => $ts,
                'created_at'             => now(),
                'updated_at'             => now(),
            ];

            if (! $latestPoint || $ts->gte($latestPoint['recorded_at'])) {
                $latestPoint = [
                    'latitude'    => $lat,
                    'longitude'   => $lng,
                    'accuracy'    => $acc,
                    'recorded_at' => $ts,
                ];
            }
        }

        if (! empty($rows) && Schema::hasTable('rider_locations')) {
            DB::table('rider_locations')->insert($rows);
        }

        if ($latestPoint) {
            $rider->update([
                'current_latitude'    => $latestPoint['latitude'],
                'current_longitude'   => $latestPoint['longitude'],
                'location_accuracy'   => $latestPoint['accuracy'],
                'location_updated_at' => now(),
                'last_seen_at'        => now(),
            ]);
        }

        return response()->json([
            'status'         => 'success',
            'inserted_count' => count($rows),
            'message'        => count($rows) . ' GPS location packet(s) synced.',
        ]);
    }

    public function updateVehicleAndDocuments(Request $request): JsonResponse
    {
        StorkSchemaBootstrapService::ensureTablesExist();
        RiderMobileSchemaService::ensureTablesExist();

        /** @var Rider $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'vehicle_type'        => ['nullable', 'string', 'max:60'],
            'plate_number'        => [
                'nullable',
                'string',
                'min:3',
                'max:25',
                function (string $attribute, mixed $value, \Closure $fail) use ($rider) {
                    $norm = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
                    if ($norm === '') {
                        return;
                    }
                    $existsInRiders = Rider::where('id', '!=', $rider->id)
                        ->whereRaw("UPPER(REPLACE(TRIM(plate_number), ' ', '')) = ?", [$norm])
                        ->exists();
                    $existsInCouriers = Courier::whereRaw("UPPER(REPLACE(TRIM(plate_number), ' ', '')) = ?", [$norm])
                        ->exists();

                    if ($existsInRiders || $existsInCouriers) {
                        $fail("The plate number {$value} is already registered to another vehicle.");
                    }
                },
            ],
            'license_no'          => [
                'nullable',
                'string',
                'min:4',
                'max:35',
                function (string $attribute, mixed $value, \Closure $fail) use ($rider) {
                    $norm = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
                    if ($norm === '') {
                        return;
                    }
                    $existsInRiders = Rider::where('id', '!=', $rider->id)
                        ->whereRaw("UPPER(REPLACE(TRIM(license_no), ' ', '')) = ?", [$norm])
                        ->exists();
                    $existsInCouriers = Courier::whereRaw("UPPER(REPLACE(TRIM(license_no), ' ', '')) = ?", [$norm])
                        ->exists();

                    if ($existsInRiders || $existsInCouriers) {
                        $fail("The driver's license number {$value} is already registered to another driver.");
                    }
                },
            ],
            'license_expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
            'license_file'        => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'or_cr_file'          => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $oldPlate = $rider->plate_number;
        $newPlate = ! empty($validated['plate_number'])
            ? strtoupper(trim($validated['plate_number']))
            : $rider->plate_number;

        $oldLicenseNo = $rider->license_no;
        $newLicenseNo = ! empty($validated['license_no'])
            ? strtoupper(trim($validated['license_no']))
            : $rider->license_no;

        $licensePath = $rider->license_path;
        if ($request->hasFile('license_file')) {
            $licensePath = $request->file('license_file')->store('riders/licenses', 'public');
        }

        $orCrPath = $rider->or_cr_path;
        if ($request->hasFile('or_cr_file')) {
            $orCrPath = $request->file('or_cr_file')->store('riders/or_cr', 'public');
        }

        $hasVehicleOrDocChange = ($newPlate !== $oldPlate)
            || ($newLicenseNo !== $oldLicenseNo)
            || $request->hasFile('license_file')
            || $request->hasFile('or_cr_file')
            || (! empty($validated['vehicle_type']) && $validated['vehicle_type'] !== $rider->vehicle_type);

        DB::transaction(function () use (
            $rider,
            $validated,
            $oldPlate,
            $newPlate,
            $oldLicenseNo,
            $newLicenseNo,
            $licensePath,
            $orCrPath,
            $hasVehicleOrDocChange
        ) {
            $updates = [
                'plate_number'        => $newPlate,
                'license_no'          => $newLicenseNo,
                'vehicle_type'        => ! empty($validated['vehicle_type']) ? trim($validated['vehicle_type']) : $rider->vehicle_type,
                'license_expiry_date' => ! empty($validated['license_expiry_date']) ? $validated['license_expiry_date'] : $rider->license_expiry_date,
                'license_path'        => $licensePath,
                'or_cr_path'          => $orCrPath,
            ];

            if ($hasVehicleOrDocChange && Schema::hasColumn('riders', 'vehicle_verification_status')) {
                $updates['vehicle_verification_status'] = 'pending_verification';
            }

            $rider->update($updates);

            if ($hasVehicleOrDocChange && Schema::hasTable('rider_vehicle_updates')) {
                DB::table('rider_vehicle_updates')->insert([
                    'rider_id'              => $rider->id,
                    'logistics_profile_id'  => $rider->logistics_profile_id,
                    'previous_plate_number' => $oldPlate,
                    'new_plate_number'      => $newPlate,
                    'vehicle_type'          => $updates['vehicle_type'],
                    'license_expiry_date'   => $updates['license_expiry_date'],
                    'license_path'          => $licensePath,
                    'or_cr_path'            => $orCrPath,
                    'status'                => 'pending_verification',
                    'remarks'               => 'Updated by Rider via Storkia Rider Mobile App (Flagged for Logistics re-verification).',
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            AuditLoggerService::log(
                action: 'rider_vehicle_or_documents_updated',
                targetType: 'Rider',
                targetId: (int) $rider->id,
                targetReference: "{$rider->full_name} ({$newPlate})",
                oldValues: [
                    'plate_number'        => $oldPlate,
                    'license_no'          => $oldLicenseNo,
                    'license_expiry_date' => $rider->getOriginal('license_expiry_date'),
                ],
                newValues: [
                    'plate_number'                => $newPlate,
                    'license_no'                  => $newLicenseNo,
                    'license_expiry_date'         => $updates['license_expiry_date'],
                    'vehicle_verification_status' => $hasVehicleOrDocChange ? 'pending_verification' : 'verified',
                ],
                reason: 'Rider updated vehicle or compliance documents via mobile app.',
                actorType: 'Rider',
                actorId: (int) $rider->id,
                actorName: $rider->full_name
            );
        });

        $rider->refresh()->loadMissing(['logisticsProfile', 'currentHubAssignment.hub', 'activeBarangayCoverages']);

        return response()->json([
            'status'  => 'success',
            'message' => $hasVehicleOrDocChange
                ? 'Vehicle details and documents saved and flagged for Logistics re-verification.'
                : 'Profile details updated successfully.',
            'data'    => $this->formatRiderProfile($rider),
        ]);
    }

    public function formatRiderProfile(Rider $rider): array
    {
        $hub = $rider->assigned_hub;
        $logistics = $rider->logisticsProfile;

        return [
            'id'                          => (int) $rider->id,
            'logistics_profile_id'        => (int) $rider->logistics_profile_id,
            'first_name'                  => (string) $rider->first_name,
            'last_name'                   => (string) $rider->last_name,
            'full_name'                   => (string) $rider->full_name,
            'birthday'                    => $rider->birthday?->format('Y-m-d'),
            'birthday_formatted'          => $rider->birthday ? ($rider->birthday->format('M d, Y') . ' (' . $rider->birthday->age . ' yrs old)') : 'Not set',
            'email'                       => (string) $rider->email,
            'contact_no'                  => (string) $rider->contact_no,
            'avatar_path'                 => $rider->avatar_path,
            'avatar_url'                  => $rider->avatar_url,
            'license_no'                  => (string) $rider->license_no,
            'license_expiry_date'         => $rider->license_expiry_date?->format('Y-m-d'),
            'license_expiry_formatted'    => $rider->license_expiry_date?->format('m/d/Y'),
            'vehicle_type'                => (string) $rider->vehicle_type,
            'plate_number'                => (string) $rider->plate_number,
            'status'                      => (string) $rider->status,
            'status_label'                => in_array(strtolower((string) $rider->status), ['approved', 'active'], true) ? 'Active' : ucfirst((string) $rider->status),
            'vehicle_verification_status' => (string) ($rider->vehicle_verification_status ?? 'verified'),
            'is_online'                   => (bool) ($rider->is_online ?? false),
            'last_seen_at'                => $rider->last_seen_at?->toIso8601String(),
            'last_checklist_passed_at'    => Schema::hasColumn('riders', 'last_checklist_passed_at') && $rider->last_checklist_passed_at
                ? Carbon::parse($rider->last_checklist_passed_at)->toIso8601String()
                : null,
            'sla_strikes'                 => (int) ($rider->sla_strikes ?? 0),
            'current_latitude'            => $rider->current_latitude !== null ? (float) $rider->current_latitude : null,
            'current_longitude'           => $rider->current_longitude !== null ? (float) $rider->current_longitude : null,
            'location_accuracy'           => $rider->location_accuracy !== null ? (float) $rider->location_accuracy : null,
            'location_updated_at'         => $rider->location_updated_at?->toIso8601String(),
            'license_url'                 => $rider->license_path ? asset('storage/' . ltrim($rider->license_path, '/')) : null,
            'or_cr_url'                   => $rider->or_cr_path ? asset('storage/' . ltrim($rider->or_cr_path, '/')) : null,
            'covered_barangays'           => $rider->covered_barangay_names,
            'formatted_barangay_coverage' => $rider->formatted_barangay_coverage,
            'logistics_company'           => [
                'id'            => $logistics?->id,
                'business_name' => $logistics?->business_name ?? 'Storkia Partner Logistics',
                'contact_no'    => $logistics?->contact_no ?? '',
                'municipality'  => $logistics?->municipality ?? '',
                'province'      => $logistics?->province ?? '',
                'full_address'  => $logistics?->full_address ?? '',
            ],
            'assigned_hub'                => $hub ? [
                'id'           => (int) $hub->id,
                'hub_code'     => (string) $hub->hub_code,
                'hub_name'     => (string) $hub->hub_name,
                'is_main_hub'  => (bool) $hub->is_main_hub,
                'contact_no'   => (string) ($hub->contact_no ?? ''),
                'barangay'     => (string) ($hub->barangay ?? ''),
                'municipality' => (string) ($hub->municipality ?? ''),
                'province'     => (string) ($hub->province ?? ''),
                'full_address' => (string) $hub->full_address,
                'latitude'     => $hub->latitude !== null ? (float) $hub->latitude : null,
                'longitude'    => $hub->longitude !== null ? (float) $hub->longitude : null,
            ] : null,
        ];
    }
}