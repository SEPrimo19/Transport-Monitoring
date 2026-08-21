<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard against a test run destroying the production database.
     *
     * This suite has no test database. `phpunit.xml` leaves the DB_CONNECTION override
     * commented out, so the connection falls through to `.env` and points at the SHARED,
     * LIVE Supabase Postgres that the mobile app also writes to. Most tests here are
     * deliberately DB-free, but not all: with the host pointed at a dead socket, eleven
     * currently fail, because the Blade-rendering tests and one org-scope test genuinely
     * read live rows.
     *
     * The catastrophic path is one line away. `database/migrations/` holds only Laravel's
     * own scaffolding, none of the Supabase schema, so adding `use RefreshDatabase;` to any
     * test file would run `migrate:fresh` and drop EVERY table in the production schema
     * before recreating three empty ones.
     *
     * prohibitDestructiveCommands() makes that throw instead of running. It is not a substitute
     * for a real test database, which is still the right long-term fix; it is the smallest
     * change that removes the irreversible outcome today.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::prohibitDestructiveCommands();
    }
}
