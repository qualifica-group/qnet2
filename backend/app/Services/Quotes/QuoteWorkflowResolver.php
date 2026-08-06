<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\WorkflowStatusSystemKey;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowCriterion;
use App\Models\QuoteWorkflowStatus;
use App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry;
use Illuminate\Support\Collection;

/**
 * Centralized workflow resolution (spec 0047, moved onto the Offerta by spec
 * 0083 D-1/D-3): the SINGLE point that decides which QuoteWorkflow (or the
 * global default set, when null) applies to a Quote, and which
 * QuoteWorkflowStatus row it should carry — consumed by
 * QuoteService::create()/update() and, via resolveAndAssign(), by the
 * configurator's delete-reassign flow. Never duplicated at either call site.
 *
 * Both `activeWorkflows()` (the domain-wide candidate set) and
 * `statusesFor()`'s result per workflow are memoized on THIS instance:
 * resolving a Quote calls `resolve()`/`statusesFor()` at most once per
 * request, and both queries return the SAME rows regardless of which quote
 * is being resolved.
 *
 * Perf constraint (spec 0083): the caller MUST eager-load
 * `offerLines.product.category` and `opportunity` (+ its
 * `customFieldValueRow`) in ONE query before resolving a batch — this
 * resolver's own `resolve()` step 1 only ever `loadMissing()`s them, a no-op
 * once already loaded.
 */
final class QuoteWorkflowResolver
{
    /**
     * @var Collection<int, QuoteWorkflow>|null
     */
    private ?Collection $activeWorkflowsCache = null;

    /**
     * @var array<int|string, Collection<int, QuoteWorkflowStatus>>
     */
    private array $statusesCache = [];

    public function __construct(private readonly QuoteCriterionFieldRegistry $fieldRegistry) {}

    /**
     * The active workflow that matches $quote (AC-010/011/012/013/014):
     * every one of a workflow's criteria must match (AND), the workflow with
     * the MOST matching criteria wins (specificity), ties broken by id asc.
     * Null when no active workflow matches — the caller falls back to the
     * global default set.
     */
    public function resolve(Quote $quote): ?QuoteWorkflow
    {
        // Step 1: make sure offerLines.product.category (business_function_id/
        // product_category_id, D-7) and opportunity(+customFieldValueRow)
        // (the inherited state_id/source_id/custom.* criteria, D-7) are
        // available without triggering a query per workflow candidate.
        $quote->loadMissing(['offerLines.product.category', 'opportunity.customFieldValueRow']);

        // Step 2: every active workflow, with its criteria eager-loaded (no
        // N+1 across the candidate set) — memoized (see class docblock).
        $workflows = $this->activeWorkflows();

        // Step 3: keep only the workflows whose criteria ALL match.
        $matching = $workflows->filter(
            fn (QuoteWorkflow $workflow): bool => $this->matches($quote, $workflow),
        );

        if ($matching->isEmpty()) {
            return null;
        }

        // Step 4: most specific (most criteria) wins; tie-break id asc.
        return $matching
            ->sort(function (QuoteWorkflow $a, QuoteWorkflow $b): int {
                $bySpecificity = $b->criteria->count() <=> $a->criteria->count();

                return $bySpecificity !== 0 ? $bySpecificity : $a->id <=> $b->id;
            })
            ->first();
    }

    /**
     * Drops both memos so the next resolve() re-reads from the database.
     *
     * Needed by the one caller that CHANGES the candidate set mid-request:
     * QuoteWorkflowService::delete() deactivates the workflow before
     * reassigning its quotes, and without this the reassignment would keep
     * resolving onto the very workflow being removed.
     */
    public function forgetCaches(): void
    {
        $this->activeWorkflowsCache = null;
        $this->statusesCache = [];
    }

    /**
     * @return Collection<int, QuoteWorkflow>
     */
    private function activeWorkflows(): Collection
    {
        return $this->activeWorkflowsCache ??= QuoteWorkflow::query()
            ->where('is_active', true)
            ->with('criteria')
            ->get();
    }

    /**
     * The ordered statuses of $workflow's own set, or the GLOBAL default set
     * (quote_workflow_id null) when $workflow is null — memoized per
     * workflow id (see class docblock).
     *
     * @return Collection<int, QuoteWorkflowStatus>
     */
    public function statusesFor(?QuoteWorkflow $workflow): Collection
    {
        $cacheKey = $workflow?->id ?? 'default';

        if (isset($this->statusesCache[$cacheKey])) {
            return $this->statusesCache[$cacheKey];
        }

        $query = QuoteWorkflowStatus::query()->orderBy('sort_order');

        $statuses = $workflow === null
            ? $query->whereNull('quote_workflow_id')->get()
            : $query->where('quote_workflow_id', $workflow->id)->get();

        return $this->statusesCache[$cacheKey] = $statuses;
    }

    /**
     * The status $quote should carry within $workflow's resolved set (D-3):
     * its current status verbatim when that status already belongs to the
     * set; otherwise the set's row sharing the current status' system_key
     * (open->open, closed_won->closed_won, closed_lost->closed_lost, AC-022);
     * otherwise the set's initial 'open' row (AC-020).
     */
    public function targetStatus(Quote $quote, ?QuoteWorkflow $workflow): QuoteWorkflowStatus
    {
        $statuses = $this->statusesFor($workflow);
        $currentStatusId = $quote->quote_workflow_status_id;

        if ($currentStatusId !== null) {
            $current = $statuses->firstWhere('id', $currentStatusId);

            if ($current !== null) {
                return $current;
            }
        }

        $currentSystemKey = $this->currentSystemKey($quote, $currentStatusId);

        if ($currentSystemKey !== null) {
            $mapped = $statuses->firstWhere('system_key', $currentSystemKey);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        $open = $statuses->firstWhere('system_key', WorkflowStatusSystemKey::Open->value);

        if ($open === null) {
            // Defense in depth: every set (a workflow's own, or the global
            // one) is seeded with its system rows (AC-004/AC-005) — should
            // never happen.
            abort(500, 'The workflow status set has no open system row.');
        }

        return $open;
    }

    /**
     * Resolve $quote's workflow/status and PERSIST only the
     * `quote_workflow_status_id` column — the configurator's delete-reassign
     * flow relies on this plain, note-free reassignment (never a user-driven
     * advance, so QuoteWorkflowStatusWriter is never involved here).
     */
    public function resolveAndAssign(Quote $quote): void
    {
        $workflow = $this->resolve($quote);
        $target = $this->targetStatus($quote, $workflow);

        $quote->quote_workflow_status_id = $target->id;
        $quote->save();
    }

    /**
     * Whether EVERY one of $workflow's criteria matches $quote (AND,
     * AC-013). A workflow with no criteria never matches (defense in depth:
     * the write path already requires min:1, AC-008, but an empty AND would
     * otherwise vacuously match everything). A criterion whose `field` is no
     * longer allow-listed (its custom field definition was disabled/
     * deleted, D10) never matches rather than throwing.
     */
    private function matches(Quote $quote, QuoteWorkflow $workflow): bool
    {
        if ($workflow->criteria->isEmpty()) {
            return false;
        }

        return $workflow->criteria->every(
            fn (QuoteWorkflowCriterion $criterion): bool => $this->fieldRegistry->isAllowed($criterion->field)
                && in_array(
                    $criterion->value_id,
                    $this->fieldRegistry->quoteValues($quote, $criterion->field),
                    true,
                ),
        );
    }

    /**
     * $quote's CURRENT status' system_key, re-fetched when the previously
     * resolved set doesn't already carry it (an explicit, single-row query —
     * never a lazy-loaded access).
     */
    private function currentSystemKey(Quote $quote, ?int $currentStatusId): ?string
    {
        if ($currentStatusId === null) {
            return null;
        }

        $quote->loadMissing('quoteWorkflowStatus');

        return $quote->quoteWorkflowStatus?->system_key;
    }
}
