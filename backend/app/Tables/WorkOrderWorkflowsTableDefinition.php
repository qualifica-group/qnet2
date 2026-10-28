<?php

namespace App\Tables;

use App\Models\User;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrderWorkflowService;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionFieldRegistry;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionValueLabelResolver;
use App\Tables\WorkOrderWorkflows\WorkOrderWorkflowColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `work-order-workflows` domain (spec 0322, twin of
 * quote-workflows). `name`/`is_active`/`updated_at` are real
 * columns; `criteria_fields`/`criteria_values`/`statuses_count` are derived
 * from the eager-loaded `criteria`/`statuses` relations (baseQuery), never
 * an extra query per row.
 */
class WorkOrderWorkflowsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly WorkOrderWorkflowService $service,
        private readonly WorkOrderCriterionFieldRegistry $criterionFieldRegistry,
        private readonly WorkOrderCriterionValueLabelResolver $valueLabelResolver,
    ) {}

    public function domain(): string
    {
        return 'work-order-workflows';
    }

    /**
     * @return class-string<WorkOrderWorkflow>
     */
    public function modelClass(): string
    {
        return WorkOrderWorkflow::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives WorkOrderWorkflowPolicy::viewAny
    // from modelClass() (work-order-workflows.viewAny).

    /**
     * @return Builder<WorkOrderWorkflow>
     */
    public function baseQuery(): Builder
    {
        return WorkOrderWorkflow::query()->with(['criteria', 'statuses']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return WorkOrderWorkflowColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return WorkOrderWorkflowColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return WorkOrderWorkflowColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'updated_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var WorkOrderWorkflow $row */
        $fieldLabels = collect($this->criterionFieldRegistry->allowedFields())->keyBy('field');
        $valueLabels = $this->valueLabelResolver->resolve($row->criteria);

        return [
            'id' => $row->id,
            'name' => $row->name,
            'criteria_fields' => $row->criteria
                ->map(fn ($criterion): string => $fieldLabels[$criterion->field]['label'] ?? $criterion->field)
                ->all(),
            'criteria_values' => $row->criteria
                ->map(fn ($criterion): string => $valueLabels[$criterion->id])
                ->all(),
            'statuses_count' => $row->statuses->count(),
            'is_active' => $row->is_active,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via WorkOrderWorkflowPolicy. No
     * system-row concept at the WORKFLOW level (unlike a single status row):
     * `delete` is always available to an actor with the permission.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var WorkOrderWorkflow $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to WorkOrderWorkflowService::delete() so the generic bulk-delete
     * endpoint re-resolves every impacted work order exactly like the
     * single DELETE /work-order-workflows/{quoteWorkflow} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var WorkOrderWorkflow $model */
        $this->service->delete($model);
    }
}
