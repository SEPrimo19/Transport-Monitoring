<?php

namespace Tests\Feature;

use App\Support\DriverAccountProvisioner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A dropped connection is a DIFFERENT failure than the Edge Function actively
 * rejecting the request: on a provincial link, the likeliest cause of "no response"
 * is the call having succeeded and the response being lost. These pin that
 * {@see DriverAccountProvisioner} tells the two apart (see DriverAccountResult
 * ::$unreachable) and gives copy that matches which one happened, rather than a
 * single generic "please try again" that invites a blind retry.
 */
class DriverAccountProvisionerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);
    }

    public function test_create_reports_unreachable_rather_than_a_generic_failure_on_a_dropped_connection(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = app(DriverAccountProvisioner::class)->create('test-session-token', [
            'organization_id' => 'org-1',
            'full_name' => 'Juan Dela Cruz',
            'employee_code' => 'jsantos',
            'license_number' => 'lic-1',
            'password' => 'a-strong-password',
        ]);

        $this->assertFalse($result->success);
        $this->assertTrue($result->unreachable);
        $this->assertStringContainsString('Check the driver list before trying again', $result->message);
        $this->assertStringContainsString('the account may already have been created', $result->message);
    }

    public function test_create_appends_a_check_the_driver_list_hint_on_a_409(): void
    {
        Http::fake([
            '*/functions/v1/driver-account' => Http::response(['error' => 'That username is already taken.'], 409),
        ]);

        $result = app(DriverAccountProvisioner::class)->create('test-session-token', [
            'organization_id' => 'org-1',
            'full_name' => 'Juan Dela Cruz',
            'employee_code' => 'jsantos',
            'license_number' => 'lic-1',
            'password' => 'a-strong-password',
        ]);

        $this->assertFalse($result->success);
        $this->assertFalse($result->unreachable);
        $this->assertStringContainsString('That username is already taken.', $result->message);
        $this->assertStringContainsString('If you just created this driver, check the driver list', $result->message);
    }

    public function test_reset_password_tells_the_operator_to_retry_with_the_same_password_on_a_dropped_connection(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = app(DriverAccountProvisioner::class)->resetPassword('test-session-token', 'profile-1', 'a-strong-password');

        $this->assertFalse($result->success);
        $this->assertTrue($result->unreachable);
        $this->assertStringContainsString('Retry with the SAME password', $result->message);
    }

    public function test_delete_account_reports_unreachable_on_a_dropped_connection(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = app(DriverAccountProvisioner::class)->deleteAccount('test-session-token', 'profile-1');

        $this->assertFalse($result->success);
        $this->assertTrue($result->unreachable);
        $this->assertStringContainsString('Check the driver list before trying again', $result->message);
    }
}
