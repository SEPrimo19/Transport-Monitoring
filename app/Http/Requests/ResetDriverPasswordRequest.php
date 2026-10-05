<?php

namespace App\Http\Requests;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\AdminPasswordPolicy;

/**
 * Reset a driver's password via the `driver-account` Edge Function
 * (DriverController::resetPassword() / DriverAccountProvisioner). Works for
 * both admin-created and promoted-from-passenger drivers — either way the
 * login lives in the same auth.users row. Which org the target driver
 * belongs to is enforced by the {driver} route-model binding (org-scoped via
 * {@see OrganizationScope}), not by this request.
 */
class ResetDriverPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin; org check is the {driver} binding
    }

    public function rules(): array
    {
        return [
            'password' => AdminPasswordPolicy::rules(confirmed: true),
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'The new password and its confirmation do not match.',
            'password.min' => 'Use at least 8 characters for the new password.',
        ];
    }
}
