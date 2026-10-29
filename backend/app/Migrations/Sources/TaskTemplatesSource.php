<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\DataObjects\TaskTemplates\CreateTaskTemplateData;
use App\DataObjects\TaskTemplates\TaskTemplateItemData;
use App\DataObjects\TaskTemplates\TaskTemplateStageData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyTaskTemplateStatusBackfill;
use App\Migrations\Support\LegacyWorkOrderStatusLink;
use App\Models\TaskTemplate;
use App\RichText\RichTextConverter;
use App\Services\TaskTemplateService;
use RuntimeException;

/**
 * `task-templates` migration source (spec 0172, D-7..D-13): imports the
 * legacy `templatebtypes` -> `templatebtypemilestones` ->
 * `templatebtypemilestoneactions` tree ("Modello di Task" -> "Fase" ->
 * azione/sotto-azione) through `TaskTemplateService::create()` — the same
 * writers a manual save uses, so parent-before-child ordering and
 * stage-key resolution are exercised for free, never duplicated here.
 *
 * The legacy sender delivers the data clean (decision 2026-10-13, supersedes
 * D-7): `is_active`, the tree anomalies (orphans, cycles, depth cap, untitled
 * actions: reported in the record's `warnings`), a resolved `parent_id`,
 * parent-before-children order and a single `estimated_minutes`. What stays
 * here is the qnet-2 side: duplicate name suffixing (D-8), request-scoped
 * `key`/`parentKey` minting, the column-length caps and the plain-text to
 * HTML conversion of the description (the target's rich-text format).
 * `TaskTemplateService::create()` does not run the FormRequest's validation,
 * hence the title/stage-name caps enforced here (spec 0172 M4).
 *
 * Idempotence is per MODEL only (D-12): `task_templates.old_id`; a model
 * already migrated is never re-created — its stages/items carry no `old_id`
 * of their own. Fase 6 of MigrationOrder (spec 0323, D-9): each action links
 * its working status by the `old_id` of `work-order-workflows`; on a re-run a
 * model imported before that link existed gets its empty links backfilled
 * (spec 0323 D-11, LegacyTaskTemplateStatusBackfill): `created` when an action
 * was linked, `skipped` otherwise.
 */
class TaskTemplatesSource extends AbstractMigrationSource
{
    // Mirrors Http\Requests\TaskTemplates\Concerns\ValidatesTaskTemplateItems.
    private const int TITLE_MAX = 191;

    private const int STAGE_NAME_MAX = 191;

    public function __construct(
        ExternalApiClient $client,
        private readonly TaskTemplateService $service,
        private readonly LegacyWorkOrderStatusLink $statusLink,
        private readonly LegacyTaskTemplateStatusBackfill $statusBackfill,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'task-templates';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [MigrationDependency::optional('work-order-workflows')];
    }

    public function label(): string
    {
        return 'Task templates';
    }

    public function endpoint(): string
    {
        return 'task-templates';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'stages_count', 'label' => 'Stages', 'type' => 'number'],
            ['id' => 'items_count', 'label' => 'Actions', 'type' => 'number'],
            ['id' => 'sub_items_count', 'label' => 'Sub-actions', 'type' => 'number'],
        ];
    }

    /**
     * The nested `stages[].items[]` tree is not a scalar preview column, so
     * the whole envelope is built here rather than through the generic
     * columns-driven default (mirrors ProductCategoryAttributesSource's own
     * override for its `attributes` array) — this IS the real parsed shape
     * `extractRecords()`/`extractPagination()` consume.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        return [
            'items' => [[
                'id' => 1,
                'name' => 'Sample task template',
                'is_active' => true,
                'warnings' => [],
                'stages' => [[
                    'id' => 10,
                    'name' => 'Sample stage',
                    'items' => [
                        [
                            'id' => 100,
                            'parent_id' => null,
                            'title' => 'Sample root action',
                            'description' => 'Sample description',
                            'estimated_minutes' => 90,
                            'work_order_workflow_status_old_id' => null,
                        ],
                        [
                            'id' => 101,
                            'parent_id' => 100,
                            'title' => 'Sample sub-action',
                            'description' => null,
                            'estimated_minutes' => null,
                        ],
                    ],
                ]],
            ]],
            'pagination' => [
                'total' => 1,
                'offset' => 0,
                'limit' => (int) config('migrations.default_per_page', 50),
                'total_pages' => 1,
            ],
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
        [$itemsCount, $subItemsCount] = $this->countItems($record);

        return [
            'id' => $record['id'] ?? null,
            'name' => $record['name'] ?? null,
            'stages_count' => count((array) ($record['stages'] ?? [])),
            'items_count' => $itemsCount,
            'sub_items_count' => $subItemsCount,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $existing = TaskTemplate::query()->where('old_id', $externalId)->first();

        if ($existing !== null) {
            return $this->backfillStatuses($existing, $record);
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $warnings = [];
        $finalName = $this->deduplicateName($name, $externalId, $warnings);

        $warnings = [...$warnings, ...array_map('strval', (array) ($record['warnings'] ?? []))];
        $tree = $this->buildTree((array) ($record['stages'] ?? []), $warnings);

        if ($tree['items'] === []) {
            $warnings[] = 'Task template has no actions; created with none.';
        }

        $data = new CreateTaskTemplateData(
            name: $finalName,
            description: null,
            isActive: (bool) ($record['is_active'] ?? true),
            items: $tree['items'],
            stages: $tree['stages'],
        );

        $taskTemplate = $this->service->create($data, $context->actor);
        $taskTemplate->old_id = $externalId;
        $taskTemplate->save();

        return MigrationRowOutcome::created($warnings, $taskTemplate);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function backfillStatuses(TaskTemplate $taskTemplate, array $record): MigrationRowOutcome
    {
        $warnings = [];
        $tree = $this->buildTree((array) ($record['stages'] ?? []), $warnings);

        // No model on the outcome: a re-run never rewrites custom fields.
        if ($this->statusBackfill->fill($taskTemplate, $tree['items'], $warnings)) {
            return MigrationRowOutcome::created($warnings);
        }

        return MigrationRowOutcome::skipped($warnings);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: int, 1: int}
     */
    private function countItems(array $record): array
    {
        $items = 0;
        $subItems = 0;

        foreach ((array) ($record['stages'] ?? []) as $stage) {
            foreach ((array) ($stage['items'] ?? []) as $item) {
                $items++;

                if (($item['parent_id'] ?? null) !== null) {
                    $subItems++;
                }
            }
        }

        return [$items, $subItems];
    }

    /**
     * Maps the legacy `stages[].items[]` (already ordered, parents first) to
     * the DTOs: a root item carries its stage key, a sub-item its parent key.
     *
     * @param  array<int, array<string, mixed>>  $rawStages
     * @param  array<int, string>  $warnings
     * @return array{stages: array<int, TaskTemplateStageData>, items: array<int, TaskTemplateItemData>}
     */
    private function buildTree(array $rawStages, array &$warnings): array
    {
        $stages = [];
        $items = [];

        foreach ($rawStages as $rawStage) {
            $stageId = (int) $rawStage['id'];
            $stageKey = 's'.$stageId;
            $stages[] = new TaskTemplateStageData(id: null, key: $stageKey, name: $this->capped((string) ($rawStage['name'] ?? ''), self::STAGE_NAME_MAX, 'Stage name', $stageId, $warnings));

            foreach ((array) ($rawStage['items'] ?? []) as $rawItem) {
                $parentId = $rawItem['parent_id'] ?? null;

                $items[] = new TaskTemplateItemData(
                    id: null,
                    title: $this->capped((string) $rawItem['title'], self::TITLE_MAX, 'Action title', (int) $rawItem['id'], $warnings),
                    description: RichTextConverter::plainTextToHtml((string) ($rawItem['description'] ?? ''), convertMentionTokens: false),
                    estimatedMinutes: isset($rawItem['estimated_minutes']) ? (int) $rawItem['estimated_minutes'] : null,
                    taskStatusId: null,
                    workOrderWorkflowStatusId: $this->statusLink->resolve($rawItem['work_order_workflow_status_old_id'] ?? null, $warnings),
                    dueOffsetDays: 0,
                    stageKey: $parentId === null ? $stageKey : null,
                    key: 'i'.$rawItem['id'],
                    parentKey: $parentId === null ? null : 'i'.$parentId,
                );
            }
        }

        return ['stages' => $stages, 'items' => $items];
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function capped(string $value, int $max, string $what, int $externalId, array &$warnings): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $warnings[] = "{$what} truncated to {$max} characters (external id {$externalId}).";

        return mb_substr($value, 0, $max);
    }

    /**
     * Case-insensitive duplicate check (D-8, collation-independent: a
     * parametrized `LOWER(name) = ?` works the same on MySQL and the
     * SQLite test connection). A duplicate is imported with the external id
     * suffixed onto its name, never silently merged or overwritten.
     *
     * @param  array<int, string>  $warnings
     */
    private function deduplicateName(string $name, int|string $externalId, array &$warnings): string
    {
        $exists = TaskTemplate::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();

        if (! $exists) {
            return $name;
        }

        $warnings[] = "Duplicate task template name; imported as \"{$name} (old_id {$externalId})\".";

        return "{$name} (old_id {$externalId})";
    }
}
