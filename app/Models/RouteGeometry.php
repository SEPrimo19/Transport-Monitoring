<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps public.route_geometry — the per-route polyline as jsonb `[[lat,lng],...]`
 * (NOT GeoJSON [lng,lat]). 1:1 with routes; deleting a route cascades its geometry.
 *
 * This CRUD only READS it (point count / presence). Editing the polyline needs a
 * map editor (Step 3), and any writer MUST keep the [lat,lng] order or the mobile
 * overlay/route-snap will be transposed.
 */
class RouteGeometry extends Model
{
    protected $table = 'route_geometry';
    protected $primaryKey = 'route_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false; // only updated_at exists (DB trigger-managed)

    protected $casts = [
        'point_count' => 'integer',
    ];
}
