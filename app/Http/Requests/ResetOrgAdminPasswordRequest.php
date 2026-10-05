<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\AdminPasswordPolicy;

/**
 * Reset an org admin's password via the `driver-account` Edge Function's
 * `reset_org_admin_password` action (OrgAdminController::resetPassword() /
 * OrgAdminAccountProvisioner). Mirrors {@see ResetDriverPasswordRequest}.
 *
 * Only reachable by a super_admin (route behind `super.admin`). The Edge Function
 * re-derives the target's real email server-side and refuses unless it is on the
 * `admins.amoectrack.invalid` domain — a promoted org admin's, or a
 * real-email-at-creation org admin's, personal address is theirs to recover, not a
 * platform admin's to set.
 */
class ResetOrgAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by super.admin middleware
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
