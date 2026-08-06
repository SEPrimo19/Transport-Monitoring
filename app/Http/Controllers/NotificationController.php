<?php

namespace App\Http\Controllers;

use App\Support\PendingReportFeed;
use Illuminate\Http\JsonResponse;

/**
 * Feeds the header notification bell after first paint.
 *
 * The bell is rendered server-side by a view composer, which means it is only ever as
 * fresh as the last full page load — an admin sitting on the Live Ops map would not
 * see a report that came in two minutes ago until they navigated. This endpoint is
 * what the layout polls to keep it current without a refresh.
 *
 * Polling, not websockets, on purpose: the shared Supabase `supabase_realtime`
 * publication carries only `vehicle_live_location` (Android migration
 * 20260430_008), so a browser Realtime subscription on `reports` would connect to
 * nothing. Adding `reports` to that publication is a schema change owned by the
 * Android repo's migrations — and reports arrive on human timescales, where a short
 * poll is indistinguishable from live.
 *
 * Kept to a SINGLE query (see PendingReportFeed) because every tab runs this on a
 * timer, and `php artisan serve` handles one request at a time — a chatty poll here
 * blocks the page the admin is actually trying to open.
 *
 * Auth/scoping come from the `supabase.admin` middleware group this route sits in;
 * there is no org filter here by design (see PendingReportFeed).
 */
class NotificationController extends Controller
{
    public function pending(PendingReportFeed $feed): JsonResponse
    {
        return response()->json($feed->payload())
            // A cached bell is a stale bell — and 304s here would defeat the poll.
            ->header('Cache-Control', 'no-store, private');
    }
}
