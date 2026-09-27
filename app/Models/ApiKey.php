<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'is_active',
        'rate_limit',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rate_limit' => 'integer',
    ];

    protected $hidden = [
        'key_hash',
    ];

    /**
     * The full key, set only on the instance generate() returns. It is never
     * stored, so this is the one chance to show it.
     */
    public ?string $plainTextKey = null;

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generate(string $name, ?int $rateLimit = null): self
    {
        $plainTextKey = 'sk_' . Str::random(32);

        $apiKey = self::create([
            'name' => $name,
            'key_hash' => self::hashKey($plainTextKey),
            'key_prefix' => substr($plainTextKey, 0, 7),
            'is_active' => true,
            'rate_limit' => $rateLimit,
        ]);

        $apiKey->plainTextKey = $plainTextKey;

        return $apiKey;
    }

    /**
     * Plain SHA-256 rather than a password hash: keys are 190 bits of random,
     * so there's nothing to brute-force, and a deterministic hash is what lets
     * the lookup use an index.
     */
    public static function hashKey(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    public static function findByPlainTextKey(string $plainTextKey): ?self
    {
        return self::where('key_hash', self::hashKey($plainTextKey))->first();
    }

    public function maskedKey(): string
    {
        return $this->key_prefix . '…';
    }
}
