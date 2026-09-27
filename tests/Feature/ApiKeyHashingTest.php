<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * API keys were stored and displayed in plaintext, so a database dump, a
 * backup, or a glance at the admin screen yielded working credentials.
 */
class ApiKeyHashingTest extends TestCase
{
    use DatabaseMigrations;

    public function test_only_a_hash_of_the_key_is_stored(): void
    {
        $apiKey = ApiKey::generate('Test Key');

        $this->assertFalse(Schema::hasColumn('api_keys', 'key'));

        $row = (array) DB::table('api_keys')->where('id', $apiKey->id)->first();
        $this->assertNotContains($apiKey->plainTextKey, $row);
        $this->assertSame(hash('sha256', $apiKey->plainTextKey), $row['key_hash']);
    }

    public function test_the_plaintext_key_still_authenticates(): void
    {
        $apiKey = ApiKey::generate('Test Key');

        $this->getJson('/api/screenshots/' . fake()->uuid(), ['X-API-Key' => $apiKey->plainTextKey])
            ->assertNotFound();

        $this->getJson('/api/screenshots/' . fake()->uuid(), ['X-API-Key' => $apiKey->key_hash])
            ->assertUnauthorized();
    }

    public function test_the_key_is_shown_once_on_creation_and_masked_afterwards(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->post(route('admin.api-keys.store'), ['name' => 'Client'])
            ->assertSessionHas('new_key', fn (string $key) => str_starts_with($key, 'sk_'));

        $plainTextKey = session('new_key');
        $this->assertNotNull(ApiKey::findByPlainTextKey($plainTextKey));

        $this->flushSession();

        $this->get(route('admin.api-keys.index'))
            ->assertOk()
            ->assertDontSee($plainTextKey)
            ->assertSee(substr($plainTextKey, 0, 7) . '…');
    }

    public function test_the_hash_is_not_serialized(): void
    {
        $this->assertArrayNotHasKey('key_hash', ApiKey::generate('Test Key')->toArray());
    }

    /**
     * Existing clients hold keys issued before hashing. The migration has to
     * carry them over, or deploying it locks every client out.
     */
    public function test_keys_issued_before_the_migration_keep_working(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1]);

        DB::table('api_keys')->insert([
            'id' => fake()->uuid(),
            'name' => 'Legacy',
            'key' => 'sk_legacyKeyIssuedBeforeHashing000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('migrate');

        $apiKey = ApiKey::findByPlainTextKey('sk_legacyKeyIssuedBeforeHashing000');
        $this->assertNotNull($apiKey);
        $this->assertSame('sk_lega…', $apiKey->maskedKey());
        $this->assertFalse(Schema::hasColumn('api_keys', 'key'));
    }
}
