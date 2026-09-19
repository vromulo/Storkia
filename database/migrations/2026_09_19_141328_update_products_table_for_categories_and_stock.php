<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add category and subcategory columns
            if (!Schema::hasColumn('products', 'category')) {
                $table->string('category')->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'subcategory')) {
                $table->string('subcategory')->nullable()->after('category');
            }
            
            // REMOVED the dropColumn('stock_quantity') code so the global cache remains intact.
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['category', 'subcategory']);
        });
    }
};