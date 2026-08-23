<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard against a test run damaging the production database.
     *
     * This suite has no test database. `phpunit.xml` leaves the DB_CONNECTION override
     * commented out, so the connection falls through to `.env` and points at the SHARED,
     * LIVE Supabase Postgres that the mobile app also writes to. Most tests here are
     * deliberately DB-free, but not all: with the host pointed at a dead socket, eleven
     * currently fail, because the Blade-rendering tests and one org-scope test genuinely
     * read live rows. Reads are tolerable. Nothing else is.
     *
     * Two independent guards, because they catch the danger at different moments.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Blocks migrate:fresh, refresh, reset and db:wipe on every connection.
        //
        // `database/migrations/` holds only Laravel's own scaffolding, none of the Supabase
        // schema, so adding a RefreshDatabase trait to any test file would drop EVERY table
        // in the production schema and then recreate three empty ones. This makes that
        // throw instead of run.
        DB::prohibitDestructiveCommands();

        // 2. Refuses outright to run a database-resetting test against the app's own project.
        //
        // The first guard stops the command; this stops the intent, earlier and with a
        // message that says what to do. It exists because the first guard is a single
        // static call that a future contributor could remove without understanding why it
        // was there.
        $this->refuseDatabaseResetAgainstProduction();
    }

    /**
     * The pooler host is shared by every project in a region, so it cannot identify one.
     * The project ref can: it is the suffix of the connection username
     * (`postgres.<project-ref>`) and it also appears in the Supabase URL the app talks to.
     *
     * So the rule is self-configuring rather than a hardcoded ref that would silently rot:
     * a test that resets the database must not be pointed at the SAME project this
     * application serves. Point `.env.testing` at a separate Supabase project and this
     * stops applying on its own.
     */
    private function refuseDatabaseResetAgainstProduction(): void
    {
        $resetting = array_filter(
            class_uses_recursive(static::class),
            fn (string $t) => str_contains($t, 'RefreshDatabase')
                || str_contains($t, 'DatabaseMigrations')
                || str_contains($t, 'DatabaseTruncation')
        );

        if ($resetting === []) {
            return;
        }

        $username = (string) config('database.connections.pgsql.username');
        $dbRef = str_contains($username, '.') ? substr(strrchr($username, '.'), 1) : '';

        $appUrl = (string) config('supabase.url');
        $appRef = preg_match('~https?://([a-z0-9]+)\.~i', $appUrl, $m) ? $m[1] : '';

        if ($dbRef !== '' && $dbRef === $appRef) {
            throw new RuntimeException(
                static::class.' resets the database, but the connection points at the same '.
                'Supabase project this application serves — the one the mobile app writes to. '.
                'Running it would destroy production data. Create a separate Supabase project '.
                'for tests, apply supabase/migrations/ to it, and point DB_* at it from '.
                '.env.testing.'
            );
        }
    }
}
