<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
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
