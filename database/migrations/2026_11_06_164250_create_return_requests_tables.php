<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One return/refund request per seller order.
         *
         * requested_resolution : what the buyer asked for  (return_refund | refund_only)
         * approved_resolution  : what was finally approved (return_refund | refund_only)
         * status               : see ReturnRequest::STATUS_* constants
         */
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 32)->unique();
            $table->foreignId('seller_order_id')->unique()->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('logistics_profile_id')->nullable()->constrained('logistics_profiles')->nullOnDelete();

            $table->string('requested_resolution', 20)->default('return_refund');
            $table->string('approved_resolution', 20)->nullable();
            $table->string('status', 40)->default('pending_seller_response')->index();

            // Buyer's claim
            $table->string('reason_category', 120);
            $table->text('reason');
            $table->json('evidence_photos')->nullable();

            // Refund: GCash only, paid by Admin from the refund queue.
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('refund_account_name', 120)->nullable();
            $table->string('refund_account_number', 20)->nullable();
            $table->string('refund_status', 30)->default('not_due')->index(); // not_due | pending_admin_refund | refunded
            $table->string('refund_reference', 80)->nullable();
            $table->text('refund_remarks')->nullable();
            $table->timestamp('refund_due_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('refunded_by_admin_id')->nullable();

            // Seller's response
            $table->string('seller_decision', 30)->nullable(); // approved_return | approved_refund_only | rejected
            $table->text('seller_decision_reason')->nullable();
            $table->timestamp('seller_response_deadline_at')->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->boolean('auto_approved')->default(false);

            // Escalation to Storkia Admin
            $table->boolean('is_escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_note')->nullable();
            $table->string('admin_decision', 30)->nullable(); // approved_return | approved_refund_only | rejected
            $table->text('admin_remarks')->nullable();
            $table->unsignedBigInteger('admin_resolved_by')->nullable();
            $table->timestamp('admin_resolved_at')->nullable();

            // Physical return (return_refund only)
            $table->timestamp('handover_deadline_at')->nullable();
            $table->string('seller_receipt_outcome', 30)->nullable(); // received_ok | received_not_as_expected
            $table->text('seller_receipt_notes')->nullable();
            $table->json('seller_receipt_photos')->nullable();
            $table->timestamp('seller_receipt_deadline_at')->nullable();
            $table->timestamp('seller_received_at')->nullable();

            // Wallet ledger
            $table->timestamp('ledger_settled_at')->nullable();
            $table->decimal('seller_deduction_amount', 12, 2)->default(0);
            $table->json('settlement_snapshot')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason', 60)->nullable();
            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
            $table->index(['buyer_id', 'status']);
            $table->index(['logistics_profile_id', 'status']);
        });

        /*
         * The physical trip back:
         *   Buyer --(Rider)--> Sorting Hub --(Courier)--> Seller
         * It mirrors the forward shipment in reverse.
         */
        Schema::create('return_shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 40)->unique();
            $table->foreignId('return_request_id')->unique()->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->constrained('seller_orders')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();
            $table->foreignId('current_rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('current_courier_id')->nullable()->constrained('couriers')->nullOnDelete();

            $table->string('status', 40)->default('awaiting_rider_assignment')->index();
            $table->decimal('total_weight', 10, 3)->default(0);
            $table->unsignedTinyInteger('pickup_attempts_count')->default(0);

            $table->json('pickup_address_snapshot');  // buyer: where the parcel is collected
            $table->json('hub_address_snapshot');     // sorting hub in the middle
            $table->json('return_address_snapshot');  // seller: final destination

            // Pay for the two return legs (funded by the seller's refund deduction).
            $table->decimal('rider_pay', 10, 2)->default(0);
            $table->decimal('courier_pay', 10, 2)->default(0);
            $table->timestamp('rider_paid_at')->nullable();
            $table->timestamp('courier_paid_at')->nullable();

            $table->timestamp('rider_assigned_at')->nullable();
            $table->timestamp('picked_up_from_buyer_at')->nullable();
            $table->timestamp('arrived_at_hub_at')->nullable();
            $table->foreignId('hub_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('courier_assigned_at')->nullable();
            $table->timestamp('picked_up_from_hub_at')->nullable();
            $table->timestamp('delivered_to_seller_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('pickup_failure_reason', 120)->nullable();
            $table->string('buyer_pickup_photo')->nullable();
            $table->string('seller_delivery_photo')->nullable();
            $table->timestamps();

            $table->index(['logistics_profile_id', 'status']);
            $table->index(['current_rider_id', 'status']);
            $table->index(['current_courier_id', 'status']);
        });

        // Timeline shown to buyer, seller and every operational role.
        Schema::create('return_shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_shipment_id')->constrained('return_shipments')->cascadeOnDelete();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('location', 150)->nullable();
            $table->string('actor_type', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['return_shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_shipment_events');
        Schema::dropIfExists('return_shipments');
        Schema::dropIfExists('return_requests');
    }
};