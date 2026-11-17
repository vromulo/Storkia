<?php

use App\Services\RiderMobileSchemaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        RiderMobileSchemaService::ensureTablesExist();

        if (Schema::hasTable('riders')) {
            Schema::table('riders', function (Blueprint $table) {
                if (! Schema::hasColumn('riders', 'vehicle_verification_status')) {
                    $table->string('vehicle_verification_status', 30)->default('verified');
                }
            });
        }

        if (Schema::hasTable('rider_vehicle_updates')) {
            Schema::table('rider_vehicle_updates', function (Blueprint $table) {
                if (! Schema::hasColumn('rider_vehicle_updates', 'previous_license_no')) {
                    $table->string('previous_license_no', 40)->nullable()->after('new_plate_number');
                }
                if (! Schema::hasColumn('rider_vehicle_updates', 'new_license_no')) {
                    $table->string('new_license_no', 40)->nullable()->after('previous_license_no');
                }
                if (! Schema::hasColumn('rider_vehicle_updates', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->after('remarks');
                }
                if (! Schema::hasColumn('rider_vehicle_updates', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rider_vehicle_updates')) {
            Schema::table('rider_vehicle_updates', function (Blueprint $table) {
                $dropCols = [];
                foreach (['reviewed_by', 'reviewed_at'] as $col) {
                    if (Schema::hasColumn('rider_vehicle_updates', $col)) {
                        $dropCols[] = $col;
                    }
                }
                if (! empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }
    }
};