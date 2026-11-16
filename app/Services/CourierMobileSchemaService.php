<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CourierMobileSchemaService
{
    protected const CACHE_KEY = 'storkia_courier_mobile_schema_v5_ready';

    protected static bool $verifiedInProcess = false;

    public static function ensureTablesExist(): void
    {
        if (self::$verifiedInProcess) {
            return;
        }

        if (Cache::get(self::CACHE_KEY) === true) {
            self::$verifiedInProcess = true;
            return;
        }

        if (
            Schema::hasTable('courier_locations')
            && Schema::hasTable('courier_vehicle_updates')
            && Schema::hasTable('courier_shift_checklists')
            && Schema::hasTable('courier_trip_incidents')
            && Schema::hasColumn('couriers', 'current_latitude')
            && Schema::hasColumn('couriers', 'avatar_path')
            && Schema::hasColumn('couriers', 'password_changed_at')
            && Schema::hasColumn('pickup_assignments', 'decline_reason')
            && Schema::hasColumn('courier_vehicle_updates', 'new_license_no')
        ) {
            Cache::put(self::CACHE_KEY, true, now()->addHours(24));
            self::$verifiedInProcess = true;
            return;
        }

        try {
            if (! Schema::hasTable('couriers') || ! Schema::hasTable('shipments')) {
                return;
            }

            self::applySchemaChanges();

            Cache::put(self::CACHE_KEY, true, now()->addHours(24));
            self::$verifiedInProcess = true;
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
        self::upgradeCourierVehicleUpdatesTable();
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
            if (! Schema::hasColumn('couriers', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('contact_no');
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
            if (! Schema::hasColumn('couriers', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
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
            $table->string('previous_license_no', 40)->nullable();
            $table->string('new_license_no', 40)->nullable();
            $table->string('vehicle_type', 60)->nullable();
            $table->unsignedInteger('previous_load_capacity')->nullable();
            $table->unsignedInteger('new_load_capacity')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_path')->nullable();
            $table->string('or_cr_path')->nullable();
            $table->string('nbi_clearance_path')->nullable();
            $table->string('status', 30)->default('pending_verification')->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status'], 'cvu_courier_status_idx');
        });
    }

    protected static function upgradeCourierVehicleUpdatesTable(): void
    {
        if (! Schema::hasTable('courier_vehicle_updates')) {
            return;
        }

        Schema::table('courier_vehicle_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('courier_vehicle_updates', 'previous_license_no')) {
                $table->string('previous_license_no', 40)->nullable()->after('new_plate_number');
            }
            if (! Schema::hasColumn('courier_vehicle_updates', 'new_license_no')) {
                $table->string('new_license_no', 40)->nullable()->after('previous_license_no');
            }
            if (! Schema::hasColumn('courier_vehicle_updates', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('courier_vehicle_updates', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
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
            'santa cruz'          => [14.2786, 121.4162],
            'sta. cruz'           => [14.2786, 121.4162],
            'santa rosa'          => [14.3122, 121.1114],
            'sta. rosa'           => [14.3122, 121.1114],
            'calamba'             => [14.2118, 121.1653],
            'san pablo'           => [14.0693, 121.3265],
            'los banos'           => [14.1700, 121.2414],
            'los baños'           => [14.1700, 121.2414],
            'bay'                 => [14.1794, 121.2869],
            'calauan'             => [14.1481, 121.3156],
            'nagcarlan'           => [14.1364, 121.4128],
            'liliw'               => [14.1319, 121.4367],
            'majayjay'            => [14.1464, 121.4728],
            'magdalena'           => [14.1997, 121.4292],
            'pagsanjan'           => [14.2725, 121.4556],
            'pila'                => [14.2333, 121.3644],
            'victoria'            => [14.2275, 121.3283],
            'lumban'              => [14.2975, 121.4608],
            'kalayaan'            => [14.3278, 121.4772],
            'paete'               => [14.3642, 121.4814],
            'pakil'               => [14.3806, 121.4786],
            'pangil'              => [14.4028, 121.4675],
            'siniloan'            => [14.4194, 121.4456],
            'famy'                => [14.4375, 121.4500],
            'mabitac'             => [14.4264, 121.4258],
            'santa maria'         => [14.4708, 121.4272],
            'cavinti'             => [14.2458, 121.5097],
            'luisiana'            => [14.1856, 121.5106],
            'alaminos'            => [14.0639, 121.2464],
            'rizal'               => [14.1119, 121.3944],
            'binan'               => [14.3364, 121.0842],
            'biñan'               => [14.3364, 121.0842],
            'cabuyao'             => [14.2725, 121.1239],
            'san pedro'           => [14.3583, 121.0472],
            'quezon city'         => [14.6760, 121.0437],
            'pasig'               => [14.5764, 121.0851],
            'makati'              => [14.5547, 121.0244],
            'manila'              => [14.5995, 120.9842],
            'taguig'              => [14.5176, 121.0509],
            'mandaluyong'         => [14.5794, 121.0359],
            'marikina'            => [14.6507, 121.1029],
            'paranaque'           => [14.4793, 121.0198],
            'parañaque'           => [14.4793, 121.0198],
            'muntinlupa'          => [14.4081, 121.0415],
            'caloocan'            => [14.6571, 120.9841],
            'pasay'               => [14.5378, 121.0014],
            'las pinas'           => [14.4445, 120.9939],
            'las piñas'           => [14.4445, 120.9939],
            'valenzuela'          => [14.7011, 120.9830],
            'malabon'             => [14.6681, 120.9658],
            'navotas'             => [14.6667, 120.9417],
            'san juan'            => [14.6019, 121.0355],
            'pateros'             => [14.5450, 121.0686],
            'dasmarinas'          => [14.3294, 120.9367],
            'dasmariñas'          => [14.3294, 120.9367],
            'imus'                => [14.4297, 120.9367],
            'bacoor'              => [14.4624, 120.9645],
            'tagaytay'            => [14.1153, 120.9621],
            'general trias'       => [14.3869, 120.8808],
            'trece martires'      => [14.2822, 120.8675],
            'silang'              => [14.2306, 120.9775],
            'kawit'               => [14.4419, 120.9036],
            'naic'                => [14.3167, 120.7667],
            'tanza'               => [14.3942, 120.8528],
            'carmona'             => [14.3133, 121.0575],
            'antipolo'            => [14.5878, 121.1761],
            'cainta'              => [14.5786, 121.1122],
            'taytay'              => [14.5583, 121.1325],
            'binangonan'          => [14.4644, 121.1928],
            'san mateo'           => [14.6969, 121.1219],
            'rodriguez'           => [14.7600, 121.1464],
            'lipa'                => [13.9411, 121.1622],
            'batangas city'       => [13.7565, 121.0583],
            'tanauan'             => [14.0862, 121.1498],
            'santo tomas'         => [14.1078, 121.1414],
            'nasugbu'             => [14.0725, 120.6325],
            'balayan'             => [13.9378, 120.7322],
            'lucena'              => [13.9372, 121.6172],
            'tayabas'             => [14.0264, 121.5928],
            'sariaya'             => [13.9625, 121.5264],
            'candelaria'          => [13.9311, 121.4233],
            'tiaong'              => [13.9547, 121.3397],
            'malolos'             => [14.8433, 120.8114],
            'meycauayan'          => [14.7369, 120.9606],
            'san jose del monte'  => [14.8139, 121.0453],
            'angeles'             => [15.1450, 120.5887],
            'san fernando'        => [15.0286, 120.6898],
            'olongapo'            => [14.8292, 120.2828],
            'tarlac city'         => [15.4755, 120.5963],
            'cabanatuan'          => [15.4865, 120.9734],
            'dagupan'             => [16.0433, 120.3333],
            'baguio'              => [16.4023, 120.5960],
            'laoag'               => [18.1960, 120.5927],
            'vigan'               => [17.5747, 120.3869],
            'tuguegarao'          => [17.6132, 121.7270],
            'santiago'            => [16.6881, 121.5486],
            'naga'                => [13.6218, 123.1948],
            'legazpi'             => [13.1391, 123.7438],
            'sorsogon city'       => [12.9742, 124.0058],
            'puerto princesa'     => [9.7392, 118.7353],
            'calapan'             => [13.4117, 121.1803],
            'cebu city'           => [10.3157, 123.8854],
            'mandaue'             => [10.3236, 123.9223],
            'lapu-lapu'           => [10.3103, 123.9494],
            'lapu lapu'           => [10.3103, 123.9494],
            'talisay'             => [10.2447, 123.8494],
            'iloilo city'         => [10.7202, 122.5621],
            'bacolod'             => [10.6770, 122.9506],
            'tagbilaran'          => [9.6477, 123.8555],
            'dumaguete'           => [9.3068, 123.3054],
            'tacloban'            => [11.2443, 125.0039],
            'ormoc'               => [11.0064, 124.6075],
            'roxas'               => [11.5853, 122.7511],
            'kalibo'              => [11.7061, 122.3647],
            'davao city'          => [7.1907, 125.4553],
            'tagum'               => [7.4478, 125.8078],
            'digos'               => [6.7497, 125.3572],
            'cagayan de oro'      => [8.4542, 124.6319],
            'iligan'              => [8.2280, 124.2452],
            'general santos'      => [6.1164, 125.1716],
            'koronadal'           => [6.5031, 124.8469],
            'zamboanga city'      => [6.9214, 122.0790],
            'butuan'              => [8.9475, 125.5406],
            'surigao city'        => [9.7844, 125.4950],
            'cotabato city'       => [7.2236, 124.2464],
            'dipolog'             => [8.5883, 123.3408],
            'pagadian'            => [7.8256, 123.4369],
            'malaybalay'          => [8.1575, 125.1278],
            'valencia'            => [7.9064, 125.0942],
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
                'abra'                => [17.5956, 120.7931],
                'agusan del norte'    => [8.9475, 125.5406],
                'agusan del sur'      => [8.5000, 125.9000],
                'aklan'               => [11.7061, 122.3647],
                'albay'               => [13.1391, 123.7438],
                'antique'             => [10.7450, 121.9422],
                'apayao'              => [18.0167, 121.1833],
                'aurora'              => [15.7586, 121.5622],
                'basilan'             => [6.6522, 121.9736],
                'bataan'              => [14.6761, 120.5361],
                'batanes'             => [20.4486, 121.9708],
                'batangas'            => [13.9411, 121.1622],
                'benguet'             => [16.4500, 120.5833],
                'biliran'             => [11.5833, 124.4667],
                'bohol'               => [9.6477, 123.8555],
                'bukidnon'            => [8.1575, 125.1278],
                'bulacan'             => [14.8433, 120.8114],
                'cagayan'             => [17.6132, 121.7270],
                'camarines norte'     => [14.1122, 122.9553],
                'camarines sur'       => [13.6218, 123.1948],
                'camiguin'            => [9.1733, 124.7294],
                'capiz'               => [11.5853, 122.7511],
                'catanduanes'         => [13.5861, 124.2372],
                'cavite'              => [14.3294, 120.9367],
                'cebu'                => [10.3157, 123.8854],
                'cotabato'            => [7.1083, 125.0889],
                'north cotabato'      => [7.1083, 125.0889],
                'davao de oro'        => [7.6000, 126.0500],
                'compostela valley'   => [7.6000, 126.0500],
                'davao del norte'     => [7.4478, 125.8078],
                'davao del sur'       => [6.7497, 125.3572],
                'davao occidental'    => [6.1000, 125.6000],
                'davao oriental'      => [6.9500, 126.2167],
                'davao'               => [7.1907, 125.4553],
                'dinagat islands'     => [10.1281, 125.6083],
                'eastern samar'       => [11.6086, 125.4319],
                'guimaras'            => [10.5928, 122.5875],
                'ifugao'              => [16.8208, 121.1000],
                'ilocos norte'        => [18.1960, 120.5927],
                'ilocos sur'          => [17.5747, 120.3869],
                'iloilo'              => [10.7202, 122.5621],
                'isabela'             => [16.9754, 121.8107],
                'kalinga'             => [17.4189, 121.4447],
                'la union'            => [16.6159, 120.3209],
                'laguna'              => [14.2786, 121.4162],
                'lanao del norte'     => [8.2280, 124.2452],
                'lanao del sur'       => [7.9986, 124.2928],
                'leyte'               => [11.2443, 125.0039],
                'maguindanao'         => [7.1333, 124.3000],
                'marinduque'          => [13.4464, 121.9033],
                'masbate'             => [12.3697, 123.6247],
                'metro manila'        => [14.6091, 121.0223],
                'ncr'                 => [14.6091, 121.0223],
                'misamis occidental'  => [8.1486, 123.8406],
                'misamis oriental'    => [8.4542, 124.6319],
                'mountain province'   => [17.0872, 120.9756],
                'negros occidental'   => [10.6770, 122.9506],
                'negros oriental'     => [9.3068, 123.3054],
                'northern samar'      => [12.4989, 124.6378],
                'nueva ecija'         => [15.4865, 120.9734],
                'nueva vizcaya'       => [16.4833, 121.1500],
                'occidental mindoro'  => [13.1500, 120.7500],
                'oriental mindoro'    => [13.4117, 121.1803],
                'palawan'             => [9.7392, 118.7353],
                'pampanga'            => [15.0286, 120.6898],
                'pangasinan'          => [16.0433, 120.3333],
                'quezon'              => [13.9372, 121.6172],
                'quirino'             => [16.2700, 121.5300],
                'rizal'               => [14.5878, 121.1761],
                'romblon'             => [12.5778, 122.2692],
                'samar'               => [11.7753, 124.8861],
                'western samar'       => [11.7753, 124.8861],
                'sarangani'           => [5.9267, 125.2753],
                'siquijor'            => [9.1994, 123.5950],
                'sorsogon'            => [12.9742, 124.0058],
                'south cotabato'      => [6.5031, 124.8469],
                'southern leyte'      => [10.1336, 124.8447],
                'sultan kudarat'      => [6.6333, 124.6000],
                'sulu'                => [6.0522, 121.0022],
                'surigao del norte'   => [9.7844, 125.4950],
                'surigao del sur'     => [9.0725, 126.1986],
                'tarlac'              => [15.4755, 120.5963],
                'tawi-tawi'           => [5.0292, 119.7731],
                'tawi tawi'           => [5.0292, 119.7731],
                'zambales'            => [15.3275, 119.9781],
                'zamboanga del norte' => [8.5883, 123.3408],
                'zamboanga del sur'   => [7.8256, 123.4369],
                'zamboanga sibugay'   => [7.7844, 122.5861],
                'zamboanga'           => [6.9214, 122.0790],
            ];

            $cleanedProv = preg_replace('/\b(province of|province)\b/', '', $province);
            $cleanedProv = trim((string) preg_replace('/\s+/', ' ', (string) $cleanedProv));

            if (isset($provLookup[$cleanedProv])) {
                $baseCoords = $provLookup[$cleanedProv];
            } else {
                foreach ($provLookup as $pKey => $coords) {
                    if ($cleanedProv !== '' && (str_contains($cleanedProv, $pKey) || str_contains($pKey, $cleanedProv))) {
                        $baseCoords = $coords;
                        break;
                    }
                }
            }
        }

        if (! $baseCoords) {
            if ($province !== '' || $municipality !== '') {
                $seed = crc32($province . '|' . $municipality);
                $baseCoords = [
                    14.5995 + ((($seed % 600) - 300) * 0.005),
                    120.9842 + (((($seed >> 8) % 600) - 300) * 0.005),
                ];
            } else {
                $baseCoords = [14.2786, 121.4162];
            }
        }

        $offsetLat = 0.0;
        $offsetLng = 0.0;
        if ($barangay !== '') {
            $hash = crc32($municipality . '|' . $barangay);
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

    public static function calculateDistanceKmBetweenLocations(
        array $originSnapshot,
        array $destinationSnapshot,
        ?object $originModel = null,
        ?object $destinationModel = null
    ): float {
        $origCoords = self::resolveCoordinatesForAddress($originSnapshot, $originModel);
        $destCoords = self::resolveCoordinatesForAddress($destinationSnapshot, $destinationModel);

        $meters = self::calculateDistanceMeters(
            (float) $origCoords['latitude'],
            (float) $origCoords['longitude'],
            (float) $destCoords['latitude'],
            (float) $destCoords['longitude']
        );

        return round($meters / 1000.0, 2);
    }
}