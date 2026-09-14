<?php

namespace App\Enums;

/**
 * The three repetition units a `task_recurrences` row can carry (spec 0120,
 * D-1): `weekly`'s `weekdays` and `monthly`'s `month_day` are each valid
 * ONLY for their own case (StoreTaskRequest/UpdateTaskRequest enforce the
 * `required_if`/`prohibited_unless` pair per field). No RRULE/RFC 5545: the
 * calculation is plain Carbon arithmetic (constraints, CLAUDE.md §0).
 */
enum TaskRecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
