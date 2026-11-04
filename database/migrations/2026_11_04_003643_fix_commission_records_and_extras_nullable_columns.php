<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commission_records')) {
            Schema::table('commission_records', function (Blueprint $table) {
                $table->unsignedBigInteger('shipment_id')->nullable()->change();
                $table->unsignedBigInteger('cod_collection_id')->nullable()->change();
                $table->unsignedBigInteger('logistics_profile_id')->nullable()->change();
                $table->unsignedBigInteger('rider_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('seller_order_extras')) {
            Schema::table('seller_order_extras', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_order_extras', 'seller_return_decision_reason')) {
                    $table->text('seller_return_decision_reason')->nullable()->after('seller_return_decision');
                }
            });
        }
    }

    public function down(): void
    {
    }
};