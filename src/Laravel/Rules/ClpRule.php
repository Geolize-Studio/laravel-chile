<?php

declare(strict_types=1);

namespace GeolizeStudio\Chile\Laravel\Rules;

use Closure;
use GeolizeStudio\Chile\Clp;
use Illuminate\Contracts\Validation\ValidationRule;

final class ClpRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Clp::tryParse($value) === null) {
            $fail('The :attribute must be a valid whole-peso CLP amount.');
        }
    }
}
