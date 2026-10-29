<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyTaskClassifier;
use App\Migrations\Support\LegacyTaskLinks;
use App\Migrations\Support\LegacyTaskRecurrenceWriter;
use App\Migrations\Support\LegacyWorkOrderStatusLink;
use App\Models\Task;
use App\Models\User;
use App\RichText\RichTextSanitizer;
use RuntimeException;

/**
 * `tasks` migration source (spec 0289): the legacy `tasks` (roots and
 * sub-tasks, assignees, watchers, record links, active recurrences) written
 * DIRECTLY to the model, not through TaskService::create() (D-5): the service
 * would force the creator to the actor, derive the status, refuse closed
 * stages, parent date ranges and blocked parents - all legitimate legacy
 * states - and notify. The whole row runs inside the engine's per-row
 * transaction and without activity log; nothing is notified.
 *
 * The legacy sends the data clean (backend.md section 9): names for the
 * lookups, the semantic status, a pruned tree (parents first), merged and
 * disjoint people, the recurrence rule in qnet-2's vocabulary. What stays
 * here is the qnet-2 side: `old_id` lookups, name resolution, the derived
 * status, the registry/referent/commessa coherence, stage-by-name on roots,
 * the series constraints (LegacyTaskRecurrenceWriter), HTML sanitization and
 * the title cap. A person, or the commessa, not migrated fails the row.
 *
 * A task already imported is never rewritten, with one exception (spec 0323
 * D-12): a re-run fills its working status snapshot when it is still empty,
 * `created` when filled, `skipped` otherwise. It never moves the commessa.
 */
class TasksSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;

    private const int TITLE_MAX = 191;

    public function __construct(
        ExternalApiClient $client,
        private readonly LegacyTaskLinks $links,
        private readonly LegacyTaskClassifier $classifier,
        private readonly LegacyTaskRecurrenceWriter $recurrenceWriter,
        private readonly RichTextSanitizer $sanitizer,
        private readonly LegacyWorkOrderStatusLink $statusLink,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'tasks';
    }

    public function label(): string
    {
        return 'Tasks';
    }

    public function endpoint(): string
    {
        return 'tasks';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
            MigrationDependency::required('work-orders'),
            MigrationDependency::optional('registries'),
            MigrationDependency::optional('referents'),
            MigrationDependency::optional('opportunities'),
            MigrationDependency::optional('work-order-workflows'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'parent_id', 'label' => 'Parent (external id)', 'type' => 'number'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'status_system_key', 'label' => 'Status key', 'type' => 'string'],
            ['id' => 'status_name', 'label' => 'Status name', 'type' => 'string'],
            ['id' => 'work_order_id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'registry_id', 'label' => 'Registry (external id)', 'type' => 'number'],
            ['id' => 'creator_user_id', 'label' => 'Creator (external id)', 'type' => 'number'],
            ['id' => 'assignee_user_ids', 'label' => 'Assignees (external ids)', 'type' => 'string'],
            ['id' => 'watcher_user_ids', 'label' => 'Watchers (external ids)', 'type' => 'string'],
            ['id' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
            ['id' => 'end_date', 'label' => 'End date', 'type' => 'date'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $existing = Task::query()->where('old_id', $externalId)->first(['id', 'work_order_workflow_status_id']);

        if ($existing !== null) {
            return $this->backfillWorkOrderStatus($existing, $record);
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * Spec 0323 D-12: a plain query update (no activity log, no event), and
     * no model on the outcome so the re-run never rewrites custom fields.
     *
     * @param  array<string, mixed>  $record
     */
    private function backfillWorkOrderStatus(Task $task, array $record): MigrationRowOutcome
    {
        if ($task->work_order_workflow_status_id !== null) {
            return MigrationRowOutcome::skipped();
        }

        $warnings = [];
        $statusId = $this->statusLink->resolve($record['work_order_workflow_status_old_id'] ?? null, $warnings);

        if ($statusId === null) {
            return MigrationRowOutcome::skipped($warnings);
        }

        Task::query()->whereKey($task->id)->update(['work_order_workflow_status_id' => $statusId]);

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: the people; any not migrated, or no creator, fails the row (D-3, D-8).
        $creatorId = $this->creatorId($record['creator_user_id'] ?? null);
        $requesterId = $this->requiredUserId($record['requester_user_id'] ?? null, 'Requester');
        $assigneeIds = $this->requiredUserIds($record['assignee_user_ids'] ?? [], 'Assignee');
        $watcherIds = $this->requiredUserIds($record['watcher_user_ids'] ?? [], 'Watcher');

        // Step 2: the record links and the tree position (D-7, D-9).
        $links = $this->links->resolve($record, $warnings);

        // Step 3: lookups by name and the status (D-6, AC-004).
        $classification = $this->classifier->classify($record, $assigneeIds, $creatorId, $requesterId, $warnings);

        // Step 4: the recurrence series of the origin or of the occurrence (D-10).
        $endDate = $this->blankToNull($record['end_date'] ?? null);
        $seriesId = $this->recurrenceWriter->resolveSeriesId($record, $endDate, $warnings);

        // Step 5: write the task quietly, then its people and the legacy anchors.
        $task = new Task([
            ...$links,
            ...$classification,
            'title' => $this->title($record['title'] ?? null, $warnings),
            'description' => $this->description($record['description'] ?? null),
            'requester_id' => $requesterId,
            'start_date' => $this->blankToNull($record['start_date'] ?? null),
            'end_date' => $endDate,
            'completion_date' => $this->blankToNull($record['completion_date'] ?? null),
            'start_time' => $this->blankToNull($record['start_time'] ?? null),
            'end_time' => $this->blankToNull($record['end_time'] ?? null),
            'estimated_minutes' => isset($record['estimated_minutes']) ? (int) $record['estimated_minutes'] : null,
            'is_blocked' => (bool) ($record['is_blocked'] ?? false),
            'requires_closure_feedback' => (bool) ($record['requires_closure_feedback'] ?? false),
            'requires_validation' => (bool) ($record['requires_validation'] ?? false),
            'closure_feedback' => $this->blankToNull($record['closure_feedback'] ?? null),
            'is_private' => false,
        ]);
        $task->creator_id = $creatorId;
        $task->task_recurrence_id = $seriesId;
        $task->subtask_position = (int) ($record['subtask_position'] ?? 0);
        $task->stage_position = (int) ($record['stage_position'] ?? 0);
        // Snapshot only (spec 0323, D-10): the import never moves the work order or its progress.
        $task->forceFill(['work_order_workflow_status_id' => $this->statusLink->resolve($record['work_order_workflow_status_old_id'] ?? null, $warnings)]);
        $task->save();

        $task->assignees()->sync($assigneeIds);
        $task->watchers()->sync($watcherIds);
        $this->finalizeLegacyRecord($task, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $task);
    }

    /**
     * @throws RuntimeException when the legacy sends no creator or one not migrated
     */
    private function creatorId(mixed $legacyUserId): int
    {
        return $this->requiredUserId($legacyUserId, 'Creator')
            ?? throw new RuntimeException('No creator in the legacy; the task is not imported.');
    }

    /**
     * @throws RuntimeException when a legacy user is sent but not migrated
     */
    private function requiredUserId(mixed $legacyUserId, string $role): ?int
    {
        if ($legacyUserId === null || $legacyUserId === '') {
            return null;
        }

        $userId = $this->resolveOldId(User::class, $legacyUserId);

        if ($userId === null) {
            throw new RuntimeException("{$role} (legacy user id {$legacyUserId}) not migrated; the task is not imported.");
        }

        return $userId;
    }

    /**
     * @return array<int, int>
     */
    private function requiredUserIds(mixed $legacyUserIds, string $role): array
    {
        $ids = [];

        foreach ((array) ($legacyUserIds ?? []) as $legacyUserId) {
            $id = $this->requiredUserId($legacyUserId, $role);

            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function title(mixed $legacyTitle, array &$warnings): string
    {
        $title = trim((string) ($legacyTitle ?? ''));

        if ($title === '') {
            throw new RuntimeException('title is required.');
        }

        if (mb_strlen($title) > self::TITLE_MAX) {
            $warnings[] = 'Title truncated to '.self::TITLE_MAX.' characters.';

            return mb_substr($title, 0, self::TITLE_MAX);
        }

        return $title;
    }

    private function description(mixed $legacyDescription): ?string
    {
        $html = $this->blankToNull($legacyDescription);

        return $html === null ? null : $this->sanitizer->sanitize($html, false);
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
