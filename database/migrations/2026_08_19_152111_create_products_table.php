<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('additional_descriptions')->nullable();
            
            // Made nullable because they are now determined by the variants
            $table->decimal('price', 10, 2)->nullable();
            $table->string('weight')->nullable();
            
            $table->decimal('discount', 10, 2)->default(0)->nullable();
            $table->json('pictures'); 
            $table->json('variants')->nullable(); 
            $table->integer('stock_quantity')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};