<?php

declare(strict_types=1);

namespace App\Support\WorkOrderWorkflows;

use App\Models\WorkOrderWorkflowCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batch-resolves the human-readable `name` a criterion's `value_id` points at
 * (`value_label` of the work order workflow resource, spec 0322): grouped by
 * WorkOrderCriterionFieldRegistry::existsTable() and read with ONE query per
 * distinct table, never per row. A criterion whose referenced row is gone, or
 * whose field is no longer allow-listed, falls back to its raw `value_id`.
 */
final class WorkOrderCriterionValueLabelResolver
{
    public function __construct(private readonly WorkOrderCriterionFieldRegistry $registry) {}

    /**
     * @param  Collection<int, WorkOrderWorkflowCriterion>  $criteria
     * @return array<int, string> criterion id => resolved label
     */
    public function resolve(Collection $criteria): array
    {
        if ($criteria->isEmpty()) {
            return [];
        }

        $namesByTable = $criteria
            ->filter(fn (WorkOrderWorkflowCriterion $criterion): bool => $this->registry->isAllowed($criterion->field))
            ->groupBy(fn (WorkOrderWorkflowCriterion $criterion): string => $this->registry->existsTable($criterion->field))
            ->map(fn (Collection $rows, string $table): Collection => DB::table($table)
                ->whereIn('id', $rows->pluck('value_id')->unique()->all())
                ->pluck('name', 'id'));

        return $criteria
            ->mapWithKeys(function (WorkOrderWorkflowCriterion $criterion) use ($namesByTable): array {
                if (! $this->registry->isAllowed($criterion->field)) {
                    return [$criterion->id => (string) $criterion->value_id];
                }

                $label = $namesByTable->get($this->registry->existsTable($criterion->field))?->get($criterion->value_id);

                return [$criterion->id => $label ?? (string) $criterion->value_id];
            })
            ->all();
    }
}
