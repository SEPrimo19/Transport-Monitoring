<?php

namespace App\Support;

/**
 * Makes one value safe to put in a CSV cell that someone will open in a spreadsheet.
 *
 * A spreadsheet reads a cell that starts with = + - or @ as a formula, and a leading tab or
 * carriage return can be used to smuggle one past a naive check. A driver name is typed by a
 * person, so an exported file could otherwise run something on the machine of whoever opens it.
 * The standard defence is to put a single quote in front, so the cell is read as text.
 *
 * Numbers are returned as they are. A number is not text a person typed, and quoting -1.5 would
 * turn a value into a string. Quoting and escaping of commas and newlines is the CSV writer's
 * job (fputcsv), not this class's.
 */
final class CsvSanitizer
{
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = (string) $value;

        if ($text !== '' && in_array($text[0], self::FORMULA_STARTS, true)) {
            return "'".$text;
        }

        return $text;
    }
}
