<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RiderMobileSchemaService
{
    protected const CACHE_KEY = 'storkia_rider_mobile_schema_v2_ready';

    public static function ensureTablesExist(): void
    {
        if (
            Cache::get(self::CACHE_KEY) === true
            && Schema::hasTable('rider_locations')
            && Schema::hasTable('rider_vehicle_updates')
            && Schema::hasTable('rider_shift_checklists')
            && Schema::hasTable('rider_trip_incidents')
            && Schema::hasColumn('riders', 'current_latitude')
            && Schema::hasColumn('riders', 'last_checklist_passed_at')
            && Schema::hasColumn('delivery_assignments', 'accepted_at')
            && Schema::hasColumn('delivery_assignments', 'decline_reason')
        ) {
            return;
        }

        try {
            if (! Schema::hasTable('riders') || ! Schema::hasTable('shipments')) {
                return;
            }

            self::upgradeRidersTable();
            self::upgradeDeliveryAssignmentsAndAttemptsTables();
            self::upgradeShipmentsAndProofsTables();
            self::upgradeCodRemittancesTables();
            self::upgradeAddressCoordinatesColumns();
            self::createRiderLocationsTable();
            self::createRiderVehicleUpdatesTable();
            self::createRiderShiftChecklistsTable();
            self::createRiderTripIncidentsTable();

            Cache::put(self::CACHE_KEY, true, now()->addHours(12));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected static function upgradeRidersTable(): void
    {
        if (! Schema::hasTable('riders')) {
            return;
        }

        Schema::table('riders', function (Blueprint $table) {
            if (! Schema::hasColumn('riders', 'current_latitude')) {
                $table->decimal('current_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('riders', 'current_longitude')) {
                $table->decimal('current_longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('riders', 'location_accuracy')) {
                $table->decimal('location_accuracy', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('riders', 'location_updated_at')) {
                $table->timestamp('location_updated_at')->nullable();
            }
            if (! Schema::hasColumn('riders', 'vehicle_verification_status')) {
                $table->string('vehicle_verification_status', 30)->default('verified');
            }
            if (! Schema::hasColumn('riders', 'last_checklist_passed_at')) {
                $table->timestamp('last_checklist_passed_at')->nullable();
            }
        });
    }

    protected static function upgradeDeliveryAssignmentsAndAttemptsTables(): void
    {
        if (Schema::hasTable('delivery_assignments')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_assignments', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('assigned_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'picked_up_at')) {
                    $table->timestamp('picked_up_at')->nullable()->after('accepted_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'failure_reason')) {
                    $table->string('failure_reason', 255)->nullable()->after('failed_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'declined_at')) {
                    $table->timestamp('declined_at')->nullable()->after('failed_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'decline_reason')) {
                    $table->string('decline_reason', 300)->nullable()->after('declined_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'is_delayed')) {
                    $table->boolean('is_delayed')->default(false)->after('decline_reason');
                }
                if (! Schema::hasColumn('delivery_assignments', 'delay_reason')) {
                    $table->string('delay_reason', 300)->nullable()->after('is_delayed');
                }
                if (! Schema::hasColumn('delivery_assignments', 'estimated_arrival_at')) {
                    $table->timestamp('estimated_arrival_at')->nullable()->after('delay_reason');
                }
            });
        }

        if (Schema::hasTable('delivery_attempts')) {
            Schema::table('delivery_attempts', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_attempts', 'notes')) {
                    $table->text('notes')->nullable()->after('failure_notes');
                }
            });
        }
    }

    protected static function upgradeShipmentsAndProofsTables(): void
    {
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'failed_attempts_count')) {
                    $table->unsignedTinyInteger('failed_attempts_count')->default(0)->after('delivery_attempts_count');
                }
                if (! Schema::hasColumn('shipments', 'last_failure_reason')) {
                    $table->string('last_failure_reason', 255)->nullable()->after('failed_attempts_count');
                }
                if (! Schema::hasColumn('shipments', 'delivery_otp')) {
                    $table->string('delivery_otp', 10)->nullable()->after('last_failure_reason');
                }
                if (! Schema::hasColumn('shipments', 'delivery_otp_attempts')) {
                    $table->unsignedTinyInteger('delivery_otp_attempts')->default(0)->after('delivery_otp');
                }
                if (! Schema::hasColumn('shipments', 'delivery_otp_locked_until')) {
                    $table->timestamp('delivery_otp_locked_until')->nullable()->after('delivery_otp_attempts');
                }
            });
        }

        if (Schema::hasTable('delivery_proofs')) {
            Schema::table('delivery_proofs', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_proofs', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('delivery_proofs', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
                if (! Schema::hasColumn('delivery_proofs', 'location_accuracy')) {
                    $table->decimal('location_accuracy', 8, 2)->nullable()->after('longitude');
                }
            });
        }
    }

    protected static function upgradeCodRemittancesTables(): void
    {
        if (Schema::hasTable('cod_remittances')) {
            Schema::table('cod_remittances', function (Blueprint $table) {
                if (! Schema::hasColumn('cod_remittances', 'total_seller_share')) {
                    $table->decimal('total_seller_share', 12, 2)->default(0.00)->after('total_collected_amount');
                }
                if (! Schema::hasColumn('cod_remittances', 'total_admin_commission')) {
                    $table->decimal('total_admin_commission', 12, 2)->default(0.00)->after('total_seller_share');
                }
                if (! Schema::hasColumn('cod_remittances', 'total_logistics_fee')) {
                    $table->decimal('total_logistics_fee', 12, 2)->default(0.00)->after('total_admin_commission');
                }
            });
        }

        if (Schema::hasTable('cod_remittance_items')) {
            Schema::table('cod_remittance_items', function (Blueprint $table) {
                if (! Schema::hasColumn('cod_remittance_items', 'amount')) {
                    $table->decimal('amount', 12, 2)->default(0.00)->after('cod_collection_id');
                }
                if (Schema::hasColumn('cod_remittance_items', 'seller_order_id')) {
                    $table->unsignedBigInteger('seller_order_id')->nullable()->change();
                }
                if (Schema::hasColumn('cod_remittance_items', 'collected_amount')) {
                    $table->decimal('collected_amount', 12, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('cod_remittance_items', 'rider_commission_amount')) {
                    $table->decimal('rider_commission_amount', 12, 2)->default(0.00)->change();
                }
            });
        }
    }

    protected static function upgradeAddressCoordinatesColumns(): void
    {
        if (Schema::hasTable('user_addresses')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                if (! Schema::hasColumn('user_addresses', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('building_details');
                }
                if (! Schema::hasColumn('user_addresses', 'longitude')) {
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
    }

    protected static function createRiderLocationsTable(): void
    {
        if (Schema::hasTable('rider_locations')) {
            return;
        }

        Schema::create('rider_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->nullable()->constrained('delivery_assignments')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['rider_id', 'recorded_at'], 'rl_rider_recorded_idx');
            $table->index(['delivery_assignment_id', 'recorded_at'], 'rl_assignment_recorded_idx');
        });
    }

    protected static function createRiderVehicleUpdatesTable(): void
    {
        if (Schema::hasTable('rider_vehicle_updates')) {
            return;
        }

        Schema::create('rider_vehicle_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->string('previous_plate_number', 40)->nullable();
            $table->string('new_plate_number', 40);
            $table->string('vehicle_type', 60)->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_path')->nullable();
            $table->string('or_cr_path')->nullable();
            $table->string('status', 30)->default('pending_verification')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
        });
    }

    protected static function createRiderShiftChecklistsTable(): void
    {
        if (Schema::hasTable('rider_shift_checklists')) {
            return;
        }

        Schema::create('rider_shift_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id');
            $table->unsignedBigInteger('logistics_profile_id');
            $table->boolean('tires_ok')->default(true);
            $table->boolean('lights_ok')->default(true);
            $table->boolean('brakes_ok')->default(true);
            $table->boolean('fuel_ok')->default(true);
            $table->boolean('helmet_gear_ok')->default(true);
            $table->boolean('thermal_bag_ok')->default(true);
            $table->boolean('all_passed')->default(true)->index();
            $table->string('failed_items_summary', 255)->nullable();
            $table->string('notes', 300)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamps();

            $table->foreign('rider_id', 'rsc_rider_id_fk')
                ->references('id')
                ->on('riders')
                ->cascadeOnDelete();

            $table->foreign('logistics_profile_id', 'rsc_log_prof_fk')
                ->references('id')
                ->on('logistics_profiles')
                ->cascadeOnDelete();

            $table->index(['rider_id', 'submitted_at'], 'rsc_rider_sub_idx');
        });
    }

    protected static function createRiderTripIncidentsTable(): void
    {
        if (Schema::hasTable('rider_trip_incidents')) {
            return;
        }

        Schema::create('rider_trip_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_assignment_id');
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedBigInteger('rider_id');
            $table->unsignedBigInteger('logistics_profile_id');
            $table->string('incident_type', 30)->index();
            $table->string('reason', 150);
            $table->string('notes', 300)->nullable();
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 30)->default('reported')->index();
            $table->timestamps();

            $table->foreign('delivery_assignment_id', 'rti_deliv_asg_fk')
                ->references('id')
                ->on('delivery_assignments')
                ->cascadeOnDelete();

            $table->foreign('shipment_id', 'rti_ship_id_fk')
                ->references('id')
                ->on('shipments')
                ->cascadeOnDelete();

            $table->foreign('rider_id', 'rti_rider_id_fk')
                ->references('id')
                ->on('riders')
                ->cascadeOnDelete();

            $table->foreign('logistics_profile_id', 'rti_log_prof_fk')
                ->references('id')
                ->on('logistics_profiles')
                ->cascadeOnDelete();

            $table->index(['delivery_assignment_id', 'incident_type'], 'rti_asg_type_idx');
        });
    }
}