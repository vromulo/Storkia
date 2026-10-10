<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('country')->default('Philippines');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');
            $table->string('province_code');
            $table->string('province');
            $table->string('municipality_code');
            $table->string('municipality');
            $table->string('barangay_code')->nullable();
            $table->string('barangay')->nullable();
            $table->string('postcode');
            $table->text('street_address');
            $table->text('building_details')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
