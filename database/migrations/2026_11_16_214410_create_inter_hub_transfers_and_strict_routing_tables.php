<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_orders')) {
            Schema::table('seller_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_orders', 'origin_logistics_hub_id')) {
                    $table->unsignedBigInteger('origin_logistics_hub_id')->nullable()->index()->after('logistics_profile_id');
                    $table->foreign('origin_logistics_hub_id', 'so_orig_hub_id_fk')
                        ->references('id')
                        ->on('logistics_hubs')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('seller_orders', 'destination_logistics_hub_id')) {
                    $table->unsignedBigInteger('destination_logistics_hub_id')->nullable()->index()->after('origin_logistics_hub_id');
                    $table->foreign('destination_logistics_hub_id', 'so_dest_hub_id_fk')
                        ->references('id')
                        ->on('logistics_hubs')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'origin_logistics_hub_id')) {
                    $table->unsignedBigInteger('origin_logistics_hub_id')->nullable()->index()->after('logistics_profile_id');
                    $table->foreign('origin_logistics_hub_id', 'shp_orig_hub_id_fk')
                        ->references('id')
                        ->on('logistics_hubs')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('shipments', 'current_logistics_hub_id')) {
                    $table->unsignedBigInteger('current_logistics_hub_id')->nullable()->index()->after('destination_logistics_hub_id');
                    $table->foreign('current_logistics_hub_id', 'shp_curr_hub_id_fk')
                        ->references('id')
                        ->on('logistics_hubs')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('shipments', 'linehaul_courier_id')) {
                    $table->unsignedBigInteger('linehaul_courier_id')->nullable()->index()->after('current_courier_id');
                    $table->foreign('linehaul_courier_id', 'shp_lh_courier_id_fk')
                        ->references('id')
                        ->on('couriers')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('shipments', 'requires_inter_hub_transfer')) {
                    $table->boolean('requires_inter_hub_transfer')->default(false)->index()->after('status');
                }

                if (! Schema::hasColumn('shipments', 'inter_hub_dispatched_at')) {
                    $table->timestamp('inter_hub_dispatched_at')->nullable()->after('arrived_at_hub_at');
                }

                if (! Schema::hasColumn('shipments', 'arrived_at_destination_hub_at')) {
                    $table->timestamp('arrived_at_destination_hub_at')->nullable()->after('inter_hub_dispatched_at');
                }

                if (! Schema::hasColumn('shipments', 'destination_hub_verified_by')) {
                    $table->unsignedBigInteger('destination_hub_verified_by')->nullable()->after('arrived_at_destination_hub_at');
                    $table->foreign('destination_hub_verified_by', 'shp_dest_hub_ver_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('inter_hub_transfers')) {
            Schema::create('inter_hub_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_reference', 50)->unique();
                $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
                $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
                $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
                $table->foreignId('origin_hub_id')->constrained('logistics_hubs')->cascadeOnDelete();
                $table->foreignId('destination_hub_id')->constrained('logistics_hubs')->cascadeOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
                $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 35)->default('pending_transfer')->index();
                $table->decimal('distance_km', 8, 2)->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('departed_origin_hub_at')->nullable();
                $table->timestamp('arrived_destination_hub_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['shipment_id', 'status'], 'iht_shipment_status_idx');
                $table->index(['origin_hub_id', 'status'], 'iht_orig_hub_status_idx');
                $table->index(['destination_hub_id', 'status'], 'iht_dest_hub_status_idx');
                $table->index(['courier_id', 'status'], 'iht_courier_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inter_hub_transfers');

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                foreach (['shp_orig_hub_id_fk', 'shp_curr_hub_id_fk', 'shp_lh_courier_id_fk', 'shp_dest_hub_ver_fk'] as $fk) {
                    try {
                        $table->dropForeign($fk);
                    } catch (\Throwable $e) {
                    }
                }

                $dropCols = [];
                foreach ([
                    'origin_logistics_hub_id',
                    'current_logistics_hub_id',
                    'linehaul_courier_id',
                    'requires_inter_hub_transfer',
                    'inter_hub_dispatched_at',
                    'arrived_at_destination_hub_at',
                    'destination_hub_verified_by',
                ] as $col) {
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
                foreach (['so_orig_hub_id_fk', 'so_dest_hub_id_fk'] as $fk) {
                    try {
                        $table->dropForeign($fk);
                    } catch (\Throwable $e) {
                    }
                }

                $dropCols = [];
                foreach (['origin_logistics_hub_id', 'destination_logistics_hub_id'] as $col) {
                    if (Schema::hasColumn('seller_orders', $col)) {
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