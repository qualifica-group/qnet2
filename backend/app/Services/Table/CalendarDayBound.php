<?php

declare(strict_types=1);

namespace App\Services\Table;

use Carbon\CarbonImmutable;

/**
 * A date filter bound read as a whole calendar day (ticket #428096). The grid
 * and the advanced filters send a bare day ("2026-11-13", or AG Grid's
 * "2026-11-13 00:00:00"); compared as-is against a DATETIME column it means
 * midnight, so "up to the 13th" dropped every callback of the 13th that has
 * an hour. Callers compare `>= day` for a lower bound and `< dayAfter(day)`
 * for an upper one, which is the same as before on a DATE column.
 */
final class CalendarDayBound
{
    private const string DAY_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})/';

    /** The `Y-m-d` day a bound starts with, or null when it does not start with a valid date. */
    public static function day(mixed $bound): ?string
    {
        if (! is_string($bound) || preg_match(self::DAY_PATTERN, $bound, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            ? "{$parts[1]}-{$parts[2]}-{$parts[3]}"
            : null;
    }

    /** The day after a `Y-m-d` day, the exclusive upper bound of that whole day. */
    public static function dayAfter(string $day): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $day)->addDay()->format('Y-m-d');
    }
}
