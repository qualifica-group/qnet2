<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Enums\ReportStatusColumn;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowCriterion;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * The read side of the statistics column configurator (spec 0391 D-7): the
 * five columns with their chosen statuses, and every status set a request can
 * be in — the "Stati predefiniti" (global set, requests without a workflow)
 * first, then each workflow by name with the categories of its criteria.
 */
final class ReportColumnStatusCatalog
{
    /** The criteria fields whose value is a product category id. */
    private const array CATEGORY_CRITERIA = ['product_category_id', 'product_category_branch_id'];

    public function __construct(private readonly ReportColumnStatusMap $statuses) {}

    /**
     * @return array{columns: array<int, array{key: string, twin_key: string|null, status_ids: array<int, int>}>, workflows: array<int, array<string, mixed>>}
     */
    public function build(): array
    {
        return ['columns' => $this->columns(), 'workflows' => $this->workflows()];
    }

    /**
     * @return array<int, array{key: string, twin_key: string|null, status_ids: array<int, int>}>
     */
    private function columns(): array
    {
        return array_map(fn (ReportStatusColumn $column): array => [
            'key' => $column->value,
            'twin_key' => $column->twin(),
            'status_ids' => $this->statuses->idsFor($column),
        ], ReportStatusColumn::cases());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflows(): array
    {
        $statusesBySet = QuoteWorkflowStatus::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'quote_workflow_id', 'name', 'group', 'color'])
            ->groupBy(static fn (QuoteWorkflowStatus $status): string => (string) $status->quote_workflow_id);
        $categoriesByWorkflow = $this->categoryNamesByWorkflow();

        $sets = [[
            'id' => null,
            'name' => __('request-management-report.default_statuses'),
            'is_active' => true,
            'categories' => [],
            'statuses' => $this->statusRows($statusesBySet->get('', collect())),
        ]];

        $workflows = QuoteWorkflow::query()->get(['id', 'name', 'is_active'])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($workflows as $workflow) {
            $sets[] = [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'is_active' => $workflow->is_active,
                'categories' => $categoriesByWorkflow[$workflow->id] ?? [],
                'statuses' => $this->statusRows($statusesBySet->get((string) $workflow->id, collect())),
            ];
        }

        return $sets;
    }

    /**
     * @param  Collection<int, QuoteWorkflowStatus>  $statuses
     * @return array<int, array{id: int, name: string, group: string, color: string|null}>
     */
    private function statusRows(Collection $statuses): array
    {
        return $statuses->map(static fn (QuoteWorkflowStatus $status): array => [
            'id' => $status->id,
            'name' => $status->name,
            'group' => $status->group->value,
            'color' => $status->color,
        ])->values()->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function categoryNamesByWorkflow(): array
    {
        $criteria = QuoteWorkflowCriterion::query()
            ->whereIn('field', self::CATEGORY_CRITERIA)
            ->get(['quote_workflow_id', 'value_id']);
        $names = ProductCategory::query()->whereIn('id', $criteria->pluck('value_id')->unique())->pluck('name', 'id');

        return $criteria
            ->groupBy('quote_workflow_id')
            ->map(static fn (Collection $rows): array => $rows
                ->map(static fn (QuoteWorkflowCriterion $criterion): ?string => $names[$criterion->value_id] ?? null)
                ->filter()
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all())
            ->all();
    }
}
