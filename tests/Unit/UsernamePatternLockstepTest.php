<?php

namespace Tests\Unit;

use App\Http\Requests\CreateDriverLoginRequest;
use App\Http\Requests\CreateOrgAdminLoginRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Support\AdminContext;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Three username/employee_code fields double as an email local-part for an
 * admin-created login and are documented as being kept in lockstep with each
 * other AND with the `driver-account` Edge Function's EMPLOYEE_CODE_PATTERN
 * (Android repo: supabase/functions/driver-account/index.ts):
 *
 *   - CreateDriverLoginRequest::rules()['employee_code']
 *   - UpdateDriverRequest::rules()['employee_code']
 *   - CreateOrgAdminLoginRequest::rules()['username']
 *
 * "Mirrored" is a claim a comment can get wrong quietly — a copy-paste that drifts
 * by one character (e.g. one file allows a leading dot the others don't) would mean
 * the form accepts what the Edge Function rejects, surfacing as a confusing 400
 * after the admin already filled in everything else. This test compares the actual
 * regex STRINGS extracted from all three classes, not hand-copied literals that
 * could independently be typed wrong the same way.
 *
 * The Edge Function itself is TypeScript/Deno and out of reach for PHPUnit; its
 * EMPLOYEE_CODE_PATTERN was cross-checked by manual reading against the pattern
 * pinned here (as of this test: `^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$`, plus a
 * separate no-consecutive-dots check) — see the QA notes for this feature.
 *
 * DB-free: only the DB-touching Rule::unique/Rule::in objects are filtered out
 * (same technique as the existing CreateDriverLoginRequestTest); UpdateDriverRequest
 * is exercised unbound (no {driver} route parameter), which its own isUsernameLogin()
 * already handles by short-circuiting before any query — see its source.
 */
class UsernamePatternLockstepTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_create_driver_login_and_create_org_admin_login_use_the_identical_regex_rules(): void
    {
        $this->assertSame(
            $this->regexRulesFor(CreateDriverLoginRequest::class, 'employee_code'),
            $this->regexRulesFor(CreateOrgAdminLoginRequest::class, 'username'),
            'CreateDriverLoginRequest::employee_code and CreateOrgAdminLoginRequest::username '.
            'must carry the exact same regex rules — a divergence means the org-admin creation '.
            'form accepts a username shape the driver creation form (or the Edge Function, which '.
            'reuses employee_code\'s pattern for both fields) would reject, or vice versa.'
        );
    }

    public function test_update_driver_and_create_org_admin_login_use_the_identical_regex_rules(): void
    {
        $this->assertSame(
            $this->regexRulesFor(UpdateDriverRequest::class, 'employee_code'),
            $this->regexRulesFor(CreateOrgAdminLoginRequest::class, 'username'),
            'UpdateDriverRequest::employee_code and CreateOrgAdminLoginRequest::username '.
            'must carry the exact same regex rules.'
        );
    }

    public function test_create_driver_login_and_update_driver_use_the_identical_regex_rules(): void
    {
        $this->assertSame(
            $this->regexRulesFor(CreateDriverLoginRequest::class, 'employee_code'),
            $this->regexRulesFor(UpdateDriverRequest::class, 'employee_code'),
            'CreateDriverLoginRequest and UpdateDriverRequest must carry the exact same '.
            'regex rules for employee_code — editing a driver must not accept a shape that '.
            'creating one would have rejected.'
        );
    }

    /** @return list<string> only the string (regex) rules for the given field, in order. */
    private function regexRulesFor(string $requestClass, string $field): array
    {
        $container = new Container;
        Container::setInstance($container);
        $container->instance(AdminContext::class, new AdminContext);

        $request = new $requestClass;

        $rules = $request->rules()[$field];

        return array_values(array_filter(
            $rules,
            fn ($rule) => is_string($rule) && str_starts_with($rule, 'regex:')
        ));
    }
}
