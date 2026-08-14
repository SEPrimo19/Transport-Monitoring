<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps public.trip_schedules — a per-route recurring departure timetable
 * (informational; it does not create trips). Org-scoped. Two modes: 'interval'
 * (every interval_minutes between start/end) or 'fixed' (a list of clock times).
 * days_of_week is ISO (1=Mon … 7=Sun); the jsonb lists map to array casts.
 */
class TripSchedule extends Model
{
    use BelongsToOrganization;

    protected $table = 'trip_schedules';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'route_id', 'label', 'mode', 'days_of_week',
        'start_time', 'end_time', 'interval_minutes', 'departure_times',
        'is_active', 'organization_id',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'departure_times' => 'array',
        'interval_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function statusBadge(): string
    {
        return $this->is_active ? 'text-bg-success' : 'text-bg-secondary';
    }

    public function daysLabel(): string
    {
        $days = collect($this->days_of_week ?? [])->sort()->map(fn ($i) => self::DAYS[$i] ?? $i);

        return $days->count() === 7 ? 'Every day' : $days->implode(', ');
    }

    private function hm(?string $t): ?string
    {
        return $t ? substr($t, 0, 5) : null;
    }

    /** Human summary of the timetable. */
    public function summary(): string
    {
        if ($this->mode === 'interval') {
            return 'Every '.$this->interval_minutes.' min · '.$this->hm($this->start_time).'–'.$this->hm($this->end_time);
        }

        return implode(', ', $this->departure_times ?? []);
    }

    /** The full generated list of departure clock times (HH:MM). */
    public function departureClockTimes(): array
    {
        if ($this->mode === 'fixed') {
            return $this->departure_times ?? [];
        }

        $start = $this->hm($this->start_time);
        $end = $this->hm($this->end_time);
        if ($start === null || $end === null || ! $this->interval_minutes) {
            return [];
        }

        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $cur = $sh * 60 + $sm;
        $last = $eh * 60 + $em;

        $out = [];
        while ($cur <= $last) {
            $out[] = sprintf('%02d:%02d', intdiv($cur, 60), $cur % 60);
            $cur += $this->interval_minutes;
        }

        return $out;
    }

    public function departureCount(): int
    {
        return count($this->departureClockTimes());
    }
}
