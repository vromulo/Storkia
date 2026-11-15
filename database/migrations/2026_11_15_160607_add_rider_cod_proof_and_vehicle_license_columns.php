<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('riders')) {
            Schema::table('riders', function (Blueprint $table) {
                if (! Schema::hasColumn('riders', 'is_online')) {
                    $table->boolean('is_online')->default(false)->index();
                }
                if (! Schema::hasColumn('riders', 'last_seen_at')) {
                    $table->timestamp('last_seen_at')->nullable();
                }
                if (! Schema::hasColumn('riders', 'sla_strikes')) {
                    $table->unsignedSmallInteger('sla_strikes')->default(0);
                }
                if (! Schema::hasColumn('riders', 'avatar_path')) {
                    $table->string('avatar_path')->nullable()->after('contact_no');
                }
                if (! Schema::hasColumn('riders', 'last_checklist_passed_at')) {
                    $table->timestamp('last_checklist_passed_at')->nullable();
                }
                if (! Schema::hasColumn('riders', 'password_changed_at')) {
                    $table->timestamp('password_changed_at')->nullable()->after('password');
                }
            });
        }

        if (Schema::hasTable('cod_remittances')) {
            Schema::table('cod_remittances', function (Blueprint $table) {
                if (! Schema::hasColumn('cod_remittances', 'proof_image_path')) {
                    $table->string('proof_image_path')->nullable()->after('notes');
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
            });
        }

        if (Schema::hasTable('delivery_chat_messages')) {
            Schema::table('delivery_chat_messages', function (Blueprint $table) {
                $table->string('message', 1000)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cod_remittances') && Schema::hasColumn('cod_remittances', 'proof_image_path')) {
            Schema::table('cod_remittances', function (Blueprint $table) {
                $table->dropColumn('proof_image_path');
            });
        }

        if (Schema::hasTable('rider_vehicle_updates')) {
            Schema::table('rider_vehicle_updates', function (Blueprint $table) {
                $dropCols = [];
                if (Schema::hasColumn('rider_vehicle_updates', 'previous_license_no')) {
                    $dropCols[] = 'previous_license_no';
                }
                if (Schema::hasColumn('rider_vehicle_updates', 'new_license_no')) {
                    $dropCols[] = 'new_license_no';
                }
                if (! empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }

        if (Schema::hasTable('riders') && Schema::hasColumn('riders', 'password_changed_at')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};