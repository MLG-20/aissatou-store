<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro sénégalais à 9 chiffres, avec ou sans indicatif : la livraison
 * (appel du livreur) et WhatsApp en ont besoin d'un exploitable.
 */
class SenegalPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::toE164($value) === null) {
            $fail('Numéro sénégalais à 9 chiffres attendu, par exemple 77 123 45 67.');
        }
    }
}
