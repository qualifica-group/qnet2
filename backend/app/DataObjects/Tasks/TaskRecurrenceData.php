<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
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
 * guarantees is the same shape.
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
            'ends' => $this->ends,
            'ends_on' => $this->endsOn,
            'occurrence_count' => $this->occurrenceCount,
        ];
    }
}
