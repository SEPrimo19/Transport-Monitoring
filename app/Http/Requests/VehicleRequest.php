<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use App\Support\AdminContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/updating a vehicle. Rules mirror the Postgres CHECK
 * The `fleet_code` COLUMN is shown to operators as "Body number", the term Philippine
 * transport actually uses for the number painted on the vehicle. The column keeps its
 * original name: renaming it would mean a migration against the live shared database
 * plus matching changes in the Android app's DTO, for no behavioural gain.
 *
 * constraints (plate 4–32, fleet_code 2–40, capacity > 0) and the GLOBAL unique
 * constraints on plate_number / fleet_code (migration 009 did NOT scope them
 * per-org, so uniqueness is checked table-wide — matching the DB).
 *
 * organization_id is a rule ONLY for super_admin (who must choose a tenant). For
 * an org_admin it is deliberately absent, so validated() never carries it and the
 * BelongsToOrganization trait stamps their own org — no cross-tenant write.
 */
class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already behind supabase.admin (active org_admin/super_admin).
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('vehicle')?->getKey();

        $rules = [
            'plate_number' => [
                'required', 'string', 'min:4', 'max:32',
                Rule::unique('vehicles', 'plate_number')->ignore($id, 'id'),
            ],
            'fleet_code' => [
                'nullable', 'string', 'min:2', 'max:40',
                Rule::unique('vehicles', 'fleet_code')->ignore($id, 'id'),
            ],
            'capacity_total' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
        ];

        if (app(AdminContext::class)->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate_number' => is_string($this->plate_number) ? trim($this->plate_number) : $this->plate_number,
            'fleet_code' => $this->filled('fleet_code') && is_string($this->fleet_code)
                ? trim($this->fleet_code)
                : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'plate_number' => 'plate number',
            'fleet_code' => 'body number',
            'capacity_total' => 'capacity',
            'organization_id' => 'organization',
        ];
    }
}
