<?php
namespace App\Rules;
use App\Services\LoanCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
class MoneyAmount implements ValidationRule
{
    public function __construct(private int $maximum = 999999999999) {}
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !is_int($value)) {
            $fail('Enter an amount using digits and at most two decimal places.'); return;
        }
        if (!preg_match('/^[0-9]{1,10}(?:[.][0-9]{1,2})?$/D', (string)$value)) {
            $fail('Use digits and at most two decimal places, without commas or currency symbols.'); return;
        }
        $cents = LoanCalculator::cents($value);
        if ($cents < 1 || $cents > $this->maximum) {
            $fail('Amount must be between '."\u{20B1}".'0.01 and '."\u{20B1}".number_format($this->maximum / 100, 2).'.');
        }
    }
}
