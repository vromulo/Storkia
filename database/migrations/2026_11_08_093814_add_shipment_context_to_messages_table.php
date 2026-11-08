<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prepares the shared `messages` table for the Seller <-> Logistics chat.
     *
     * - `shipment_id` lets a chat bubble carry a shipment card (the same way
     *   `product_id` carries a product card in the Buyer <-> Seller chat).
     * - `product_id` / `attachment_path` are normally added at runtime by
     *   StorkSchemaBootstrapService; they are guarded here so a fresh database
     *   built purely from migrations ends up with the same shape.
     * - Two composite indexes keep conversation lookups and unread counters
     *   fast now that three portals share this table.
     */
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->index();
            }

            if (! Schema::hasColumn('messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }

            if (! Schema::hasColumn('messages', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->index();
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasIndex('messages', 'messages_pair_idx')) {
                $table->index(['user_id', 'receiver_id', 'id'], 'messages_pair_idx');
            }

            if (! Schema::hasIndex('messages', 'messages_receiver_unread_idx')) {
                $table->index(['receiver_id', 'is_read'], 'messages_receiver_unread_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasIndex('messages', 'messages_pair_idx')) {
                $table->dropIndex('messages_pair_idx');
            }

            if (Schema::hasIndex('messages', 'messages_receiver_unread_idx')) {
                $table->dropIndex('messages_receiver_unread_idx');
            }
        });

        if (Schema::hasColumn('messages', 'shipment_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn('shipment_id');
            });
        }
    }
};