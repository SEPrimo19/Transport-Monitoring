<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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

        // The assignment being updated, if any. Its existing driver/vehicle/route stay
        // acceptable even once deactivated, so an admin can still fix the shift window
        // of an assignment whose bus went into maintenance yesterday. Only a CHANGE to
        // an inactive entity is rejected.
        $current = $this->route('assignment');

        $rules = [
            'driver_profile_id' => ['required', 'string',
                $this->activeOnly('drivers', 'profile_id', $org, 'is_active', true, $current?->driver_profile_id)],
            'vehicle_id' => ['required', 'string',
                $this->activeOnly('vehicles', 'id', $org, 'status', 'active', $current?->vehicle_id)],
            'route_id' => ['required', 'string',
                $this->activeOnly('routes', 'id', $org, 'is_active', true, $current?->route_id)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];

        if ($ctx->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    /**
     * An `exists` rule scoped to the tenant that additionally requires the row to be
     * active — with an escape hatch for the value already persisted on the record
     * being edited.
     *
     * Why this exists: migration 20260814_027 makes the database refuse to start a trip
     * from a suspended driver, a vehicle in maintenance, or a retired route. Without a
     * matching rule here, the admin would happily save an assignment that is guaranteed
     * to fail at the terminal — and the driver would be the one to discover it.
     *
     * The active predicate is nested inside its own group so the `or` cannot escape the
     * organization_id filter. Flattening it would let a crafted POST reference another
     * tenant's row.
     */
    private function activeOnly(
        string $table,
        string $column,
        ?string $org,
        string $activeColumn,
        mixed $activeValue,
        mixed $keep = null
    ): Exists {
        return Rule::exists($table, $column)
            ->where('organization_id', $org)
            ->where(function ($query) use ($column, $activeColumn, $activeValue, $keep) {
                $query->where(function ($group) use ($column, $activeColumn, $activeValue, $keep) {
                    $group->where($activeColumn, $activeValue);
                    if ($keep !== null) {
                        $group->orWhere($column, $keep);
                    }
                });
            });
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
