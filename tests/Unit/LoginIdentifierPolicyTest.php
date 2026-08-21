<?php

namespace Tests\Unit;

use App\Support\LoginIdentifierPolicy;
use App\Support\OrgAdminAccountEmail;
use PHPUnit\Framework\TestCase;

/**
 * Pins {@see LoginIdentifierPolicy::resolveAuthEmail()} — the web-admin sibling of
 * the mobile app's LoginIdentifierPolicy.kt, resolving the ADMIN `.invalid` domain
 * instead of the driver one. Pure function, no framework/DB needed.
 */
class LoginIdentifierPolicyTest extends TestCase
{
    public function test_a_plain_username_gets_the_admin_domain_appended(): void
    {
        $this->assertSame(
            'jdoe@'.OrgAdminAccountEmail::DOMAIN,
            LoginIdentifierPolicy::resolveAuthEmail('jdoe')
        );
    }

    public function test_an_address_that_already_contains_an_at_sign_is_passed_through_unchanged(): void
    {
        $this->assertSame('admin@example.com', LoginIdentifierPolicy::resolveAuthEmail('admin@example.com'));
    }

    public function test_surrounding_whitespace_is_trimmed_before_the_domain_is_appended(): void
    {
        $this->assertSame(
            'jdoe@'.OrgAdminAccountEmail::DOMAIN,
            LoginIdentifierPolicy::resolveAuthEmail('  jdoe  ')
        );
    }

    public function test_surrounding_whitespace_is_trimmed_from_a_real_email_too(): void
    {
        $this->assertSame('admin@example.com', LoginIdentifierPolicy::resolveAuthEmail('  admin@example.com  '));
    }

    public function test_an_uppercase_username_is_lowercased_before_the_domain_is_appended(): void
    {
        $this->assertSame(
            'jdoe@'.OrgAdminAccountEmail::DOMAIN,
            LoginIdentifierPolicy::resolveAuthEmail('JDoe')
        );
    }

    public function test_a_real_emails_case_is_left_alone(): void
    {
        // A real email's case is the account's own business, unlike an
        // admin-domain address, which the Edge Function always mints lowercase.
        $this->assertSame('Admin@Example.com', LoginIdentifierPolicy::resolveAuthEmail('Admin@Example.com'));
    }

    public function test_an_admin_domain_address_typed_in_mixed_case_is_still_lowercased(): void
    {
        $domain = OrgAdminAccountEmail::DOMAIN;

        $this->assertSame(
            'jdoe@'.$domain,
            LoginIdentifierPolicy::resolveAuthEmail('JDoe@'.strtoupper($domain))
        );
    }

    public function test_an_empty_string_falls_through_the_username_branch(): void
    {
        // No "@", so this documents the fall-through behavior rather than asserting
        // it's meaningful — CreateOrgAdminLoginRequest/AuthController's own
        // `required` rule rejects an empty identifier before this is ever reached.
        $this->assertSame('@'.OrgAdminAccountEmail::DOMAIN, LoginIdentifierPolicy::resolveAuthEmail(''));
    }
}
