<?php

namespace Tests\Concerns;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\AdminContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Two cooperatives in a private in-memory SQLite database, and the helpers to put rows in it.
 *
 * Used by every page test that runs the real controllers, the real scoped models and the real
 * aggregation SQL, and never touches the shared Supabase Postgres. The class using it calls
 * setUpFixture() from setUp() and tearDownFixture() from tearDown().
 */
trait TwoCooperativeFixture
{
    private string $orgA;

    private string $orgB;

    private string $originalConnection;

    private string $anaId;

    private string $bobId;

    private string $benId;

    protected function setUpFixture(): void
    {

        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'qa_sqlite',
            'database.connections.qa_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('qa_sqlite');

        Schema::create('organizations', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('name')->nullable();
            $b->string('primary_color')->nullable();
            $b->string('secondary_color')->nullable();
            $b->string('logo_url')->nullable();
            $b->string('status')->nullable();
            $b->integer('overspeed_limit_kmh')->nullable();
        });
        Schema::create('profiles', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('full_name')->nullable();
            $b->string('role')->nullable();
            $b->string('organization_id')->nullable();
            $b->boolean('is_active')->default(true);
        });
        Schema::create('drivers', function (Blueprint $b) {
            $b->string('profile_id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('employee_code')->nullable();
            $b->boolean('is_active')->default(true);
        });
        Schema::create('routes', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('code')->nullable();
            $b->string('name')->nullable();
            $b->boolean('is_active')->default(true);
        });
        // The layout's notification bell reads recent reports with their vehicle and route, so
        // a full page render needs these even though the scorecard itself does not.
        Schema::create('vehicles', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('plate_number')->nullable();
            $b->string('fleet_code')->nullable();
            $b->string('status')->nullable();
            $b->boolean('is_active')->default(true);
        });
        Schema::create('trip_metrics', function (Blueprint $b) {
            $b->string('trip_id')->primary();
            $b->string('organization_id');
            $b->string('driver_profile_id');
            $b->string('vehicle_id')->nullable();
            $b->string('route_id')->nullable();
            $b->string('started_at');
            $b->string('ended_at')->nullable();
            $b->integer('duration_min')->default(0);
            $b->float('distance_km')->nullable();
            $b->float('avg_moving_speed_kmh')->nullable();
            $b->float('max_speed_kmh')->nullable();
            $b->float('overspeed_share')->nullable();
            $b->integer('overspeed_limit_kmh_used')->nullable();
            $b->string('scheduled_departure')->nullable();
            $b->integer('departure_delta_min')->nullable();
            $b->string('punctuality');
            $b->integer('point_count')->default(0);
            $b->string('data_quality')->default('complete');
            $b->string('finalized_at')->nullable();
            $b->string('computed_at')->nullable();
        });
        Schema::create('reports', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('trip_id')->nullable();
            $b->string('category')->nullable();
            $b->string('status')->nullable();
            $b->string('resolution_outcome')->nullable();
            $b->boolean('is_anonymous')->default(false);
            $b->string('passenger_profile_id')->nullable();
            $b->string('description')->nullable();
            $b->string('vehicle_id')->nullable();
            $b->string('route_id')->nullable();
            $b->string('resolved_at')->nullable();
            $b->string('resolved_by_profile_id')->nullable();
            $b->string('created_at')->nullable();
            $b->string('updated_at')->nullable();
        });

        $this->orgA = (string) Str::uuid();
        $this->orgB = (string) Str::uuid();
        $this->withoutMiddleware(AuthenticateSupabaseAdmin::class);

        DB::table('organizations')->insert([
            ['id' => $this->orgA, 'name' => 'Cooperative A', 'status' => 'active', 'overspeed_limit_kmh' => null],
            ['id' => $this->orgB, 'name' => 'Cooperative B', 'status' => 'active', 'overspeed_limit_kmh' => 60],
        ]);

        $this->anaId = $this->seedDriver($this->orgA, 'Ana Cruz', 'ana');
        $this->bobId = $this->seedDriver($this->orgA, null, 'bob');        // a profile with no name
        $this->benId = $this->seedDriver($this->orgB, 'Ben Reyes', 'ben');
    }

    protected function tearDownFixture(): void
    {
        config(['database.default' => $this->originalConnection]);
        DB::purge('qa_sqlite');
    }

    protected function actAs(string $role, ?string $org): void
    {
        $this->app->make(AdminContext::class)->fill([
            'userId' => (string) Str::uuid(), 'role' => $role, 'organizationId' => $org,
        ]);
    }

    protected function seedDriver(string $org, ?string $name, string $code): string
    {
        $id = (string) Str::uuid();
        DB::table('profiles')->insert(['id' => $id, 'full_name' => $name, 'role' => 'driver', 'organization_id' => $org]);
        DB::table('drivers')->insert(['profile_id' => $id, 'organization_id' => $org, 'employee_code' => $code]);

        return $id;
    }

    protected function seedTrip(string $org, string $driver, string $punctuality, array $extra = []): string
    {
        $id = (string) Str::uuid();
        DB::table('trip_metrics')->insert($extra + [
            'trip_id' => $id, 'organization_id' => $org, 'driver_profile_id' => $driver,
            'started_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'duration_min' => 60, 'distance_km' => 40.0, 'avg_moving_speed_kmh' => 40.0,
            'max_speed_kmh' => 70.0, 'punctuality' => $punctuality, 'computed_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    protected function seedReport(string $org, ?string $trip, string $category, string $status = 'submitted', ?string $outcome = null): void
    {
        DB::table('reports')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $org, 'trip_id' => $trip, 'category' => $category,
            'status' => $status, 'resolution_outcome' => $outcome, 'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function rows(\Illuminate\Testing\TestResponse $response): array
    {
        return $response->viewData('rows');
    }

    protected function seedRoute(string $org, string $code): string
    {
        $id = (string) Str::uuid();
        DB::table('routes')->insert(['id' => $id, 'organization_id' => $org, 'code' => $code, 'name' => "Route $code"]);

        return $id;
    }

    /** @return array<int, array<int, string>> the CSV as rows, header first */
    protected function csv(\Illuminate\Testing\TestResponse $response): array
    {
        $body = $response->streamedContent();
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

        return array_map('str_getcsv', array_filter(explode("\n", str_replace("\r\n", "\n", $body)), 'strlen'));
    }
}
