<?php

namespace App\Rules;

use App\Services\PublicUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            app(PublicUrlGuard::class)->check($value);
        } catch (InvalidArgumentException $e) {
            $fail("The :attribute is not allowed. {$e->getMessage()}");
        }
    }
}
