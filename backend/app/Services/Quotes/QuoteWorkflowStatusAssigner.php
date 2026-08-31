<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\WorkflowStatusSystemKey;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;

/**
 * Owns `quotes.quote_workflow_status_id` on the write side (spec 0083,
 * D-1/D-8): the create-time bootstrap placeholder (`globalDefaultOpenStatusId()`)
 * and the single entry point that resolves the FINAL baseline and applies an
 * explicit client override on top (`assign()`), both used identically by
 * `QuoteService::create()`/`update()`.
 *
 * Extracted out of `QuoteService` (spec 0087, R-4/team-lead directive, second
 * split): its two dependencies, `QuoteWorkflowResolver` and
 * `QuoteWorkflowStatusWriter`, are used ONLY by these two methods — nothing
 * else in QuoteService touches them — so this is the boundary with the least
 * cross-dependency of the two remaining candidates (the other being the
 * aggregates/name pair). A PURE relocation: no behaviour change from the
 * code that used to live in QuoteService.
 */
final class QuoteWorkflowStatusAssigner
{
    public function __construct(
        private readonly QuoteWorkflowResolver $workflowResolver,
        private readonly QuoteWorkflowStatusWriter $workflowStatusWriter,
    ) {}

    /**
     * The single write-side entry point for `quote_workflow_status_id`:
     * resolves the baseline the criteria-matched workflow set (or the
     * global default) assigns THIS offer right now (AC-020/022, verbatim/
     * system_key/open precedence — see QuoteWorkflowResolver::targetStatus()),
     * then — when the client explicitly submitted a DIFFERENT status —
     * advances onto it through QuoteWorkflowStatusWriter, the ONE choke
     * point that enforces set-membership (AC-021) and the mandatory-note
     * rule (AC-023/024/025).
     */
    public function assign(Quote $quote, ?int $submittedStatusId, ?string $note, User $actor): void
    {
        $workflow = $this->workflowResolver->resolve($quote);
        $quote->quote_workflow_status_id = $this->workflowResolver->targetStatus($quote, $workflow)->id;

        if ($submittedStatusId !== null && $submittedStatusId !== $quote->quote_workflow_status_id) {
            $this->workflowStatusWriter->apply($quote, $submittedStatusId, $actor, $note);
        }
    }

    /**
     * The `open` row of the GLOBAL default set (`quote_workflow_id` null) —
     * the bootstrap placeholder QuoteService::create() inserts a brand-new
     * Quote with before its own criteria (the REVENUE offer lines) are
     * persisted. Sharing this row's `system_key` is what lets
     * QuoteWorkflowResolver::targetStatus() correctly remap it onto the
     * FINAL resolved set right after (system_key match, never a hardcoded
     * assumption).
     */
    public function globalDefaultOpenStatusId(): int
    {
        $id = QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', WorkflowStatusSystemKey::Open->value)
            ->value('id');

        if ($id === null) {
            // Defense in depth: the global set is always seeded with its
            // `open` row (AC-004/AC-005) — should never happen.
            abort(500, 'The global default quote workflow status set has no open system row.');
        }

        return (int) $id;
    }
}
