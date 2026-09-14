<?php

namespace App\Enums;

/**
 * How a `task_recurrences` row stops producing occurrences (spec 0120, D-1):
 * `on_date` caps the series at `ends_on`, `after_count` caps it at
 * `occurrence_count` TOTAL occurrences INCLUDING the capostipite (D-4), and
 * `never` never declares the series exhausted.
 */
enum TaskRecurrenceEnd: string
{
    case OnDate = 'on_date';
    case AfterCount = 'after_count';
    case Never = 'never';
}
