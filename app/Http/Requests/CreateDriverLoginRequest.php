<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use App\Support\DriverAccountProvisioner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\AdminPasswordPolicy;

/**
 * "Create a driver login outright" — the admin mints a brand-new account for a
 * driver who has no Gmail or low tech confidence, rather than making them
 * self-register in the app. DriverController::storeLogin() sends this to the
 * `driver-account` Supabase Edge Function via {@see DriverAccountProvisioner}.
 *
 * `employee_code` doubles as the username half of a synthetic sign-in address
 * (`<code>@drivers.amoectrack.invalid`, built by the Edge Function; the mobile app's
 * login screen appends the same domain when the typed value has no "@") — so on
 * top of matching `drivers_employee_code_len` (2–40 chars), it is restricted to
 * characters that survive being placed in an email local-part.
 *
 * organization_id is a rule ONLY for super_admin, who must choose the target org
 * — mirrors {@see StoreDriverRequest}.
 */
class CreateDriverLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin
    }

    public function rules(): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'employee_code' => [
                'required',
                'string',
                'min:2',
                'max:40',
                // Must survive being placed in an email local-part: no leading/trailing
                // dot and no "..", neither of which is a valid dot-atom. Mirrored in the
                // `driver-account` Edge Function (index.ts, EMPLOYEE_CODE_PATTERN — also
                // reused there for the org-admin `username` field) and in
                // UpdateDriverRequest and CreateOrgAdminLoginRequest — keep all in lockstep.
                'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/',
                'regex:/^(?!.*\.\.).*$/',
                Rule::unique('drivers', 'employee_code'),
            ],
            'license_number' => ['required', 'string', 'min:3', 'max:64', Rule::unique('drivers', 'license_number')],
            'password' => AdminPasswordPolicy::rules(),
        ];

        if (app(AdminContext::class)->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->full_name) ? trim($this->full_name) : $this->full_name,
            // Lowercased before validation/uniqueness so "JSantos" and "jsantos"
            // are treated as the same username — they would resolve to the exact
            // same @drivers.amoectrack.invalid address once the Edge Function builds it.
            'employee_code' => is_string($this->employee_code) ? strtolower(trim($this->employee_code)) : $this->employee_code,
            'license_number' => is_string($this->license_number) ? trim($this->license_number) : $this->license_number,
        ]);
    }

    public function attributes(): array
    {
        return [
            'employee_code' => 'username',
            'license_number' => 'license number',
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.regex' => 'Use only letters, numbers, dots, hyphens and underscores, and don\'t start, end or double up on a dot — this becomes the driver\'s sign-in username.',
        ];
    }
}
