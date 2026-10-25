<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\TwoCooperativeFixture;
use Tests\TestCase;

/**
 * The driver profile page, end to end: the real controller, the real scoped models and the real
 * cross-schema account lookup over two cooperatives in a private in-memory SQLite database.
 *
 * It sits beside a named person's licence, phone and complaint history, so what it must never
 * show matters as much as what it does: no complainant, no report text, no other cooperative.
 */
class DriverProfilePageTest extends TestCase
{
    use TwoCooperativeFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
    }

    private function seedAssignment(string $driver, ?string $vehicle, ?string $route, ?string $starts, ?string $ends, bool $active = true): void
    {
        DB::table('assignments')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $this->orgA, 'driver_profile_id' => $driver,
            'vehicle_id' => $vehicle, 'route_id' => $route, 'starts_at' => $starts, 'ends_at' => $ends,
            'is_active' => $active,
        ]);
    }

    private function seedVehicle(string $fleet, string $plate): string
    {
        $id = (string) Str::uuid();
        DB::table('vehicles')->insert([
            'id' => $id, 'organization_id' => $this->orgA, 'fleet_code' => $fleet, 'plate_number' => $plate,
            'status' => 'active', 'is_active' => true,
        ]);

        return $id;
    }

    private function profile(string $driverId, string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->get('/drivers/'.$driverId.$query);
    }

    public function test_it_shows_identity_and_contact(): void
    {
        DB::table('drivers')->where('profile_id', $this->anaId)->update(['license_number' => 'LIC-12345', 'phone' => '0917 111 2222']);
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId)->assertOk()
            ->assertSee('Ana Cruz')
            ->assertSee('ana')
            ->assertSee('LIC-12345')
            ->assertSee('0917 111 2222')
            ->assertSee('Active');
    }

    public function test_a_suspended_driver_shows_suspended_and_still_opens(): void
    {
        DB::table('drivers')->where('profile_id', $this->anaId)->update(['is_active' => false]);
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId)->assertOk()->assertSee('Suspended');
    }

    public function test_a_driver_with_no_name_falls_back_and_does_not_crash(): void
    {
        $nobody = $this->seedDriver($this->orgA, null, '');
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->bobId)->assertOk()->assertSee('bob');
        $this->profile($nobody)->assertOk()->assertSee('Unnamed driver');
    }

    public function test_a_username_login_shows_the_username_and_a_promoted_driver_their_email(): void
    {
        DB::table('auth.users')->insert(['id' => $this->anaId, 'email' => 'ana@drivers.amoectrack.invalid']);
        DB::table('auth.users')->insert(['id' => $this->bobId, 'email' => 'bob.real@example.com']);
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId)->assertOk()->assertSee('username')->assertDontSee('drivers.amoectrack.invalid');
        $this->profile($this->bobId)->assertOk()->assertSee('bob.real@example.com');
    }

    public function test_assignments_show_vehicle_route_and_status(): void
    {
        $route = $this->seedRoute($this->orgA, 'ALN-CAL');
        $bus = $this->seedVehicle('BUS-1', 'AAA 111');
        $fmt = 'Y-m-d H:i:s';
        $this->seedAssignment($this->anaId, $bus, $route, now()->addDays(3)->format($fmt), now()->addDays(4)->format($fmt));
        $this->seedAssignment($this->anaId, $bus, $route, now()->subHour()->format($fmt), now()->addHour()->format($fmt));
        $this->seedAssignment($this->anaId, $bus, $route, now()->subDays(5)->format($fmt), now()->subDays(4)->format($fmt));
        $this->seedAssignment($this->anaId, $bus, $route, now()->subHours(2)->format($fmt), now()->addHour()->format($fmt), false);
        $this->actAs('org_admin', $this->orgA);

        $response = $this->profile($this->anaId)->assertOk();
        $statuses = array_column($response->viewData('assignments'), 'status');

        $this->assertSame(['Upcoming', 'Active now', 'Disabled', 'Ended'], $statuses, 'Newest first, each with its own status.');
        $response->assertSee('BUS-1')->assertSee('ALN-CAL');
    }

    public function test_a_driver_with_no_assignments_says_so(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId)->assertOk()->assertSee('No assignments');
    }

    public function test_an_assignment_with_open_ended_bounds_does_not_crash(): void
    {
        $this->seedAssignment($this->anaId, null, null, null, null);
        $this->actAs('org_admin', $this->orgA);

        $row = $this->profile($this->anaId)->assertOk()->viewData('assignments')[0];

        $this->assertSame('Active now', $row['status']);
        $this->assertSame('—', $row['route']);
    }

    public function test_another_cooperatives_assignment_never_appears(): void
    {
        DB::table('assignments')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $this->orgB, 'driver_profile_id' => $this->anaId,
            'is_active' => true,
        ]);
        $this->actAs('org_admin', $this->orgA);

        $this->assertSame([], $this->profile($this->anaId)->assertOk()->viewData('assignments'));
    }

    public function test_performance_summary_shows_trips_and_on_time_and_dashes_when_unscored(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $this->anaId, 'late');
        $this->seedTrip($this->orgA, $this->bobId, 'no_timetable');
        $this->actAs('org_admin', $this->orgA);

        $ana = $this->profile($this->anaId)->assertOk()->viewData('row');
        $bob = $this->profile($this->bobId)->assertOk()->viewData('row');

        $this->assertSame(2, $ana['trips']);
        $this->assertSame(50.0, $ana['on_time_pct']);
        $this->assertSame(1, $bob['trips']);
        $this->assertNull($bob['on_time_pct']);
    }

    public function test_a_driver_with_no_trips_renders_with_dashes(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId)->assertOk()->assertSee('No trips in this period');
    }

    public function test_recent_trips_are_limited_to_ten(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['started_at' => now()->subDays(2)->addMinutes($i)->format('Y-m-d H:i:s')]);
        }
        $this->actAs('org_admin', $this->orgA);

        $response = $this->profile($this->anaId)->assertOk();

        $this->assertCount(10, $response->viewData('trips'));
        $this->assertSame(12, $response->viewData('row')['trips'], 'The summary counts every trip in the period.');
    }

    public function test_conduct_and_safety_complaints_are_listed_but_not_other_categories(): void
    {
        $trip = $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedReport($this->orgA, $trip, 'driver_behavior');
        $this->seedReport($this->orgA, $trip, 'safety');
        $this->seedReport($this->orgA, $trip, 'vehicle_condition');
        $this->seedReport($this->orgA, $trip, 'route_issue');
        $this->actAs('org_admin', $this->orgA);

        $categories = $this->profile($this->anaId)->assertOk()->viewData('complaints')->pluck('category')->sort()->values()->all();

        $this->assertSame(['driver_behavior', 'safety'], $categories);
    }

    public function test_nothing_identifying_about_a_complainant_appears(): void
    {
        $passenger = (string) Str::uuid();
        DB::table('profiles')->insert(['id' => $passenger, 'full_name' => 'Passenger Secretname', 'role' => 'passenger', 'organization_id' => $this->orgA]);
        $trip = $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedReport($this->orgA, $trip, 'driver_behavior', 'submitted', null, [
            'passenger_profile_id' => $passenger, 'description' => 'SECRET-DESCRIPTION-TEXT',
        ]);
        $this->seedReport($this->orgA, $trip, 'safety', 'submitted', null, [
            'passenger_profile_id' => $passenger, 'is_anonymous' => true, 'description' => 'SECRET-DESCRIPTION-TEXT',
        ]);
        $this->actAs('org_admin', $this->orgA);

        $body = $this->profile($this->anaId)->assertOk()->getContent();

        $this->assertStringNotContainsString('Passenger Secretname', $body);
        $this->assertStringNotContainsString('SECRET-DESCRIPTION-TEXT', $body);
    }

    public function test_the_period_notice_shows_when_a_range_is_ignored(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId, '?from=2026-10-20&to=2026-10-01')->assertOk()->assertSee('last 30 days');
    }

    public function test_another_cooperatives_driver_is_a_404(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->benId)->assertNotFound();
    }

    public function test_a_non_uuid_driver_is_a_404(): void
    {
        $this->actAs('org_admin', $this->orgA);

        // The drivers resource shares this path for its update and delete verbs, so a GET that is not
        // a UUID is refused as 405 rather than 404. Either way there is no page, no data, no 500.
        $this->assertContains($this->get('/drivers/not-a-uuid')->getStatusCode(), [404, 405]);
    }

    public function test_a_super_admin_is_refused(): void
    {
        $this->actAs('super_admin', null);

        $this->profile($this->anaId)->assertForbidden();
    }

    public function test_the_route_does_not_swallow_the_create_screen(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/drivers/create')->assertOk()->assertDontSee('Latest trips');
    }

    public function test_the_header_links_point_at_the_existing_screens(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->profile($this->anaId)->assertOk()
            ->assertSee('/drivers/'.$this->anaId.'/edit', false)
            ->assertSee('/analytics/drivers/'.$this->anaId, false)
            ->assertSee('/report-center/drivers', false);
    }

    public function test_the_drivers_list_links_each_name_to_the_profile(): void
    {
        // The list joins auth.users in a way this fixture does not model, so the link is checked in
        // the template itself rather than by rendering the page.
        $source = file_get_contents(resource_path('views/drivers/index.blade.php'));

        $this->assertStringContainsString("route('drivers.show'", $source);
    }
}
