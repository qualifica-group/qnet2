<?php

declare(strict_types=1);

namespace App\Exports\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryPeriod;
use Carbon\CarbonImmutable;

/**
 * Italian text formatting shared by the two xlsx exports (spec 0122, D-12,
 * MT-B5). Month/weekday names are a hardcoded map rather than Carbon's own
 * `isoFormat` locale: `config('app.locale')` defaults to `en`
 * (config/app.php) and nothing else in this codebase calls
 * `Carbon::setLocale()` — the same reasoning `WorkCalendar` already applies
 * to the Italian holiday dates of D-7. This is document CONTENT (team-lead
 * brief), not UI copy, so it is not routed through `lang/it.json` either.
 */
final class TimeEntryExportFormatter
{
    private const string MISSING_VALUE = '-';

    /**
     * @var array<int, string>
     */
    private const array MONTH_NAMES = [
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
        5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
        9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
    ];

    /**
     * @var array<int, string>
     */
    private const array WEEKDAY_NAMES = [
        1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì',
        5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica',
    ];

    /**
     * The filtered export's period label (spec 0122, data_contract GET
     * exports/filtered): "Settembre 2026" for a full calendar month,
     * "Settimana del d/m/Y" for a full ISO week (Monday..Sunday), "Lunedì
     * 14/09/2026" for a single day, else "Periodo dal d/m/Y al d/m/Y".
     *
     * Classified from the RESOLVED date range rather than
     * `TimeEntryFilterData::periodPreset`: the frontend always submits
     * explicit `date_from`/`date_to` (AC-032), so the preset itself is not a
     * reliable signal by the time this runs — a range that merely happens to
     * cover a full week/month reads as that label regardless of how it was
     * produced, mirroring what the legacy report showed for the equivalent
     * shape.
     */
    public function periodLabel(TimeEntryPeriod $period): string
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $period->dateFrom);
        $to = CarbonImmutable::createFromFormat('Y-m-d', $period->dateTo);

        if ($from->format('Y-m-d') === $to->format('Y-m-d')) {
            return self::WEEKDAY_NAMES[$from->dayOfWeekIso].' '.$from->format('d/m/Y');
        }

        if ($from->dayOfWeekIso === 1 && $to->dayOfWeekIso === 7 && $from->diffInDays($to) === 6) {
            return 'Settimana del '.$from->format('d/m/Y');
        }

        if ($from->day === 1 && $to->format('Y-m-d') === $from->endOfMonth()->format('Y-m-d')) {
            return $this->monthYearLabel((int) $from->format('n'), (int) $from->format('Y'));
        }

        return 'Periodo dal '.$from->format('d/m/Y').' al '.$to->format('d/m/Y');
    }

    public function monthYearLabel(int $month, int $year): string
    {
        return self::MONTH_NAMES[$month].' '.$year;
    }

    public function minutesToHhMm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function minutesToHourMinuteLabel(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'min';
    }

    /**
     * Two decimals, trailing zeros trimmed (`50.00` -> `50%`, `33.33`
     * stays), mirroring qnet's own `WorkingActivityExport` percentage cells.
     */
    public function percentageLabel(float $percentage): string
    {
        return rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.').'%';
    }

    public function timeLabel(?string $time): string
    {
        return $time === null || $time === '' ? self::MISSING_VALUE : substr($time, 0, 5);
    }

    public function textOrPlaceholder(?string $value): string
    {
        return $value === null || $value === '' ? self::MISSING_VALUE : $value;
    }
}
