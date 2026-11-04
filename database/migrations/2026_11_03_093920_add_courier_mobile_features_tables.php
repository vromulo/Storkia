<?php

use App\Services\CourierMobileSchemaService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CourierMobileSchemaService::applySchemaChanges();
    }

    public function down(): void
    {
    }
};