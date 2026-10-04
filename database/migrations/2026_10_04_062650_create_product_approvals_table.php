<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            
            $table->enum('status', ['Pending', 'Approved', 'Disapproved'])->default('Pending');
            $table->text('remarks')->nullable(); // Admin feedback/warnings
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_approvals');
    }
};