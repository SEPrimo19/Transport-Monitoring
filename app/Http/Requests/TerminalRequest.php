<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a terminal. Mirrors the DB CHECK/UNIQUE
 * constraints (code 2-32 & GLOBALLY unique, name 2-120, latitude -90..90,
 * longitude -180..180, both added in migration 015 for address). Coordinates are
 * optional but must come as a pair — a terminal with a lat and no lng is useless
 * to the map overlay.
 */
class TerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin
    }

    public function rules(): array
    {
        $ctx = app(AdminContext::class);
        $id = $this->route('terminal')?->getKey();

        $rules = [
            'code' => ['required', 'string', 'min:2', 'max:32', Rule::unique('terminals', 'code')->ignore($id, 'id')],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];

        if ($ctx->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->code) ? trim($this->code) : $this->code,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'address' => $this->filled('address') ? trim($this->address) : null,
            'latitude' => $this->filled('latitude') ? $this->latitude : null,
            'longitude' => $this->filled('longitude') ? $this->longitude : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required_with' => 'Enter a latitude too (coordinates come as a pair).',
            'longitude.required_with' => 'Enter a longitude too (coordinates come as a pair).',
        ];
    }
}
