<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_address_id')->nullable()->constrained('user_addresses')->nullOnDelete();
            $table->json('address_snapshot');
            $table->string('payment_method', 20)->default('COD');
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'created_at']);
        });

        Schema::create('seller_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('seller_order_number', 40)->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->nullable()->constrained('logistics_profiles')->nullOnDelete();
            $table->string('status', 40)->default('pending_seller_acceptance')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('total_weight', 10, 3)->default(0);
            $table->string('shipping_tier', 30)->default('inter_province');
            $table->json('pickup_address_snapshot');
            $table->json('delivery_address_snapshot');
            $table->timestamp('acceptance_deadline_at')->nullable()->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('ready_for_pickup_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('confirmation_deadline_at')->nullable()->index();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->boolean('has_unresolved_dispute')->default(false)->index();
            $table->text('dispute_reason')->nullable();
            $table->timestamp('dispute_reported_at')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('stock_deducted')->default(true);
            $table->boolean('stock_restored')->default(false);
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['buyer_id', 'status']);
            $table->index(['logistics_profile_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 150);
            $table->string('product_image')->nullable();
            $table->string('variant_id', 64)->nullable();
            $table->string('variant_name', 100)->nullable();
            $table->string('sub_variant_id', 64)->nullable();
            $table->string('sub_variant_name', 100)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discounted_unit_price', 12, 2);
            $table->decimal('unit_weight', 10, 3)->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->decimal('line_weight', 10, 3)->default(0);
            $table->timestamps();

            $table->index('seller_order_id');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('actor_type', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['seller_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('orders');
    }
};