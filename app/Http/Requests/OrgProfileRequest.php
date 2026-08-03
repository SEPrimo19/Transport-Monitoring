<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a cooperative editing ITS OWN profile (org_admin self-service).
 * Mirrors {@see OrganizationRequest} but deliberately OMITS the two platform-level
 * fields an org_admin must not touch:
 *   - slug   — the immutable tenant key (set once, by the platform).
 *   - status — suspending your own tenant would lock you out; that stays with
 *              super_admin via the Organizations screens.
 * Adds location_retention_days (the Chapter 1 Scope data-retention control).
 * Authorization (an active org_admin/super_admin) is handled by the
 * supabase.admin middleware; which org is edited comes from AdminContext, never
 * from the request — so there is no organization_id field here to trust.
 */
class OrgProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by supabase.admin middleware; org resolved from AdminContext
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'region_center_lat' => ['nullable', 'required_with:region_center_lng', 'numeric', 'between:-90,90'],
            'region_center_lng' => ['nullable', 'required_with:region_center_lat', 'numeric', 'between:-180,180'],
            'map_style_url' => ['nullable', 'url', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'location_retention_days' => ['nullable', 'integer', 'between:1,3650'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
            'location_retention_days' => $this->filled('location_retention_days') ? $this->location_retention_days : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'region_center_lat' => 'region center latitude',
            'region_center_lng' => 'region center longitude',
            'map_style_url' => 'map style URL',
            'logo_url' => 'logo URL',
            'location_retention_days' => 'retention period',
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Use a 6-digit hex colour like #165935.',
            'secondary_color.regex' => 'Use a 6-digit hex colour like #6c757d.',
            'region_center_lat.required_with' => 'Enter a latitude too (the region center is a pair).',
            'region_center_lng.required_with' => 'Enter a longitude too (the region center is a pair).',
            'location_retention_days.between' => 'Choose between 1 and 3650 days, or leave blank to keep data indefinitely.',
        ];
    }
}
