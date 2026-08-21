<?php

namespace Tests\Unit;

use App\Http\Requests\CreateOrgAdminLoginRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unlike CreateDriverLoginRequestTest, CreateOrgAdminLoginRequest carries no
 * Rule::unique on `username` — there is no local table for it, only the synthetic
 * email the driver-account Edge Function's create_org_admin action builds server
 * side — so its `username`/`email` rules can be pulled straight out of rules() and
 * validated directly, with no DB-free filtering step needed. `organization_id`'s
 * Rule::exists is simply never exercised because it isn't part of the data under
 * test in any of these cases.
 */
class CreateOrgAdminLoginRequestTest extends TestCase
{
    public function test_username_is_trimmed_and_lowercased_before_validation(): void
    {
        $request = new CreateOrgAdminLoginRequest;
        $request->merge(['username' => 'JSantos ']);

        $method = new ReflectionMethod(CreateOrgAdminLoginRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('jsantos', $request->input('username'));
    }

    public function test_a_blank_email_is_normalized_to_null_not_an_empty_string(): void
    {
        $request = new CreateOrgAdminLoginRequest;
        $request->merge(['email' => '   ']);

        $method = new ReflectionMethod(CreateOrgAdminLoginRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertNull($request->input('email'));
    }

    public function test_an_omitted_email_is_also_normalized_to_null(): void
    {
        $request = new CreateOrgAdminLoginRequest;
        $request->merge([]);

        $method = new ReflectionMethod(CreateOrgAdminLoginRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertNull($request->input('email'));
    }

    public function test_a_supplied_email_is_trimmed_and_lowercased(): void
    {
        $request = new CreateOrgAdminLoginRequest;
        $request->merge(['email' => '  Admin@Example.com  ']);

        $method = new ReflectionMethod(CreateOrgAdminLoginRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('admin@example.com', $request->input('email'));
    }

    public function test_a_clean_username_is_accepted(): void
    {
        $this->assertTrue($this->validateUsername('jsantos')->passes());
    }

    #[DataProvider('invalidUsernames')]
    public function test_a_username_that_could_not_be_an_email_local_part_is_rejected(string $username): void
    {
        $this->assertTrue($this->validateUsername($username)->fails());
    }

    public static function invalidUsernames(): array
    {
        return [
            'internal space' => ['js antos'],
            'not a dot-atom character' => ['jsantos!'],
            'leading dot' => ['.jsantos'],
            'consecutive dots' => ['js..antos'],
        ];
    }

    /** Omitted email — the null branch — is valid: `nullable` skips the `email` format rule. */
    public function test_email_omitted_is_valid(): void
    {
        $this->assertTrue($this->validateEmail(null)->passes());
    }

    /** Supplied email must still look like an email. */
    public function test_a_supplied_email_that_is_not_a_valid_email_is_rejected(): void
    {
        $this->assertTrue($this->validateEmail('not-an-email')->fails());
    }

    /** Supplied email, valid shape — accepted. */
    public function test_a_supplied_valid_email_is_accepted(): void
    {
        $this->assertTrue($this->validateEmail('admin@example.com')->passes());
    }

    private function validateUsername(string $username): Validator
    {
        return $this->validatorFactory()->make(
            ['username' => $username],
            ['username' => (new CreateOrgAdminLoginRequest)->rules()['username']],
        );
    }

    private function validateEmail(?string $email): Validator
    {
        return $this->validatorFactory()->make(
            ['email' => $email],
            ['email' => (new CreateOrgAdminLoginRequest)->rules()['email']],
        );
    }

    private function validatorFactory(): ValidatorFactory
    {
        $translator = new Translator(new ArrayLoader, 'en');

        return new ValidatorFactory($translator);
    }
}
