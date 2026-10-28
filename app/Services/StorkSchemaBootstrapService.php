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
    protected const SCHEMA_VERSION_CACHE_KEY = 'storkia_additive_schema_v2_ready';

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
            self::createBuyerCancellationLogsTable();

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
            $table->timestamps();
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
                'shipment_id'                 => $shipment->id,
                'seller_order_id'             => $sellerOrder->id,
                'order_id'                    => $shipment->order_id,
                'seller_profile_id'           => $shipment->seller_profile_id,
                'logistics_profile_id'        => $shipment->logistics_profile_id,
                'logistics_hub_id'            => $mainHub?->id,
                'package_reference'           => 'PKG-' . str_replace('TRK-', '', $shipment->tracking_number) . '-01',
                'qr_token'                    => Str::random(64),
                'package_number'              => 1,
                'total_packages'              => 1,
                'handover_method'             => 'courier_pickup',
                'package_weight'              => $shipment->total_weight,
                'cod_amount'                  => $shipment->cod_amount,
                'total_items_count'           => max(1, (int) $items->sum('quantity')),
                'manifest_snapshot'           => $manifestItems,
                'seller_prepared_at'          => $shipment->pickup_requested_at ?? $shipment->created_at,
                'courier_verified_at'         => $isPickedUpOrLater ? ($shipment->picked_up_at ?? now()) : null,
                'courier_verification_method' => $isPickedUpOrLater ? 'manual' : null,
                'hub_verified_at'             => $isAtHubOrLater ? ($shipment->arrived_at_hub_at ?? now()) : null,
                'hub_verified_by'             => $shipment->hub_verified_by,
                'hub_verification_method'     => $isAtHubOrLater ? 'manual' : null,
                'rider_accepted_at'           => $isOutForDeliveryOrLater ? ($shipment->out_for_delivery_at ?? now()) : null,
                'rider_verified_at'           => $isOutForDeliveryOrLater ? ($shipment->out_for_delivery_at ?? now()) : null,
                'rider_verification_method'   => $isOutForDeliveryOrLater ? 'manual' : null,
                'rider_collected_at'          => $isOutForDeliveryOrLater ? ($shipment->out_for_delivery_at ?? now()) : null,
                'delivery_verified_at'        => $shipment->status === 'delivered' ? ($shipment->delivered_at ?? now()) : null,
                'delivery_verification_method'=> $shipment->status === 'delivered' ? 'manual' : null,
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