<?php

namespace App\Rules;

use App\Support\Legal\PeruvianRuc;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPeruvianRuc implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PeruvianRuc::isValid((string) $value)) {
            $fail('Ingresa un RUC peruano valido.');
        }
    }
}
