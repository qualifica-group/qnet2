<?php

declare(strict_types=1);

namespace App\Services\WorkOrderWorkflows;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * for-select feed of EVERY work-order workflow status (default set +
 * all workflows) for the "Stato lavorazione commessa" picker of a task
 * template row (spec 0323, D-5; ADR 0011). A template is not bound to one
 * workflow, so the list is grouped by workflow client-side.
 *
 * Statuses of an INACTIVE workflow are offered only when explicitly requested
 * via `ids[]` (edit-mode hydration of an already-picked value).
 */
final class WorkOrderWorkflowStatusForSelectService
{
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->baseQuery()
            ->where(static fn (Builder $q) => $q->whereNull('work_order_workflows.id')->orWhere('work_order_workflows.is_active', true));

        if ($query->hasSearch()) {
            $like = '%'.$query->search.'%';
            $base->where(static fn (Builder $q) => $q
                ->where('work_order_workflow_statuses.name', 'like', $like)
                ->orWhere('work_order_workflows.name', 'like', $like));
        }

        $result = $query->page($base, fn (Builder $q): Builder => $this->ordered($q));

        return $result->withItems($result->items->concat($this->hydratedIds($result, $query)));
    }

    /**
     * @return Builder<WorkOrderWorkflowStatus>
     */
    private function baseQuery(): Builder
    {
        return WorkOrderWorkflowStatus::query()
            ->leftJoin('work_order_workflows', 'work_order_workflows.id', '=', 'work_order_workflow_statuses.work_order_workflow_id')
            ->select(['work_order_workflow_statuses.*', 'work_order_workflows.name as work_order_workflow_name']);
    }

    /**
     * Default set first, then workflows by name, then the status order.
     *
     * @param  Builder<WorkOrderWorkflowStatus>  $builder
     * @return Builder<WorkOrderWorkflowStatus>
     */
    private function ordered(Builder $builder): Builder
    {
        return $builder
            ->orderByRaw('(work_order_workflows.id IS NULL) DESC')
            ->orderBy('work_order_workflows.name')
            ->orderBy('work_order_workflow_statuses.sort_order')
            ->orderBy('work_order_workflow_statuses.id');
    }

    /**
     * Requested `ids[]` not already on the page: bypasses search and the
     * active-workflow filter; the total is unaffected.
     *
     * @return Collection<int, WorkOrderWorkflowStatus>
     */
    private function hydratedIds(ForSelectResult $page, ForSelectQuery $query): Collection
    {
        $missing = array_values(array_diff($query->ids, $page->items->pluck('id')->all()));

        if ($missing === []) {
            return collect();
        }

        return $this->ordered($this->baseQuery()->whereIn('work_order_workflow_statuses.id', $missing))->get();
    }
}
