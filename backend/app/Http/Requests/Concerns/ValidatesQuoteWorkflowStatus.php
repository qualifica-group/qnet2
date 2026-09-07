<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Services\Quotes\QuoteWorkflowResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

/**
 * Shared cross-field validation for the `quote_workflow_status_id` submitted
 * on the quote write endpoints (spec 0083, T-04, AC-021): the chosen status
 * must belong to the workflow set QuoteWorkflowResolver resolves for the
 * SUBMITTED (not-yet-persisted) `offer_lines`/`opportunity_id` — the exact
 * values the request is about to write, not whatever is currently on the
 * model — mirroring the former App\Http\Requests\Concerns\
 * ValidatesWorkflowStatus, whose transient record here is a Quote carrying
 * the submitted offer lines instead of an Opportunity's product lines.
 *
 * Not submitted, or submitted null, both skip validation entirely: either
 * means "let the resolver decide" (see CreateQuoteData/UpdateQuoteData
 * docblocks), never a value to check against a set.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesQuoteWorkflowStatus
{
    /**
     * Spec 0102, D-3: mirrors QuoteWorkflowStatusWriter::LINE_REQUIRED_GROUPS
     * — the destination `group`s that gate the transition on at least one
     * REVENUE line, keyed here on `offer_lines` (AC-044) rather than
     * `quote_workflow_status_id`, for a 422 the panel can attach to the
     * lines block instead of only toasting the writer's own rejection.
     *
     * @var list<WorkflowStatusGroup>
     */
    private const array LINE_REQUIRED_GROUPS = [
        WorkflowStatusGroup::ClosedWon,
        WorkflowStatusGroup::ClosedLost,
        WorkflowStatusGroup::Validated,
    ];

    /**
     * @param  Quote|null  $current  the persisted quote on an update (null on
     *                               create), the fallback source for the resolving Opportunity when the
     *                               submission left `offer_lines` untouched (partial PATCH)
     */
    protected function validateQuoteWorkflowStatus(Validator $validator, ?Quote $current = null): void
    {
        $submitted = $this->input('quote_workflow_status_id');

        if ($submitted === null || ! is_numeric($submitted)) {
            return;
        }

        $resolver = app(QuoteWorkflowResolver::class);
        $quote = $this->resolutionQuote($current);
        $workflow = $resolver->resolve($quote);
        $allowedIds = $resolver->statusesFor($workflow)->pluck('id')->all();

        if (! in_array((int) $submitted, $allowedIds, true)) {
            $validator->errors()->add(
                'quote_workflow_status_id',
                "The selected status does not belong to the offer's resolved workflow.",
            );
        }
    }

    /**
     * Spec 0102, D-3/AC-044/045: the panel's own mirror of
     * QuoteWorkflowStatusWriter::assertOfferLineForStatus() — same predicate
     * (a closing/validating `group` demands a REVENUE line), evaluated
     * against the SUBMITTED `offer_lines` when this payload carries them,
     * else $current's persisted ones (resolutionQuote()'s own fallback). The
     * writer stays the enforcing gate for every channel (spec 0102 D-3); this
     * only lets the panel surface the SAME rejection keyed on `offer_lines`
     * instead of a generic toast.
     *
     * Mirrors the writer's own AC-026 early return (spec 0083): a resend of
     * $current's OWN status is a no-op there, so it must never 422 here
     * either (AC-018) — this trait's sole caller, unlike the writer, has no
     * other reason to reject a same-status resubmission.
     */
    protected function validateQuoteWorkflowStatusRequiresOfferLine(Validator $validator, ?Quote $current = null): void
    {
        $submitted = $this->input('quote_workflow_status_id');

        if ($submitted === null || ! is_numeric($submitted)) {
            return;
        }

        if ($current !== null && (int) $submitted === $current->quote_workflow_status_id) {
            return; // AC-018: resending the current status is not an advance.
        }

        $targetStatus = QuoteWorkflowStatus::query()->find((int) $submitted);

        if ($targetStatus === null || ! in_array($targetStatus->group, self::LINE_REQUIRED_GROUPS, true)) {
            return;
        }

        if ($this->resolutionQuote($current)->offerLines->isNotEmpty()) {
            return;
        }

        $validator->errors()->add('offer_lines', __('quotes.offer_line_required_for_status'));
    }

    /**
     * A transient (never-persisted) Quote carrying the SUBMITTED
     * `offer_lines`/resolving Opportunity when present, falling back to
     * $current's persisted values for whichever the request left untouched.
     * `opportunity_id` is immutable on update (`prohibited`), so $current's
     * own opportunity is always the right fallback there.
     */
    private function resolutionQuote(?Quote $current): Quote
    {
        $quote = new Quote;

        $opportunityId = $this->has('opportunity_id')
            ? (int) $this->input('opportunity_id')
            : $current?->opportunity_id;

        $quote->setRelation(
            'opportunity',
            $opportunityId === null ? null : Opportunity::query()->with('customFieldValueRow')->find($opportunityId),
        );
        $quote->setRelation('offerLines', $this->resolutionOfferLines($current));

        return $quote;
    }

    /**
     * The submitted `offer_lines` rows, projected onto transient QuoteLine
     * models carrying their `product.category` relation (well-formed rows
     * only — a malformed row is already 422'd by ValidatesQuoteLines), when
     * present; else $current's PERSISTED offer lines (an explicit query,
     * never a lazy-loaded access).
     *
     * @return Collection<int, QuoteLine>
     */
    private function resolutionOfferLines(?Quote $current): Collection
    {
        $submitted = $this->input('offer_lines');

        if (! is_array($submitted)) {
            return $current?->offerLines()->with('product.category')->get() ?? collect();
        }

        $productIds = collect($submitted)
            ->filter(static fn (mixed $row): bool => is_array($row) && isset($row['product_id']))
            ->map(static fn (array $row): int => (int) $row['product_id'])
            ->unique()
            ->values();

        $productsById = Product::query()->with('category')->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($submitted)
            ->filter(static fn (mixed $row): bool => is_array($row) && isset($row['product_id']))
            ->map(static function (array $row) use ($productsById): QuoteLine {
                $line = new QuoteLine;
                $line->setRelation('product', $productsById->get((int) $row['product_id']));

                return $line;
            })
            ->values();
    }
}
