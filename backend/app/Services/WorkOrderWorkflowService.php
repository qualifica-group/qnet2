<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\WorkOrderWorkflows\CreateWorkOrderWorkflowData;
use App\DataObjects\WorkOrderWorkflows\UpdateWorkOrderWorkflowData;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowCriterion;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
use App\Services\WorkOrderWorkflows\WorkOrderWorkflowStatusWriter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `work-order-workflows` configurator resource (spec
 * 0322, D-6): create/update/delete of a workflow plus its criteria/statuses
 * child collections, and the global default status set. Twin of
 * QuoteWorkflowService.
 *
 * create()/update() only ever write THIS workflow's own rows; delete() uses
 * the resolver to re-resolve every work order left referencing one of the
 * deleted workflow's statuses — the SAME resolver the work order service
 * uses, never duplicated here.
 */
class WorkOrderWorkflowService
{
    public function __construct(
        private readonly WorkOrderWorkflowStatusWriter $statusWriter,
        private readonly WorkOrderWorkflowResolver $resolver,
    ) {}

    public function loadDetail(WorkOrderWorkflow $workflow): WorkOrderWorkflow
    {
        return $workflow->load([
            'criteria',
            'statuses' => fn ($query) => $query->orderBy('sort_order'),
        ]);
    }

    /**
     * Creates the workflow, its criteria, and its status set — the mandatory
     * system rows plus $data->statuses' custom rows — atomically.
     */
    public function create(CreateWorkOrderWorkflowData $data): WorkOrderWorkflow
    {
        $workflow = DB::transaction(function () use ($data): WorkOrderWorkflow {
            // Step 1: the criteria combination must be unique (defense in
            // depth beyond the FormRequest).
            $this->assertSignatureUnique(CreateWorkOrderWorkflowData::computeSignature($data->criteria), excludeWorkflowId: null);

            // Step 2: the workflow, its criteria and its status set.
            $workflow = WorkOrderWorkflow::query()->create($data->attributes());

            $this->syncCriteria($workflow, $data->criteria);
            $this->statusWriter->createWithCustoms($workflow->id, $data->statuses, $data->openStatus, $data->completedStatus, $data->closedStatus);

            return $workflow;
        });

        return $this->loadDetail($workflow);
    }

    /**
     * Partial update: `name`/`is_active` when submitted; `criteria` re-synced
     * and revalidated when submitted; `statuses` synced (custom rows
     * full-replace, system rows descriptive-only) when submitted — every
     * sub-part independently optional (PATCH).
     */
    public function update(WorkOrderWorkflow $workflow, UpdateWorkOrderWorkflowData $data): WorkOrderWorkflow
    {
        DB::transaction(function () use ($workflow, $data): void {
            if ($data->hasCriteria()) {
                $this->assertSignatureUnique(CreateWorkOrderWorkflowData::computeSignature($data->criteria), excludeWorkflowId: $workflow->id);
            }

            // Unconditional save: fires the model's saved event even when no
            // native attribute changed (e.g. only `statuses` submitted).
            $workflow->fill($data->submittedAttributes())->save();

            if ($data->hasCriteria()) {
                $this->syncCriteria($workflow, $data->criteria);
            }

            if ($data->hasStatuses()) {
                $this->statusWriter->syncCustoms($workflow->id, $data->statuses);
            }
        });

        return $this->loadDetail($workflow->fresh());
    }

    /**
     * Deletes the workflow (cascading its criteria/statuses at the DB layer),
     * after RE-RESOLVING every work order that referenced one of its
     * statuses onto whatever set now applies to it, so none is left
     * orphaned.
     *
     * `quoteLines.product` is eager-loaded UP FRONT: the resolver's own
     * `resolve()` only `loadMissing()`s it, a query PER ROW otherwise.
     */
    public function delete(WorkOrderWorkflow $workflow): void
    {
        DB::transaction(function () use ($workflow): void {
            $impactedIds = WorkOrder::query()
                ->whereIn('work_order_workflow_status_id', $workflow->statuses()->pluck('id'))
                ->pluck('id');

            // Step 1: take the workflow out of the candidate set BEFORE
            // reassigning, so the resolver cannot hand its work orders
            // straight back to the workflow that is about to disappear.
            $workflow->update(['is_active' => false]);
            $this->resolver->forgetCaches();

            // Step 2: move every impacted work order off the doomed status
            // rows. This MUST precede the delete: the column is NOT NULL and
            // `restrictOnDelete`, so the DB refuses to cascade the status rows
            // away while a work order still points at one.
            WorkOrder::query()
                ->whereIn('id', $impactedIds)
                ->with('quoteLines.product')
                ->get()
                ->each(fn (WorkOrder $workOrder) => $this->resolver->resolveAndAssign($workOrder));

            // Step 3: nothing references the set any more.
            $workflow->delete();
        });
    }

    /**
     * Authoritative sync of the GLOBAL default status set
     * (work_order_workflow_id null): same custom-rows-sync/system-rows-guard
     * rules as a workflow's own set, via the same scoped writer.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>  $statuses
     * @return EloquentCollection<int, WorkOrderWorkflowStatus>
     */
    public function syncDefaultStatuses(array $statuses): EloquentCollection
    {
        $this->statusWriter->syncCustoms(null, $statuses);

        return $this->defaultStatuses();
    }

    /**
     * @return EloquentCollection<int, WorkOrderWorkflowStatus>
     */
    public function defaultStatuses(): EloquentCollection
    {
        return WorkOrderWorkflowStatus::query()
            ->whereNull('work_order_workflow_id')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Full-replace sync of $workflow's criteria (delete-all + insert),
     * idempotent within the surrounding transaction.
     *
     * @param  array<int, array{field: string, value_id: int}>  $criteria
     */
    private function syncCriteria(WorkOrderWorkflow $workflow, array $criteria): void
    {
        $workflow->criteria()->delete();

        foreach ($criteria as $criterion) {
            $workflow->criteria()->create($criterion);
        }
    }

    /**
     * No two workflows may share the exact same criteria combination. There is
     * no signature column: the combination is compared in memory over the
     * (small) set of configured workflows.
     */
    private function assertSignatureUnique(string $signature, ?int $excludeWorkflowId): void
    {
        $duplicate = WorkOrderWorkflowCriterion::query()
            ->when($excludeWorkflowId !== null, fn ($query) => $query->where('work_order_workflow_id', '!=', $excludeWorkflowId))
            ->get(['work_order_workflow_id', 'field', 'value_id'])
            ->groupBy('work_order_workflow_id')
            ->contains(fn ($criteria): bool => CreateWorkOrderWorkflowData::computeSignature(
                $criteria->map(static fn (WorkOrderWorkflowCriterion $criterion): array => $criterion->only(['field', 'value_id'])),
            ) === $signature);

        if ($duplicate) {
            abort(422, 'A workflow with this exact combination of criteria already exists.');
        }
    }
}
