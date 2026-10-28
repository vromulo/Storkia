<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_profile_id')->constrained('logistics_profiles')->cascadeOnDelete();

            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->date('birthday');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('contact_no')->unique();

            $table->string('license_no')->unique();
            $table->string('license_type')->default('Professional');
            $table->json('dl_codes');
            $table->date('license_expiry_date');

            $table->string('vehicle_type');
            $table->string('plate_number')->unique();
            $table->unsignedInteger('load_capacity');
            $table->string('home_hub');

            $table->string('license_path');
            $table->string('or_cr_path');
            $table->string('nbi_clearance_path');

            $table->string('password');
            $table->enum('status', ['active', 'on_delivery', 'off_duty', 'suspended'])->default('active');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};