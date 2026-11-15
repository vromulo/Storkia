<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('couriers')) {
            Schema::table('couriers', function (Blueprint $table) {
                if (! Schema::hasColumn('couriers', 'avatar_path')) {
                    $table->string('avatar_path')->nullable()->after('contact_no');
                }
                if (! Schema::hasColumn('couriers', 'password_changed_at')) {
                    $table->timestamp('password_changed_at')->nullable()->after('password');
                }
                if (! Schema::hasColumn('couriers', 'vehicle_verification_status')) {
                    $table->string('vehicle_verification_status', 30)->default('verified');
                }
                if (! Schema::hasColumn('couriers', 'last_checklist_passed_at')) {
                    $table->timestamp('last_checklist_passed_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('courier_vehicle_updates')) {
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
    }

    public function down(): void
    {
        if (Schema::hasTable('courier_vehicle_updates')) {
            Schema::table('courier_vehicle_updates', function (Blueprint $table) {
                $dropCols = [];
                foreach (['previous_license_no', 'new_license_no', 'reviewed_by', 'reviewed_at'] as $col) {
                    if (Schema::hasColumn('courier_vehicle_updates', $col)) {
                        $dropCols[] = $col;
                    }
                }
                if (! empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }

        if (Schema::hasTable('couriers') && Schema::hasColumn('couriers', 'password_changed_at')) {
            Schema::table('couriers', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};