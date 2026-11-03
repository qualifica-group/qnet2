<?php

namespace App\Imports\RequestManagement;

use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * Reads the date a file cell carries (spec 0348, AC-015). SpreadsheetReader
 * hands real Excel dates over as "Y-m-d H:i:s"; typed text arrives as
 * "d/m/Y" (the app's locale: a slash date is day first) or "Y-m-d"; a bare
 * serial number is an Excel day count. Anything else is unreadable: null,
 * never a guess.
 */
final class ImportDateParser
{
    /** Excel's day 0 (1899-12-30, absorbing the 1900 leap-year bug) as a Unix-day offset. */
    private const string EXCEL_EPOCH = '1899-12-30';

    /** Plausible serial range: 1954 .. 2064, keeps small integers (a phone, a quantity) out. */
    private const int SERIAL_MIN = 20000;

    private const int SERIAL_MAX = 60000;

    private const int MIN_YEAR = 1900;

    private const array TEXT_FORMATS = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y', 'd.m.Y'];

    public function parse(string $raw): ?CarbonImmutable
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return $this->fromSerial((float) $value);
        }

        foreach (self::TEXT_FORMATS as $format) {
            $date = $this->tryFormat($format, $value);

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function fromSerial(float $serial): ?CarbonImmutable
    {
        if ($serial < self::SERIAL_MIN || $serial > self::SERIAL_MAX) {
            return null;
        }

        return CarbonImmutable::parse(self::EXCEL_EPOCH)->addDays((int) floor($serial))->startOfDay();
    }

    private function tryFormat(string $format, string $value): ?CarbonImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $problems = DateTimeImmutable::getLastErrors();

        // A warning is how PHP reports a rolled-over date ("31/02/2026" -> March): not a real date.
        if ($date === false || ($problems !== false && ($problems['warning_count'] > 0 || $problems['error_count'] > 0))) {
            return null;
        }

        // A two-digit year parses as year 26 AD: never a date anyone meant.
        return (int) $date->format('Y') < self::MIN_YEAR ? null : CarbonImmutable::instance($date);
    }
}
