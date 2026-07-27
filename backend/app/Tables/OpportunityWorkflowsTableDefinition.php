<?php

namespace App\Tables;

use App\Models\OpportunityWorkflow;
use App\Models\User;
use App\Services\OpportunityWorkflowService;
use App\Support\OpportunityWorkflows\CriterionFieldRegistry;
use App\Support\OpportunityWorkflows\CriterionValueLabelResolver;
use App\Tables\OpportunityWorkflows\OpportunityWorkflowColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `opportunity-workflows` domain (spec 0047, Lane
 * A). `name`/`is_active`/`updated_at` are real columns; `criteria_fields`/
 * `criteria_values`/`statuses_count` are derived from the eager-loaded
 * `criteria`/`statuses` relations (baseQuery), never an extra query per row.
 */
class OpportunityWorkflowsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly OpportunityWorkflowService $service,
        private readonly CriterionFieldRegistry $criterionFieldRegistry,
        private readonly CriterionValueLabelResolver $valueLabelResolver,
    ) {}

    public function domain(): string
    {
        return 'opportunity-workflows';
    }

    /**
     * @return class-string<OpportunityWorkflow>
     */
    public function modelClass(): string
    {
        return OpportunityWorkflow::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives OpportunityWorkflowPolicy::viewAny
    // from modelClass() (opportunity-workflows.viewAny).

    /**
     * @return Builder<OpportunityWorkflow>
     */
    public function baseQuery(): Builder
    {
        return OpportunityWorkflow::query()->with(['criteria', 'statuses']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return OpportunityWorkflowColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return OpportunityWorkflowColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return OpportunityWorkflowColumnCatalog::actions();
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
        /** @var OpportunityWorkflow $row */
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
     * Allowed action keys for a single row, via OpportunityWorkflowPolicy.
     * No system-row concept at the WORKFLOW level (unlike a single status
     * row): `delete` is always available to an actor with the permission.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var OpportunityWorkflow $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
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
     * Delegate to OpportunityWorkflowService::delete() so the generic
     * bulk-delete endpoint re-resolves every impacted Opportunity (AC-018)
     * exactly like the single DELETE /opportunity-workflows/{opportunityWorkflow}
     * endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var OpportunityWorkflow $model */
        $this->service->delete($model);
    }
}
