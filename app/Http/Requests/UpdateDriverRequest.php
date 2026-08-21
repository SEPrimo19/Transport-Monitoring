<?php

namespace App\Http\Requests;

use App\Support\DriverAccountEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Edit an existing driver's employment details + active status. Email is immutable
 * here (it lives in auth.users / GoTrue) and the organization is fixed after
 * promotion, so neither is editable.
 *
 * `employee_code` is editable for a PROMOTED driver (it's just a roster field), but
 * for an admin-created login it doubles as the username baked into the GoTrue email
 * at creation (`<code>@drivers.amoectrack.invalid`) — changing it here would silently
 * lock the driver out with nothing explaining why. {@see isUsernameLogin()} locks it
 * server-side; the edit view mirrors this with a `readonly` input, but that's a UX
 * courtesy only — this rule is the actual enforcement.
 */
class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin
    }

    public function rules(): array
    {
        $driver = $this->route('driver');
        $id = $driver?->getKey(); // profile_id

        $employeeCodeRules = [
            'required',
            'string',
            'min:2',
            'max:40',
            // Mirrors CreateDriverLoginRequest's charset (see its docblock) and the
            // `driver-account` Edge Function's — kept in lockstep because this value
            // doubles as an email local-part for admin-created logins.
            'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/',
            'regex:/^(?!.*\.\.).*$/', // no consecutive dots — not a valid dot-atom
            Rule::unique('drivers', 'employee_code')->ignore($id, 'profile_id'),
        ];

        if ($this->isUsernameLogin()) {
            $employeeCodeRules[] = Rule::in([$driver->employee_code]);
        }

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'employee_code' => $employeeCodeRules,
            'license_number' => ['required', 'string', 'min:3', 'max:64', Rule::unique('drivers', 'license_number')->ignore($id, 'profile_id')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->full_name) ? trim($this->full_name) : $this->full_name,
            // Lowercased so an edit can never write a value that could not be the email
            // local-part it doubles as for an admin-created login (mirrors
            // CreateDriverLoginRequest — see its docblock).
            'employee_code' => is_string($this->employee_code) ? strtolower(trim($this->employee_code)) : $this->employee_code,
            'license_number' => is_string($this->license_number) ? trim($this->license_number) : $this->license_number,
            'phone' => $this->filled('phone') && is_string($this->phone) ? trim($this->phone) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** True when this driver's login was minted by "Create a new login", not promoted from a personal email. */
    public function isUsernameLogin(): bool
    {
        $driver = $this->route('driver');
        if ($driver === null) {
            return false;
        }

        $email = DB::table('auth.users')->where('id', $driver->profile_id)->value('email');

        return DriverAccountEmail::isUsernameLogin($email);
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
            'employee_code.regex' => 'Use only letters, numbers, dots, hyphens and underscores, and don\'t start, end or double up on a dot.',
            'employee_code.in' => 'This is the driver\'s sign-in username and can\'t be changed here.',
        ];
    }
}
