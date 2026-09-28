<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\DataObjects\TaskTemplates\CreateTaskTemplateData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\TaskTemplateTreeFlattener;
use App\Models\TaskTemplate;
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
 * The legacy endpoint is a raw projection (D-7): every cleanup rule below —
 * duplicate name suffixing (D-8), `is_active` from the title (D-10), the
 * sub-tree anomaly rules (D-9, TaskTemplateTreeFlattener) — lives here,
 * never upstream. `TaskTemplateService::create()` does not run the
 * FormRequest's own validation, so TaskTemplateTreeFlattener self-enforces
 * the same invariants (depth cap, no stage on a sub-item, title length)
 * that validation would.
 *
 * Idempotence is per MODEL only (D-12): `task_templates.old_id`; a model
 * already migrated is skipped whole — its stages/items carry no `old_id` of
 * their own, so a re-import cannot tell which row is "the same" one and
 * does not try. Fase 1 of MigrationOrder (D-13): this source references
 * nothing else.
 */
class TaskTemplatesSource extends AbstractMigrationSource
{
    private const string INACTIVE_MARKER = 'non attivo';

    public function __construct(
        ExternalApiClient $client,
        private readonly TaskTemplateService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'task-templates';
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
                'stages' => [[
                    'id' => 10,
                    'name' => 'Sample stage',
                    'position' => 0,
                    'items' => [
                        [
                            'id' => 100,
                            'parent_id' => null,
                            'title' => 'Sample root action',
                            'description' => 'Sample description',
                            'estimated_hours' => 1,
                            'estimated_minutes' => 30,
                            'position' => 0,
                        ],
                        [
                            'id' => 101,
                            'parent_id' => 100,
                            'title' => 'Sample sub-action',
                            'description' => null,
                            'estimated_hours' => null,
                            'estimated_minutes' => null,
                            'position' => 1,
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

        if ($this->existsByOldId(TaskTemplate::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $warnings = [];
        $finalName = $this->deduplicateName($name, $externalId, $warnings);

        $flattener = new TaskTemplateTreeFlattener((array) ($record['stages'] ?? []));
        $tree = $flattener->flatten();
        $warnings = [...$warnings, ...$flattener->warnings()];

        if ($tree['items'] === []) {
            $warnings[] = 'Task template has no actions; created with none.';
        }

        $data = new CreateTaskTemplateData(
            name: $finalName,
            description: null,
            isActive: mb_stripos($name, self::INACTIVE_MARKER) === false,
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
