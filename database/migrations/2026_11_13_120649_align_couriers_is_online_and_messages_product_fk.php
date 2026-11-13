<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('couriers') && Schema::hasColumn('couriers', 'is_online')) {
            Schema::table('couriers', function (Blueprint $table) {
                $table->boolean('is_online')->default(true)->change();
            });
        }

        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'product_id')) {
            $hasForeignKey = collect(Schema::getForeignKeys('messages'))
                ->contains(fn ($foreignKey) => $foreignKey['columns'] === ['product_id']);

            if (! $hasForeignKey) {
                if (Schema::hasIndex('messages', 'messages_product_id_index')) {
                    Schema::table('messages', function (Blueprint $table) {
                        $table->dropIndex('messages_product_id_index');
                    });
                }

                Schema::table('messages', function (Blueprint $table) {
                    $table->foreign('product_id')
                        ->references('id')
                        ->on('products')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('couriers') && Schema::hasColumn('couriers', 'is_online')) {
            Schema::table('couriers', function (Blueprint $table) {
                $table->boolean('is_online')->default(false)->change();
            });
        }
    }
};