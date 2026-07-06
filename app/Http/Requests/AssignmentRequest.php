<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating an assignment. The driver, vehicle and route
 * must all belong to the assignment's organization (the selected org for a
 * super_admin, the admin's own org otherwise). Mirrors the DB partial-unique
 * indexes: an ACTIVE assignment is refused if that driver or vehicle already has a
 * different active one. ends_at must be after starts_at (CHECK assignments_time_range).
 */
class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // behind supabase.admin
    }

    public function rules(): array
    {
        $ctx = app(AdminContext::class);
        $org = $ctx->isSuperAdmin() ? $this->input('organization_id') : $ctx->organizationId;

        $rules = [
            'driver_profile_id' => ['required', 'string', Rule::exists('drivers', 'profile_id')->where('organization_id', $org)],
            'vehicle_id' => ['required', 'string', Rule::exists('vehicles', 'id')->where('organization_id', $org)],
            'route_id' => ['required', 'string', Rule::exists('routes', 'id')->where('organization_id', $org)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];

        if ($ctx->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->boolean('is_active')) {
                return; // only ACTIVE assignments are constrained to one-per-driver/vehicle
            }

            $ignoreId = $this->route('assignment')?->getKey();

            if ($this->filled('driver_profile_id') && $this->activeConflict('driver_profile_id', $this->input('driver_profile_id'), $ignoreId)) {
                $v->errors()->add('driver_profile_id', 'This driver already has an active assignment. Deactivate it first, or set this one inactive.');
            }

            if ($this->filled('vehicle_id') && $this->activeConflict('vehicle_id', $this->input('vehicle_id'), $ignoreId)) {
                $v->errors()->add('vehicle_id', 'This vehicle already has an active assignment. Deactivate it first, or set this one inactive.');
            }
        });
    }

    private function activeConflict(string $column, string $value, ?string $ignoreId): bool
    {
        return DB::table('assignments')
            ->where($column, $value)
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function attributes(): array
    {
        return [
            'driver_profile_id' => 'driver',
            'vehicle_id' => 'vehicle',
            'route_id' => 'route',
            'starts_at' => 'start',
            'ends_at' => 'end',
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'driver_profile_id.exists' => 'Choose a driver in this organization.',
            'vehicle_id.exists' => 'Choose a vehicle in this organization.',
            'route_id.exists' => 'Choose a route in this organization.',
            'ends_at.after' => 'The end must be after the start.',
        ];
    }
}
