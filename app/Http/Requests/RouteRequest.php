<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a route. Mirrors the DB CHECK/UNIQUE
 * constraints (code 2-32 & GLOBALLY unique, name 2-120, distance > 0,
 * duration > 0 or null, origin != destination) and enforces that BOTH terminals
 * belong to the route's organization — an org_admin can't attach another tenant's
 * terminal, and a super_admin's terminals must match the org they picked.
 */
class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind supabase.admin
    }

    public function rules(): array
    {
        $ctx = app(AdminContext::class);
        $id = $this->route('route')?->getKey();

        // Terminals must live in the route's org: the selected org for super_admin,
        // the admin's own org otherwise.
        $orgForTerminals = $ctx->isSuperAdmin() ? $this->input('organization_id') : $ctx->organizationId;
        $terminalInOrg = Rule::exists('terminals', 'id')->where('organization_id', $orgForTerminals);

        $rules = [
            'code' => ['required', 'string', 'min:2', 'max:32', Rule::unique('routes', 'code')->ignore($id, 'id')],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'origin_terminal_id' => ['required', 'string', $terminalInOrg],
            'destination_terminal_id' => ['required', 'string', 'different:origin_terminal_id', $terminalInOrg],
            'distance_km' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
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
            'duration_minutes' => $this->filled('duration_minutes') ? $this->duration_minutes : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'origin_terminal_id' => 'origin terminal',
            'destination_terminal_id' => 'destination terminal',
            'distance_km' => 'distance',
            'duration_minutes' => 'duration',
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'origin_terminal_id.exists' => 'Choose an origin terminal in this organization.',
            'destination_terminal_id.exists' => 'Choose a destination terminal in this organization.',
            'destination_terminal_id.different' => 'Origin and destination must be different terminals.',
        ];
    }
}
