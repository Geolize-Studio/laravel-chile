<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Laravel\Rules;

use Closure;
use GeolizeStudio\Chile\Rut;
use Illuminate\Contracts\Validation\ValidationRule;

final class RutRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Rut::tryParse($value) === null) {
            $fail('The :attribute field must be a valid RUT.');
        }
    }
}
