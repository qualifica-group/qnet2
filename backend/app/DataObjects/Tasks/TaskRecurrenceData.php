<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use App\Models\TaskRecurrence;

/**
 * The recurrence RULE (spec 0120, D-1), the one shape shared by three
 * unrelated consumers: the validated `recurrence` sub-object of
 * StoreTaskRequest/UpdateTaskRequest (fromValidated()), the persisted
 * `TaskRecurrence` row read back for calculation (fromModel()), and
 * App\Services\Tasks\TaskRecurrenceCalculator's own pure input — one DTO
 * rather than three near-identical shapes, the same reasoning
 * CreateTaskData/UpdateTaskData already follow for `tasks` itself.
 *
 * `weekdays`/`monthDay`/`endsOn`/`occurrenceCount` are each meaningful for
 * exactly one `frequency`/`ends` value; the FormRequest layer enforces the
 * pairing (`required_if`/`prohibited_unless`), so a value reaching this DTO
 * from an HTTP request has already been shaped correctly — a value built by
 * fromModel() carries whatever the row itself holds, which the write path
 * guarantees is the same shape. Spec 0155, D-1 adds `monthMode`/`ordinal`/
 * `ordinalWeekday`/`yearMonth`/`workdaysOnly` on the same terms: each is
 * meaningful only for the `frequency`/`monthMode` combination TaskRecurrenceRules
 * pairs it with, `null`/`false` otherwise.
 */
final readonly class TaskRecurrenceData
{
    /**
     * @param  array<int, int>|null  $weekdays
     */
    public function __construct(
        public TaskRecurrenceFrequency $frequency,
        public int $interval,
        public ?array $weekdays,
        public ?int $monthDay,
        public ?TaskRecurrenceMonthMode $monthMode,
        public ?int $ordinal,
        public ?int $ordinalWeekday,
        public ?int $yearMonth,
        public bool $workdaysOnly,
        public TaskRecurrenceEnd $ends,
        public ?string $endsOn,
        public ?int $occurrenceCount,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated `recurrence` sub-array
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            frequency: TaskRecurrenceFrequency::from((string) $data['frequency']),
            interval: (int) $data['interval'],
            weekdays: isset($data['weekdays']) ? array_values(array_map(intval(...), (array) $data['weekdays'])) : null,
            monthDay: isset($data['month_day']) ? (int) $data['month_day'] : null,
            monthMode: isset($data['month_mode']) ? TaskRecurrenceMonthMode::from((string) $data['month_mode']) : null,
            ordinal: isset($data['ordinal']) ? (int) $data['ordinal'] : null,
            ordinalWeekday: isset($data['ordinal_weekday']) ? (int) $data['ordinal_weekday'] : null,
            yearMonth: isset($data['year_month']) ? (int) $data['year_month'] : null,
            workdaysOnly: (bool) ($data['workdays_only'] ?? false),
            ends: TaskRecurrenceEnd::from((string) $data['ends']),
            endsOn: isset($data['ends_on']) ? (string) $data['ends_on'] : null,
            occurrenceCount: isset($data['occurrence_count']) ? (int) $data['occurrence_count'] : null,
        );
    }

    public static function fromModel(TaskRecurrence $recurrence): self
    {
        return new self(
            frequency: $recurrence->frequency,
            interval: $recurrence->interval,
            weekdays: $recurrence->weekdays,
            monthDay: $recurrence->month_day,
            monthMode: $recurrence->month_mode,
            ordinal: $recurrence->ordinal,
            ordinalWeekday: $recurrence->ordinal_weekday,
            yearMonth: $recurrence->year_month,
            workdaysOnly: $recurrence->workdays_only,
            ends: $recurrence->ends,
            endsOn: $recurrence->ends_on?->toDateString(),
            occurrenceCount: $recurrence->occurrence_count,
        );
    }

    /**
     * The mass-assignable column map for `task_recurrences`.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'frequency' => $this->frequency,
            'interval' => $this->interval,
            'weekdays' => $this->weekdays,
            'month_day' => $this->monthDay,
            'month_mode' => $this->monthMode,
            'ordinal' => $this->ordinal,
            'ordinal_weekday' => $this->ordinalWeekday,
            'year_month' => $this->yearMonth,
            'workdays_only' => $this->workdaysOnly,
            'ends' => $this->ends,
            'ends_on' => $this->endsOn,
            'occurrence_count' => $this->occurrenceCount,
        ];
    }
}
