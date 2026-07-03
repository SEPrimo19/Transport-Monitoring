<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps the existing Supabase `vehicles` table (schema owned by the Android repo's
 * supabase/migrations — do NOT create a Laravel migration for it).
 *
 * NOTE (mobile↔web alignment): `status` (active/maintenance/inactive) is the web
 * view; `is_active` is what RLS + mobile read. Keep them in sync on every write —
 * is_active := (status === 'active') — so a Maintenance/Inactive vehicle stops
 * showing to passengers. There is NO DB trigger; this app owns that rule.
 */
class Vehicle extends Model
{
    use BelongsToOrganization;

    /** vehicle_status enum in migration 20260702_015 — order = display order. */
    public const STATUSES = ['active', 'maintenance', 'inactive'];

    protected $table = 'vehicles';

    // Supabase uses UUID primary keys, not auto-increment integers.
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plate_number',
        'fleet_code',
        'capacity_total',
        'is_active',
        'status',
        'organization_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity_total' => 'integer',
    ];

    /** Tenant owner. Organization is the tenant root (NOT org-scoped). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Bootstrap badge class for the current status. */
    public function statusBadge(): string
    {
        return match ($this->status) {
            'active' => 'text-bg-success',
            'maintenance' => 'text-bg-warning',
            'inactive' => 'text-bg-secondary',
            default => 'text-bg-light',
        };
    }
}
