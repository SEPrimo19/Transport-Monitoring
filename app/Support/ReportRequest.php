<?php

namespace App\Support;

use App\Models\Route;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The period and route a report was asked for, resolved once for every report.
 *
 * A range that cannot be used is replaced by the default period, and `notice` says so, because
 * a report that quietly covers different dates from the ones requested is worse than an error.
 * The route is only honoured when it is a UUID that resolves through the scoped Route model:
 * a malformed value would reach Postgres as an invalid uuid (a 500) and another cooperative's
 * route id is simply not found.
 */
final class ReportRequest
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public ?string $preset = null;

    public ?string $routeId = null;

    public ?string $notice = null;

    public static function fromRequest(Request $request, CarbonImmutable $now): self
    {
        $preset = self::text($request, 'preset');
        $fromParam = self::text($request, 'from');
        $toParam = self::text($request, 'to');
        $routeParam = self::text($request, 'route');

        $period = ScorecardPolicy::resolvePeriod($preset, $fromParam, $toParam, $now);

        $self = new self;
        $self->from = $period['from'];
        $self->to = $period['to'];
        $self->preset = $preset;
        $self->routeId = self::usableRoute($routeParam);
        $self->notice = self::noticeFor($fromParam, $toParam, $routeParam, $routeParam === null || $self->routeId !== null);

        return $self;
    }

    /**
     * Pure. A sentence when a requested range or route could not be honoured, otherwise null.
     */
    public static function noticeFor(?string $from, ?string $to, ?string $routeParam, bool $routeUsable): ?string
    {
        $notices = [];

        if ($from !== null || $to !== null) {
            $tz = new \DateTimeZone('UTC');
            $start = ScorecardPolicy::parseDay($from, $tz);
            $end = ScorecardPolicy::parseDay($to, $tz);

            if ($start === null || $end === null || $start > $end) {
                $notices[] = 'That date range could not be used, so the last 30 days are shown.';
            }
        }

        if ($routeParam !== null && ! $routeUsable) {
            $notices[] = 'That route is not available, so all routes are shown.';
        }

        return $notices === [] ? null : implode(' ', $notices);
    }

    /** The carry-over for links: the resolved dates and the route, nothing that was ignored. */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'route' => $this->routeId,
        ]);
    }

    /** A query value that is a plain string, or null. `?from[]=x` arrives as an array. */
    private static function text(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function usableRoute(?string $routeId): ?string
    {
        if ($routeId === null || ! Str::isUuid($routeId)) {
            return null;
        }

        return Route::query()->whereKey($routeId)->exists() ? $routeId : null;
    }
}
