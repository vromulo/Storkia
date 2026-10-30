<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\LogisticsEmployeeHubAssignment;
use App\Models\LogisticsHub;
use App\Models\LogisticsProfile;
use App\Models\Rider;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\ShipmentPackage;
use App\Models\StorkVoucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StorkSchemaBootstrapService
{
    protected const SCHEMA_VERSION_CACHE_KEY = 'storkia_additive_schema_v5_ready';

    public static function ensureTablesExist(): void
    {
        if (Cache::get(self::SCHEMA_VERSION_CACHE_KEY) === true) {
            return;
        }

        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('logistics_profiles')) {
                return;
            }

            self::createLogisticsHubsTable();
            self::createLogisticsEmployeeHubAssignmentsTable();
            self::createRiderBarangayCoveragesTable();
            self::createShipmentPackagesTable();
            self::createShipmentVerificationsTable();

            self::createSellerGcashAccountsTable();
            self::createOrderPaymentSubmissionsTable();
            self::createStorkVouchersTable();
            self::createBuyerVoucherClaimsTable();
            self::createSellerOrderExtrasTable();
            self::upgradeSellerOrderExtrasRefundColumns();
            self::createBuyerCancellationLogsTable();

            self::createRoleWalletsTable();
            self::upgradeRoleWalletsColumns();
            self::createWalletTransactionsTable();
            self::createBuyerAccountSanctionsTable();
            self::createBuyerBanAppealsTable();
            self::createSellerRtsClaimsTable();
            self::createOrderItemReviewsTable();
            self::upgradeOrderItemReviewsColumns();
            self::createOrderServiceRatingsTable();
            self::createSellerStoreFollowsTable();

            self::createPlatformAuditLogsTable();
            self::createSellerPayoutRequestsTable();
            self::createSellerCommissionRemittancesTable();
            self::createBuyerWishlistsTable();
            self::createSuperDealsTable();
            self::createBundleDealsTable();
            self::createLogisticsShippingRatesTable();
            self::createPlatformAnnouncementsTable();
            self::createHeroBannersTable();
            self::upgradeOperationalProfilesAndProductsColumns();

            self::backfillMainHubsAndPackages();
            self::seedDefaultVouchers();

            Cache::put(self::SCHEMA_VERSION_CACHE_KEY, true, now()->addHours(12));
        } catch (\Throwable $e) {
        }
    }

    protected static function createLogisticsHubsTable(): void
    {
        if (Schema::hasTable('logistics_hubs')) {
            return;
        }

        Schema::create('logistics_hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('branch_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('hub_code', 40)->unique();
            $table->string('hub_name', 120);
            $table->boolean('is_main_hub')->default(false)->index();
            $table->string('email_prefix', 60)->nullable();
            $table->string('contact_no', 30)->nullable();
            $table->string('province_code', 30)->nullable();
            $table->string('province', 100);
            $table->string('municipality_code', 30)->nullable();
            $table->string('municipality', 100)->index();
            $table->string('barangay_code', 30)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('house_details', 150)->nullable();
            $table->string('status', 25)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_logistics_profile_id', 'is_main_hub']);
            $table->index(['parent_logistics_profile_id', 'status']);
        });
    }

    protected static function createLogisticsEmployeeHubAssignmentsTable(): void
    {
        if (Schema::hasTable('logistics_employee_hub_assignments')) {
            return;
        }

        Schema::create('logistics_employee_hub_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_hub_id')->constrained('logistics_hubs')->cascadeOnDelete();
            $table->string('employee_type', 20)->index();
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->cascadeOnDelete();
            $table->string('municipality', 100)->index();
            $table->boolean('is_current')->default(true)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'is_current']);
            $table->index(['rider_id', 'is_current']);
            $table->index(['logistics_hub_id', 'employee_type', 'is_current'], 'leha_hub_type_current_idx');
        });
    }

    protected static function createRiderBarangayCoveragesTable(): void
    {
        if (Schema::hasTable('rider_barangay_coverages')) {
            return;
        }

        Schema::create('rider_barangay_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_hub_id')->constrained('logistics_hubs')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->string('municipality_code', 30)->nullable();
            $table->string('municipality', 100)->index();
            $table->string('barangay_code', 30)->nullable();
            $table->string('barangay_name', 120)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rider_id', 'is_active']);
            $table->index(['logistics_hub_id', 'barangay_name', 'is_active'], 'rbc_hub_brgy_active_idx');
        });
    }

    protected static function createShipmentPackagesTable(): void
    {
        if (Schema::hasTable('shipment_packages')) {
            return;
        }

        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $table->string('package_reference', 50)->unique();
            $table->string('qr_token', 64)->unique();
            $table->unsignedSmallInteger('package_number')->default(1);
            $table->unsignedSmallInteger('total_packages')->default(1);
            $table->string('handover_method', 30)->default('courier_pickup')->index();
            $table->decimal('package_weight', 10, 3)->default(0);
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->unsignedInteger('total_items_count')->default(1);
            $table->json('manifest_snapshot');
            $table->unsignedInteger('label_printed_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamp('seller_prepared_at')->nullable();
            $table->timestamp('courier_verified_at')->nullable();
            $table->string('courier_verification_method', 20)->nullable();
            $table->timestamp('seller_dropped_off_at')->nullable();
            $table->timestamp('hub_verified_at')->nullable();
            $table->foreignId('hub_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hub_verification_method', 20)->nullable();
            $table->timestamp('rider_accepted_at')->nullable();
            $table->timestamp('rider_verified_at')->nullable();
            $table->string('rider_verification_method', 20)->nullable();
            $table->timestamp('rider_collected_at')->nullable();
            $table->timestamp('delivery_verified_at')->nullable();
            $table->string('delivery_verification_method', 20)->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'package_number']);
            $table->index(['logistics_profile_id', 'logistics_hub_id']);
        });
    }

    protected static function createShipmentVerificationsTable(): void
    {
        if (Schema::hasTable('shipment_verifications')) {
            return;
        }

        Schema::create('shipment_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('shipment_package_id')->constrained('shipment_packages')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $table->string('stage', 40)->index();
            $table->string('verification_method', 20);
            $table->string('lookup_token_or_number', 100);
            $table->string('actor_type', 30);
            $table->unsignedBigInteger('actor_id');
            $table->boolean('is_confirmed')->default(false)->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'stage', 'is_confirmed'], 'sv_shipment_stage_confirmed_idx');
        });
    }

    protected static function createSellerGcashAccountsTable(): void
    {
        if (Schema::hasTable('seller_gcash_accounts')) {
            return;
        }

        Schema::create('seller_gcash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->unique()->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('account_name', 120);
            $table->string('gcash_number', 25);
            $table->string('qr_image_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    protected static function createOrderPaymentSubmissionsTable(): void
    {
        if (Schema::hasTable('order_payment_submissions')) {
            return;
        }

        Schema::create('order_payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('seller_gcash_name', 120);
            $table->string('seller_gcash_number', 25);
            $table->string('sender_gcash_number', 25);
            $table->timestamp('paid_at');
            $table->string('reference_number', 80)->unique();
            $table->string('reference_code', 80);
            $table->string('screenshot_path');
            $table->decimal('submitted_amount', 12, 2);
            $table->string('status', 35)->default('submitted')->index();
            $table->boolean('is_suspicious')->default(false)->index();
            $table->text('suspicious_reason')->nullable();
            $table->timestamp('suspicious_reported_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('switched_to_cod')->default(false)->index();
            $table->timestamp('switched_to_cod_at')->nullable();
            $table->timestamp('seller_verified_at')->nullable();
            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
            $table->index(['buyer_id', 'status']);
        });
    }

    protected static function createStorkVouchersTable(): void
    {
        if (Schema::hasTable('stork_vouchers')) {
            return;
        }

        Schema::create('stork_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->nullable()->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('title', 120);
            $table->string('description', 255)->nullable();
            $table->string('scope', 25)->default('store')->index();
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount_cap', 12, 2)->nullable();
            $table->decimal('min_spend', 12, 2)->default(0);
            $table->unsignedInteger('total_usage_limit')->default(100);
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedSmallInteger('per_buyer_limit')->default(1);
            $table->string('visibility', 20)->default('claimable')->index();
            $table->json('applicable_product_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
            $table->index(['scope', 'status']);
        });
    }

    protected static function createBuyerVoucherClaimsTable(): void
    {
        if (Schema::hasTable('buyer_voucher_claims')) {
            return;
        }

        Schema::create('buyer_voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('stork_vouchers')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('times_used')->default(0);
            $table->foreignId('last_seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['voucher_id', 'buyer_id']);
            $table->index(['buyer_id', 'times_used']);
        });
    }

    protected static function createSellerOrderExtrasTable(): void
    {
        if (Schema::hasTable('seller_order_extras')) {
            return;
        }

        Schema::create('seller_order_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->string('buyer_note', 200)->nullable();
            $table->foreignId('store_voucher_id')->nullable()->constrained('stork_vouchers')->nullOnDelete();
            $table->string('store_voucher_code', 20)->nullable();
            $table->decimal('store_voucher_discount', 12, 2)->default(0);
            $table->foreignId('platform_voucher_id')->nullable()->constrained('stork_vouchers')->nullOnDelete();
            $table->string('platform_voucher_code', 20)->nullable();
            $table->decimal('platform_voucher_discount', 12, 2)->default(0);
            $table->foreignId('shipping_voucher_id')->nullable()->constrained('stork_vouchers')->nullOnDelete();
            $table->string('shipping_voucher_code', 20)->nullable();
            $table->decimal('shipping_voucher_discount', 12, 2)->default(0);
            $table->decimal('original_subtotal_amount', 12, 2)->default(0);
            $table->decimal('original_shipping_fee', 12, 2)->default(0);
            $table->string('buyer_cancel_reason', 150)->nullable();
            $table->text('buyer_cancel_notes')->nullable();
            $table->foreignId('cancelled_by_buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('dispute_evidence_photos')->nullable();
            $table->string('dispute_reason_category', 120)->nullable();
            $table->string('seller_return_decision', 30)->nullable();
            $table->text('seller_return_Decision_reason')->nullable();
            $table->timestamp('seller_return_responded_at')->nullable();
            $table->boolean('is_escalated_to_admin')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->string('refund_method', 35)->nullable();
            $table->string('refund_account_name', 120)->nullable();
            $table->string('refund_account_number', 40)->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0.00);
            $table->string('refund_status', 30)->nullable()->index();
            $table->timestamp('refund_released_at')->nullable();
            $table->timestamps();
        });
    }

    protected static function upgradeSellerOrderExtrasRefundColumns(): void
    {
        if (! Schema::hasTable('seller_order_extras')) {
            return;
        }

        Schema::table('seller_order_extras', function (Blueprint $table) {
            if (! Schema::hasColumn('seller_order_extras', 'refund_method')) {
                $table->string('refund_method', 35)->nullable();
                $table->string('refund_account_name', 120)->nullable();
                $table->string('refund_account_number', 40)->nullable();
                $table->decimal('refund_amount', 12, 2)->default(0.00);
                $table->string('refund_status', 30)->nullable()->index();
                $table->timestamp('refund_released_at')->nullable();
            }
        });
    }

    protected static function createBuyerCancellationLogsTable(): void
    {
        if (Schema::hasTable('buyer_cancellation_logs')) {
            return;
        }

        Schema::create('buyer_cancellation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('cancel_reason', 150);
            $table->timestamp('cancelled_at')->index();
            $table->timestamps();

            $table->index(['buyer_id', 'cancelled_at']);
            $table->index(['buyer_id', 'seller_profile_id', 'cancelled_at'], 'bcl_buyer_seller_date_idx');
            $table->index(['buyer_id', 'product_id', 'cancelled_at'], 'bcl_buyer_prod_date_idx');
        });
    }

    protected static function createRoleWalletsTable(): void
    {
        if (Schema::hasTable('role_wallets')) {
            return;
        }

        Schema::create('role_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 30)->index();
            $table->unsignedBigInteger('owner_id')->index();
            $table->string('owner_name', 150)->nullable();
            $table->decimal('pending_balance', 14, 2)->default(0.00);
            $table->decimal('available_balance', 14, 2)->default(0.00);
            $table->decimal('platform_commission_arrears', 14, 2)->default(0.00);
            $table->decimal('total_earned', 14, 2)->default(0.00);
            $table->decimal('total_withdrawn', 14, 2)->default(0.00);
            $table->timestamp('last_credited_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id'], 'rw_owner_type_id_unique');
        });
    }

    protected static function upgradeRoleWalletsColumns(): void
    {
        if (! Schema::hasTable('role_wallets')) {
            return;
        }

        Schema::table('role_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('role_wallets', 'platform_commission_arrears')) {
                $table->decimal('platform_commission_arrears', 14, 2)->default(0.00)->after('available_balance');
            }
        });
    }

    protected static function createWalletTransactionsTable(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_wallet_id')->constrained('role_wallets')->cascadeOnDelete();
            $table->string('transaction_reference', 50)->unique();
            $table->string('owner_type', 30)->index();
            $table->unsignedBigInteger('owner_id')->index();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->string('payment_method', 20)->default('COD');
            $table->decimal('amount', 14, 2);
            $table->decimal('pending_balance_after', 14, 2)->default(0.00);
            $table->decimal('available_balance_after', 14, 2)->default(0.00);
            $table->string('status', 25)->default('completed')->index();
            $table->string('description', 255);
            $table->json('meta_snapshot')->nullable();
            $table->timestamps();

            $table->index(['seller_order_id', 'owner_type', 'entry_type'], 'wt_order_owner_entry_idx');
        });
    }

    protected static function createBuyerAccountSanctionsTable(): void
    {
        if (Schema::hasTable('buyer_account_sanctions')) {
            return;
        }

        Schema::create('buyer_account_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('cod_abandonment_strikes')->default(0);
            $table->unsignedSmallInteger('ban_level')->default(0);
            $table->string('account_status', 35)->default('active')->index();
            $table->timestamp('temp_ban_started_at')->nullable();
            $table->timestamp('temp_ban_ends_at')->nullable();
            $table->boolean('appeal_required')->default(false)->index();
            $table->timestamp('permanently_banned_at')->nullable();
            $table->foreignId('last_abandoned_seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
            $table->text('last_abandonment_reason')->nullable();
            $table->json('strike_history')->nullable();
            $table->timestamps();
        });
    }

    protected static function createBuyerBanAppealsTable(): void
    {
        if (Schema::hasTable('buyer_ban_appeals')) {
            return;
        }

        Schema::create('buyer_ban_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sanction_id')->constrained('buyer_account_sanctions')->cascadeOnDelete();
            $table->unsignedSmallInteger('ban_level')->default(1);
            $table->text('appeal_explanation');
            $table->string('supporting_photo_path')->nullable();
            $table->string('status', 25)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
        });
    }

    protected static function createSellerRtsClaimsTable(): void
    {
        if (Schema::hasTable('seller_rts_claims')) {
            return;
        }

        Schema::create('seller_rts_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->nullable()->constrained('logistics_profiles')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('abandonment_reason', 255);
            $table->unsignedSmallInteger('failed_attempts_count')->default(1);
            $table->string('rts_status', 35)->default('in_transit_to_seller')->index();
            $table->boolean('seller_received_parcel')->default(false);
            $table->timestamp('seller_confirmed_received_at')->nullable();
            $table->boolean('is_damaged_or_lost')->default(false);
            $table->decimal('claimed_product_expense', 12, 2)->default(0.00);
            $table->text('claim_description')->nullable();
            $table->string('evidence_photo_path')->nullable();
            $table->string('reimbursement_status', 30)->default('not_applicable')->index();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->text('admin_resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    protected static function createOrderItemReviewsTable(): void
    {
        if (Schema::hasTable('order_item_reviews')) {
            return;
        }

        Schema::create('order_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('review_text')->nullable();
            $table->string('review_photo_path')->nullable();
            $table->json('review_photos')->nullable();
            $table->unsignedTinyInteger('edit_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->unsignedTinyInteger('seller_reply_edit_count')->default(0);
            $table->timestamp('seller_reply_edited_at')->nullable();
            $table->timestamps();

            $table->index(['seller_order_id', 'buyer_id']);
            $table->index(['product_id', 'rating']);
        });
    }

    protected static function upgradeOrderItemReviewsColumns(): void
    {
        if (! Schema::hasTable('order_item_reviews')) {
            return;
        }

        Schema::table('order_item_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('order_item_reviews', 'review_photos')) {
                $table->json('review_photos')->nullable()->after('review_photo_path');
                $table->unsignedTinyInteger('edit_count')->default(0)->after('review_photos');
                $table->timestamp('edited_at')->nullable()->after('edit_count');
                $table->unsignedTinyInteger('seller_reply_edit_count')->default(0)->after('seller_replied_at');
                $table->timestamp('seller_reply_edited_at')->nullable()->after('seller_reply_edit_count');
            }
        });
    }

    protected static function createOrderServiceRatingsTable(): void
    {
        if (Schema::hasTable('order_service_ratings')) {
            return;
        }

        Schema::create('order_service_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->nullable()->constrained('logistics_profiles')->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->unsignedTinyInteger('product_quality_rating')->default(5);
            $table->unsignedTinyInteger('parcel_condition_rating')->default(5);
            $table->unsignedTinyInteger('seller_service_rating')->default(5);
            $table->unsignedTinyInteger('logistics_hub_rating')->default(5);
            $table->unsignedTinyInteger('rider_delivery_rating')->default(5);
            $table->decimal('overall_average_rating', 3, 2)->default(5.00);
            $table->text('review_comment')->nullable();
            $table->string('review_photo_path')->nullable();
            $table->timestamps();

            $table->index(['seller_profile_id', 'overall_average_rating'], 'osr_seller_avg_idx');
            $table->index(['buyer_id', 'seller_order_id']);
        });
    }

    protected static function createSellerStoreFollowsTable(): void
    {
        if (Schema::hasTable('seller_store_follows')) {
            return;
        }

        Schema::create('seller_store_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('follower_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_following')->default(true)->index();
            $table->foreignId('reward_voucher_id')->nullable()->constrained('stork_vouchers')->nullOnDelete();
            $table->string('reward_voucher_code', 25)->nullable();
            $table->timestamp('reward_issued_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_profile_id', 'follower_user_id'], 'ssf_seller_follower_unique');
        });
    }

    protected static function createPlatformAuditLogsTable(): void
    {
        if (Schema::hasTable('platform_audit_logs')) {
            return;
        }

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 30)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name', 150)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->string('action', 80)->index();
            $table->string('target_type', 60)->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_reference', 80)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    protected static function createSellerPayoutRequestsTable(): void
    {
        if (Schema::hasTable('seller_payout_requests')) {
            return;
        }

        Schema::create('seller_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('payout_reference', 50)->unique();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('role_wallet_id')->constrained('role_wallets')->cascadeOnDelete();
            $table->decimal('requested_amount', 14, 2);
            $table->string('payout_method', 35)->default('GCASH');
            $table->string('account_name', 120);
            $table->string('account_number', 50);
            $table->string('status', 25)->default('pending')->index();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->unsignedBigInteger('processed_by_admin_id')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
        });
    }

    protected static function createSellerCommissionRemittancesTable(): void
    {
        if (Schema::hasTable('seller_commission_remittances')) {
            return;
        }

        Schema::create('seller_commission_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('remittance_reference', 50)->unique();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('role_wallet_id')->constrained('role_wallets')->cascadeOnDelete();
            $table->decimal('amount_remitted', 14, 2);
            $table->string('sender_gcash_number', 25);
            $table->string('gcash_reference_number', 80)->unique();
            $table->string('screenshot_path')->nullable();
            $table->string('status', 25)->default('pending')->index();
            $table->unsignedBigInteger('verified_by_admin_id')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    protected static function createBuyerWishlistsTable(): void
    {
        if (Schema::hasTable('buyer_wishlists')) {
            return;
        }

        Schema::create('buyer_wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['buyer_id', 'product_id'], 'bw_buyer_product_unique');
        });
    }

    protected static function createSuperDealsTable(): void
    {
        if (Schema::hasTable('super_deals')) {
            return;
        }

        Schema::create('super_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('deal_type', 25)->default('super_deal')->index();
            $table->string('title', 120)->nullable();
            $table->decimal('deal_price', 12, 2)->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->unsignedInteger('flash_quantity')->nullable();
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('status', 25)->default('active')->index();
            $table->timestamps();

            $table->index(['product_id', 'status', 'starts_at', 'ends_at'], 'sd_prod_status_dates_idx');
        });
    }

    protected static function createBundleDealsTable(): void
    {
        if (Schema::hasTable('bundle_deals')) {
            return;
        }

        Schema::create('bundle_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('bundle_type', 25)->default('percentage_off');
            $table->unsignedSmallInteger('required_quantity')->default(3);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->foreignId('free_gift_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('applicable_product_ids');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 25)->default('active')->index();
            $table->timestamps();
        });
    }

    protected static function createLogisticsShippingRatesTable(): void
    {
        if (Schema::hasTable('logistics_shipping_rates')) {
            return;
        }

        Schema::create('logistics_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_profile_id')->nullable()->constrained('logistics_profiles')->cascadeOnDelete();
            $table->string('zone_code', 40)->index();
            $table->string('zone_name', 120);
            $table->string('origin_region', 80)->nullable();
            $table->string('destination_region', 80)->nullable();
            $table->decimal('first_kg_rate', 10, 2)->default(50.00);
            $table->decimal('additional_kg_rate', 10, 2)->default(15.00);
            $table->boolean('is_serviceable')->default(true)->index();
            $table->timestamps();
        });
    }

    protected static function createPlatformAnnouncementsTable(): void
    {
        if (Schema::hasTable('platform_announcements')) {
            return;
        }

        Schema::create('platform_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('category', 40)->default('announcement');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    protected static function createHeroBannersTable(): void
    {
        if (Schema::hasTable('hero_banners')) {
            return;
        }

        Schema::create('hero_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('subtitle', 255)->nullable();
            $table->string('badge_text', 60)->nullable();
            $table->string('cta_text', 60)->default('Shop Now');
            $table->string('cta_url', 255)->default('/shop');
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    protected static function upgradeOperationalProfilesAndProductsColumns(): void
    {
        if (Schema::hasTable('seller_profiles')) {
            Schema::table('seller_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_profiles', 'is_on_vacation')) {
                    $table->boolean('is_on_vacation')->default(false)->index();
                    $table->string('store_description', 500)->nullable();
                    $table->string('banner_path')->nullable();
                    $table->unsignedSmallInteger('sla_strikes')->default(0);
                    $table->boolean('gcash_blocked_for_arrears')->default(false);
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'publish_status')) {
                    $table->string('publish_status', 25)->default('active')->index();
                    $table->unsignedSmallInteger('per_buyer_limit')->nullable();
                    $table->boolean('low_stock_alerted')->default(false);
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'is_online')) {
                    $table->boolean('is_online')->default(false)->index();
                    $table->timestamp('last_seen_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('couriers')) {
            Schema::table('couriers', function (Blueprint $table) {
                if (! Schema::hasColumn('couriers', 'is_online')) {
                    $table->boolean('is_online')->default(true)->index();
                    $table->timestamp('last_seen_at')->nullable();
                    $table->unsignedSmallInteger('sla_strikes')->default(0);
                }
            });
        }

        if (Schema::hasTable('riders')) {
            Schema::table('riders', function (Blueprint $table) {
                if (! Schema::hasColumn('riders', 'is_online')) {
                    $table->boolean('is_online')->default(true)->index();
                    $table->timestamp('last_seen_at')->nullable();
                    $table->unsignedSmallInteger('sla_strikes')->default(0);
                }
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'product_id')) {
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->string('attachment_path')->nullable();
                }
            });
        }
    }

    public static function backfillMainHubsAndPackages(): void
    {
        $profiles = LogisticsProfile::all();

        foreach ($profiles as $profile) {
            $mainHub = LogisticsHub::where('parent_logistics_profile_id', $profile->id)
                ->where('is_main_hub', true)
                ->first();

            if (! $mainHub) {
                $mainHub = LogisticsHub::create([
                    'parent_logistics_profile_id' => $profile->id,
                    'branch_user_id'              => null,
                    'hub_code'                    => 'HUB-MAIN-' . str_pad((string) $profile->id, 4, '0', STR_PAD_LEFT),
                    'hub_name'                    => $profile->business_name . ' (Main Hub - ' . $profile->municipality . ')',
                    'is_main_hub'                 => true,
                    'contact_no'                  => $profile->contact_no,
                    'province'                    => $profile->province,
                    'municipality'                => $profile->municipality,
                    'barangay'                    => $profile->barangay,
                    'street'                      => $profile->street,
                    'house_details'               => $profile->house_details,
                    'status'                      => 'active',
                    'created_by'                  => $profile->user_id,
                ]);
            }

            $unassignedCouriers = Courier::where('logistics_profile_id', $profile->id)
                ->whereDoesntHave('hubAssignments', fn ($q) => $q->where('is_current', true))
                ->get();

            foreach ($unassignedCouriers as $courier) {
                LogisticsEmployeeHubAssignment::create([
                    'logistics_profile_id' => $profile->id,
                    'logistics_hub_id'     => $mainHub->id,
                    'employee_type'        => 'courier',
                    'courier_id'           => $courier->id,
                    'municipality'         => $mainHub->municipality,
                    'is_current'           => true,
                    'assigned_by'          => $profile->user_id,
                    'assigned_at'          => now(),
                    'remarks'              => 'Assigned to Main Hub',
                ]);
            }

            $unassignedRiders = Rider::where('logistics_profile_id', $profile->id)
                ->whereDoesntHave('hubAssignments', fn ($q) => $q->where('is_current', true))
                ->get();

            foreach ($unassignedRiders as $rider) {
                LogisticsEmployeeHubAssignment::create([
                    'logistics_profile_id' => $profile->id,
                    'logistics_hub_id'     => $mainHub->id,
                    'employee_type'        => 'rider',
                    'rider_id'             => $rider->id,
                    'municipality'         => $mainHub->municipality,
                    'is_current'           => true,
                    'assigned_by'          => $profile->user_id,
                    'assigned_at'          => now(),
                    'remarks'              => 'Assigned to Main Hub',
                ]);
            }
        }

        $shipmentsWithoutPackages = Shipment::with(['sellerOrder.items'])
            ->whereDoesntHave('packages')
            ->get();

        foreach ($shipmentsWithoutPackages as $shipment) {
            $sellerOrder = $shipment->sellerOrder;
            if (! $sellerOrder) {
                continue;
            }

            $mainHub = LogisticsHub::where('parent_logistics_profile_id', $shipment->logistics_profile_id)
                ->where('is_main_hub', true)
                ->first();

            $items = $sellerOrder->items ?? collect();
            $manifestItems = $items->map(function ($item) {
                $variantLabel = implode(' / ', array_filter([$item->variant_name, $item->sub_variant_name]));
                return [
                    'order_item_id' => $item->id,
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product_name,
                    'variant'       => $variantLabel ?: null,
                    'sku'           => 'SKU-' . str_pad((string) $item->product_id, 5, '0', STR_PAD_LEFT) . ($item->variant_id ? '-' . strtoupper(substr((string) $item->variant_id, -4)) : ''),
                    'quantity'      => (int) $item->quantity,
                    'unit_weight'   => (float) $item->unit_weight,
                    'line_weight'   => (float) $item->line_weight,
                    'unit_price'    => (float) $item->discounted_unit_price,
                    'line_total'    => (float) $item->line_total,
                ];
            })->values()->all();

            $isOutForDeliveryOrLater = in_array($shipment->status, ['out_for_delivery', 'delivered', 'delivery_failed'], true);
            $isAtHubOrLater = $isOutForDeliveryOrLater || in_array($shipment->status, ['received_at_hub', 'rider_assigned', 'scheduled_redelivery'], true);
            $isPickedUpOrLater = $isAtHubOrLater || in_array($shipment->status, ['picked_up_from_seller', 'in_transit_to_hub'], true);

            ShipmentPackage::create([
                'shipment_id'                  => $shipment->id,
                'seller_order_id'              => $sellerOrder->id,
                'order_id'                     => $shipment->order_id,
                'seller_profile_id'            => $shipment->seller_profile_id,
                'logistics_profile_id'         => $shipment->logistics_profile_id,
                'logistics_hub_id'             => $mainHub?->id,
                'package_reference'            => 'PKG-' . str_replace('TRK-', '', $shipment->tracking_number) . '-01',
                'qr_token'                     => Str::random(64),
                'package_number'               => 1,
                'total_packages'               => 1,
                'handover_method'              => 'courier_pickup',
                'package_weight'               => $shipment->total_weight,
                'cod_amount'                   => $shipment->cod_amount,
                'total_items_count'            => max(1, (int) $items->sum('quantity')),
                'manifest_snapshot'            => $manifestItems,
                'seller_prepared_at'           => $shipment->pickup_requested_at ?? $shipment->created_at,
                'courier_verified_at'          => $isPickedUpOrLater ? ($shipment->picked_up_at ?? now()) : null,
                'courier_verification_method'  => $isPickedUpOrLater ? 'manual' : null,
                'hub_verified_at'              => $isAtHubOrLater ? ($shipment->arrived_at_hub_at ?? now()) : null,
                'hub_verified_by'              => $shipment->hub_verified_by,
                'hub_verification_method'      => $isAtHubOrLater ? 'manual' : null,
                'rider_accepted_at'            => $isOutForDeliveryOrLater ? ($shipment->out_for_delivery_at ?? now()) : null,
                'rider_verified_at'            => $isOutForDeliveryOrLater ? ($shipment->out_for_delivery_at ?? now()) : null,
                'rider_verification_method'    => $isOutForDeliveryOrLater ? 'manual' : null,
                'rider_collected_at'           => $isOutForDeliveryOrLater ? ($shipment->out_for_delivery_at ?? now()) : null,
                'delivery_verified_at'         => $shipment->status === 'delivered' ? ($shipment->delivered_at ?? now()) : null,
                'delivery_verification_method' => $shipment->status === 'delivered' ? 'manual' : null,
            ]);
        }
    }

    protected static function seedDefaultVouchers(): void
    {
        if (! Schema::hasTable('stork_vouchers')) {
            return;
        }

        if (StorkVoucher::count() > 0) {
            return;
        }

        $firstSeller = SellerProfile::orderBy('id', 'asc')->first();
        $secondSeller = SellerProfile::orderBy('id', 'asc')->skip(1)->first() ?? $firstSeller;

        StorkVoucher::create([
            'seller_profile_id' => null,
            'code'              => 'PLAT100',
            'title'             => 'Storkia Platform ₱100 Off',
            'description'       => '₱100.00 off platform-wide on orders ₱800.00 and above.',
            'scope'             => 'platform',
            'discount_type'     => 'fixed',
            'discount_value'    => 100.00,
            'max_discount_cap'  => null,
            'min_spend'         => 800.00,
            'total_usage_limit' => 500,
            'used_count'        => 0,
            'per_buyer_limit'   => 2,
            'visibility'        => 'claimable',
            'starts_at'         => now()->subDays(2),
            'ends_at'           => now()->addMonths(3),
            'status'            => 'active',
        ]);

        StorkVoucher::create([
            'seller_profile_id' => null,
            'code'              => 'FREESHIP',
            'title'             => 'Free Shipping Voucher (Up to ₱50)',
            'description'       => 'Up to ₱50.00 off shipping fees with minimum spend of ₱300.00.',
            'scope'             => 'shipping',
            'discount_type'     => 'fixed',
            'discount_value'    => 50.00,
            'max_discount_cap'  => 50.00,
            'min_spend'         => 300.00,
            'total_usage_limit' => 500,
            'used_count'        => 0,
            'per_buyer_limit'   => 3,
            'visibility'        => 'claimable',
            'starts_at'         => now()->subDays(2),
            'ends_at'           => now()->addMonths(3),
            'status'            => 'active',
        ]);

        if ($firstSeller) {
            StorkVoucher::create([
                'seller_profile_id' => $firstSeller->id,
                'created_by'        => $firstSeller->user_id,
                'code'              => 'MCH50',
                'title'             => $firstSeller->business_name . ' ₱50 Off',
                'description'       => 'Save ₱50.00 on ' . $firstSeller->business_name . ' items (Min. spend ₱300.00).',
                'scope'             => 'store',
                'discount_type'     => 'fixed',
                'discount_value'    => 50.00,
                'max_discount_cap'  => null,
                'min_spend'         => 300.00,
                'total_usage_limit' => 100,
                'used_count'        => 0,
                'per_buyer_limit'   => 1,
                'visibility'        => 'claimable',
                'starts_at'         => now()->subDays(2),
                'ends_at'           => now()->addMonths(2),
                'status'            => 'active',
            ]);

            StorkVoucher::create([
                'seller_profile_id' => $firstSeller->id,
                'created_by'        => $firstSeller->user_id,
                'code'              => 'MCH10',
                'title'             => $firstSeller->business_name . ' 10% Off (Cap ₱100)',
                'description'       => '10% off capped at ₱100.00 (Min. spend ₱500.00).',
                'scope'             => 'store',
                'discount_type'     => 'percentage',
                'discount_value'    => 10.00,
                'max_discount_cap'  => 100.00,
                'min_spend'         => 500.00,
                'total_usage_limit' => 100,
                'used_count'        => 0,
                'per_buyer_limit'   => 1,
                'visibility'        => 'claimable',
                'starts_at'         => now()->subDays(2),
                'ends_at'           => now()->addMonths(2),
                'status'            => 'active',
            ]);
        }

        if ($secondSeller) {
            StorkVoucher::create([
                'seller_profile_id' => $secondSeller->id,
                'created_by'        => $secondSeller->user_id,
                'code'              => 'RVSECRET',
                'title'             => 'Secret VIP Store Code ₱100 Off',
                'description'       => 'Code-only store voucher: ₱100.00 off with ₱1,000.00 minimum spend.',
                'scope'             => 'store',
                'discount_type'     => 'fixed',
                'discount_value'    => 100.00,
                'max_discount_cap'  => null,
                'min_spend'         => 1000.00,
                'total_usage_limit' => 50,
                'used_count'        => 0,
                'per_buyer_limit'   => 1,
                'visibility'        => 'code_only',
                'starts_at'         => now()->subDays(2),
                'ends_at'           => now()->addMonths(2),
                'status'            => 'active',
            ]);
        }
    }
}