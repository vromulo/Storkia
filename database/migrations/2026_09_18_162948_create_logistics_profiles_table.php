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
        Schema::create('logistics_profiles', function (Blueprint $table) {
            $table->id();
            // Exactly one approved record per logistics account
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Contact & Hub Location
            $table->string('contact_no');
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('street')->nullable();
            $table->string('house_details')->nullable();

            // Hub / Center Info
            $table->string('business_name');
            $table->string('line_of_business');

            // Verification Documents
            $table->string('id_path');
            $table->string('permit_path');

            // NOTE: No status column by design. Row existence = Approved.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_profiles');
    }
};
