<?php

namespace Tests\Unit;

use App\Support\DriverAccountEmail;
use App\Support\OrgAdminAccountEmail;
use PHPUnit\Framework\TestCase;

/**
 * Two separate `.invalid` namespaces exist side by side: drivers under
 * DriverAccountEmail::DOMAIN (`drivers.amoectrack.invalid`), org admins under
 * OrgAdminAccountEmail::DOMAIN (`admins.amoectrack.invalid`). They must never
 * collide or be mistaken for each other by a suffix check — see both classes'
 * docblocks. This pins that requirement directly rather than trusting the two
 * literals were typed correctly by eye.
 *
 * The mobile app's own LoginIdentifierPolicy.kt (Android repo:
 * app/src/main/java/com/example/amoectrack/core/common/LoginIdentifierPolicy.kt)
 * hardcodes the SAME driver-domain literal independently (`DRIVER_EMAIL_DOMAIN =
 * "drivers.amoectrack.invalid"`), confirmed by manual reading since a Kotlin file
 * cannot be exercised from PHPUnit — pinned here as a literal so this test fails
 * loudly if DriverAccountEmail::DOMAIN is ever renamed without updating the mobile
 * side to match.
 */
class AdminCreatedLoginDomainNamespaceTest extends TestCase
{
    /** Hand-verified against LoginIdentifierPolicy.kt's DRIVER_EMAIL_DOMAIN constant. */
    private const ANDROID_DRIVER_EMAIL_DOMAIN = 'drivers.amoectrack.invalid';

    public function test_the_driver_and_admin_domains_are_different_subdomains(): void
    {
        $this->assertNotSame(DriverAccountEmail::DOMAIN, OrgAdminAccountEmail::DOMAIN);
    }

    public function test_neither_domain_is_a_suffix_of_the_other(): void
    {
        // Guards the actual collision a suffix (str_ends_with) check could fall for:
        // if one domain were e.g. "admins.drivers.amoectrack.invalid", a driver
        // address would also satisfy an admin suffix check and vice versa.
        $this->assertFalse(str_ends_with(DriverAccountEmail::DOMAIN, '.'.OrgAdminAccountEmail::DOMAIN));
        $this->assertFalse(str_ends_with(OrgAdminAccountEmail::DOMAIN, '.'.DriverAccountEmail::DOMAIN));
    }

    public function test_a_driver_domain_address_is_not_recognized_as_an_org_admin_login(): void
    {
        $this->assertFalse(OrgAdminAccountEmail::isUsernameLogin('jsantos@'.DriverAccountEmail::DOMAIN));
    }

    public function test_an_org_admin_domain_address_is_not_recognized_as_a_driver_login(): void
    {
        $this->assertFalse(DriverAccountEmail::isUsernameLogin('jsantos@'.OrgAdminAccountEmail::DOMAIN));
    }

    public function test_both_domains_are_under_the_reserved_invalid_tld(): void
    {
        // RFC 6761 §6.4: unregistrable, so no one can ever stand up a mail server for
        // either and intercept a recovery email for a login created without one.
        $this->assertStringEndsWith('.invalid', DriverAccountEmail::DOMAIN);
        $this->assertStringEndsWith('.invalid', OrgAdminAccountEmail::DOMAIN);
    }

    public function test_the_web_admins_driver_domain_matches_the_mobile_apps_own_constant(): void
    {
        $this->assertSame(self::ANDROID_DRIVER_EMAIL_DOMAIN, DriverAccountEmail::DOMAIN);
    }
}
