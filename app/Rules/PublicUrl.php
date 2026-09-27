<?php

namespace App\Rules;

use App\Exceptions\UrlNotAllowed;
use App\Services\PublicUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            app(PublicUrlGuard::class)->check($value);
        } catch (UrlNotAllowed $e) {
            $fail("The :attribute is not allowed. {$e->getMessage()}");
        }
    }
}
