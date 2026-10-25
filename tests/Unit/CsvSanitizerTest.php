<?php

namespace Tests\Unit;

use App\Support\CsvSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A spreadsheet treats a cell that starts with = + - or @ as a formula. A driver name, or any
 * free text that reaches an export, is typed by a person, so an exported file could otherwise
 * run something on the machine of whoever opens it. The fix is the standard one: neutralise the
 * leading character so the cell is read as text.
 */
class CsvSanitizerTest extends TestCase
{
    #[DataProvider('formulaStarts')]
    public function test_a_cell_that_could_be_a_formula_is_made_into_text(string $cell): void
    {
        $this->assertSame("'".$cell, CsvSanitizer::cell($cell));
    }

    public static function formulaStarts(): array
    {
        return [
            'equals' => ['=HYPERLINK("http://evil","click")'],
            'plus' => ['+1+1'],
            'minus' => ['-2+3'],
            'at' => ['@SUM(A1:A9)'],
            'a leading tab' => ["\t=1+1"],
            'a leading carriage return' => ["\r=1+1"],
        ];
    }

    public function test_ordinary_text_is_left_alone(): void
    {
        $this->assertSame('Ana Cruz', CsvSanitizer::cell('Ana Cruz'));
        $this->assertSame("Dela Cruz, Juan", CsvSanitizer::cell("Dela Cruz, Juan"));
        $this->assertSame('a=b', CsvSanitizer::cell('a=b'), 'Only a LEADING character matters.');
        $this->assertSame('', CsvSanitizer::cell(''));
    }

    public function test_null_becomes_an_empty_cell_not_the_word_null(): void
    {
        $this->assertSame('', CsvSanitizer::cell(null));
    }

    public function test_real_numbers_are_not_mangled_even_when_negative(): void
    {
        // A number is not text a person typed, and a quote in front of -1.5 would turn a value
        // into a string in the spreadsheet.
        $this->assertSame('12.5', CsvSanitizer::cell(12.5));
        $this->assertSame('3', CsvSanitizer::cell(3));
        $this->assertSame('-1.5', CsvSanitizer::cell(-1.5));
        $this->assertSame('0', CsvSanitizer::cell(0));
    }
}
