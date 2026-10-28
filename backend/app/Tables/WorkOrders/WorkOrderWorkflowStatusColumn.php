<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The `work_order_workflow_status` ("Stato lavorazione", spec 0322) column of
 * the `work-orders` grid: the cell `{id, name, color}`, the row's own allowed
 * status ids, and the allow-listed `set` filter / sort / distinct values by
 * the related row's name (twin of QuoteRelationColumns' quote_workflow_status).
 * Split out of WorkOrderDerivedColumns (file-size split, engineering.md §6).
 *
 * The allowed ids come from WorkOrderWorkflowResolver, memoized per workflow
 * for the request, so a page costs one query per distinct workflow; the
 * caller eager-loads `quoteLines.product` so resolving never lazy-loads.
 */
final class WorkOrderWorkflowStatusColumn
{
    public const string COLUMN = 'work_order_workflow_status';

    public const string OPTIONS_KEY = 'work_order_workflow_status_options';

    private const string LABEL_COLUMN = 'name';

    /** Caps the WHERE IN cardinality of the set filter (defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly WorkOrderWorkflowResolver $resolver) {}

    /**
     * The row keys for $workOrder: the badge cell and the select editor's ids.
     *
     * @return array<string, mixed>
     */
    public function project(WorkOrder $workOrder): array
    {
        $status = $workOrder->workOrderWorkflowStatus;

        return [
            self::COLUMN => $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color],
            self::OPTIONS_KEY => $this->resolver
                ->statusesFor($this->resolver->resolve($workOrder))
                ->pluck('id')
                ->all(),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $values = $this->filterValues($filter);

        if ($values === []) {
            return;
        }

        $query->whereHas('workOrderWorkflowStatus', static function (Builder $statusQuery) use ($values): void {
            $statusQuery->whereIn(self::LABEL_COLUMN, $values);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(
            DB::table('work_order_workflow_statuses')
                ->select(self::LABEL_COLUMN)
                ->whereColumn('work_order_workflow_statuses.id', 'work_orders.work_order_workflow_status_id')
                ->limit(1),
            $direction,
        );
    }

    /**
     * Distinct status names among the work orders matching $query.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        return WorkOrderWorkflowStatus::query()
            ->toBase()
            ->whereIn('id', (clone $query)->select('work_orders.work_order_workflow_status_id'))
            ->when($search !== null && $search !== '', function (QueryBuilder $builder) use ($search): void {
                $builder->where(self::LABEL_COLUMN, 'like', '%'.$this->escapeLike((string) $search).'%');
            })
            ->groupBy(self::LABEL_COLUMN)
            ->orderBy(self::LABEL_COLUMN)
            ->limit($limit)
            ->pluck(self::LABEL_COLUMN)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
