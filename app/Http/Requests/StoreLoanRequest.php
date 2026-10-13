<?php
namespace App\Http\Requests;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;
class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return [
            'borrower_id' => ['bail', 'required', 'integer', 'min:1', 'exists:borrowers,id'],
            'principal' => ['bail', 'required', new MoneyAmount(909090909090)],
            'date_borrowed' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'submission_key' => ['required', 'uuid'],
            'mode' => ['nullable', 'in:new_loan'],
            'interest_rate' => ['prohibited'], 'balance' => ['prohibited'], 'admin_id' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }
}
