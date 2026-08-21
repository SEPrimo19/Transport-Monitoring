<?php

namespace App\Http\Requests;

use App\Support\OrgAdminAccountProvisioner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Create a new login" for an org admin — the org-admin sibling of
 * {@see CreateDriverLoginRequest}. Sent to the `driver-account` Supabase Edge
 * Function's `create_org_admin` action via {@see OrgAdminAccountProvisioner}.
 *
 * Only reachable by a super_admin — the route sits behind the `super.admin`
 * middleware group (see routes/web.php), so unlike CreateDriverLoginRequest
 * there is no org_admin caller tier to fall back to its own organization for;
 * `organization_id` is always required here.
 *
 * `username` doubles as the local part of a SYNTHETIC sign-in address
 * (`<username>@admins.amoectrack.invalid`, built by the Edge Function) ONLY when
 * `email` is left blank — so on top of length, it is restricted to characters
 * that survive being placed in an email local-part. This is the SAME charset as
 * `employee_code` on the driver side: mirrored in `CreateDriverLoginRequest`,
 * `UpdateDriverRequest`, and the `driver-account` Edge Function's
 * `EMPLOYEE_CODE_PATTERN` (reused there, not duplicated, for this field too) —
 * keep all in lockstep.
 *
 * `email` is OPTIONAL and validated only as a shape (`email` rule) when supplied —
 * that optionality is the entire point of this action existing (see the Edge
 * Function's create_org_admin docs). Uniqueness of the resulting address is not
 * pre-checked here (there is no local table for it, unlike drivers'
 * `employee_code`/`license_number`); a collision surfaces as a 409 from the Edge
 * Function, which {@see OrgAdminAccountProvisioner} turns into a
 * plain-language error.
 */
class CreateOrgAdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by super.admin middleware
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'username' => [
                'required',
                'string',
                'min:2',
                'max:40',
                // Must survive being placed in an email local-part: no leading/trailing
                // dot and no "..", neither of which is a valid dot-atom.
                'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/',
                'regex:/^(?!.*\.\.).*$/',
            ],
            'password' => ['required', 'string', 'min:8'],
            'email' => ['nullable', 'email'],
            'organization_id' => ['required', 'string', Rule::exists('organizations', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => is_string($this->full_name) ? trim($this->full_name) : $this->full_name,
            // Lowercased before validation: the Edge Function lowercases the username
            // before building the synthetic address, so "JSantos" and "jsantos" must
            // resolve to the exact same one.
            'username' => is_string($this->username) ? strtolower(trim($this->username)) : $this->username,
            // Blank stays null (not empty string) so `nullable` actually applies and the
            // Edge Function payload carries a real "omitted" signal, not "".
            'email' => $this->filled('email') && is_string($this->email) ? trim(strtolower($this->email)) : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'username' => 'username',
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use only letters, numbers, dots, hyphens and underscores, and don\'t start, end or double up on a dot — this becomes the sign-in username when no email is given.',
        ];
    }
}
