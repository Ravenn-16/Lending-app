<?php
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
class PlainText implements ValidationRule
{
    public function __construct(private bool $multiline = false) {}
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || preg_match('/[<>\p{Cc}\p{Cf}]/u', $this->multiline ? str_replace(["\r", "\n", "\t"], '', $value) : $value) || !preg_match('/[\p{L}\p{N}]/u', $value)) {
            $fail('Enter readable plain text without HTML or hidden control characters.');
        }
    }
}
