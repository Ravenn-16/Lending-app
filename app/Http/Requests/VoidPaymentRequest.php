<?php
namespace App\Http\Requests;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return ['reason' => ['bail', 'required', 'string', 'max:500', new PlainText(true)],
            'submission_key' => ['required', 'uuid'], 'admin_id' => ['prohibited'], 'amount' => ['prohibited'], 'effective_date' => ['prohibited']];
    }
}
