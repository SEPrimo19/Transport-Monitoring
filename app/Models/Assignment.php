<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps public.assignments — the driver↔vehicle↔route pairing (with a validity
 * window) that a driver starts a trip FROM. Admin-created (created_by_profile_id);
 * the mobile driver app only reads its active assignment. Org-scoped.
 *
 * DB enforces (partial unique, WHERE is_active): a driver has AT MOST ONE active
 * assignment, and a vehicle AT MOST ONE — AssignmentRequest checks this for a clean
 * error, the index is the safety net. CHECK ends_at > starts_at.
 */
class Assignment extends Model
{
    use BelongsToOrganization;

    protected $table = 'assignments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'driver_profile_id',
        'vehicle_id',
        'route_id',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by_profile_id',
        'organization_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_profile_id', 'profile_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function statusBadge(): string
    {
        return $this->is_active ? 'text-bg-success' : 'text-bg-secondary';
    }
}
