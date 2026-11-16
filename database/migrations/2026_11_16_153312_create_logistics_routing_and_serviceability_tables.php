<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_hubs')) {
            Schema::table('logistics_hubs', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_hubs', 'max_courier_pickup_radius_km')) {
                    $table->decimal('max_courier_pickup_radius_km', 6, 2)->default(50.00)->after('status');
                }
                if (! Schema::hasColumn('logistics_hubs', 'max_rider_delivery_radius_km')) {
                    $table->decimal('max_rider_delivery_radius_km', 6, 2)->default(20.00)->after('max_courier_pickup_radius_km');
                }
            });
        }

        if (Schema::hasTable('seller_orders')) {
            Schema::table('seller_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_orders', 'is_unserviceable_exception')) {
                    $table->boolean('is_unserviceable_exception')->default(false)->index()->after('rejection_reason');
                }
                if (! Schema::hasColumn('seller_orders', 'seller_to_buyer_distance_km')) {
                    $table->decimal('seller_to_buyer_distance_km', 8, 2)->nullable()->after('shipping_tier');
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'destination_logistics_hub_id')) {
                    $table->unsignedBigInteger('destination_logistics_hub_id')->nullable()->index()->after('logistics_profile_id');
                    $table->foreign('destination_logistics_hub_id', 'shp_dest_hub_id_fk')
                        ->references('id')
                        ->on('logistics_hubs')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('shipments', 'origin_to_hub_distance_km')) {
                    $table->decimal('origin_to_hub_distance_km', 8, 2)->nullable()->after('shipping_fee');
                }
                if (! Schema::hasColumn('shipments', 'hub_to_buyer_distance_km')) {
                    $table->decimal('hub_to_buyer_distance_km', 8, 2)->nullable()->after('origin_to_hub_distance_km');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasColumn('shipments', 'destination_logistics_hub_id')) {
                    try {
                        $table->dropForeign('shp_dest_hub_id_fk');
                    } catch (\Throwable $e) {
                    }
                    $table->dropColumn('destination_logistics_hub_id');
                }
                $dropCols = [];
                foreach (['origin_to_hub_distance_km', 'hub_to_buyer_distance_km'] as $col) {
                    if (Schema::hasColumn('shipments', $col)) {
                        $dropCols[] = $col;
                    }
                }
                if (! empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }

        if (Schema::hasTable('seller_orders')) {
            Schema::table('seller_orders', function (Blueprint $table) {
                $dropCols = [];
                foreach (['is_unserviceable_exception', 'seller_to_buyer_distance_km'] as $col) {
                    if (Schema::hasColumn('seller_orders', $col)) {
                        $dropCols[] = $col;
                    }
                }
                if (! empty($dropCols)) {
                    $table->dropColumn($dropCols);
                }
            });
        }

        if (Schema::hasTable('logistics_hubs')) {
            Schema::table('logistics_hubs', function (Blueprint $table) {
                $dropCols = [];
                foreach (['max_courier_pickup_radius_km', 'max_rider_delivery_radius_km'] as $col) {
                    if (Schema::hasColumn('logistics_hubs', $col)) {
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