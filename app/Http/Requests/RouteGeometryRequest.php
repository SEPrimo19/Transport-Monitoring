<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation for saving a route polyline. The map editor submits `points` as a JSON
 * string of `[[lat,lng],...]`; we decode it and enforce the DB shape: an array of at
 * least 2 [lat,lng] pairs, each within valid coordinate ranges. To CLEAR a polyline
 * the editor calls DELETE (destroy) instead — a 0/1-point save is rejected here
 * (mirroring the route_geometry_point_count_min CHECK). Authorization is handled by
 * the org-scoped {route} binding + supabase.admin middleware, not here.
 */
class RouteGeometryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by supabase.admin + org-scoped route binding
    }

    protected function prepareForValidation(): void
    {
        $decoded = null;
        if (is_string($this->points)) {
            $decoded = json_decode($this->points, true);
        } elseif (is_array($this->points)) {
            $decoded = $this->points;
        }

        $this->merge(['points' => $decoded]);
    }

    public function rules(): array
    {
        return [
            // 5000 is a generous ceiling; the densest seeded route is ~1527 points.
            'points' => ['required', 'array', 'min:2', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $points = $this->points;
            if (! is_array($points)) {
                return; // the base 'array' rule already reports this
            }

            foreach ($points as $i => $p) {
                $lat = is_array($p) ? ($p[0] ?? null) : null;
                $lng = is_array($p) ? ($p[1] ?? null) : null;

                if (! is_array($p) || count($p) !== 2
                    || ! is_numeric($lat) || ! is_numeric($lng)
                    || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    $v->errors()->add('points',
                        'The polyline has an invalid coordinate at point '.($i + 1).'. Each point must be [lat, lng] within valid ranges.');

                    return; // one clear error is enough
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'points.required' => 'Draw a polyline with at least 2 points, or use “Remove polyline” to clear it.',
            'points.array' => 'The polyline data was malformed. Please redraw and try again.',
            'points.min' => 'A polyline needs at least 2 points.',
            'points.max' => 'That polyline has too many points (max 5000).',
        ];
    }
}
