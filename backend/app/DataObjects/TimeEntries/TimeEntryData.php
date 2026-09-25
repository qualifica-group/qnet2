<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

/**
 * Validated payload for writing a TimeEntry (POST/PUT /api/time-entries,
 * POST /api/tasks/{task}/time-entries — spec 0122, data_contract). Declared
 * DTO (no "magic flying array") so the FormRequest -> Service contract is
 * explicit — see standards/architecture.md → Data Transfer Objects.
 *
 * `title`/`registryId`/`opportunityId`/`workOrderId`/`taskId` are carried
 * here exactly as SUBMITTED: the D-5 override (imposing the three record
 * links and the title from a linked Task, or deriving the client from an
 * opportunity/commessa) is `TimeEntryLinkResolver`'s job, not this DTO's —
 * `attributes()` deliberately excludes all five, since TimeEntryService
 * writes them from the resolver's verdict instead (D-5 must never be
 * bypassable by whatever this DTO happens to carry).
 *
 * `userId` is present ONLY on the generic POST (data_contract: optional,
 * "diverso da se stessi solo con manageAll", AC-007); PUT's FormRequest
 * makes `user_id` `prohibited`, so `fromValidated()` naturally leaves it
 * null there, and `forTask()` never sets it at all — the task-scoped POST
 * always writes for the authenticated actor (D-9).
 *
 * `workOrderStageId` (spec 0163, D-1) is likewise carried exactly as
 * SUBMITTED: with `taskId` set it is IGNORED by the resolver, which imposes
 * the linked Task's own stage instead — `forTask()` never even reads it from
 * the payload, since the task-scoped POST/complete never solicit it in the
 * first place (D-1: "input ignorato" means there is nothing to ignore, not
 * that a submitted value would be accepted then discarded).
 */
final readonly class TimeEntryData
{
    public function __construct(
        public string $date,
        public int $taskTypeId,
        public int $minutes,
        public ?string $title = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $notes = null,
        public ?int $registryId = null,
        public ?int $opportunityId = null,
        public ?int $workOrderId = null,
        public ?int $taskId = null,
        public ?int $userId = null,
        public ?int $workOrderStageId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            date: (string) $data['date'],
            taskTypeId: (int) $data['task_type_id'],
            minutes: (int) $data['minutes'],
            title: self::nullableString($data, 'title'),
            startTime: self::nullableString($data, 'start_time'),
            endTime: self::nullableString($data, 'end_time'),
            notes: self::nullableString($data, 'notes'),
            registryId: self::nullableInt($data, 'registry_id'),
            opportunityId: self::nullableInt($data, 'opportunity_id'),
            workOrderId: self::nullableInt($data, 'work_order_id'),
            taskId: self::nullableInt($data, 'task_id'),
            userId: self::nullableInt($data, 'user_id'),
            workOrderStageId: self::nullableInt($data, 'work_order_stage_id'),
        );
    }

    /**
     * The Task-scoped POST (data_contract: no `title`/links/`user_id` in the
     * body at all — D-9 derives every one of them from $taskId).
     *
     * @param  array<string, mixed>  $data
     */
    public static function forTask(array $data, int $taskId): self
    {
        return new self(
            date: (string) $data['date'],
            taskTypeId: (int) $data['task_type_id'],
            minutes: (int) $data['minutes'],
            startTime: self::nullableString($data, 'start_time'),
            endTime: self::nullableString($data, 'end_time'),
            notes: self::nullableString($data, 'notes'),
            taskId: $taskId,
        );
    }

    /**
     * The mass-assignable column map, EXCLUDING `user_id` and the five D-5/
     * spec-0163 link columns (`title`, `registry_id`, `opportunity_id`,
     * `work_order_id`, `task_id`, `work_order_stage_id`) — those are written
     * by TimeEntryService from TimeEntryLinkResolver's verdict, never
     * straight off this DTO.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'date' => $this->date,
            'task_type_id' => $this->taskTypeId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'minutes' => $this->minutes,
            'notes' => $this->notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        return isset($data[$key]) ? (int) $data[$key] : null;
    }
}
