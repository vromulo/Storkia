<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Delete Account" never removes the users row. It stamps archived_at
     * (and the reason the person gave) so the account moves to the admin's
     * Archived Accounts tab and can be restored.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('archive_reason', 100)->nullable();
            $table->string('archive_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archive_reason', 'archive_note']);
        });
    }
};