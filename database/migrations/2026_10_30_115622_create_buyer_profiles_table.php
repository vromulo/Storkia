<?php

use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('username', 30)->unique();
            $table->string('avatar_path')->nullable();
            $table->timestamp('username_changed_at')->nullable();
            $table->timestamps();
        });

        // Automatically backfill buyer profiles for all existing Buyer accounts
        $buyers = User::where('role', 'Buyer')->get();
        foreach ($buyers as $buyer) {
            BuyerProfile::firstOrCreate(
                ['user_id' => $buyer->id],
                [
                    'username'            => BuyerProfile::generateUniqueUsername($buyer->email),
                    'avatar_path'         => null,
                    'username_changed_at' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_profiles');
    }
};