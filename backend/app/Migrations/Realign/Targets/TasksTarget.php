<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Migrations\Realign\AbstractLegacyRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\OperationalLegacyValues;
use App\Migrations\Realign\Support\OperationalLookupNames;
use App\Migrations\Support\LegacyTaskClassifier;
use App\Migrations\Support\LegacyTaskLinks;
use App\Migrations\Support\LegacyWorkOrderStatusLink;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrderWorkflowStatus;
use App\RichText\RichTextSanitizer;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Aligns a task already imported (spec 0375 D-11): creator, parent and recurrence
 * series never move. Where the import would fall back on a default or an empty link
 * for a value it cannot resolve, an existing task keeps what it has.
 */
final class TasksTarget extends AbstractLegacyRealignTarget
{
    use OperationalLegacyValues;

    private const int TITLE_MAX = 191;

    private const array COLUMNS = [
        'title', 'description', 'estimated_minutes', 'is_blocked', 'requires_closure_feedback', 'requires_validation',
        'closure_feedback', 'registry_id', 'referent_id', 'opportunity_id', 'work_order_id', 'work_order_stage_id',
    ];

    /** Task column => legacy reference: sent but unresolved keeps the current link. */
    private const array KEPT_LINKS = [
        'registry_id' => 'registry_id',
        'referent_id' => 'referent_id',
        'opportunity_id' => 'opportunity_id',
        'work_order_stage_id' => 'work_order_stage_name',
    ];

    /** Task column => [legacy name, label in the classifier's warning]. */
    private const array DEFAULTED_LOOKUPS = [
        'task_type_id' => ['type_name', 'task type'],
        'task_priority_id' => ['priority_name', 'task priority'],
        'task_importance_id' => ['importance_name', 'task importance'],
    ];

    public function __construct(
        private readonly LegacyTaskLinks $links,
        private readonly LegacyTaskClassifier $classifier,
        private readonly LegacyWorkOrderStatusLink $statusLink,
        private readonly RichTextSanitizer $sanitizer,
        private readonly OperationalLookupNames $names,
    ) {}

    public function source(): string
    {
        return 'tasks';
    }

    protected function modelClass(): string
    {
        return Task::class;
    }

    /**
     * @param  Task  $record
     */
    public function snapshot(Model $record): array
    {
        return [
            ...$this->columns($record, self::COLUMNS),
            'start_date' => $this->shortDate($record->getRawOriginal('start_date')),
            'end_date' => $this->shortDate($record->getRawOriginal('end_date')),
            'completion_date' => $this->shortDate($record->getRawOriginal('completion_date')),
            'start_time' => $this->shortTime($record->getRawOriginal('start_time')),
            'end_time' => $this->shortTime($record->getRawOriginal('end_time')),
            'status' => $this->names->name(TaskStatus::class, $record->getRawOriginal('task_status_id')),
            'type' => $this->names->name(TaskType::class, $record->getRawOriginal('task_type_id')),
            'priority' => $this->names->name(TaskPriority::class, $record->getRawOriginal('task_priority_id')),
            'importance' => $this->names->name(TaskImportance::class, $record->getRawOriginal('task_importance_id')),
            'category' => $this->names->name(TaskCategory::class, $record->getRawOriginal('task_category_id')),
            'work_order_workflow_status' => $this->names->name(WorkOrderWorkflowStatus::class, $record->getRawOriginal('work_order_workflow_status_id')),
            'requester' => $this->personName(User::query()->find($record->getRawOriginal('requester_id'))),
            'assignees' => $this->peopleNames($record->assignees()),
            'watchers' => $this->peopleNames($record->watchers()),
        ];
    }

    /**
     * @param  Task  $record
     */
    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $warnings = array_map('strval', (array) ($legacy['warnings'] ?? []));

        // Step 1: the people; anyone not migrated fails the row (AC-019).
        $requesterId = $this->requiredUserId($legacy['requester_user_id'] ?? null, 'Requester');
        $assigneeIds = $this->requiredUserIds($legacy['assignee_user_ids'] ?? [], 'Assignee');
        $watcherIds = $this->requiredUserIds($legacy['watcher_user_ids'] ?? [], 'Watcher');

        // Step 2: the values the legacy decides, in memory.
        $record->forceFill([
            ...$this->scalars($record, $legacy, $warnings),
            ...$this->links($legacy, $warnings),
            ...$this->classification($record, $legacy, $assigneeIds, $requesterId, $warnings),
            ...$this->workflowStatus($legacy, $warnings),
            'requester_id' => $requesterId,
        ]);

        // Step 3: the people pivots, then one quiet save only when something differs.
        $changed = $this->syncPeople($record, $assigneeIds, $watcherIds) || $record->isDirty();

        if ($changed) {
            $this->saveWithLegacyTimestamp($record, $legacy);
            array_walk($warnings, static fn (string $warning) => $notes->warn($warning));
        }
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function scalars(Task $task, array $legacy, array &$warnings): array
    {
        $description = $this->blankToNull($legacy['description'] ?? null);

        return [
            'title' => $this->capped($legacy['title'] ?? null, self::TITLE_MAX, 'Title', $warnings) ?? throw new RuntimeException('title is required.'),
            'description' => $description === null ? null : $this->sanitizer->sanitize($description, false),
            'start_date' => $this->blankToNull($legacy['start_date'] ?? null),
            'end_date' => $this->blankToNull($legacy['end_date'] ?? null),
            'completion_date' => $this->blankToNull($legacy['completion_date'] ?? null),
            'start_time' => $this->legacyTime($task, 'start_time', $legacy['start_time'] ?? null),
            'end_time' => $this->legacyTime($task, 'end_time', $legacy['end_time'] ?? null),
            'estimated_minutes' => isset($legacy['estimated_minutes']) ? (int) $legacy['estimated_minutes'] : null,
            'is_blocked' => (bool) ($legacy['is_blocked'] ?? false),
            'requires_closure_feedback' => (bool) ($legacy['requires_closure_feedback'] ?? false),
            'requires_validation' => (bool) ($legacy['requires_validation'] ?? false),
            'closure_feedback' => $this->blankToNull($legacy['closure_feedback'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, int|null>
     */
    private function links(array $legacy, array &$warnings): array
    {
        $links = $this->links->resolve($legacy, $warnings);
        unset($links['parent_task_id']);

        foreach (self::KEPT_LINKS as $column => $legacyKey) {
            if ($links[$column] === null && $this->legacySentLink($legacy[$legacyKey] ?? null)) {
                unset($links[$column]);
            }
        }

        return $links;
    }

    private function legacySentLink(mixed $reference): bool
    {
        return is_string($reference) && ! is_numeric($reference)
            ? trim($reference) !== ''
            : $this->isSent($reference);
    }

    /**
     * The classifier's defaults and derived status are for a new task: a value the
     * legacy does not send or that qnet-2 does not know leaves the existing one.
     *
     * @param  array<string, mixed>  $legacy
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, string>  $warnings
     * @return array<string, int|null>
     */
    private function classification(Task $task, array $legacy, array $assigneeIds, ?int $requesterId, array &$warnings): array
    {
        $classified = $this->classifier->classify($legacy, $assigneeIds, (int) $task->creator_id, $requesterId, $warnings);
        $statusSent = $this->blankToNull($legacy['status_system_key'] ?? null) !== null || $this->blankToNull($legacy['status_name'] ?? null) !== null;

        if (! $statusSent || $this->wasUnknown('task status', $warnings)) {
            unset($classified['task_status_id']);
        }

        foreach (self::DEFAULTED_LOOKUPS as $column => [$legacyKey, $label]) {
            if ($this->blankToNull($legacy[$legacyKey] ?? null) === null || $this->wasUnknown($label, $warnings)) {
                unset($classified[$column]);
            }
        }

        if ($this->wasUnknown('task category', $warnings)) {
            unset($classified['task_category_id']);
        }

        return $classified;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function wasUnknown(string $label, array $warnings): bool
    {
        return array_any($warnings, static fn (string $warning): bool => str_starts_with($warning, "Unknown {$label} \""));
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     * @return array<string, int|null>
     */
    private function workflowStatus(array $legacy, array &$warnings): array
    {
        $reference = $legacy['work_order_workflow_status_old_id'] ?? null;
        $statusId = $this->statusLink->resolve($reference, $warnings);

        return $statusId === null && $this->isSent($reference) ? [] : ['work_order_workflow_status_id' => $statusId];
    }

    /**
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, int>  $watcherIds
     */
    private function syncPeople(Task $task, array $assigneeIds, array $watcherIds): bool
    {
        $changed = false;

        if (! $this->sameIds($task->assignees()->pluck('users.id')->map(intval(...))->all(), $assigneeIds)) {
            $task->assignees()->sync($assigneeIds);
            $changed = true;
        }

        if (! $this->sameIds($task->watchers()->pluck('users.id')->map(intval(...))->all(), $watcherIds)) {
            $task->watchers()->sync($watcherIds);
            $changed = true;
        }

        return $changed;
    }
}
