<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use Illuminate\Validation\Rule;

/**
 * The `time_entry.*` rules shared by StoreTaskTimeEntryRequest and
 * CompleteTaskRequest (spec 0123, D-1/D-3): ONE source, so the two
 * FormRequests cannot copy-drift apart — AC-009 diffs their actual rule
 * maps (stripped of prefix) and expects them identical.
 *
 * `$prefix` lets a caller nest the whole set under a parent key
 * (CompleteTaskRequest nests it under `time_entry`): sibling references
 * (`required_with`/`after`) are built off the SAME prefix, since Laravel
 * resolves them against the fully-qualified, dot-notated field name.
 */
final class TimeEntryValidationRules
{
    private const int NOTES_MAX = 5000;

    private const string TIME_FORMAT = 'H:i';

    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = ''): array
    {
        $field = static fn (string $key): string => $prefix === '' ? $key : $prefix.'.'.$key;

        return [
            $field('date') => ['required', 'date_format:'.self::DATE_FORMAT],
            $field('task_type_id') => [
                'required',
                'integer',
                Rule::exists('task_types', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            $field('start_time') => ['nullable', 'string', 'date_format:'.self::TIME_FORMAT, 'required_with:'.$field('end_time')],
            $field('end_time') => ['nullable', 'string', 'date_format:'.self::TIME_FORMAT, 'required_with:'.$field('start_time'), 'after:'.$field('start_time')],
            $field('minutes') => ['required', 'integer', 'min:1', 'max:1440'],
            $field('notes') => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }
}
