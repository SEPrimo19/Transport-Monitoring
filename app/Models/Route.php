<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Maps public.routes (schema owned by the Android repo's migrations). Each
 * direction is its own route row (Allen→Calbayog and Calbayog→Allen are two
 * routes), 1:1 with route_geometry.
 *
 * NOTE: `App\Models\Route` is unrelated to the `Route` facade used in routes/web.php;
 * they live in different namespaces.
 */
class Route extends Model
{
    use BelongsToOrganization;

    // Postgres mints routes.id (uuid primary key default gen_random_uuid()), but
    // Eloquent only reads a generated key back for an INCREMENTING model:
    // performInsert() calls insertAndSetId() for those and a bare insert for
    // everything else, so $route->getKey() stayed null after a create. That was
    // invisible in every other controller, because their store() methods redirect
    // to a parameterless *.index. RouteController::store() redirects to
    // routes.geometry.edit, whose URI is routes/{route}/geometry -- so the insert
    // committed, URL generation then threw UrlGenerationException on the null key,
    // and the admin saw a 500 for a route that had in fact been saved, minus the
    // polyline step that redirect exists to force.
    //
    // HasUuids assigns the key in PHP before the insert (performInsert ->
    // setUniqueIds), so the model knows its own id. It also makes a non-UUID
    // {route} segment a clean 404 instead of a Postgres 22P02 cast error.
    use HasUuids;

    protected $table = 'routes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'origin_terminal_id',
        'destination_terminal_id',
        'distance_km',
        'duration_minutes',
        'is_active',
        'organization_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'distance_km' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    public function originTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'origin_terminal_id');
    }

    public function destinationTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'destination_terminal_id');
    }

    public function geometry(): HasOne
    {
        return $this->hasOne(RouteGeometry::class, 'route_id');
    }

    public function statusBadge(): string
    {
        return $this->is_active ? 'text-bg-success' : 'text-bg-secondary';
    }
}
