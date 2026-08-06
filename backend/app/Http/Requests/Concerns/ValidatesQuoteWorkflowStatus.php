<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
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
