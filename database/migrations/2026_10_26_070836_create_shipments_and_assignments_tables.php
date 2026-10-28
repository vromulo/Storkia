<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 40)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('current_courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $table->foreignId('current_rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->string('status', 40)->default('pickup_requested')->index();
            $table->decimal('total_weight', 10, 3)->default(0);
            $table->decimal('cod_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->unsignedTinyInteger('delivery_attempts_count')->default(0);
            $table->json('pickup_address_snapshot');
            $table->json('hub_address_snapshot');
            $table->json('delivery_address_snapshot');
            $table->timestamp('pickup_requested_at')->nullable();
            $table->timestamp('courier_assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('arrived_at_hub_at')->nullable();
            $table->foreignId('hub_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rider_assigned_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['logistics_profile_id', 'status']);
            $table->index(['current_courier_id', 'status']);
            $table->index(['current_rider_id', 'status']);
        });

        Schema::create('shipment_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('actor_type', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
        });

        Schema::create('pickup_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('couriers')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 35)->default('assigned')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->foreignId('hub_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hub_verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
            $table->index(['logistics_profile_id', 'status']);
        });

        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status', 35)->default('assigned')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['logistics_profile_id', 'status']);
        });

        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->constrained('delivery_assignments')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('outcome', 25)->index();
            $table->string('failure_reason', 120)->nullable();
            $table->text('failure_notes')->nullable();
            $table->string('logistics_resolution', 35)->nullable()->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['shipment_id', 'attempt_number']);
        });

        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('delivery_assignment_id')->constrained('delivery_assignments')->cascadeOnDelete();
            $table->foreignId('delivery_attempt_id')->nullable()->constrained('delivery_attempts')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('recipient_name', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_proofs');
        Schema::dropIfExists('delivery_attempts');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('pickup_assignments');
        Schema::dropIfExists('shipment_tracking');
        Schema::dropIfExists('shipments');
    }
};