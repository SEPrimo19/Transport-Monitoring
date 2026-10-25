<?php

namespace Tests\Unit;

use App\Support\CsvReport;
use Tests\TestCase;

/**
 * Every report's CSV goes through this one writer, so the properties a spreadsheet user relies
 * on are pinned here once: the byte order mark that makes Excel read Filipino names, formula
 * safety, and an empty cell (never a zero) for a figure that was not measured.
 */
class CsvReportTest extends TestCase
{
    /** @return array{0: string, 1: array<int, array<int, string|null>>} the raw body and the parsed rows */
    private function csv(array $header, array $rows): array
    {
        $response = CsvReport::stream('x.csv', $header, $rows);

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $text = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        $parsed = array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', ''),
            array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $text)), 'strlen'))
        );

        return [$body, $parsed];
    }

    public function test_it_writes_a_bom_and_the_header_first(): void
    {
        [$body, $rows] = $this->csv(['Driver', 'Trips'], [['Ana', 3]]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertSame(['Driver', 'Trips'], $rows[0]);
        $this->assertSame(['Ana', '3'], $rows[1]);
    }

    public function test_a_formula_looking_cell_is_neutralised(): void
    {
        [, $rows] = $this->csv(['Driver'], [['=SUM(A1)'], ['@evil'], ['-1+1']]);

        $this->assertSame("'=SUM(A1)", $rows[1][0]);
        $this->assertSame("'@evil", $rows[2][0]);
        $this->assertSame("'-1+1", $rows[3][0]);
    }

    public function test_null_is_an_empty_cell_not_zero(): void
    {
        [, $rows] = $this->csv(['A', 'B', 'C'], [['x', null, 0]]);

        $this->assertSame(['x', '', '0'], $rows[1]);
    }

    public function test_a_comma_and_a_quote_survive_a_round_trip(): void
    {
        [, $rows] = $this->csv(['Driver'], [['Cruz, "Ana"']]);

        $this->assertSame('Cruz, "Ana"', $rows[1][0]);
    }

    public function test_the_content_type_and_filename_are_set(): void
    {
        $response = CsvReport::stream('driver-performance.csv', ['A'], []);

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('driver-performance.csv', $response->headers->get('Content-Disposition'));
    }
}
