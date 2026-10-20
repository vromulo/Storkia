<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_profiles', function (Blueprint $table) {
            $table->string('line_of_business')->nullable()->change();
            // Alternatively, drop it if it is completely unused:
            // $table->dropColumn('line_of_business');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_profiles', function (Blueprint $table) {
            $table->string('line_of_business')->nullable(false)->change();
        });
    }
};
