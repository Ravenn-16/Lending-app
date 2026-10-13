<?php
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match('/^\+?[0-9 ()-]+$/D', $value)) {
            $fail('Use digits, spaces, parentheses or hyphens, with an optional leading plus sign.'); return;
        }
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) < 10 || strlen($digits) > 15 || preg_match('/^(.)\1+$/', $digits)) {
            $fail('Enter a phone number with 10 to 15 digits. Repeated single digits are not valid.');
        }
        if (substr_count($value, '(') !== substr_count($value, ')') || !preg_match('/^(?:[^()]|\([0-9 ]+\))*$/D', $value)) {
            $fail('Phone number parentheses must be balanced.');
        }
    }
}
