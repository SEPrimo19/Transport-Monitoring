<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one writer behind every report's CSV download.
 *
 * Cells are raw values; each goes through CsvSanitizer (formula safety, null as an empty cell,
 * never 0). A UTF-8 byte order mark comes first because that is what makes Excel read names
 * with accents correctly. Quoting of commas and newlines is fputcsv's job.
 */
final class CsvReport
{
    /**
     * @param  array<int, mixed>  $header
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function stream(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(static function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            // escape: '' is explicit because PHP's default escape character is deprecated.
            fputcsv($out, array_map([CsvSanitizer::class, 'cell'], $header), ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map([CsvSanitizer::class, 'cell'], $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
