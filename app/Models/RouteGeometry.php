<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps public.route_geometry — the per-route polyline as jsonb `[[lat,lng],...]`
 * (NOT GeoJSON [lng,lat]). 1:1 with routes; deleting a route cascades its geometry.
 *
 * The map editor writes here via updateOrCreate. Any writer MUST keep the [lat,lng]
 * order or the mobile overlay/route-snap will be transposed. The DB enforces
 * point_count >= 2, so "no polyline" is represented by the ROW BEING ABSENT — clear
 * geometry by deleting the row, never by saving 0/1 points. organization_id is
 * auto-stamped from the parent route by a DB trigger on insert (we also set it
 * explicitly for clarity).
 */
class RouteGeometry extends Model
{
    protected $table = 'route_geometry';

    protected $primaryKey = 'route_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false; // only updated_at exists (DB trigger-managed)

    protected $fillable = [
        'route_id',
        'organization_id',
        'points',
        'point_count',
        'source',
    ];

    protected $casts = [
        'points' => 'array',
        'point_count' => 'integer',
    ];
}
