<?php

namespace App\Tables;

use App\Models\QuoteWorkflow;
use App\Models\User;
use App\Services\QuoteWorkflowService;
use App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry;
use App\Support\QuoteWorkflows\QuoteCriterionValueLabelResolver;
use App\Tables\QuoteWorkflows\QuoteWorkflowColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `quote-workflows` domain (spec 0047, moved onto
 * the Offerta by spec 0083 D-6). `name`/`is_active`/`updated_at` are real
 * columns; `criteria_fields`/`criteria_values`/`statuses_count` are derived
 * from the eager-loaded `criteria`/`statuses` relations (baseQuery), never
 * an extra query per row.
 */
class QuoteWorkflowsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly QuoteWorkflowService $service,
        private readonly QuoteCriterionFieldRegistry $criterionFieldRegistry,
        private readonly QuoteCriterionValueLabelResolver $valueLabelResolver,
    ) {}

    public function domain(): string
    {
        return 'quote-workflows';
    }

    /**
     * @return class-string<QuoteWorkflow>
     */
    public function modelClass(): string
    {
        return QuoteWorkflow::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives QuoteWorkflowPolicy::viewAny
    // from modelClass() (quote-workflows.viewAny).

    /**
     * @return Builder<QuoteWorkflow>
     */
    public function baseQuery(): Builder
    {
        return QuoteWorkflow::query()->with(['criteria', 'statuses']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return QuoteWorkflowColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return QuoteWorkflowColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return QuoteWorkflowColumnCatalog::actions();
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
        /** @var QuoteWorkflow $row */
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
     * Allowed action keys for a single row, via QuoteWorkflowPolicy. No
     * system-row concept at the WORKFLOW level (unlike a single status row):
     * `delete` is always available to an actor with the permission.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var QuoteWorkflow $row */
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
     * Delegate to QuoteWorkflowService::delete() so the generic bulk-delete
     * endpoint re-resolves every impacted Quote (AC-018) exactly like the
     * single DELETE /quote-workflows/{quoteWorkflow} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var QuoteWorkflow $model */
        $this->service->delete($model);
    }
}
