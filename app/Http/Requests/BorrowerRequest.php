<?php
namespace App\Http\Requests;
use App\Rules\{PlainText, PhoneNumber};
use Illuminate\Foundation\Http\FormRequest;
class BorrowerRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    protected function prepareForValidation(): void
    {
        foreach (['name', 'location', 'contact_number'] as $field) {
            if (is_string($this->input($field))) $this->merge([$field => preg_replace('/^\p{Z}+|\p{Z}+$/u', '', trim($this->input($field)))]);
        }
    }
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255', new PlainText, 'regex:/^[\p{L}\p{M} .\x{2019}\x{27}-]+$/u', 'regex:/\p{L}/u'],
            'location' => ['prohibited'],
            'province_code' => ['bail', 'nullable', 'required_with:city_code,barangay_code', 'string', 'max:20'],
            'city_code' => ['bail', 'nullable', 'required_with:province_code,barangay_code', 'string', 'max:20'],
            'barangay_code' => ['bail', 'nullable', 'required_with:province_code,city_code', 'string', 'max:20'],
            'contact_number' => ['bail', 'nullable', 'string', 'max:50', new PhoneNumber],
            'submission_key' => $this->isMethod('post') ? ['required', 'uuid'] : ['nullable', 'uuid'],
        ];
    }
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->hasAny(['province_code', 'city_code', 'barangay_code']) || !$this->filled('province_code')) return;
            $addresses = app(\App\Services\PhilippineAddress::class);
            $province = $this->string('province_code')->toString();
            $city = $this->string('city_code')->toString();
            $barangay = $this->string('barangay_code')->toString();
            if (!$addresses->find($addresses->provinces(), $province)) $validator->errors()->add('province_code', 'Choose a valid province or area.');
            elseif (!$addresses->find($addresses->cities($province), $city)) $validator->errors()->add('city_code', 'Choose a city or municipality in the selected province or area.');
            elseif (!$addresses->resolve($province, $city, $barangay)) $validator->errors()->add('barangay_code', 'Choose a barangay in the selected city or municipality.');
            elseif (mb_strlen($addresses->resolve($province, $city, $barangay)) > 255) $validator->errors()->add('barangay_code', 'The selected address exceeds the supported length.');
        }];
    }
    public function messages(): array { return ['name.regex' => 'Use a name with letters, spaces, periods, apostrophes or hyphens.']; }
    public function borrowerData(): array
    {
        $data = $this->safe()->only(['name', 'contact_number', 'province_code', 'city_code', 'barangay_code']);
        foreach (['province_code', 'city_code', 'barangay_code'] as $field) $data[$field] = $data[$field] ?? null;
        $data['location'] = $data['province_code'] ? app(\App\Services\PhilippineAddress::class)->resolve($data['province_code'], $data['city_code'], $data['barangay_code']) : null;
        $existing = $this->route('borrower');
        if (!$data['province_code'] && $existing instanceof \App\Models\Borrower && !$existing->province_code) $data['location'] = $existing->location;
        $data['name'] = preg_replace('/\p{Z}+/u', ' ', $data['name']);
        if (!empty($data['contact_number'])) {
            $data['contact_number'] = (str_starts_with($data['contact_number'], '+') ? '+' : '').preg_replace('/\D/', '', $data['contact_number']);
        }
        return $data;
    }
}
