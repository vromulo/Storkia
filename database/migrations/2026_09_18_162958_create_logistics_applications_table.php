<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logistics_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);

            // Contact & Hub Location Snapshot
            $table->string('contact_no');
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('street')->nullable();
            $table->string('house_details')->nullable();

            // Center Name
            $table->string('business_name');

            // Verification Documents
            $table->string('id_path');
            $table->string('permit_path');

            // Review Audit Trail
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_applications');
    }
};
