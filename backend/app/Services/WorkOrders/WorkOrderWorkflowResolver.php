<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowCriterion;
use App\Models\WorkOrderWorkflowStatus;
use App\Support\WorkOrderWorkflows\WorkOrderClassificationSource;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionFieldRegistry;
use Illuminate\Support\Collection;

/**
 * Centralized workflow resolution for work orders (spec 0322, D-6): the
 * SINGLE point that decides which WorkOrderWorkflow (or the global default
 * set, when null) applies to a WorkOrder and which status row it should
 * carry. Twin of App\Services\Quotes\QuoteWorkflowResolver.
 *
 * Both `activeWorkflows()` and `statusesFor()`'s result per workflow are
 * memoized on THIS instance (see forgetCaches()).
 *
 * Perf constraint: a batch caller (grid, reassignment) MUST eager-load
 * `quoteLines.product` in ONE query before resolving — resolve() step 1 only
 * ever `loadMissing()`s it, a no-op once already loaded.
 */
final class WorkOrderWorkflowResolver
{
    /**
     * @var Collection<int, WorkOrderWorkflow>|null
     */
    private ?Collection $activeWorkflowsCache = null;

    /**
     * @var array<int|string, Collection<int, WorkOrderWorkflowStatus>>
     */
    private array $statusesCache = [];

    public function __construct(
        private readonly WorkOrderCriterionFieldRegistry $fieldRegistry,
        private readonly WorkOrderClassificationSource $classification,
    ) {}

    /**
     * The active workflow that matches $workOrder: every one of a workflow's
     * criteria must match (AND), the workflow with the MOST criteria wins
     * (specificity), then the one whose category branch matched CLOSEST, ties
     * broken by id asc. Null when none matches (also a commessa with no
     * REVENUE line) — the caller falls back to the global default set.
     */
    public function resolve(WorkOrder $workOrder): ?WorkOrderWorkflow
    {
        // Step 1: the REVENUE lines' products, without a query per candidate.
        $workOrder->loadMissing('quoteLines.product');

        // Step 2: every active workflow with its criteria (memoized).
        $workflows = $this->activeWorkflows();

        // Step 3: keep only the workflows whose criteria ALL match.
        $matching = $workflows->filter(
            fn (WorkOrderWorkflow $workflow): bool => $this->matches($workOrder, $workflow),
        );

        if ($matching->isEmpty()) {
            return null;
        }

        // Step 4: most specific wins; then the CLOSEST category branch; then id asc.
        return $matching
            ->sort(function (WorkOrderWorkflow $a, WorkOrderWorkflow $b) use ($workOrder): int {
                $bySpecificity = $b->criteria->count() <=> $a->criteria->count();

                if ($bySpecificity !== 0) {
                    return $bySpecificity;
                }

                $byDepth = $this->matchDepth($workOrder, $a) <=> $this->matchDepth($workOrder, $b);

                return $byDepth !== 0 ? $byDepth : $a->id <=> $b->id;
            })
            ->first();
    }

    /**
     * Drops the memos so the next resolve() re-reads from the database.
     *
     * Needed by the callers that CHANGE the candidate set or the category
     * tree mid-request (WorkOrderWorkflowService::delete() deactivates the
     * workflow before reassigning its work orders).
     */
    public function forgetCaches(): void
    {
        $this->activeWorkflowsCache = null;
        $this->statusesCache = [];
        $this->classification->forgetCaches();
    }

    /**
     * The ordered statuses of $workflow's own set, or the GLOBAL default set
     * (work_order_workflow_id null) when $workflow is null — memoized per
     * workflow id.
     *
     * @return Collection<int, WorkOrderWorkflowStatus>
     */
    public function statusesFor(?WorkOrderWorkflow $workflow): Collection
    {
        $cacheKey = $workflow?->id ?? 'default';

        if (isset($this->statusesCache[$cacheKey])) {
            return $this->statusesCache[$cacheKey];
        }

        $query = WorkOrderWorkflowStatus::query()->orderBy('sort_order');

        $statuses = $workflow === null
            ? $query->whereNull('work_order_workflow_id')->get()
            : $query->where('work_order_workflow_id', $workflow->id)->get();

        return $this->statusesCache[$cacheKey] = $statuses;
    }

    /**
     * The status $workOrder should carry within $workflow's resolved set: its
     * current status verbatim when it already belongs to the set; otherwise
     * the set's row sharing the current status' system_key; otherwise the
     * set's initial 'open' row.
     */
    public function targetStatus(WorkOrder $workOrder, ?WorkOrderWorkflow $workflow): WorkOrderWorkflowStatus
    {
        $statuses = $this->statusesFor($workflow);
        $currentStatusId = $workOrder->work_order_workflow_status_id;

        if ($currentStatusId !== null) {
            $current = $statuses->firstWhere('id', $currentStatusId);

            if ($current !== null) {
                return $current;
            }
        }

        $currentSystemKey = $this->currentSystemKey($workOrder, $currentStatusId);

        if ($currentSystemKey !== null) {
            $mapped = $statuses->firstWhere('system_key', $currentSystemKey);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        $open = $statuses->firstWhere('system_key', WorkOrderWorkflowStatusSystemKey::Open->value);

        if ($open === null) {
            // Defense in depth: every set is seeded with its system rows.
            abort(500, 'The work order workflow status set has no open system row.');
        }

        return $open;
    }

    /**
     * Resolve $workOrder's workflow/status and PERSIST only the
     * `work_order_workflow_status_id` column (plain reassignment, never a
     * user-driven change). The column is outside #[Fillable], hence
     * forceFill().
     */
    public function resolveAndAssign(WorkOrder $workOrder): void
    {
        $target = $this->targetStatus($workOrder, $this->resolve($workOrder));

        $workOrder->forceFill(['work_order_workflow_status_id' => $target->id])->save();
    }

    /**
     * @return Collection<int, WorkOrderWorkflow>
     */
    private function activeWorkflows(): Collection
    {
        return $this->activeWorkflowsCache ??= WorkOrderWorkflow::query()
            ->where('is_active', true)
            ->with('criteria')
            ->get();
    }

    /**
     * Whether EVERY one of $workflow's criteria matches $workOrder (AND). A
     * workflow with no criteria never matches (an empty AND would otherwise
     * vacuously match everything), nor does a criterion whose `field` is no
     * longer allow-listed.
     */
    private function matches(WorkOrder $workOrder, WorkOrderWorkflow $workflow): bool
    {
        if ($workflow->criteria->isEmpty()) {
            return false;
        }

        return $workflow->criteria->every(
            fn (WorkOrderWorkflowCriterion $criterion): bool => $this->fieldRegistry->isAllowed($criterion->field)
                && in_array(
                    $criterion->value_id,
                    $this->fieldRegistry->workOrderValues($workOrder, $criterion->field),
                    true,
                ),
        );
    }

    /**
     * How far up the category tree $workflow matched $workOrder: zero when
     * the workflow has no branch criterion, so an exact-category match always
     * outranks an ancestor match.
     */
    private function matchDepth(WorkOrder $workOrder, WorkOrderWorkflow $workflow): int
    {
        $criterion = $workflow->criteria->firstWhere('field', WorkOrderCriterionFieldRegistry::BRANCH_FIELD);

        if ($criterion === null) {
            return 0;
        }

        return $this->fieldRegistry->branchDistances($workOrder)[$criterion->value_id] ?? 0;
    }

    private function currentSystemKey(WorkOrder $workOrder, ?int $currentStatusId): ?string
    {
        if ($currentStatusId === null) {
            return null;
        }

        $workOrder->loadMissing('workOrderWorkflowStatus');

        return $workOrder->workOrderWorkflowStatus?->system_key;
    }
}
