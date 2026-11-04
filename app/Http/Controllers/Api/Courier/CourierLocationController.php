<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\PickupAssignment;
use App\Services\CourierMobileSchemaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourierLocationController extends Controller
{
    public function updateLocation(Request $request): JsonResponse
    {
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        if (strtolower((string) $courier->status) === 'suspended') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Suspended courier accounts cannot transmit location telemetry.',
            ], 403);
        }

        $validated = $request->validate([
            'latitude'             => ['required', 'numeric', 'between:-90,90'],
            'longitude'            => ['required', 'numeric', 'between:-180,180'],
            'accuracy'             => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'speed'                => ['nullable', 'numeric', 'min:0'],
            'heading'              => ['nullable', 'numeric', 'between:0,360'],
            'pickup_assignment_id' => ['nullable', 'integer'],
            'recorded_at'          => ['nullable', 'date'],
        ]);

        $recordedAt = ! empty($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        if ($recordedAt->gt(now()->addMinutes(5))) {
            $recordedAt = now();
        }

        $assignmentId = null;
        if (! empty($validated['pickup_assignment_id'])) {
            $ownedAssignment = PickupAssignment::where('id', (int) $validated['pickup_assignment_id'])
                ->where('courier_id', $courier->id)
                ->first();
            $assignmentId = $ownedAssignment?->id;
        } else {
            $activeAssignment = PickupAssignment::where('courier_id', $courier->id)
                ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
                ->orderByDesc('updated_at')
                ->first();
            $assignmentId = $activeAssignment?->id;
        }

        $lat = round((float) $validated['latitude'], 7);
        $lng = round((float) $validated['longitude'], 7);
        $acc = isset($validated['accuracy']) ? round((float) $validated['accuracy'], 2) : 10.0;
        $spd = isset($validated['speed']) ? round((float) $validated['speed'], 2) : 0.0;
        $hdg = isset($validated['heading']) ? round((float) $validated['heading'], 2) : 0.0;

        $duplicateExists = CourierLocation::where('courier_id', $courier->id)
            ->where('recorded_at', $recordedAt)
            ->exists();

        $locationRecord = null;
        if (! $duplicateExists) {
            $locationRecord = CourierLocation::create([
                'courier_id'           => $courier->id,
                'pickup_assignment_id' => $assignmentId,
                'latitude'             => $lat,
                'longitude'            => $lng,
                'accuracy'             => $acc,
                'speed'                => $spd,
                'heading'              => $hdg,
                'recorded_at'          => $recordedAt,
            ]);
        }

        $currentUpdatedAt = $courier->location_updated_at ? Carbon::parse($courier->location_updated_at) : null;
        if (! $currentUpdatedAt || $recordedAt->gte($currentUpdatedAt)) {
            $updateCourier = [
                'last_seen_at' => now(),
            ];
            if (Schema::hasColumn('couriers', 'current_latitude')) {
                $updateCourier['current_latitude'] = $lat;
                $updateCourier['current_longitude'] = $lng;
                $updateCourier['location_accuracy'] = $acc;
                $updateCourier['location_updated_at'] = $recordedAt;
            }
            $courier->forceFill($updateCourier)->save();
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Courier GPS telemetry recorded.',
            'data'    => [
                'location_id'          => $locationRecord?->id,
                'courier_id'           => $courier->id,
                'pickup_assignment_id' => $assignmentId,
                'latitude'             => $lat,
                'longitude'            => $lng,
                'accuracy'             => $acc,
                'recorded_at'          => $recordedAt->toIso8601String(),
            ],
        ]);
    }

    public function batchUpdateLocations(Request $request): JsonResponse
    {
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $validated = $request->validate([
            'locations'                          => ['required', 'array', 'min:1', 'max:100'],
            'locations.*.latitude'               => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude'              => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy'               => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'locations.*.speed'                  => ['nullable', 'numeric', 'min:0'],
            'locations.*.heading'                => ['nullable', 'numeric', 'between:0,360'],
            'locations.*.pickup_assignment_id'   => ['nullable', 'integer'],
            'locations.*.recorded_at'            => ['required', 'date'],
        ]);

        $activeAssignment = PickupAssignment::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit_to_hub'])
            ->orderByDesc('updated_at')
            ->first();

        $insertedCount = 0;
        $latestPing = null;

        DB::transaction(function () use ($courier, $validated, $activeAssignment, &$insertedCount, &$latestPing) {
            $sorted = collect($validated['locations'])
                ->sortBy(fn ($item) => Carbon::parse($item['recorded_at'])->timestamp)
                ->values();

            foreach ($sorted as $ping) {
                $recordedAt = Carbon::parse($ping['recorded_at']);
                if ($recordedAt->gt(now()->addMinutes(5))) {
                    $recordedAt = now();
                }

                $alreadySaved = CourierLocation::where('courier_id', $courier->id)
                    ->where('recorded_at', $recordedAt)
                    ->exists();

                if ($alreadySaved) {
                    continue;
                }

                $assignmentId = ! empty($ping['pickup_assignment_id'])
                    ? (int) $ping['pickup_assignment_id']
                    : $activeAssignment?->id;

                CourierLocation::create([
                    'courier_id'           => $courier->id,
                    'pickup_assignment_id' => $assignmentId,
                    'latitude'             => round((float) $ping['latitude'], 7),
                    'longitude'            => round((float) $ping['longitude'], 7),
                    'accuracy'             => isset($ping['accuracy']) ? round((float) $ping['accuracy'], 2) : 10.0,
                    'speed'                => isset($ping['speed']) ? round((float) $ping['speed'], 2) : 0.0,
                    'heading'              => isset($ping['heading']) ? round((float) $ping['heading'], 2) : 0.0,
                    'recorded_at'          => $recordedAt,
                ]);

                $insertedCount++;
                $latestPing = [
                    'latitude'    => round((float) $ping['latitude'], 7),
                    'longitude'   => round((float) $ping['longitude'], 7),
                    'accuracy'    => isset($ping['accuracy']) ? round((float) $ping['accuracy'], 2) : 10.0,
                    'recorded_at' => $recordedAt,
                ];
            }

            if ($latestPing) {
                $currentUpdatedAt = $courier->location_updated_at ? Carbon::parse($courier->location_updated_at) : null;
                if (! $currentUpdatedAt || $latestPing['recorded_at']->gte($currentUpdatedAt)) {
                    $courier->forceFill([
                        'current_latitude'    => $latestPing['latitude'],
                        'current_longitude'   => $latestPing['longitude'],
                        'location_accuracy'   => $latestPing['accuracy'],
                        'location_updated_at' => $latestPing['recorded_at'],
                        'last_seen_at'        => now(),
                    ])->save();
                }
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => "Synchronized {$insertedCount} queued offline location update(s).",
            'data'    => [
                'synced_count' => $insertedCount,
            ],
        ]);
    }

    public function latestLocation(Request $request): JsonResponse
    {
        CourierMobileSchemaService::ensureTablesExist();

        /** @var Courier $courier */
        $courier = $request->user();

        $latest = CourierLocation::where('courier_id', $courier->id)
            ->orderByDesc('recorded_at')
            ->first();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'courier_id'  => $courier->id,
                'is_online'   => (bool) $courier->is_online,
                'latitude'    => $latest ? (float) $latest->latitude : ($courier->current_latitude ? (float) $courier->current_latitude : null),
                'longitude'   => $latest ? (float) $latest->longitude : ($courier->current_longitude ? (float) $courier->current_longitude : null),
                'accuracy'    => $latest ? (float) $latest->accuracy : ($courier->location_accuracy ? (float) $courier->location_accuracy : null),
                'recorded_at' => $latest?->recorded_at?->toIso8601String() ?? ($courier->location_updated_at ? Carbon::parse($courier->location_updated_at)->toIso8601String() : null),
            ],
        ]);
    }
}