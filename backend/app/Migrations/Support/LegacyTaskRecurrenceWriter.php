<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use App\Models\Task;
use App\Models\TaskRecurrence;

/**
 * Resolves the recurrence series of an imported task (spec 0289, D-10). The
 * legacy sends the rule already in qnet-2's vocabulary on the series origin
 * (`recurrence`) and the origin's id on each generated occurrence
 * (`recurrence_source_id`); this class only enforces what qnet-2 owns: a rule
 * outside the enums or an origin without `end_date` (the scheduler anchors on
 * it) makes no series, and an occurrence joins the origin's series unless
 * that would repeat an `end_date` (UNIQUE(task_recurrence_id, end_date)) -
 * in every refused case the task is imported without a series, with a warning.
 */
final class LegacyTaskRecurrenceWriter
{
    /**
     * The `task_recurrence_id` the task must carry, creating the series row
     * when the record is an origin. An origin's own rule wins over a source id.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    public function resolveSeriesId(array $record, ?string $endDate, array &$warnings): ?int
    {
        $rule = $record['recurrence'] ?? null;

        if (is_array($rule)) {
            return $this->createSeries($rule, $endDate, $warnings);
        }

        $sourceId = $record['recurrence_source_id'] ?? null;

        if ($sourceId === null || $sourceId === '') {
            return null;
        }

        return $this->joinSeries($sourceId, $endDate, $warnings);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<int, string>  $warnings
     */
    private function createSeries(array $rule, ?string $endDate, array &$warnings): ?int
    {
        if ($endDate === null) {
            $warnings[] = 'Recurrence not imported: the series origin has no end date.';

            return null;
        }

        $frequency = TaskRecurrenceFrequency::tryFrom((string) ($rule['frequency'] ?? ''));
        $ends = TaskRecurrenceEnd::tryFrom((string) ($rule['ends'] ?? ''));
        $monthMode = ($rule['month_mode'] ?? null) === null ? null : TaskRecurrenceMonthMode::tryFrom((string) $rule['month_mode']);

        if ($frequency === null || $ends === null || (($rule['month_mode'] ?? null) !== null && $monthMode === null)) {
            $warnings[] = 'Recurrence not imported: frequency, month mode or end type outside the supported values.';

            return null;
        }

        $recurrence = TaskRecurrence::query()->create([
            'frequency' => $frequency,
            'interval' => (int) ($rule['interval'] ?? 1),
            'weekdays' => isset($rule['weekdays']) ? array_values(array_map(intval(...), (array) $rule['weekdays'])) : null,
            'month_day' => isset($rule['month_day']) ? (int) $rule['month_day'] : null,
            'month_mode' => $monthMode,
            'ordinal' => isset($rule['ordinal']) ? (int) $rule['ordinal'] : null,
            'ordinal_weekday' => isset($rule['ordinal_weekday']) ? (int) $rule['ordinal_weekday'] : null,
            'year_month' => isset($rule['year_month']) ? (int) $rule['year_month'] : null,
            'workdays_only' => (bool) ($rule['workdays_only'] ?? false),
            'ends' => $ends,
            'ends_on' => $rule['ends_on'] ?? null,
            'occurrence_count' => isset($rule['occurrence_count']) ? (int) $rule['occurrence_count'] : null,
            'generated_until' => $rule['generated_until'] ?? null,
        ]);

        return $recurrence->id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function joinSeries(int|string $sourceId, ?string $endDate, array &$warnings): ?int
    {
        $seriesId = Task::query()->where('old_id', $sourceId)->value('task_recurrence_id');

        if ($seriesId === null) {
            $warnings[] = "Occurrence imported without series: its origin (legacy id {$sourceId}) has no series in qnet-2.";

            return null;
        }

        if ($endDate === null) {
            $warnings[] = 'Occurrence imported without series: it has no end date.';

            return null;
        }

        $taken = Task::query()->where('task_recurrence_id', $seriesId)->whereDate('end_date', $endDate)->exists();

        if ($taken) {
            $warnings[] = "Occurrence imported without series: the series already has a task ending on {$endDate}.";

            return null;
        }

        return (int) $seriesId;
    }
}
