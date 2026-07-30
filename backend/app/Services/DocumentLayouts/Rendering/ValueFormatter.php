<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\VatRate;
use Illuminate\Support\Carbon;

/**
 * The ONE shared value-formatting implementation spec 0070's
 * rendering_contract demands ("una sola implementazione, condivisa da
 * variabili e tabella"): every variable resolved by VariableResolver and
 * every products_table cell goes through this class, never a bespoke
 * `number_format()` call elsewhere.
 *
 * Contract (frozen): amounts -> 2 decimals, comma decimal separator, dot
 * thousands separator, no currency symbol; quantities -> up to 2 decimals,
 * no useless trailing zeros; VAT rate -> vatRate.name, fallback rate + '%',
 * empty string when null; dates -> d/m/Y; date-times -> d/m/Y H:i; a missing
 * or masked value is ALWAYS an empty string — never "null", "-", or a raw
 * token.
 */
final class ValueFormatter
{
    private function __construct() {}

    /**
     * A currency amount: 2 decimals, ',' decimal separator, '.' thousands
     * separator, no symbol. Empty string for null (never "0,00").
     */
    public static function currency(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    /**
     * A plain quantity: up to 2 decimals, trailing zeros (and a trailing
     * decimal separator) stripped, ',' decimal separator when a fraction
     * remains. Empty string for null.
     */
    public static function quantity(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = number_format((float) $value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return str_replace('.', ',', $formatted);
    }

    /**
     * A VAT rate: the named rate's own label when present, otherwise its
     * numeric percentage; empty string when there is no rate at all.
     */
    public static function vatRate(?VatRate $vatRate): string
    {
        if ($vatRate === null) {
            return '';
        }

        if ($vatRate->name !== null && $vatRate->name !== '') {
            return $vatRate->name;
        }

        return self::quantity($vatRate->rate).'%';
    }

    /**
     * A date, formatted `d/m/Y`. Empty string for null/unparseable input.
     */
    public static function date(Carbon|string|null $value): string
    {
        $date = self::toCarbon($value);

        return $date?->format(RenderingUnits::DATE_FORMAT) ?? '';
    }

    /**
     * A date+time, formatted `d/m/Y H:i`. Empty string for null/unparseable input.
     */
    public static function dateTime(Carbon|string|null $value): string
    {
        $date = self::toCarbon($value);

        return $date?->format(RenderingUnits::DATE_TIME_FORMAT) ?? '';
    }

    /**
     * A plain text value: trimmed, never null (empty string instead) — the
     * catch-all for every string-typed variable/column that is not a
     * currency/quantity/date.
     */
    public static function text(?string $value): string
    {
        return $value === null ? '' : trim($value);
    }

    private static function toCarbon(Carbon|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
