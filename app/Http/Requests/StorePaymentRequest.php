<?php
namespace App\Http\Requests;
use App\Models\Loan;
use App\Rules\MoneyAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return [
            'borrower_id' => ['bail', 'required', 'integer', 'min:1', 'exists:borrowers,id'],
            'loan_id' => ['bail', 'required', 'integer', 'min:1', 'exists:loans,id'],
            'payment_amount' => ['bail', 'required', new MoneyAmount],
            'payment_date' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'submission_key' => ['required', 'uuid'], 'mode' => ['nullable', 'in:payment'],
            'balance' => ['prohibited'], 'admin_id' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['loan_id', 'borrower_id', 'payment_date'])) return;
            $loan = Loan::find($this->integer('loan_id'));
            if (!$loan) return;
            if ($loan->borrower_id !== $this->integer('borrower_id')) $validator->errors()->add('loan_id', 'Choose a loan belonging to the selected borrower.');
            if ($this->string('payment_date')->toString() < $loan->date_borrowed) $validator->errors()->add('payment_date', 'Payment cannot precede the loan date.');
        }];
    }
}
