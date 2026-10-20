<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('logistics_profiles', 'line_of_business')) {
                $table->dropColumn('line_of_business');
            }

            if (! Schema::hasColumn('logistics_profiles', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('business_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logistics_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('logistics_profiles', 'logo_path')) {
                $table->dropColumn('logo_path');
            }

            if (! Schema::hasColumn('logistics_profiles', 'line_of_business')) {
                $table->string('line_of_business')->nullable()->after('business_name');
            }
        });
    }
};