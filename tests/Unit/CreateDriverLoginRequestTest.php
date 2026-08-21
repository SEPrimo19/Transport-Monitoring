<?php

namespace Tests\Unit;

use App\Http\Requests\CreateDriverLoginRequest;
use App\Support\AdminContext;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * `employee_code` doubles as an email local-part for an admin-created driver login
 * (`<code>@drivers.amoectrack.invalid`, minted by the `driver-account` Edge Function), so
 * it needs BOTH normalization (trim + lowercase — otherwise "JSantos" and "jsantos"
 * could coexist as visually-duplicate usernames) and a charset restricted to
 * characters that survive being a dot-atom (no leading/trailing dot, no "..").
 *
 * Deliberately DB-free, per the review that asked for this test: CreateDriverLoginRequest
 * ::rules() also carries a Rule::unique(...) for employee_code, which needs a live
 * connection to evaluate — that's not what THIS test is pinning, so it's filtered out
 * before validating (see regexRules()). A minimal Validator\Factory + Container are
 * built by hand instead of booting the framework, so nothing here ever reaches a
 * database or a facade root.
 */
class CreateDriverLoginRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_username_is_trimmed_and_lowercased_before_validation(): void
    {
        $request = new CreateDriverLoginRequest;
        $request->merge(['employee_code' => 'JSantos ']);

        $method = new ReflectionMethod(CreateDriverLoginRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('jsantos', $request->input('employee_code'));
    }

    public function test_a_clean_username_is_accepted(): void
    {
        $this->assertTrue($this->validateEmployeeCode('jsantos')->passes());
    }

    #[DataProvider('invalidUsernames')]
    public function test_a_username_that_could_not_be_an_email_local_part_is_rejected(string $employeeCode): void
    {
        $this->assertTrue($this->validateEmployeeCode($employeeCode)->fails());
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

    private function validateEmployeeCode(string $employeeCode): Validator
    {
        return $this->validatorFactory()->make(
            ['employee_code' => $employeeCode],
            ['employee_code' => $this->employeeCodeRegexRules()],
        );
    }

    /** The employee_code rules straight from the real class, minus the DB-touching Rule::unique. */
    private function employeeCodeRegexRules(): array
    {
        $container = new Container;
        Container::setInstance($container);
        $container->instance(AdminContext::class, new AdminContext);

        $rules = (new CreateDriverLoginRequest)->rules()['employee_code'];

        return array_values(array_filter($rules, fn ($rule) => is_string($rule)));
    }

    private function validatorFactory(): ValidatorFactory
    {
        $translator = new Translator(new ArrayLoader, 'en');

        return new ValidatorFactory($translator);
    }
}
