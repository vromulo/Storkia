<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Help Center (Admin <-> Buyer / Seller / Logistics).
     *
     * Admins sign in through the separate `admins` table, so their replies
     * cannot live in `messages` (whose sender and receiver are `users` rows).
     *
     * One conversation per user (a single running thread with the support
     * team). Admins never start conversations: a row is created the first
     * time a user writes in, and any admin can answer it (shared inbox).
     */
    public function up(): void
    {
        if (! Schema::hasTable('support_conversations')) {
            Schema::create('support_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('status', 20)->default('open')->index(); // open | resolved
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_messages')) {
            Schema::create('support_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
                $table->string('sender_type', 10); // user | admin
                $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('sender_admin_id')->nullable();
                $table->text('message');
                $table->string('attachment_path')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'id'], 'support_msgs_thread_idx');
                $table->index(['conversation_id', 'sender_type', 'is_read'], 'support_msgs_unread_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};