<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use Illuminate\Contracts\Validation\Validator;

/**
 * The `time_entry_shares` rules of the two bulk-complete endpoints (spec 0338),
 * kept in ONE place so POST /api/tasks/bulk and the task board's bulk cannot
 * drift. `time_entry.minutes` is the TOTAL; the shares say how many of those
 * minutes each selected task gets.
 */
final class BulkTimeEntrySharesRules
{
    public const string FIELD = 'time_entry_shares';

    /**
     * 23:59: the time entry rules read times as `H:i`, so a slot ending at
     * 24:00 could be written here but never saved again from the edit form.
     */
    private const int LAST_MINUTE_OF_DAY = 1439;

    /**
     * @param  array<int|string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $input, bool $timeEntryPresent): array
    {
        if (($input['action'] ?? null) !== 'complete' || ! $timeEntryPresent) {
            return [self::FIELD => ['prohibited']];
        }

        $taskIds = collect(is_array($input['task_ids'] ?? null) ? $input['task_ids'] : [])
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return [
            self::FIELD => ['required', 'array', 'min:1'],
            self::FIELD.'.*.task_id' => ['required', 'integer', 'distinct', 'in:'.implode(',', $taskIds ?: [0])],
            self::FIELD.'.*.minutes' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Cross-field checks that single-field rules cannot express: one share per
     * selected task, shares summing to the total, and the consecutive time
     * slots staying inside the day.
     *
     * @param  array<string, mixed>  $input
     */
    public static function validateConsistency(Validator $validator, array $input): void
    {
        $errors = $validator->errors();
        $shares = $input[self::FIELD] ?? null;
        $timeEntry = $input['time_entry'] ?? null;

        if (! is_array($shares) || ! is_array($timeEntry) || $errors->has('time_entry.minutes') || $errors->has(self::FIELD.'.*')) {
            return;
        }

        $total = (int) ($timeEntry['minutes'] ?? 0);
        $taskIds = is_array($input['task_ids'] ?? null) ? $input['task_ids'] : [];

        if (count($shares) !== count($taskIds)) {
            $errors->add(self::FIELD, 'There must be exactly one share for each selected task.');
        } elseif (array_sum(array_column($shares, 'minutes')) !== $total) {
            $errors->add(self::FIELD, 'The shares must add up to the total minutes of the time entry.');
        }

        $startTime = $timeEntry['start_time'] ?? null;

        if (is_string($startTime) && ! $errors->has('time_entry.start_time')
            && self::toMinutes($startTime) + $total > self::LAST_MINUTE_OF_DAY) {
            $errors->add('time_entry.minutes', 'The time entry must end by 23:59.');
        }
    }

    /**
     * @param  array<int, mixed>  $shares  validated `time_entry_shares`
     * @return array<int, array{task_id: int, minutes: int}>
     */
    public static function normalize(array $shares): array
    {
        return array_map(
            fn (mixed $share): array => ['task_id' => (int) $share['task_id'], 'minutes' => (int) $share['minutes']],
            array_values($shares),
        );
    }

    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
