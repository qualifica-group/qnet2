<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\QuoteLineType;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use Illuminate\Support\Collection;

/**
 * Writes a quote's submitted line sets (full-replace per tab, D-8), covering
 * $opportunity's `opportunity_product_lines` for REVENUE lines before they
 * are written (D-7) so a coverage failure leaves NOTHING persisted for that
 * tab. Extracted out of `QuoteService` (spec 0087, R-4/team-lead directive):
 * that class was at 499/500 lines with no room left, and this pair of
 * methods (`writeSubmittedLines`/`coverOpportunity`) is the most self-
 * contained pull — its only two dependencies (`QuoteLineWriter`,
 * `OpportunityProductLineCoverage`) are not shared with any other
 * QuoteService private method, and it carries no state across create()/
 * update() calls. A PURE relocation: no behaviour change from the code that
 * used to live in QuoteService.
 */
final class QuoteLineCoverageWriter
{
    /**
     * The error field a REVENUE line's coverage failure is reported under
     * (OpportunityProductLineCoverage::ensure()'s $errorField) — distinct from
     * the opportunities picker's own `products_of_interest` key.
     */
    private const string COVERAGE_ERROR_FIELD = 'offer_lines';

    public function __construct(
        private readonly QuoteLineWriter $lineWriter,
        private readonly OpportunityProductLineCoverage $coverage,
    ) {}

    /**
     * Writes whichever of $offerLines/$costLines is non-null (the caller's
     * "was this key submitted" signal) — $offerLines additionally covers
     * $opportunity's product lines (D-7) before the REVENUE rows are
     * written, so a coverage failure leaves NOTHING persisted for this tab.
     *
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    public function writeSubmitted(Quote $quote, ?Opportunity $opportunity, ?array $offerLines, ?array $costLines): void
    {
        if ($offerLines !== null) {
            $this->coverOpportunity($opportunity, $offerLines);
            $this->lineWriter->sync($quote, QuoteLineType::Revenue, $offerLines);
        }

        if ($costLines !== null) {
            $this->lineWriter->sync($quote, QuoteLineType::Cost, $costLines);
        }
    }

    /**
     * Resolves the products referenced by $lines (with their category) and
     * ensures $opportunity's `opportunity_product_lines` cover every one of
     * them (D-7, shared OpportunityProductLineCoverage — AC-050/051/052/053).
     * COST lines never call this (D-7): only REVENUE lines can trigger it,
     * enforced by the single call site in writeSubmitted().
     *
     * @param  array<int, QuoteLineData>  $lines
     */
    private function coverOpportunity(?Opportunity $opportunity, array $lines): void
    {
        if ($opportunity === null || $lines === []) {
            return;
        }

        $productIds = array_values(array_unique(array_map(
            static fn (QuoteLineData $line): int => $line->productId,
            $lines,
        )));

        /** @var Collection<int, Product> $products */
        $products = Product::query()->with('category')->whereIn('id', $productIds)->get();

        $this->coverage->ensure($opportunity, $products, self::COVERAGE_ERROR_FIELD);
    }
}
