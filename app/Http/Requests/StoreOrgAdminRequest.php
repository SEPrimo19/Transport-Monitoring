<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for promoting an existing account to org_admin of a chosen tenant.
 * Super-admin only (gated by the super.admin middleware). The email is resolved to
 * an exact account in the controller; here we only validate shape + that the org
 * exists. full_name is optional (keeps the person's existing name when blank).
 */
class StoreOrgAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by super.admin middleware
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'organization_id' => ['required', 'string', Rule::exists('organizations', 'id')],
            'full_name' => ['nullable', 'string', 'min:2', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? trim(strtolower($this->email)) : $this->email,
            'full_name' => $this->filled('full_name') ? trim($this->full_name) : null,
        ]);
    }
}
