<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps public.trip_metrics: one summary row per ended trip (migration 045), filled hourly by
 * rollup_trip_metrics() and frozen once the trip is six hours old. Aggregates only; no
 * coordinates are stored.
 *
 * It exists because location_points is purged by each organization's retention setting, so
 * a report that read raw GPS would give a different answer for the same month depending on
 * when it was run. These rows do not change when the points are deleted.
 *
 * READ-ONLY, ON PURPOSE. Nothing in the console writes here: the rollup, running as the
 * database owner, is the only writer, and a driver's figures must not be editable from a
 * web form. $guarded = ['*'] means no attribute can be mass-assigned; TripMetricIsolationTest
 * also fails if a write call appears in this file.
 *
 * ORG-SCOPED via BelongsToOrganization, like every tenant model. These are per-driver
 * performance figures, which are HR data, so a super_admin reaches none of this (the routes
 * sit behind tenant.admin).
 */
class TripMetric extends Model
{
    use BelongsToOrganization;

    protected $table = 'trip_metrics';

    protected $primaryKey = 'trip_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'finalized_at' => 'datetime',
        'computed_at' => 'datetime',
        'duration_min' => 'integer',
        'point_count' => 'integer',
        'departure_delta_min' => 'integer',
        'scheduled_departure' => 'datetime',
        'distance_km' => 'float',
        'avg_moving_speed_kmh' => 'float',
        'max_speed_kmh' => 'float',
        'overspeed_share' => 'float',
    ];
}
