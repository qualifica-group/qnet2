<?php

declare(strict_types=1);

namespace App\Imports\Support;

/**
 * Amount cells of the payroll files: "1.234,56", "1234,56", "1234.56",
 * negatives allowed.
 */
final class ImportNumber
{
    /**
     * The last separator is the decimal one when both appear; a lone comma is
     * decimal; a lone dot is decimal unless it repeats (thousands). Null when
     * the text is not a number.
     */
    public static function parse(string $value): ?float
    {
        $value = str_replace([' ', "\u{00A0}"], '', $value);
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && ($lastDot === false || $lastComma > $lastDot)) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '', $value);
        } elseif ($lastDot !== false && substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 ? (float) $value : null;
    }
}
