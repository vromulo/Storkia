<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->string('category');
            $table->timestamps();

            $table->unique(['seller_profile_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_categories');
    }
};