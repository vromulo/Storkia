<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('buyer_wallet_topups')) {
            Schema::create('buyer_wallet_topups', function (Blueprint $table) {
                $table->id();
                $table->string('topup_reference', 50)->unique();
                $table->unsignedBigInteger('buyer_id');
                $table->unsignedBigInteger('role_wallet_id');
                $table->string('admin_gcash_name', 120)->nullable();
                $table->string('admin_gcash_number', 30)->nullable();
                $table->string('sender_account_name', 120);
                $table->string('sender_account_number', 30)->nullable();
                $table->string('reference_number', 80)->unique();
                $table->string('screenshot_path');
                $table->decimal('approved_amount', 14, 2)->nullable();
                $table->string('status', 30)->default('pending_verification')->index();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('wallet_transaction_id')->nullable();
                $table->timestamps();

                $table->foreign('buyer_id', 'bwt_buyer_id_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign('role_wallet_id', 'bwt_wallet_id_fk')
                    ->references('id')
                    ->on('role_wallets')
                    ->cascadeOnDelete();

                $table->index(['buyer_id', 'status'], 'bwt_buyer_status_idx');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'coins_used_total')) {
                    $table->unsignedBigInteger('coins_used_total')->default(0)->after('shipping_fee_total');
                }
                if (! Schema::hasColumn('orders', 'coins_discount_total')) {
                    $table->decimal('coins_discount_total', 12, 2)->default(0.00)->after('coins_used_total');
                }
                if (! Schema::hasColumn('orders', 'wallet_paid_total')) {
                    $table->decimal('wallet_paid_total', 12, 2)->default(0.00)->after('coins_discount_total');
                }
            });
        }

        if (Schema::hasTable('seller_order_extras')) {
            Schema::table('seller_order_extras', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_order_extras', 'coins_used')) {
                    $table->unsignedBigInteger('coins_used')->default(0)->after('shipping_voucher_discount');
                }
                if (! Schema::hasColumn('seller_order_extras', 'coins_discount_amount')) {
                    $table->decimal('coins_discount_amount', 12, 2)->default(0.00)->after('coins_used');
                }
                if (! Schema::hasColumn('seller_order_extras', 'coins_earned_on_completion')) {
                    $table->unsignedBigInteger('coins_earned_on_completion')->default(0)->after('coins_discount_amount');
                }
                if (! Schema::hasColumn('seller_order_extras', 'wallet_paid_amount')) {
                    $table->decimal('wallet_paid_amount', 12, 2)->default(0.00)->after('coins_earned_on_completion');
                }
            });
        }

        if (Schema::hasTable('order_item_reviews')) {
            Schema::table('order_item_reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('order_item_reviews', 'purchased_variant_label')) {
                    $table->string('purchased_variant_label', 180)->nullable()->after('rating');
                }
                if (! Schema::hasColumn('order_item_reviews', 'helpful_count')) {
                    $table->unsignedInteger('helpful_count')->default(0)->after('seller_reply_edited_at');
                }
                if (! Schema::hasColumn('order_item_reviews', 'coins_rewarded')) {
                    $table->unsignedInteger('coins_rewarded')->default(0)->after('helpful_count');
                }
            });
        }

        if (! Schema::hasTable('product_review_upvotes')) {
            Schema::create('product_review_upvotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('review_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->foreign('review_id', 'pru_review_id_fk')
                    ->references('id')
                    ->on('order_item_reviews')
                    ->cascadeOnDelete();

                $table->foreign('user_id', 'pru_user_id_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->unique(['review_id', 'user_id'], 'pru_review_user_unq');
            });
        }

        if (! Schema::hasTable('product_restock_subscriptions')) {
            Schema::create('product_restock_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('user_id');
                $table->string('variant_name', 100)->nullable();
                $table->string('sub_variant_name', 100)->nullable();
                $table->string('status', 25)->default('active')->index();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->foreign('product_id', 'prs_prod_id_fk')
                    ->references('id')
                    ->on('products')
                    ->cascadeOnDelete();

                $table->foreign('user_id', 'prs_user_id_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->index(['product_id', 'status'], 'prs_prod_status_idx');
                $table->index(['user_id', 'product_id'], 'prs_user_prod_idx');
            });
        }

        if (! Schema::hasTable('product_questions')) {
            Schema::create('product_questions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('seller_profile_id');
                $table->unsignedBigInteger('buyer_id');
                $table->text('question');
                $table->text('answer')->nullable();
                $table->unsignedBigInteger('answered_by_user_id')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->boolean('is_visible')->default(true)->index();
                $table->timestamps();

                $table->foreign('product_id', 'pq_prod_id_fk')
                    ->references('id')
                    ->on('products')
                    ->cascadeOnDelete();

                $table->foreign('seller_profile_id', 'pq_seller_prof_fk')
                    ->references('id')
                    ->on('seller_profiles')
                    ->cascadeOnDelete();

                $table->foreign('buyer_id', 'pq_buyer_id_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign('answered_by_user_id', 'pq_ans_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(['product_id', 'is_visible'], 'pq_prod_vis_idx');
            });
        }

        if (Schema::hasTable('super_deals')) {
            Schema::table('super_deals', function (Blueprint $table) {
                if (! Schema::hasColumn('super_deals', 'variant_name')) {
                    $table->string('variant_name', 100)->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('super_deals', 'sub_variant_name')) {
                    $table->string('sub_variant_name', 100)->nullable()->after('variant_name');
                }
            });
        }

        if (Schema::hasTable('bundle_deals')) {
            Schema::table('bundle_deals', function (Blueprint $table) {
                if (! Schema::hasColumn('bundle_deals', 'free_gift_variant_name')) {
                    $table->string('free_gift_variant_name', 100)->nullable()->after('free_gift_product_id');
                }
                if (! Schema::hasColumn('bundle_deals', 'free_gift_sub_variant_name')) {
                    $table->string('free_gift_sub_variant_name', 100)->nullable()->after('free_gift_variant_name');
                }
            });
        }

        if (Schema::hasTable('return_requests')) {
            Schema::table('return_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('return_requests', 'refund_payout_method')) {
                    $table->string('refund_payout_method', 25)->default('WALLET')->after('refund_amount');
                }
            });
        }

        if (Schema::hasTable('return_shipments')) {
            Schema::table('return_shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('return_shipments', 'delivery_attempts_count')) {
                    $table->unsignedTinyInteger('delivery_attempts_count')->default(0)->after('pickup_attempts_count');
                }
                if (! Schema::hasColumn('return_shipments', 'delivery_failure_reason')) {
                    $table->string('delivery_failure_reason', 150)->nullable()->after('pickup_failure_reason');
                }
                if (! Schema::hasColumn('return_shipments', 'delivery_failed_at')) {
                    $table->timestamp('delivery_failed_at')->nullable()->after('failed_at');
                }
            });
        }

        if (Schema::hasTable('riders')) {
            Schema::table('riders', function (Blueprint $table) {
                if (! Schema::hasColumn('riders', 'last_checklist_passed_at')) {
                    $table->timestamp('last_checklist_passed_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('delivery_assignments')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('delivery_assignments', 'declined_at')) {
                    $table->timestamp('declined_at')->nullable()->after('failed_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'decline_reason')) {
                    $table->string('decline_reason', 300)->nullable()->after('declined_at');
                }
                if (! Schema::hasColumn('delivery_assignments', 'is_delayed')) {
                    $table->boolean('is_delayed')->default(false)->after('decline_reason');
                }
                if (! Schema::hasColumn('delivery_assignments', 'delay_reason')) {
                    $table->string('delay_reason', 300)->nullable()->after('is_delayed');
                }
                if (! Schema::hasColumn('delivery_assignments', 'estimated_arrival_at')) {
                    $table->timestamp('estimated_arrival_at')->nullable()->after('delay_reason');
                }
            });
        }

        if (! Schema::hasTable('rider_shift_checklists')) {
            Schema::create('rider_shift_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rider_id');
                $table->unsignedBigInteger('logistics_profile_id');
                $table->boolean('tires_ok')->default(true);
                $table->boolean('lights_ok')->default(true);
                $table->boolean('brakes_ok')->default(true);
                $table->boolean('fuel_ok')->default(true);
                $table->boolean('helmet_gear_ok')->default(true);
                $table->boolean('thermal_bag_ok')->default(true);
                $table->boolean('all_passed')->default(true)->index();
                $table->string('failed_items_summary', 255)->nullable();
                $table->string('notes', 300)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamp('submitted_at')->index();
                $table->timestamps();

                $table->foreign('rider_id', 'rsc_rider_id_fk')
                    ->references('id')
                    ->on('riders')
                    ->cascadeOnDelete();

                $table->foreign('logistics_profile_id', 'rsc_log_prof_fk')
                    ->references('id')
                    ->on('logistics_profiles')
                    ->cascadeOnDelete();

                $table->index(['rider_id', 'submitted_at'], 'rsc_rider_sub_idx');
            });
        }

        if (! Schema::hasTable('rider_trip_incidents')) {
            Schema::create('rider_trip_incidents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_assignment_id');
                $table->unsignedBigInteger('shipment_id');
                $table->unsignedBigInteger('rider_id');
                $table->unsignedBigInteger('logistics_profile_id');
                $table->string('incident_type', 30)->index();
                $table->string('reason', 150);
                $table->string('notes', 300)->nullable();
                $table->timestamp('estimated_arrival_at')->nullable();
                $table->string('photo_path')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('status', 30)->default('reported')->index();
                $table->timestamps();

                $table->foreign('delivery_assignment_id', 'rti_deliv_asg_fk')
                    ->references('id')
                    ->on('delivery_assignments')
                    ->cascadeOnDelete();

                $table->foreign('shipment_id', 'rti_ship_id_fk')
                    ->references('id')
                    ->on('shipments')
                    ->cascadeOnDelete();

                $table->foreign('rider_id', 'rti_rider_id_fk')
                    ->references('id')
                    ->on('riders')
                    ->cascadeOnDelete();

                $table->foreign('logistics_profile_id', 'rti_log_prof_fk')
                    ->references('id')
                    ->on('logistics_profiles')
                    ->cascadeOnDelete();

                $table->index(['delivery_assignment_id', 'incident_type'], 'rti_asg_type_idx');
            });
        }

        if (! Schema::hasTable('delivery_chat_messages')) {
            Schema::create('delivery_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shipment_id');
                $table->unsignedBigInteger('seller_order_id');
                $table->unsignedBigInteger('delivery_assignment_id')->nullable();
                $table->unsignedBigInteger('buyer_id');
                $table->unsignedBigInteger('rider_id');
                $table->string('sender_type', 15);
                $table->string('message', 1000);
                $table->string('attachment_path')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->foreign('shipment_id', 'dcm_ship_id_fk')
                    ->references('id')
                    ->on('shipments')
                    ->cascadeOnDelete();

                $table->foreign('seller_order_id', 'dcm_so_id_fk')
                    ->references('id')
                    ->on('seller_orders')
                    ->cascadeOnDelete();

                $table->foreign('delivery_assignment_id', 'dcm_deliv_asg_fk')
                    ->references('id')
                    ->on('delivery_assignments')
                    ->nullOnDelete();

                $table->foreign('buyer_id', 'dcm_buyer_id_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->foreign('rider_id', 'dcm_rider_id_fk')
                    ->references('id')
                    ->on('riders')
                    ->cascadeOnDelete();

                $table->index(['shipment_id', 'id'], 'dcm_ship_msg_idx');
                $table->index(['seller_order_id', 'is_read'], 'dcm_so_unread_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_chat_messages');
        Schema::dropIfExists('rider_trip_incidents');
        Schema::dropIfExists('rider_shift_checklists');
        Schema::dropIfExists('product_questions');
        Schema::dropIfExists('product_restock_subscriptions');
        Schema::dropIfExists('product_review_upvotes');
        Schema::dropIfExists('buyer_wallet_topups');
    }
};