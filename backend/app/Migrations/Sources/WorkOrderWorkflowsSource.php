<?php

namespace App\Migrations\Sources;

use App\DataObjects\WorkOrderWorkflows\CreateWorkOrderWorkflowData;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\LegacyWorkflowStatusAligner;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowCriterion;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrderWorkflowService;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionFieldRegistry;
use RuntimeException;

/**
 * `work-order-workflows` migration source (spec 0322, D-11): one legacy record
 * per `progress` category, already shaped by the legacy (trimmed name, business
 * function and optional category as legacy ids, statuses in order). Each record
 * becomes ONE active workflow whose criteria are the business function and, when
 * sent, the category branch; its statuses are custom rows in the `in_progress`
 * group carrying the legacy `progress.id` as `old_id`. The legacy also sends each
 * status `group` and, for at most one status per key, a `system_key`
 * (open|completed|closed): that status IS the set's system row of the key
 * (spec 0324, D-3); keys the legacy does not send keep the writer's default row.
 *
 * Only looked up and written: an unresolved function or category, a percentage
 * outside 0..100 or an invalid `system_key`/`group` fails the row with nothing
 * written. A workflow with the same criteria signature is skipped. A re-run on a
 * set already imported realigns it to the legacy (D-4) and is skipped when
 * nothing changes. Written without activity log.
 */
class WorkOrderWorkflowsSource extends AbstractMigrationSource
{
    private const int MIN_PERCENTAGE = 0;

    private const int MAX_PERCENTAGE = 100;

    private ?LegacyWorkflowStatusAligner $aligner = null;

    public function key(): string
    {
        return 'work-order-workflows';
    }

    public function label(): string
    {
        return 'Work order workflows';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('business-functions'),
            MigrationDependency::optional('product-categories'),
        ];
    }

    public function endpoint(): string
    {
        return 'work-order-workflows';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Workflow (external id)', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'business_function_id', 'label' => 'Business function (external id)', 'type' => 'number'],
            ['id' => 'product_category_id', 'label' => 'Product category (external id)', 'type' => 'number'],
            ['id' => 'statuses_count', 'label' => 'Statuses', 'type' => 'number'],
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
        return [
            'id' => $record['id'] ?? null,
            'name' => $record['name'] ?? null,
            'business_function_id' => $record['business_function_id'] ?? null,
            'product_category_id' => $record['product_category_id'] ?? null,
            'statuses_count' => is_array($record['statuses'] ?? null) ? count($record['statuses']) : 0,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        // Step 1: validate the statuses before anything else
        $statuses = $this->validatedStatuses($record['statuses'] ?? []);
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 2: a set already imported is realigned to the legacy, never recreated
        $importedWorkflowId = $this->importedWorkflowId($statuses);

        if ($importedWorkflowId !== null) {
            return $this->aligner()->align($importedWorkflowId, $statuses)
                ? MigrationRowOutcome::created($warnings)
                : MigrationRowOutcome::skipped($warnings);
        }

        // Step 3: resolve the criteria; an unresolved one fails the row
        $criteria = $this->resolvedCriteria($record);
        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Workflow name is required.');
        }

        // Step 4: the same criteria combination already configured is left as is
        if ($this->signatureExists(CreateWorkOrderWorkflowData::computeSignature($criteria))) {
            return MigrationRowOutcome::skipped($warnings);
        }

        // Step 5: create the workflow, stamp the legacy ids and adopt the system keys, without activity log
        activity()->withoutLogs(fn () => $this->createWorkflow($name, $criteria, $statuses));

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, array{field: string, value_id: int}>
     */
    private function resolvedCriteria(array $record): array
    {
        $functionId = $this->resolveOldId(BusinessFunction::class, (int) ($record['business_function_id'] ?? 0))
            ?? throw new RuntimeException("Business function not migrated (legacy id {$record['business_function_id']}); migrate business functions first.");

        $criteria = [['field' => 'business_function_id', 'value_id' => $functionId]];

        if (($record['product_category_id'] ?? null) !== null) {
            $categoryId = $this->resolveOldId(ProductCategory::class, (int) $record['product_category_id'])
                ?? throw new RuntimeException("Product category not migrated (legacy id {$record['product_category_id']}); migrate product categories first.");

            $criteria[] = ['field' => WorkOrderCriterionFieldRegistry::BRANCH_FIELD, 'value_id' => $categoryId];
        }

        return $criteria;
    }

    private function aligner(): LegacyWorkflowStatusAligner
    {
        return $this->aligner ??= app(LegacyWorkflowStatusAligner::class);
    }

    /**
     * The workflow already holding any of the legacy statuses, or null for a set never imported.
     * Statuses of the global default set do not count: it is never touched.
     *
     * @param  list<array{old_id: int}>  $statuses
     */
    private function importedWorkflowId(array $statuses): ?int
    {
        if ($statuses === []) {
            return null;
        }

        return WorkOrderWorkflowStatus::query()
            ->whereIn('old_id', array_column($statuses, 'old_id'))
            ->whereNotNull('work_order_workflow_id')
            ->value('work_order_workflow_id');
    }

    /**
     * @return list<array{old_id: int, name: string, progress_percentage: ?int, system_key: ?string, group: string}>
     */
    private function validatedStatuses(mixed $statuses): array
    {
        if (! is_array($statuses)) {
            throw new RuntimeException('Statuses must be a list.');
        }

        $validated = [];
        $usedKeys = [];

        foreach (array_values($statuses) as $position => $status) {
            $number = $position + 1;
            $percentage = is_array($status) ? ($status['progress_percentage'] ?? null) : null;
            $name = is_array($status) ? trim((string) ($status['name'] ?? '')) : '';

            if (! is_array($status) || ! is_int($status['id'] ?? null) || $name === '') {
                throw new RuntimeException("Status {$number}: id and name are required.");
            }

            if ($percentage !== null && (! is_int($percentage) || $percentage < self::MIN_PERCENTAGE || $percentage > self::MAX_PERCENTAGE)) {
                throw new RuntimeException("Status {$number}: progress_percentage must be null or an integer between ".self::MIN_PERCENTAGE.' and '.self::MAX_PERCENTAGE.'.');
            }

            $systemKey = $status['system_key'] ?? null;
            $group = $status['group'] ?? null;

            if ($systemKey !== null && ! in_array($systemKey, WorkOrderWorkflowStatusSystemKey::values(), true)) {
                throw new RuntimeException("Status {$number}: unknown system_key.");
            }

            if (! is_string($group) || WorkOrderStatus::tryFrom($group) === null) {
                throw new RuntimeException("Status {$number}: unknown group.");
            }

            if ($systemKey !== null && $systemKey !== $group) {
                throw new RuntimeException("Status {$number}: system_key must equal group.");
            }

            if ($systemKey !== null && in_array($systemKey, $usedKeys, true)) {
                throw new RuntimeException("Status {$number}: system_key \"{$systemKey}\" is already used by another status of the set.");
            }

            $usedKeys[] = $systemKey;
            $validated[] = ['old_id' => $status['id'], 'name' => $name, 'progress_percentage' => $percentage, 'system_key' => $systemKey, 'group' => $group];
        }

        return $validated;
    }

    private function signatureExists(string $signature): bool
    {
        return WorkOrderWorkflow::query()->with('criteria')->get()->contains(
            fn (WorkOrderWorkflow $workflow): bool => CreateWorkOrderWorkflowData::computeSignature(
                $workflow->criteria->map(fn (WorkOrderWorkflowCriterion $criterion): array => $criterion->only(['field', 'value_id']))->all(),
            ) === $signature,
        );
    }

    /**
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     * @param  list<array{old_id: int, name: string, progress_percentage: ?int, system_key: ?string, group: string}>  $statuses
     */
    private function createWorkflow(string $name, array $criteria, array $statuses): void
    {
        $workflow = app(WorkOrderWorkflowService::class)->create(CreateWorkOrderWorkflowData::fromValidated([
            'name' => $name,
            'is_active' => true,
            'criteria' => $criteria,
            'statuses' => array_map(fn (array $status): array => [
                'name' => $status['name'],
                'description' => null,
                'color' => null,
                'group' => $status['group'],
                'progress_percentage' => $status['progress_percentage'],
            ], $statuses),
        ]));

        $customs = $workflow->statuses->whereNull('system_key')->values();

        foreach ($statuses as $index => $status) {
            $customs[$index]->forceFill(['old_id' => $status['old_id']])->save();
        }

        $this->aligner()->align($workflow->id, $statuses);
    }
}
