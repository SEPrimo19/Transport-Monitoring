<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating an organization (tenant). Mirrors the DB
 * CHECK/UNIQUE constraints (slug 2-50 & unique, name 2-120, status in
 * active/suspended, region lat/lng ranges). The slug is the stable tenant key
 * seeds/lookups reference, so it is REQUIRED on create but IMMUTABLE on update
 * (the edit form omits it). Authorization (super_admin only) is handled by the
 * `super.admin` middleware, not here.
 */
class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by super.admin middleware
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'status' => ['required', Rule::in(array_keys(\App\Models\Organization::STATUSES))],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'region_center_lat' => ['nullable', 'required_with:region_center_lng', 'numeric', 'between:-90,90'],
            'region_center_lng' => ['nullable', 'required_with:region_center_lat', 'numeric', 'between:-180,180'],
            'map_style_url' => ['nullable', 'url', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ];

        // slug is set once, at creation. On update the form doesn't send it.
        if ($this->route('organization') === null) {
            $rules['slug'] = ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('organizations', 'slug')];
        }

        return $rules;
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
        ]);
    }

    public function attributes(): array
    {
        return [
            'region_center_lat' => 'region center latitude',
            'region_center_lng' => 'region center longitude',
            'map_style_url' => 'map style URL',
            'logo_url' => 'logo URL',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and hyphens only (e.g. "amoec").',
            'primary_color.regex' => 'Use a 6-digit hex colour like #0d6efd.',
            'secondary_color.regex' => 'Use a 6-digit hex colour like #6c757d.',
            'region_center_lat.required_with' => 'Enter a latitude too (the region center is a pair).',
            'region_center_lng.required_with' => 'Enter a longitude too (the region center is a pair).',
        ];
    }
}
