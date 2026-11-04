<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CourierMobileSchemaService
{
    protected const CACHE_KEY = 'storkia_courier_mobile_schema_v2_ready';

    public static function ensureTablesExist(): void
    {
        if (
            Cache::get(self::CACHE_KEY) === true
            && Schema::hasTable('courier_locations')
            && Schema::hasTable('courier_vehicle_updates')
            && Schema::hasTable('courier_shift_checklists')
            && Schema::hasTable('courier_trip_incidents')
            && Schema::hasColumn('couriers', 'current_latitude')
            && Schema::hasColumn('pickup_assignments', 'decline_reason')
        ) {
            return;
        }

        try {
            if (! Schema::hasTable('couriers') || ! Schema::hasTable('shipments')) {
                return;
            }

            self::applySchemaChanges();

            Cache::put(self::CACHE_KEY, true, now()->addHours(12));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function applySchemaChanges(): void
    {
        self::upgradeCouriersTable();
        self::upgradePickupAssignmentsTable();
        self::upgradeAddressCoordinatesColumns();
        self::createCourierLocationsTable();
        self::createCourierVehicleUpdatesTable();
        self::createCourierShiftChecklistsTable();
        self::createCourierTripIncidentsTable();
    }

    protected static function upgradeCouriersTable(): void
    {
        if (! Schema::hasTable('couriers')) {
            return;
        }

        Schema::table('couriers', function (Blueprint $table) {
            if (! Schema::hasColumn('couriers', 'is_online')) {
                $table->boolean('is_online')->default(false)->index();
            }
            if (! Schema::hasColumn('couriers', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
            if (! Schema::hasColumn('couriers', 'sla_strikes')) {
                $table->unsignedSmallInteger('sla_strikes')->default(0);
            }
            if (! Schema::hasColumn('couriers', 'current_latitude')) {
                $table->decimal('current_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('couriers', 'current_longitude')) {
                $table->decimal('current_longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('couriers', 'location_accuracy')) {
                $table->decimal('location_accuracy', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('couriers', 'location_updated_at')) {
                $table->timestamp('location_updated_at')->nullable();
            }
            if (! Schema::hasColumn('couriers', 'vehicle_verification_status')) {
                $table->string('vehicle_verification_status', 30)->default('verified');
            }
            if (! Schema::hasColumn('couriers', 'last_checklist_passed_at')) {
                $table->timestamp('last_checklist_passed_at')->nullable();
            }
        });
    }

    protected static function upgradePickupAssignmentsTable(): void
    {
        if (! Schema::hasTable('pickup_assignments')) {
            return;
        }

        Schema::table('pickup_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('pickup_assignments', 'declined_at')) {
                $table->timestamp('declined_at')->nullable()->after('handed_over_at');
            }
            if (! Schema::hasColumn('pickup_assignments', 'decline_reason')) {
                $table->string('decline_reason', 300)->nullable()->after('declined_at');
            }
            if (! Schema::hasColumn('pickup_assignments', 'seal_number')) {
                $table->string('seal_number', 30)->nullable()->after('decline_reason');
            }
            if (! Schema::hasColumn('pickup_assignments', 'is_delayed')) {
                $table->boolean('is_delayed')->default(false)->after('seal_number');
            }
            if (! Schema::hasColumn('pickup_assignments', 'delay_reason')) {
                $table->string('delay_reason', 300)->nullable()->after('is_delayed');
            }
            if (! Schema::hasColumn('pickup_assignments', 'estimated_arrival_at')) {
                $table->timestamp('estimated_arrival_at')->nullable()->after('delay_reason');
            }
            if (! Schema::hasColumn('pickup_assignments', 'has_discrepancy')) {
                $table->boolean('has_discrepancy')->default(false)->after('estimated_arrival_at');
            }
            if (! Schema::hasColumn('pickup_assignments', 'discrepancy_notes')) {
                $table->text('discrepancy_notes')->nullable()->after('has_discrepancy');
            }
        });
    }

    protected static function upgradeAddressCoordinatesColumns(): void
    {
        if (Schema::hasTable('seller_profiles')) {
            Schema::table('seller_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_profiles', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('house_details');
                }
                if (! Schema::hasColumn('seller_profiles', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }

        if (Schema::hasTable('logistics_hubs')) {
            Schema::table('logistics_hubs', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_hubs', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('house_details');
                }
                if (! Schema::hasColumn('logistics_hubs', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }

        if (Schema::hasTable('logistics_profiles')) {
            Schema::table('logistics_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_profiles', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('house_details');
                }
                if (! Schema::hasColumn('logistics_profiles', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    protected static function createCourierLocationsTable(): void
    {
        if (Schema::hasTable('courier_locations')) {
            return;
        }

        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('pickup_assignment_id')->nullable()->constrained('pickup_assignments')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['courier_id', 'recorded_at'], 'cl_courier_recorded_idx');
            $table->index(['pickup_assignment_id', 'recorded_at'], 'cl_assignment_recorded_idx');
        });
    }

    protected static function createCourierVehicleUpdatesTable(): void
    {
        if (Schema::hasTable('courier_vehicle_updates')) {
            return;
        }

        Schema::create('courier_vehicle_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->string('previous_plate_number', 40)->nullable();
            $table->string('new_plate_number', 40);
            $table->string('vehicle_type', 60)->nullable();
            $table->unsignedInteger('previous_load_capacity')->nullable();
            $table->unsignedInteger('new_load_capacity')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_path')->nullable();
            $table->string('or_cr_path')->nullable();
            $table->string('nbi_clearance_path')->nullable();
            $table->string('status', 30)->default('pending_verification')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status'], 'cvu_courier_status_idx');
        });
    }

    protected static function createCourierShiftChecklistsTable(): void
    {
        if (Schema::hasTable('courier_shift_checklists')) {
            return;
        }

        Schema::create('courier_shift_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->boolean('tires_ok')->default(true);
            $table->boolean('lights_ok')->default(true);
            $table->boolean('brakes_ok')->default(true);
            $table->boolean('fuel_ok')->default(true);
            $table->boolean('cargo_lock_ok')->default(true);
            $table->boolean('all_passed')->default(true)->index();
            $table->string('failed_items_summary', 255)->nullable();
            $table->string('notes', 300)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();

            $table->index(['courier_id', 'submitted_at'], 'csc_courier_submitted_idx');
        });
    }

    protected static function createCourierTripIncidentsTable(): void
    {
        if (Schema::hasTable('courier_trip_incidents')) {
            return;
        }

        Schema::create('courier_trip_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_assignment_id')->constrained('pickup_assignments')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->string('incident_type', 30)->index();
            $table->string('reason', 150);
            $table->string('notes', 300)->nullable();
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('reported')->index();
            $table->timestamps();

            $table->index(['pickup_assignment_id', 'incident_type'], 'cti_assignment_type_idx');
        });
    }

    public static function resolveCoordinatesForAddress(array $snapshot, ?object $model = null): array
    {
        $lat = $snapshot['latitude'] ?? ($model->latitude ?? null);
        $lng = $snapshot['longitude'] ?? ($model->longitude ?? null);

        if (is_numeric($lat) && is_numeric($lng) && (float) $lat != 0.0 && (float) $lng != 0.0) {
            return [
                'latitude'       => round((float) $lat, 7),
                'longitude'      => round((float) $lng, 7),
                'is_approximate' => false,
            ];
        }

        $municipality = strtolower(trim((string) ($snapshot['municipality'] ?? ($model->municipality ?? ''))));
        $province = strtolower(trim((string) ($snapshot['province'] ?? ($model->province ?? ''))));
        $barangay = strtolower(trim((string) ($snapshot['barangay'] ?? ($model->barangay ?? ''))));

        $lookup = [
            'santa cruz'       => [14.2786, 121.4162],
            'sta. cruz'        => [14.2786, 121.4162],
            'santa rosa'       => [14.3122, 121.1114],
            'sta. rosa'        => [14.3122, 121.1114],
            'calamba'          => [14.2118, 121.1653],
            'san pablo'        => [14.0693, 121.3265],
            'los banos'        => [14.1700, 121.2414],
            'los baños'        => [14.1700, 121.2414],
            'pagsanjan'        => [14.2725, 121.4556],
            'pila'             => [14.2333, 121.3644],
            'victoria'         => [14.2275, 121.3283],
            'lumban'           => [14.2975, 121.4608],
            'kalayaan'         => [14.3278, 121.4772],
            'binan'            => [14.3364, 121.0842],
            'biñan'            => [14.3364, 121.0842],
            'cabuyao'          => [14.2725, 121.1239],
            'san pedro'        => [14.3583, 121.0472],
            'quezon city'      => [14.6760, 121.0437],
            'pasig'            => [14.5764, 121.0851],
            'pasig city'       => [14.5764, 121.0851],
            'makati'           => [14.5547, 121.0244],
            'makati city'      => [14.5547, 121.0244],
            'manila'           => [14.5995, 120.9842],
            'city of manila'   => [14.5995, 120.9842],
            'taguig'           => [14.5176, 121.0509],
            'mandaluyong'      => [14.5794, 121.0359],
            'marikina'         => [14.6507, 121.1029],
            'paranaque'        => [14.4793, 121.0198],
            'muntinlupa'       => [14.4081, 121.0415],
            'caloocan'         => [14.6571, 120.9841],
            'pasay'            => [14.5378, 121.0014],
            'dasmarinas'       => [14.3294, 120.9367],
            'imus'             => [14.4297, 120.9367],
            'bacoor'           => [14.4624, 120.9645],
            'tagaytay'         => [14.1153, 120.9621],
            'general trias'    => [14.3869, 120.8808],
            'trece martires'   => [14.2822, 120.8675],
            'antipolo'         => [14.5878, 121.1761],
            'cainta'           => [14.5786, 121.1122],
            'taytay'           => [14.5583, 121.1325],
            'lipa'             => [13.9411, 121.1622],
            'batangas city'    => [13.7565, 121.0583],
            'lucena'           => [13.9372, 121.6172],
            'cebu city'        => [10.3157, 123.8854],
            'davao city'       => [7.1907, 125.4553],
        ];

        $cleanedMun = preg_replace('/\b(city of|municipality of|city)\b/', '', $municipality);
        $cleanedMun = trim((string) preg_replace('/\s+/', ' ', (string) $cleanedMun));

        $baseCoords = $lookup[$municipality]
            ?? $lookup[$cleanedMun]
            ?? null;

        if (! $baseCoords) {
            foreach ($lookup as $key => $coords) {
                if ($cleanedMun !== '' && (str_contains($cleanedMun, $key) || str_contains($key, $cleanedMun))) {
                    $baseCoords = $coords;
                    break;
                }
            }
        }

        if (! $baseCoords) {
            $provLookup = [
                'laguna'       => [14.2786, 121.4162],
                'cavite'       => [14.3294, 120.9367],
                'batangas'     => [13.9411, 121.1622],
                'rizal'        => [14.5878, 121.1761],
                'quezon'       => [13.9372, 121.6172],
                'metro manila' => [14.6091, 121.0223],
                'ncr'          => [14.6091, 121.0223],
                'cebu'         => [10.3157, 123.8854],
                'davao'        => [7.1907, 125.4553],
            ];
            foreach ($provLookup as $pKey => $coords) {
                if (str_contains($province, $pKey)) {
                    $baseCoords = $coords;
                    break;
                }
            }
        }

        if (! $baseCoords) {
            $baseCoords = [14.2786, 121.4162];
        }

        $offsetLat = 0.0;
        $offsetLng = 0.0;
        if ($barangay !== '') {
            $hash = crc32($barangay);
            $offsetLat = (($hash % 100) - 50) * 0.00018;
            $offsetLng = ((($hash >> 8) % 100) - 50) * 0.00018;
        }

        return [
            'latitude'       => round($baseCoords[0] + $offsetLat, 7),
            'longitude'      => round($baseCoords[1] + $offsetLng, 7),
            'is_approximate' => true,
        ];
    }

    public static function calculateDistanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1);
    }
}