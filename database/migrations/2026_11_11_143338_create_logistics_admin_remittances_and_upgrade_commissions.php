<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logistics_admin_remittances')) {
            Schema::create('logistics_admin_remittances', function (Blueprint $table) {
                $table->id();
                $table->string('remittance_reference', 50)->unique();
                $table->unsignedBigInteger('logistics_profile_id');
                $table->unsignedBigInteger('submitted_by_user_id')->nullable();
                $table->decimal('total_cod_amount', 14, 2)->default(0.00);
                $table->decimal('total_product_subtotal', 14, 2)->default(0.00);
                $table->decimal('total_shipping_fee', 14, 2)->default(0.00);
                $table->decimal('total_admin_commission', 14, 2)->default(0.00);
                $table->decimal('total_seller_net', 14, 2)->default(0.00);
                $table->decimal('total_logistics_earning', 14, 2)->default(0.00);
                $table->decimal('total_courier_commission', 14, 2)->default(0.00);
                $table->decimal('total_rider_commission', 14, 2)->default(0.00);
                $table->string('payout_method', 25)->default('GCASH');
                $table->string('admin_gcash_name', 120)->nullable();
                $table->string('admin_gcash_number', 30)->nullable();
                $table->string('sender_account_name', 120);
                $table->string('sender_account_number', 30);
                $table->string('gcash_reference_number', 80)->unique();
                $table->string('proof_image_path')->nullable();
                $table->string('status', 30)->default('pending_verification')->index();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->index();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->foreign('logistics_profile_id', 'lar_log_prof_fk')
                    ->references('id')
                    ->on('logistics_profiles')
                    ->cascadeOnDelete();

                $table->foreign('submitted_by_user_id', 'lar_sub_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(['logistics_profile_id', 'status'], 'lar_logistics_status_idx');
            });
        }

        if (
            Schema::hasTable('logistics_admin_remittance_items')
            && DB::table('logistics_admin_remittance_items')->count() === 0
        ) {
            Schema::dropIfExists('logistics_admin_remittance_items');
        }

        if (! Schema::hasTable('logistics_admin_remittance_items')) {
            Schema::create('logistics_admin_remittance_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('logistics_admin_remittance_id');
                $table->unsignedBigInteger('cod_collection_id');
                $table->unsignedBigInteger('cod_remittance_id')->nullable();
                $table->unsignedBigInteger('seller_order_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->unsignedBigInteger('commission_record_id')->nullable();
                $table->decimal('cod_amount', 14, 2)->default(0.00);
                $table->decimal('product_subtotal', 14, 2)->default(0.00);
                $table->decimal('shipping_fee', 14, 2)->default(0.00);
                $table->decimal('admin_commission_amount', 14, 2)->default(0.00);
                $table->decimal('seller_net_earning', 14, 2)->default(0.00);
                $table->decimal('logistics_earning', 14, 2)->default(0.00);
                $table->decimal('courier_commission_amount', 14, 2)->default(0.00);
                $table->decimal('rider_commission_amount', 14, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('logistics_admin_remittance_id', 'lari_lar_id_fk')
                    ->references('id')
                    ->on('logistics_admin_remittances')
                    ->cascadeOnDelete();

                $table->foreign('cod_collection_id', 'lari_cod_col_fk')
                    ->references('id')
                    ->on('cod_collections')
                    ->cascadeOnDelete();

                $table->foreign('cod_remittance_id', 'lari_cod_rem_fk')
                    ->references('id')
                    ->on('cod_remittances')
                    ->nullOnDelete();

                $table->foreign('seller_order_id', 'lari_so_id_fk')
                    ->references('id')
                    ->on('seller_orders')
                    ->cascadeOnDelete();

                $table->foreign('shipment_id', 'lari_ship_id_fk')
                    ->references('id')
                    ->on('shipments')
                    ->nullOnDelete();

                $table->foreign('commission_record_id', 'lari_comm_rec_fk')
                    ->references('id')
                    ->on('commission_records')
                    ->nullOnDelete();

                $table->index(['logistics_admin_remittance_id', 'cod_collection_id'], 'lari_remit_col_idx');
                $table->index(['seller_order_id'], 'lari_seller_order_idx');
            });
        }

        if (Schema::hasTable('commission_records')) {
            Schema::table('commission_records', function (Blueprint $table) {
                if (! Schema::hasColumn('commission_records', 'courier_id')) {
                    $table->unsignedBigInteger('courier_id')->nullable()->after('logistics_profile_id');
                    $table->foreign('courier_id', 'cr_courier_id_fk')
                        ->references('id')
                        ->on('couriers')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('commission_records', 'courier_commission_rate')) {
                    $table->decimal('courier_commission_rate', 5, 2)->default(0.00)->after('logistics_commission_amount');
                }
                if (! Schema::hasColumn('commission_records', 'courier_commission_amount')) {
                    $table->decimal('courier_commission_amount', 12, 2)->default(0.00)->after('courier_commission_rate');
                }
                if (! Schema::hasColumn('commission_records', 'operational_fare_fee')) {
                    $table->decimal('operational_fare_fee', 12, 2)->default(0.00)->after('shipping_fee');
                }
                if (! Schema::hasColumn('commission_records', 'cod_remittance_stage')) {
                    $table->string('cod_remittance_stage', 35)->default('awaiting_rider')->index()->after('status');
                }
                if (! Schema::hasColumn('commission_records', 'logistics_admin_remittance_id')) {
                    $table->unsignedBigInteger('logistics_admin_remittance_id')->nullable()->index()->after('cod_collection_id');
                }
            });
        }

        if (Schema::hasTable('role_wallets')) {
            Schema::table('role_wallets', function (Blueprint $table) {
                if (! Schema::hasColumn('role_wallets', 'unremitted_cod_balance')) {
                    $table->decimal('unremitted_cod_balance', 14, 2)->default(0.00)->after('platform_commission_arrears');
                }
            });
        }

        if (Schema::hasTable('cod_collections')) {
            Schema::table('cod_collections', function (Blueprint $table) {
                if (! Schema::hasColumn('cod_collections', 'logistics_admin_remittance_id')) {
                    $table->unsignedBigInteger('logistics_admin_remittance_id')->nullable()->index()->after('delivery_assignment_id');
                }
                if (! Schema::hasColumn('cod_collections', 'admin_remittance_status')) {
                    $table->string('admin_remittance_status', 30)->default('unremitted')->index()->after('status');
                }
                if (! Schema::hasColumn('cod_collections', 'admin_remitted_at')) {
                    $table->timestamp('admin_remitted_at')->nullable()->after('verified_at');
                }
                if (! Schema::hasColumn('cod_collections', 'admin_verified_at')) {
                    $table->timestamp('admin_verified_at')->nullable()->after('admin_remitted_at');
                }
                if (! Schema::hasColumn('cod_collections', 'admin_verified_by_admin_id')) {
                    $table->unsignedBigInteger('admin_verified_by_admin_id')->nullable()->after('admin_verified_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_admin_remittance_items');
        Schema::dropIfExists('logistics_admin_remittances');
    }
};