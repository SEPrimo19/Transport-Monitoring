<?php

namespace Tests\Unit;

use App\Http\Requests\OrgProfileRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * The departure-enforcement toggle, and specifically what happens when it is absent.
 *
 * An unchecked HTML checkbox is not submitted at all. So the interesting case is not
 * "false was sent", it is "nothing was sent", and the request has to read that as OFF
 * rather than leaving the previous value in place. Getting this wrong would make the
 * toggle one-way: an admin could switch enforcement on and never switch it back from the
 * settings page, and the only symptom would be drivers being refused at the terminal for
 * a setting the admin believes they cleared.
 *
 * Off is also the safe resting state. A blocked departure means a vehicle carrying
 * passengers that nobody can see on the map, which is worse than a trip recorded off
 * schedule.
 */
class DepartureWindowSettingTest extends TestCase
{
    private function normalized(array $payload): array
    {
        $request = OrgProfileRequest::createFrom(Request::create('/organization', 'PUT', $payload));

        // prepareForValidation() is protected; it is the method under test.
        $method = new \ReflectionMethod(OrgProfileRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        return $request->all();
    }

    public function test_an_absent_checkbox_means_enforcement_is_off(): void
    {
        // The exact payload a browser sends when the admin clears the box.
        $data = $this->normalized(['timezone' => 'Asia/Manila', 'departure_grace_minutes' => 45]);

        $this->assertFalse($data['enforce_departure_window']);
    }

    public function test_a_checked_box_turns_enforcement_on(): void
    {
        $data = $this->normalized([
            'timezone' => 'Asia/Manila',
            'departure_grace_minutes' => 45,
            'enforce_departure_window' => '1',
        ]);

        $this->assertTrue($data['enforce_departure_window']);
    }

    public function test_the_value_is_always_a_boolean(): void
    {
        // Guards the column type. `enforce_departure_window` is NOT NULL boolean, so a
        // stray "on" or "0" string reaching the database would be a 500 at save time.
        foreach (['1', 'on', 'true', true] as $truthy) {
            $data = $this->normalized([
                'timezone' => 'Asia/Manila',
                'departure_grace_minutes' => 45,
                'enforce_departure_window' => $truthy,
            ]);
            $this->assertIsBool($data['enforce_departure_window']);
            $this->assertTrue($data['enforce_departure_window'], "value {$truthy} should read as on");
        }

        foreach (['0', 'false', '', false] as $falsy) {
            $data = $this->normalized([
                'timezone' => 'Asia/Manila',
                'departure_grace_minutes' => 45,
                'enforce_departure_window' => $falsy,
            ]);
            $this->assertIsBool($data['enforce_departure_window']);
            $this->assertFalse($data['enforce_departure_window']);
        }
    }

    public function test_the_grace_period_is_untouched_by_the_toggle(): void
    {
        // The two settings are independent: turning enforcement off must not quietly
        // reset the grace band the cooperative configured.
        $data = $this->normalized(['timezone' => 'Asia/Manila', 'departure_grace_minutes' => 45]);

        $this->assertSame(45, (int) $data['departure_grace_minutes']);
    }
}
