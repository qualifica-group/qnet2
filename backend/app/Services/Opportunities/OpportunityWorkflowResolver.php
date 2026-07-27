<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\WorkflowStatusSystemKey;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowCriterion;
use App\Models\OpportunityWorkflowStatus;
use App\Support\OpportunityWorkflows\CriterionFieldRegistry;
use Illuminate\Support\Collection;

/**
 * Centralized workflow resolution (spec 0047, scope item): the SINGLE point
 * that decides which OpportunityWorkflow (or the global default set, when
 * null) applies to an Opportunity, and which OpportunityWorkflowStatus row it
 * should carry — consumed by OpportunityService::create()/update() and, via
 * resolveAndAssign(), by the Lane A configurator's delete-reassign flow.
 * Never duplicated at either call site (constraints §"unico punto usato da
 * OpportunityService... nessuna duplicazione lato frontend").
 *
 * Both `activeWorkflows()` (the domain-wide candidate set, spec 0047) and
 * `statusesFor()`'s result per workflow are memoized on THIS instance (spec
 * 0054, per-row workflow-status options on the request-management grid):
 * resolving a whole PAGE of rows calls `resolve()`/`statusesFor()` once per
 * row, and both queries return the SAME rows regardless of which opportunity
 * is being resolved — repeating them per row would be a page-sized batch of
 * redundant identical queries. Memoizing here (rather than at each call site)
 * benefits every consumer transparently, with no change to any signature or
 * return value.
 */
final class OpportunityWorkflowResolver
{
    /**
     * @var Collection<int, OpportunityWorkflow>|null
     */
    private ?Collection $activeWorkflowsCache = null;

    /**
     * @var array<int|string, Collection<int, OpportunityWorkflowStatus>>
     */
    private array $statusesCache = [];

    public function __construct(private readonly CriterionFieldRegistry $fieldRegistry) {}

    /**
     * The active workflow that matches $opportunity (AC-010/011/012/013/014):
     * every one of a workflow's criteria must match (AND), the workflow with
     * the MOST matching criteria wins (specificity), ties broken by id asc
     * (D4). Null when no active workflow matches — the caller falls back to
     * the global default set.
     */
    public function resolve(Opportunity $opportunity): ?OpportunityWorkflow
    {
        // Step 1: make sure productLines (business_function_id/
        // product_category_id, AC-013) and customFieldValueRow (a custom
        // relation criterion, AC-031/AC-032) are available without
        // triggering a query per workflow candidate.
        $opportunity->loadMissing(['productLines', 'customFieldValueRow']);

        // Step 2: every active workflow, with its criteria eager-loaded (no
        // N+1 across the candidate set) — memoized (see class docblock).
        $workflows = $this->activeWorkflows();

        // Step 3: keep only the workflows whose criteria ALL match.
        $matching = $workflows->filter(
            fn (OpportunityWorkflow $workflow): bool => $this->matches($opportunity, $workflow),
        );

        if ($matching->isEmpty()) {
            return null;
        }

        // Step 4: most specific (most criteria) wins; tie-break id asc (D4).
        return $matching
            ->sort(function (OpportunityWorkflow $a, OpportunityWorkflow $b): int {
                $bySpecificity = $b->criteria->count() <=> $a->criteria->count();

                return $bySpecificity !== 0 ? $bySpecificity : $a->id <=> $b->id;
            })
            ->first();
    }

    /**
     * @return Collection<int, OpportunityWorkflow>
     */
    private function activeWorkflows(): Collection
    {
        return $this->activeWorkflowsCache ??= OpportunityWorkflow::query()
            ->where('is_active', true)
            ->with('criteria')
            ->get();
    }

    /**
     * The ordered statuses of $workflow's own set, or the GLOBAL default set
     * (opportunity_workflow_id null) when $workflow is null — memoized per
     * workflow id (see class docblock).
     *
     * @return Collection<int, OpportunityWorkflowStatus>
     */
    public function statusesFor(?OpportunityWorkflow $workflow): Collection
    {
        $cacheKey = $workflow?->id ?? 'default';

        if (isset($this->statusesCache[$cacheKey])) {
            return $this->statusesCache[$cacheKey];
        }

        $query = OpportunityWorkflowStatus::query()->orderBy('sort_order');

        $statuses = $workflow === null
            ? $query->whereNull('opportunity_workflow_id')->get()
            : $query->where('opportunity_workflow_id', $workflow->id)->get();

        return $this->statusesCache[$cacheKey] = $statuses;
    }

    /**
     * The status $opportunity should carry within $workflow's resolved set
     * (D3): its current status verbatim when that status already belongs to
     * the set; otherwise the set's row sharing the current status'
     * system_key (open->open, closed_won->closed_won, closed_lost->
     * closed_lost); otherwise the set's initial 'open' row.
     */
    public function targetStatus(Opportunity $opportunity, ?OpportunityWorkflow $workflow): OpportunityWorkflowStatus
    {
        $statuses = $this->statusesFor($workflow);
        $currentStatusId = $opportunity->opportunity_workflow_status_id;

        if ($currentStatusId !== null) {
            $current = $statuses->firstWhere('id', $currentStatusId);

            if ($current !== null) {
                return $current;
            }
        }

        $currentSystemKey = $this->currentSystemKey($opportunity, $currentStatusId);

        if ($currentSystemKey !== null) {
            $mapped = $statuses->firstWhere('system_key', $currentSystemKey);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        $open = $statuses->firstWhere('system_key', WorkflowStatusSystemKey::Open->value);

        if ($open === null) {
            // Defense in depth: every set (a workflow's own, or the global
            // one) is seeded with its 3 system rows (AC-004/AC-005) — should
            // never happen.
            abort(500, 'The workflow status set has no open system row.');
        }

        return $open;
    }

    /**
     * Resolve $opportunity's workflow/status and PERSIST only the
     * `opportunity_workflow_status_id` column — the contract Lane A's
     * delete-reassign flow (and OpportunityService's fallback path) relies
     * on.
     */
    public function resolveAndAssign(Opportunity $opportunity): void
    {
        $workflow = $this->resolve($opportunity);
        $target = $this->targetStatus($opportunity, $workflow);

        $opportunity->opportunity_workflow_status_id = $target->id;
        $opportunity->save();
    }

    /**
     * Whether EVERY one of $workflow's criteria matches $opportunity (AND,
     * AC-013). A workflow with no criteria never matches (defense in depth:
     * the write path already requires min:1, AC-008, but an empty AND would
     * otherwise vacuously match everything). A criterion whose `field` is no
     * longer allow-listed (its custom field definition was disabled/
     * deleted, D10) never matches rather than throwing.
     */
    private function matches(Opportunity $opportunity, OpportunityWorkflow $workflow): bool
    {
        if ($workflow->criteria->isEmpty()) {
            return false;
        }

        return $workflow->criteria->every(
            fn (OpportunityWorkflowCriterion $criterion): bool => $this->fieldRegistry->isAllowed($criterion->field)
                && in_array(
                    $criterion->value_id,
                    $this->fieldRegistry->opportunityValues($opportunity, $criterion->field),
                    true,
                ),
        );
    }

    /**
     * $opportunity's CURRENT status' system_key, re-fetched when the
     * previously resolved set doesn't already carry it (an explicit,
     * single-row query — never a lazy-loaded access).
     */
    private function currentSystemKey(Opportunity $opportunity, ?int $currentStatusId): ?string
    {
        if ($currentStatusId === null) {
            return null;
        }

        $opportunity->loadMissing('workflowStatus');

        return $opportunity->workflowStatus?->system_key;
    }
}
