<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A buyer's report against a seller (store) account.
         *
         * status       : see SellerReport::STATUS_* constants
         * admin_action : what the admin did when resolving the report
         *                (warn | suspend_temp | suspend_perm). NULL while the report is
         *                still open, and NULL when it was dismissed.
         * open_lock    : "<reporter_id>:<seller_profile_id>" while the report is open and NULL
         *                once it is closed. The unique index lets the database itself guarantee
         *                "one open report per buyer per seller", even if two requests race.
         */
        Schema::create('seller_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 32)->unique();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();

            // Buyer's report
            $table->string('reason_category', 40);
            $table->text('description');
            $table->json('evidence_photos')->nullable();

            // Lifecycle
            $table->string('status', 20)->default('pending')->index();
            $table->string('open_lock', 60)->nullable()->unique();

            // Admin handling
            $table->string('admin_action', 30)->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['seller_profile_id', 'status']);
            $table->index(['reporter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_reports');
    }
};