<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps public.trips (read-only in the admin — trips are created/ended by the driver
 * app). Org-scoped. Used by the trip-history screens; the live map reads
 * vehicle_live_location instead.
 */
class Trip extends Model
{
    use BelongsToOrganization;

    protected $table = 'trips';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** trip_status enum. */
    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'ended' => 'Ended',
        'cancelled' => 'Cancelled',
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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'active' => 'text-bg-success',
            'ended' => 'text-bg-secondary',
            'pending' => 'text-bg-info',
            'cancelled' => 'text-bg-danger',
            default => 'text-bg-light',
        };
    }

    /** Actual travel time in minutes (null until the trip has both timestamps). */
    public function durationMinutes(): ?int
    {
        if ($this->started_at === null || $this->ended_at === null) {
            return null;
        }

        return (int) round(max(0, $this->ended_at->timestamp - $this->started_at->timestamp) / 60);
    }
}
