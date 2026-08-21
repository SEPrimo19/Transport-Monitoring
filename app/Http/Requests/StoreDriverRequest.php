<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Promote a passenger to driver." The person must already have an account
 * (self-registered in the app). We take their exact email + employment details;
 * DriverController::store() resolves the email to an org-less passenger and
 * elevates them. No login account is created here (option A — no service_role).
 *
 * organization_id is a rule ONLY for super_admin, who must choose the target org.
 */
class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin
    }

    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'email'],
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'employee_code' => ['required', 'string', 'min:2', 'max:40', Rule::unique('drivers', 'employee_code')],
            'license_number' => ['required', 'string', 'min:3', 'max:64', Rule::unique('drivers', 'license_number')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
        ];

        if (app(AdminContext::class)->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? trim(strtolower($this->email)) : $this->email,
            'full_name' => is_string($this->full_name) ? trim($this->full_name) : $this->full_name,
            // Lowercased like CreateDriverLoginRequest does — otherwise "JSantos" (promoted
            // here) and "jsantos" (created as a login) can coexist as visually-duplicate
            // usernames in the same roster.
            'employee_code' => is_string($this->employee_code) ? strtolower(trim($this->employee_code)) : $this->employee_code,
            'license_number' => is_string($this->license_number) ? trim($this->license_number) : $this->license_number,
            'phone' => $this->filled('phone') && is_string($this->phone) ? trim($this->phone) : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'employee_code' => 'employee code',
            'license_number' => 'license number',
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, spaces, +, -, ( ) only).',
        ];
    }
}
