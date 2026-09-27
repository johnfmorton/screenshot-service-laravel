<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the plaintext `key` column with a SHA-256 hash plus a short prefix
 * for display. Clients keep sending the same key; only its storage changes.
 *
 * One-way: the plaintext is gone after this runs, so down() can restore the
 * column but not the values. Back up the database before migrating if anyone
 * still needs to read an existing key out of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->char('key_hash', 64)->nullable()->after('name');
            $table->string('key_prefix', 16)->nullable()->after('key_hash');
        });

        DB::table('api_keys')->orderBy('id')->chunkById(500, function ($keys) {
            foreach ($keys as $key) {
                DB::table('api_keys')->where('id', $key->id)->update([
                    'key_hash' => hash('sha256', $key->key),
                    'key_prefix' => substr($key->key, 0, 7),
                ]);
            }
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->char('key_hash', 64)->nullable(false)->change();
            $table->string('key_prefix', 16)->nullable(false)->change();
            $table->unique('key_hash');

            $table->dropUnique(['key']);
            $table->dropIndex(['key']);
            $table->dropColumn('key');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Nullable: the original values can't be recovered from a hash, so
            // every key issued before the rollback has to be reissued.
            $table->string('key')->nullable()->unique()->after('name');
            $table->index('key');

            $table->dropUnique(['key_hash']);
            $table->dropColumn(['key_hash', 'key_prefix']);
        });
    }
};
