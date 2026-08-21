<?php

namespace Tests\Feature;

use App\Support\OrgAdminAccountProvisioner;
use App\Support\SupabaseAccountCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Org-admin sibling of DriverAccountProvisionerTest. Pins that
 * {@see OrgAdminAccountProvisioner} tells a dropped connection apart from a
 * confirmed rejection (see DriverAccountResult::$unreachable) via the SAME shared
 * HTTP plumbing ({@see SupabaseAccountCall}) the driver provisioner
 * uses — a regression here would mean the two silently drifted.
 */
class OrgAdminAccountProvisionerTest extends TestCase
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

        $result = app(OrgAdminAccountProvisioner::class)->create('test-session-token', [
            'organization_id' => 'org-1',
            'full_name' => 'Juan Dela Cruz',
            'username' => 'jsantos',
            'password' => 'a-strong-password',
            'email' => null,
        ]);

        $this->assertFalse($result->success);
        $this->assertTrue($result->unreachable);
        $this->assertStringContainsString('Check the org admin list before trying again', $result->message);
    }

    public function test_create_appends_a_check_the_org_admin_list_hint_on_a_409(): void
    {
        Http::fake([
            '*/functions/v1/driver-account' => Http::response(['error' => 'That username is already taken.'], 409),
        ]);

        $result = app(OrgAdminAccountProvisioner::class)->create('test-session-token', [
            'organization_id' => 'org-1',
            'full_name' => 'Juan Dela Cruz',
            'username' => 'jsantos',
            'password' => 'a-strong-password',
            'email' => null,
        ]);

        $this->assertFalse($result->success);
        $this->assertFalse($result->unreachable);
        $this->assertStringContainsString('That username is already taken.', $result->message);
        $this->assertStringContainsString('If you just created this admin, check the org admin list', $result->message);
    }

    public function test_create_sends_the_create_org_admin_action_with_the_payload_as_given(): void
    {
        Http::fake([
            '*/functions/v1/driver-account' => Http::response([
                'profile_id' => 'profile-1',
                'email' => 'jsantos@admins.amoectrack.invalid',
            ], 200),
        ]);

        $result = app(OrgAdminAccountProvisioner::class)->create('test-session-token', [
            'organization_id' => 'org-1',
            'full_name' => 'Juan Dela Cruz',
            'username' => 'jsantos',
            'password' => 'a-strong-password',
            'email' => null,
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('profile-1', $result->profileId);
        $this->assertSame('jsantos@admins.amoectrack.invalid', $result->email);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['action'] ?? null) === 'create_org_admin'
                && ($body['username'] ?? null) === 'jsantos'
                && array_key_exists('email', $body)
                && $body['email'] === null;
        });
    }

    public function test_reset_password_tells_the_operator_to_retry_with_the_same_password_on_a_dropped_connection(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = app(OrgAdminAccountProvisioner::class)->resetPassword('test-session-token', 'profile-1', 'a-strong-password');

        $this->assertFalse($result->success);
        $this->assertTrue($result->unreachable);
        $this->assertStringContainsString('Retry with the SAME password', $result->message);
    }

    public function test_reset_password_sends_the_reset_org_admin_password_action(): void
    {
        Http::fake([
            '*/functions/v1/driver-account' => Http::response(['ok' => true], 200),
        ]);

        $result = app(OrgAdminAccountProvisioner::class)->resetPassword('test-session-token', 'profile-1', 'a-strong-password');

        $this->assertTrue($result->success);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['action'] ?? null) === 'reset_org_admin_password'
                && ($body['profile_id'] ?? null) === 'profile-1';
        });
    }
}
