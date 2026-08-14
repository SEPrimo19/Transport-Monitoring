<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the super_admin onboarding wizard: create a tenant AND name its
 * first org_admin in one submission. The organization half mirrors
 * {@see OrganizationRequest} (create shape — slug required + unique); the admin
 * half is the email of an EXISTING passenger account to promote, plus an optional
 * display name. Whether that email actually resolves to a promotable passenger is
 * a business check done in the controller (mirrors OrgAdminController), not here.
 * Authorization (super_admin) is enforced by the super.admin middleware.
 */
class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by super.admin middleware
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            // ── Organization ──
            'slug' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('organizations', 'slug')],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'status' => ['required', Rule::in(array_keys(Organization::STATUSES))],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'region_center_lat' => ['nullable', 'required_with:region_center_lng', 'numeric', 'between:-90,90'],
            'region_center_lng' => ['nullable', 'required_with:region_center_lat', 'numeric', 'between:-180,180'],
            'map_style_url' => ['nullable', 'url', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],

            // ── First administrator ──
            'admin_email' => ['required', 'email', 'max:200'],
            'admin_full_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => is_string($this->slug) ? strtolower(trim($this->slug)) : $this->slug,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'legal_name' => $this->filled('legal_name') ? trim($this->legal_name) : null,
            'logo_url' => $this->filled('logo_url') ? trim($this->logo_url) : null,
            'primary_color' => $this->filled('primary_color') ? strtolower(trim($this->primary_color)) : null,
            'secondary_color' => $this->filled('secondary_color') ? strtolower(trim($this->secondary_color)) : null,
            'region_center_lat' => $this->filled('region_center_lat') ? $this->region_center_lat : null,
            'region_center_lng' => $this->filled('region_center_lng') ? $this->region_center_lng : null,
            'map_style_url' => $this->filled('map_style_url') ? trim($this->map_style_url) : null,
            'contact_email' => $this->filled('contact_email') ? trim($this->contact_email) : null,
            'contact_phone' => $this->filled('contact_phone') ? trim($this->contact_phone) : null,
            'admin_email' => is_string($this->admin_email) ? strtolower(trim($this->admin_email)) : $this->admin_email,
            'admin_full_name' => $this->filled('admin_full_name') ? trim($this->admin_full_name) : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'admin_email' => 'administrator email',
            'admin_full_name' => 'administrator name',
            'region_center_lat' => 'region center latitude',
            'region_center_lng' => 'region center longitude',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and hyphens only (e.g. "amoec").',
            'slug.unique' => 'That slug is already taken by another organization.',
            'primary_color.regex' => 'Use a 6-digit hex colour like #165935.',
            'secondary_color.regex' => 'Use a 6-digit hex colour like #6c757d.',
        ];
    }
}
