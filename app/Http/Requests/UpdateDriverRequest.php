<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit an existing driver's employment details + active status. Email is immutable
 * here (it lives in auth.users / GoTrue) and the organization is fixed after
 * promotion, so neither is editable.
 */
class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin
    }

    public function rules(): array
    {
        $id = $this->route('driver')?->getKey(); // profile_id

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'employee_code' => ['required', 'string', 'min:2', 'max:40', Rule::unique('drivers', 'employee_code')->ignore($id, 'profile_id')],
            'license_number' => ['required', 'string', 'min:3', 'max:64', Rule::unique('drivers', 'license_number')->ignore($id, 'profile_id')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->full_name) ? trim($this->full_name) : $this->full_name,
            'employee_code' => is_string($this->employee_code) ? trim($this->employee_code) : $this->employee_code,
            'license_number' => is_string($this->license_number) ? trim($this->license_number) : $this->license_number,
            'phone' => $this->filled('phone') && is_string($this->phone) ? trim($this->phone) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'employee_code' => 'employee code',
            'license_number' => 'license number',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, spaces, +, -, ( ) only).',
        ];
    }
}
