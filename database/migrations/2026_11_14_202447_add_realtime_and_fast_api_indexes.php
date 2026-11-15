<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (! Schema::hasIndex('notifications', 'notifs_notifiable_read_created_idx')) {
                    $table->index(['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'notifs_notifiable_read_created_idx');
                }
            });
        }

        if (Schema::hasTable('pickup_assignments')) {
            Schema::table('pickup_assignments', function (Blueprint $table) {
                if (! Schema::hasIndex('pickup_assignments', 'pa_courier_status_assigned_idx')) {
                    $table->index(['courier_id', 'status', 'assigned_at'], 'pa_courier_status_assigned_idx');
                }
                if (! Schema::hasIndex('pickup_assignments', 'pa_shipment_status_idx')) {
                    $table->index(['shipment_id', 'status'], 'pa_shipment_status_idx');
                }
            });
        }

        if (Schema::hasTable('delivery_assignments')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                if (! Schema::hasIndex('delivery_assignments', 'da_rider_status_assigned_idx')) {
                    $table->index(['rider_id', 'status', 'assigned_at'], 'da_rider_status_assigned_idx');
                }
                if (! Schema::hasIndex('delivery_assignments', 'da_shipment_status_idx')) {
                    $table->index(['shipment_id', 'status'], 'da_shipment_status_idx');
                }
            });
        }

        if (Schema::hasTable('seller_orders')) {
            Schema::table('seller_orders', function (Blueprint $table) {
                if (! Schema::hasIndex('seller_orders', 'so_seller_profile_status_created_idx')) {
                    $table->index(['seller_profile_id', 'status', 'created_at'], 'so_seller_profile_status_created_idx');
                }
                if (! Schema::hasIndex('seller_orders', 'so_buyer_status_created_idx')) {
                    $table->index(['buyer_id', 'status', 'created_at'], 'so_buyer_status_created_idx');
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasIndex('shipments', 'shp_logistics_status_updated_idx')) {
                    $table->index(['logistics_profile_id', 'status', 'updated_at'], 'shp_logistics_status_updated_idx');
                }
            });
        }

        if (Schema::hasTable('return_shipments')) {
            Schema::table('return_shipments', function (Blueprint $table) {
                if (! Schema::hasIndex('return_shipments', 'rshp_courier_status_updated_idx')) {
                    $table->index(['current_courier_id', 'status', 'updated_at'], 'rshp_courier_status_updated_idx');
                }
                if (! Schema::hasIndex('return_shipments', 'rshp_rider_status_updated_idx')) {
                    $table->index(['current_rider_id', 'status', 'updated_at'], 'rshp_rider_status_updated_idx');
                }
            });
        }

        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                if (! Schema::hasIndex('wallet_transactions', 'wt_owner_created_idx')) {
                    $table->index(['owner_type', 'owner_id', 'created_at'], 'wt_owner_created_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasIndex('notifications', 'notifs_notifiable_read_created_idx')) {
                    $table->dropIndex('notifs_notifiable_read_created_idx');
                }
            });
        }

        if (Schema::hasTable('pickup_assignments')) {
            Schema::table('pickup_assignments', function (Blueprint $table) {
                if (Schema::hasIndex('pickup_assignments', 'pa_courier_status_assigned_idx')) {
                    $table->dropIndex('pa_courier_status_assigned_idx');
                }
                if (Schema::hasIndex('pickup_assignments', 'pa_shipment_status_idx')) {
                    $table->dropIndex('pa_shipment_status_idx');
                }
            });
        }

        if (Schema::hasTable('delivery_assignments')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                if (Schema::hasIndex('delivery_assignments', 'da_rider_status_assigned_idx')) {
                    $table->dropIndex('da_rider_status_assigned_idx');
                }
                if (Schema::hasIndex('delivery_assignments', 'da_shipment_status_idx')) {
                    $table->dropIndex('da_shipment_status_idx');
                }
            });
        }

        if (Schema::hasTable('seller_orders')) {
            Schema::table('seller_orders', function (Blueprint $table) {
                if (Schema::hasIndex('seller_orders', 'so_seller_profile_status_created_idx')) {
                    $table->dropIndex('so_seller_profile_status_created_idx');
                }
                if (Schema::hasIndex('seller_orders', 'so_buyer_status_created_idx')) {
                    $table->dropIndex('so_buyer_status_created_idx');
                }
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasIndex('shipments', 'shp_logistics_status_updated_idx')) {
                    $table->dropIndex('shp_logistics_status_updated_idx');
                }
            });
        }

        if (Schema::hasTable('return_shipments')) {
            Schema::table('return_shipments', function (Blueprint $table) {
                if (Schema::hasIndex('return_shipments', 'rshp_courier_status_updated_idx')) {
                    $table->dropIndex('rshp_courier_status_updated_idx');
                }
                if (Schema::hasIndex('return_shipments', 'rshp_rider_status_updated_idx')) {
                    $table->dropIndex('rshp_rider_status_updated_idx');
                }
            });
        }

        if (Schema::hasTable('wallet_transactions')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                if (Schema::hasIndex('wallet_transactions', 'wt_owner_created_idx')) {
                    $table->dropIndex('wt_owner_created_idx');
                }
            });
        }
    }
};