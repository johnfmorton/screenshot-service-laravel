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
        'key',
        'is_active',
        'rate_limit',
        'user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rate_limit' => 'integer',
    ];

    protected $hidden = [];

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
        return self::create([
            'name' => $name,
            'key' => 'sk_' . Str::random(32),
            'is_active' => true,
            'rate_limit' => $rateLimit,
        ]);
    }
}
