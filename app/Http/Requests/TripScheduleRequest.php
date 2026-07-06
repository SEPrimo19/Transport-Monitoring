<?php

namespace App\Http\Requests;

use App\Support\AdminContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for a route timetable. The route must belong to the schedule's org.
 * Interval mode needs start/end/interval (end after start); fixed mode needs a list
 * of HH:MM departure times. The unused mode's columns are nulled so a stale value
 * never trips the DB CHECK constraints.
 */
class TripScheduleRequest extends FormRequest
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
            'route_id' => ['required', 'string', Rule::exists('routes', 'id')->where('organization_id', $org)],
            'label' => ['nullable', 'string', 'max:80'],
            'mode' => ['required', Rule::in(['interval', 'fixed'])],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'is_active' => ['boolean'],

            'start_time' => ['nullable', 'required_if:mode,interval', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:mode,interval', 'date_format:H:i', 'after:start_time'],
            'interval_minutes' => ['nullable', 'required_if:mode,interval', 'integer', 'min:1', 'max:1440'],

            'departure_times' => ['nullable', 'required_if:mode,fixed', 'array', 'min:1'],
            'departure_times.*' => ['date_format:H:i'],
        ];

        if ($ctx->isSuperAdmin()) {
            $rules['organization_id'] = ['required', 'string', Rule::exists('organizations', 'id')];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('mode');

        // Fixed times arrive as a comma-separated string; normalise to an array.
        $times = $this->input('departure_times');
        if (is_string($times)) {
            $times = collect(explode(',', $times))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'days_of_week' => array_values(array_map('intval', (array) $this->input('days_of_week', []))),
            'label' => $this->filled('label') ? trim($this->label) : null,
            // Null out the fields that don't belong to the chosen mode.
            'start_time' => $mode === 'interval' ? $this->input('start_time') : null,
            'end_time' => $mode === 'interval' ? $this->input('end_time') : null,
            'interval_minutes' => $mode === 'interval' ? $this->input('interval_minutes') : null,
            'departure_times' => $mode === 'fixed' ? $times : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'route_id' => 'route',
            'days_of_week' => 'days',
            'interval_minutes' => 'interval',
            'departure_times' => 'departure times',
            'organization_id' => 'organization',
        ];
    }

    public function messages(): array
    {
        return [
            'route_id.exists' => 'Choose a route in this organization.',
            'end_time.after' => 'The end time must be after the start time.',
            'departure_times.*.date_format' => 'Each departure must be a time like 06:30.',
            'departure_times.required_if' => 'Enter at least one departure time (e.g. 06:00, 09:00).',
        ];
    }
}
