<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps public.reports (schema owned by the Android repo's migrations). Reports are
 * submitted by passengers in the app; the web admin only VIEWS and TRIAGES them
 * (status), never creates or deletes them.
 *
 * DB constraint reports_resolution_fields: resolved/dismissed REQUIRE resolved_at
 * set; submitted/reviewing REQUIRE it null. ReportController enforces both sides.
 */
class Report extends Model
{
    use BelongsToOrganization;

    protected $table = 'reports';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'status',
        'resolved_at',
        'resolved_by_profile_id',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'passenger_reported_at' => 'datetime',
        'passenger_latitude' => 'float',
        'passenger_longitude' => 'float',
        'is_anonymous' => 'boolean',
    ];

    /** enum value => mobile-facing label. */
    public const CATEGORIES = [
        'safety' => 'Safety',
        'driver_behavior' => 'Driver Behavior',
        'vehicle_condition' => 'Vehicle Condition',
        'route_issue' => 'Route Issue',
        'other' => 'Other',
    ];

    /** enum value => mobile-facing label. */
    public const STATUSES = [
        'submitted' => 'Pending',
        'reviewing' => 'Reviewed',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
    ];

    /** Statuses that require resolved_at / resolved_by to be set. */
    public const RESOLVED_STATES = ['resolved', 'dismissed'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'passenger_profile_id');
    }

    /**
     * Reporter name for admin display, honouring the passenger's anonymity choice.
     * When the report was filed anonymously the reporter FK is still stored (own
     * history + abuse traceability) but the identity is MASKED here — the one place
     * admins read a reporter's name. Returns null when there is genuinely no name.
     */
    public function reporterDisplayName(): ?string
    {
        if ($this->is_anonymous) {
            return 'Anonymous';
        }

        return $this->reporter?->full_name;
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'resolved_by_profile_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'submitted' => 'text-bg-warning',
            'reviewing' => 'text-bg-info',
            'resolved' => 'text-bg-success',
            'dismissed' => 'text-bg-secondary',
            default => 'text-bg-light',
        };
    }

    public function hasEvidence(): bool
    {
        return ! empty($this->evidence_object_path);
    }

    public function evidenceIsImage(): bool
    {
        return str_starts_with((string) $this->evidence_mime_type, 'image/');
    }

    public function evidenceIsVideo(): bool
    {
        return str_starts_with((string) $this->evidence_mime_type, 'video/');
    }

    public function hasPassengerLocation(): bool
    {
        return $this->passenger_latitude !== null && $this->passenger_longitude !== null;
    }
}
