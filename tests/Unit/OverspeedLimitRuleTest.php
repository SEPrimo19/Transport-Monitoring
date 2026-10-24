<?php

namespace Tests\Unit;

use App\Http\Requests\OrgProfileRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * An organization's over-speed limit feeds a number shown beside a NAMED driver, so the
 * setting has to be either a sane limit or absent. It has no default: a legal limit is not
 * something this system knows, and an invented one would turn phone-GPS noise into a mark
 * against someone. Blank must therefore mean "not set" (NULL), never 0.
 *
 * DB-free like CreateDriverLoginRequestTest: OrgProfileRequest::rules() reaches no
 * database, so a hand-built Validator is enough.
 */
class OverspeedLimitRuleTest extends TestCase
{
    #[DataProvider('acceptable')]
    public function test_a_blank_or_sane_limit_is_accepted(mixed $value): void
    {
        $this->assertTrue($this->validate($value)->passes());
    }

    public static function acceptable(): array
    {
        return [
            'not set' => [null],
            'lowest allowed' => [20],
            'highest allowed' => [200],
            'a typical limit as a string' => ['60'],
        ];
    }

    #[DataProvider('rejected')]
    public function test_an_implausible_or_malformed_limit_is_rejected(mixed $value): void
    {
        $this->assertTrue($this->validate($value)->fails());
    }

    public static function rejected(): array
    {
        return [
            'below the floor' => [19],
            'above the ceiling' => [201],
            'not a number' => ['abc'],
            'a fraction' => ['20.5'],
            'negative' => [-60],
        ];
    }

    public function test_a_blank_field_becomes_null_not_zero(): void
    {
        $request = new OrgProfileRequest;
        $request->merge(['overspeed_limit_kmh' => '']);

        $method = new ReflectionMethod(OrgProfileRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertNull($request->input('overspeed_limit_kmh'),
            'A blank limit must mean "not set". 0 would read as a real limit nobody can meet.');
    }

    private function validate(mixed $value): Validator
    {
        $rules = (new OrgProfileRequest)->rules();

        return (new ValidatorFactory(new Translator(new ArrayLoader, 'en')))->make(
            ['overspeed_limit_kmh' => $value],
            ['overspeed_limit_kmh' => $rules['overspeed_limit_kmh']],
        );
    }
}
