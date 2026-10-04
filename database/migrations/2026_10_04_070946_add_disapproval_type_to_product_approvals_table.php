<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_approvals', function (Blueprint $table) {
            $table->string('disapproval_type')->nullable()->after('status'); 
        });
    }

    public function down(): void
    {
        Schema::table('product_approvals', function (Blueprint $table) {
            $table->dropColumn('disapproval_type');
        });
    }
};