<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->string('payment_reference', 45)->unique();
            $table->string('method', 20)->default('COD');
            $table->string('status', 30)->default('unpaid')->index();
            $table->decimal('product_subtotal', 12, 2);
            $table->decimal('shipping_fee', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cod_collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_reference', 45)->unique();
            $table->foreignId('shipment_id')->unique()->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->constrained('delivery_assignments')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('collected_amount', 12, 2);
            $table->decimal('product_subtotal', 12, 2);
            $table->decimal('shipping_fee', 12, 2);
            $table->string('status', 35)->default('collected')->index();
            $table->timestamp('collected_at');
            $table->timestamp('remitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['logistics_profile_id', 'status']);
            $table->index(['rider_id', 'status']);
        });

        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('remittance_reference', 45)->unique();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->decimal('total_collected_amount', 12, 2);
            $table->decimal('total_rider_commission', 12, 2);
            $table->decimal('net_remitted_amount', 12, 2);
            $table->string('status', 30)->default('submitted')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['logistics_profile_id', 'status']);
            $table->index(['rider_id', 'status']);
        });

        Schema::create('cod_remittance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_remittance_id')->constrained('cod_remittances')->cascadeOnDelete();
            $table->foreignId('cod_collection_id')->unique()->constrained('cod_collections')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->decimal('collected_amount', 12, 2);
            $table->decimal('rider_commission_amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('commission_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('cod_collection_id')->constrained('cod_collections')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->decimal('product_subtotal', 12, 2);
            $table->decimal('shipping_fee', 12, 2);
            $table->decimal('total_cod_amount', 12, 2);
            $table->decimal('admin_commission_rate', 5, 2)->default(10.00);
            $table->decimal('admin_commission_amount', 12, 2);
            $table->decimal('seller_net_earning', 12, 2);
            $table->decimal('logistics_commission_rate', 5, 2)->default(10.00);
            $table->decimal('logistics_commission_amount', 12, 2);
            $table->decimal('rider_commission_rate', 5, 2)->default(10.00);
            $table->decimal('rider_commission_amount', 12, 2);
            $table->decimal('logistics_operational_revenue', 12, 2);
            $table->decimal('logistics_total_earning', 12, 2);
            $table->string('status', 30)->default('recorded')->index();
            $table->timestamp('calculated_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
            $table->index(['logistics_profile_id', 'status']);
        });

        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_reference', 45)->unique();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('gross_product_subtotal', 12, 2);
            $table->decimal('admin_commission_total', 12, 2);
            $table->decimal('net_settlement_amount', 12, 2);
            $table->string('status', 30)->default('released')->index();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
            $table->index(['logistics_profile_id', 'status']);
        });

        Schema::create('seller_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_settlement_id')->constrained('seller_settlements')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('commission_record_id')->unique()->constrained('commission_records')->cascadeOnDelete();
            $table->decimal('product_subtotal', 12, 2);
            $table->decimal('admin_commission_amount', 12, 2);
            $table->decimal('seller_net_earning', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_settlement_items');
        Schema::dropIfExists('seller_settlements');
        Schema::dropIfExists('commission_records');
        Schema::dropIfExists('cod_remittance_items');
        Schema::dropIfExists('cod_remittances');
        Schema::dropIfExists('cod_collections');
        Schema::dropIfExists('payments');
    }
};