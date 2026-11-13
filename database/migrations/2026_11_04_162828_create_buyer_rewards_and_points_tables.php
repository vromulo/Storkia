<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('buyer_reward_wallets')) {
            Schema::create('buyer_reward_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('coins_balance')->default(0);
                $table->unsignedBigInteger('lifetime_coins_earned')->default(0);
                $table->unsignedBigInteger('lifetime_coins_spent')->default(0);
                $table->unsignedInteger('spin_tickets')->default(0);
                $table->timestamp('last_daily_login_spin_at')->nullable()->index();
                $table->timestamp('last_spin_played_at')->nullable()->index();
                $table->timestamp('last_chest_opened_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('buyer_reward_transactions')) {
            Schema::create('buyer_reward_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reward_wallet_id')->constrained('buyer_reward_wallets')->cascadeOnDelete();
                $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
                $table->unsignedBigInteger('voucher_id')->nullable()->index();
                $table->string('reference_code', 50)->unique();
                $table->string('source_type', 40)->index();
                $table->string('reward_type', 30)->default('coins');
                $table->integer('coins_delta')->default(0);
                $table->integer('spins_delta')->default(0);
                $table->unsignedBigInteger('coins_balance_after')->default(0);
                $table->unsignedInteger('spins_balance_after')->default(0);
                $table->unsignedTinyInteger('stage_level')->nullable();
                $table->string('description', 255);
                $table->json('meta_snapshot')->nullable();
                $table->timestamps();

                $table->index(['buyer_id', 'created_at'], 'brt_buyer_created_idx');
                $table->index(['seller_order_id', 'source_type'], 'brt_order_source_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_reward_transactions');
        Schema::dropIfExists('buyer_reward_wallets');
    }
};