<?php

namespace App\Enums;

/**
 * The five repetition units a `task_recurrences` row can carry (spec 0120,
 * D-1; spec 0155, D-1 adds `Yearly`/`Custom`): `weekly`'s `weekdays`,
 * `monthly`/`yearly`'s `month_day`/`month_mode`/`ordinal`/`ordinal_weekday`
 * and `yearly`'s own `year_month` are each valid ONLY for their own case
 * (TaskRecurrenceRules enforces the `required_if`/`prohibited_unless` pair
 * per field). `Custom` is q-net's own "every N days" alias for `Daily` —
 * TaskRecurrenceCalculator treats the two identically, `interval` being the
 * only field either one reads. No RRULE/RFC 5545: the calculation is plain
 * Carbon arithmetic (constraints, CLAUDE.md §0).
 */
enum TaskRecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';
}
