<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('logistics_profiles', 'line_of_business')) {
            Schema::table('logistics_profiles', function (Blueprint $table) {
                $table->dropColumn('line_of_business');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty or guarded so rollbacks don't conflict with 2026_10_20_031439
    }
};