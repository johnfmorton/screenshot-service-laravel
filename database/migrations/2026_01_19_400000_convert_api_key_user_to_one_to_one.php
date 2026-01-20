<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add user_id foreign key column to api_keys table
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Migrate data: for each user, keep only the most recent API key
        $userKeys = DB::table('api_key_user')
            ->join('api_keys', 'api_key_user.api_key_id', '=', 'api_keys.id')
            ->select('api_key_user.user_id', 'api_key_user.api_key_id', 'api_keys.created_at')
            ->orderBy('api_keys.created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        foreach ($userKeys as $userId => $keys) {
            // Keep only the most recent key (first in desc order)
            $mostRecentKeyId = $keys->first()->api_key_id;
            DB::table('api_keys')
                ->where('id', $mostRecentKeyId)
                ->update(['user_id' => $userId]);
        }

        // Drop the pivot table
        Schema::dropIfExists('api_key_user');

        // Note: No unique constraint on user_id since super admins can have multiple keys
        // Sub user constraint is enforced at application level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the pivot table
        Schema::create('api_key_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('api_key_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'api_key_id']);
        });

        // Migrate data back to pivot table
        $apiKeys = DB::table('api_keys')
            ->whereNotNull('user_id')
            ->select('id', 'user_id')
            ->get();

        foreach ($apiKeys as $apiKey) {
            DB::table('api_key_user')->insert([
                'user_id' => $apiKey->user_id,
                'api_key_id' => $apiKey->id,
            ]);
        }

        // Remove user_id column from api_keys
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
