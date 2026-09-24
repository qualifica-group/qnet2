<?php

namespace App\Enums;

/**
 * How a `monthly`/`yearly` `task_recurrences` row picks its day within the
 * target month (spec 0155, D-1): `fixed` uses `month_day`, clamped to the
 * month's own length — the ONLY mode that existed before this spec, and the
 * one `null` still means (TaskRecurrenceCalculator's own docblock). `ordinal`
 * uses `ordinal` (1..5) + `ordinal_weekday` (1..7, ISO) instead — "the 2nd
 * Tuesday" — and produces NO occurrence for that period when the month has
 * no such Nth weekday (AC-001: ordinal 5 in a short month is skipped, never
 * moved to another day).
 */
enum TaskRecurrenceMonthMode: string
{
    case Fixed = 'fixed';
    case Ordinal = 'ordinal';
}
