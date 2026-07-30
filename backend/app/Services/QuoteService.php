<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Enums\QuoteLineType;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Services\Commissions\QuoteLineCommissionWriter;
use App\Services\Concerns\GeneratesSequentialCode;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\Quotes\QuoteLineWriter;
use App\Services\Quotes\QuoteTotalsCalculator;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `quotes` resource (spec 0065): create/update (with
 * the server-generated QUO-0001 code, D-13; the Opportunity snapshot of the
 * 3 roles plus the sede operativa, D-3), the full-replace line sync per tab
 * (D-8), the REVENUE-only
 * opportunity coverage (D-7), and the persisted, always-recalculated
 * economic aggregates (D-9).
 */
class QuoteService
{
    use GeneratesSequentialCode;

    private const string CODE_PREFIX = 'QUO';

    private const string CODE_TABLE = 'quotes';

    private const string CODE_COLUMN = 'code';

    /**
     * The error field a REVENUE line's coverage failure is reported under
     * (OpportunityProductLineCoverage::ensure()'s $errorField) — distinct
     * from the opportunities picker's own `products_of_interest` key, since a
     * quote payload has no such field.
     */
    private const string COVERAGE_ERROR_FIELD = 'offer_lines';

    /**
     * Relations eager-loaded for the detail read tree (QuoteResource), so a
     * single query never N+1s.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'opportunity',
        'quoteStatus',
        'commercial',
        'reporter',
        'supervisor',
        'company',
        'companySite',
        // The site has no own name: its label is composed from the primary
        // address + city (OperationalSiteLabel), so both are eager-loaded.
        'operationalSite.addresses.city',
        'offerLines.product.category',
        'offerLines.quote',
        'offerLines.vatRate',
        'offerLines.commissions.recipient',
        'costLines.product.category',
        'costLines.quote',
        'costLines.vatRate',
        'costLines.commissions.recipient',
    ];

    public function __construct(
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly QuoteLineWriter $lineWriter,
        private readonly QuoteTotalsCalculator $totalsCalculator,
        private readonly OpportunityProductLineCoverage $coverage,
        private readonly QuoteLineCommissionWriter $commissionWriter,
    ) {}

    public function loadDetail(Quote $quote): Quote
    {
        return $quote->load(self::DETAIL_RELATIONS);
    }

    /**
     * The next sequential code (QUO-0001...) as a non-binding suggestion for
     * the create form's auto-fill (D-13). Lock-free: the binding value is
     * still resolved atomically in create().
     */
    public function previewNextCode(): string
    {
        return $this->peekNextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
    }

    /**
     * Create a new quote. A manual `code` (D-13) is persisted as submitted;
     * otherwise one is generated inside the transaction with a pessimistic
     * lock, so two concurrent creates never collide.
     */
    public function create(CreateQuoteData $data): Quote
    {
        $quote = DB::transaction(function () use ($data): Quote {
            // Step 1: resolve the opportunity and snapshot its 3 commercial
            // roles (D-3) for every one of them the client did NOT submit.
            $opportunity = Opportunity::findOrFail($data->opportunityId);
            $attributes = $this->applySnapshotDefaults($data, $opportunity);

            // Step 2: default the working status to the system 'new' row
            // (AC-023) when the client omitted it.
            $attributes['quote_status_id'] ??= $this->systemStatusGuard->resolveNewStatusId(QuoteStatus::class);

            // Step 3: `code` is deliberately absent from Quote's #[Fillable]
            // (D-13), so it is assigned directly AFTER the fillable
            // attributes, mirroring ProjectService::create().
            $quote = new Quote($attributes);
            $quote->code = $data->code ?? $this->nextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
            $quote->save();

            // Step 4: write the submitted line sets (full-replace, D-8) and
            // cover the opportunity for REVENUE lines only (D-7).
            $this->writeSubmittedLines($quote, $opportunity, $data->offerLines, $data->costLines);

            // Step 5: persist the recalculated aggregates (D-9).
            $this->persistAggregates($quote);

            return $quote;
        });

        return $this->loadDetail($quote);
    }

    /**
     * Update an existing quote. Only the submitted scalar keys are touched
     * (partial PATCH); `opportunity_id`/`code` never reach $data (rejected
     * upstream as immutable, AC-025/AC-069). A line set is full-replaced
     * ONLY when its own key was submitted (AC-036/037); the aggregates are
     * ALWAYS recalculated, even on a scalar-only PATCH (AC-041).
     */
    public function update(Quote $quote, UpdateQuoteData $data): Quote
    {
        DB::transaction(function () use ($quote, $data): void {
            // Unconditional save: mirrors OpportunityService/ProjectService's
            // own update() — a clean save runs no UPDATE query.
            $quote->fill($data->submittedAttributes())->save();

            $this->writeSubmittedLines(
                $quote,
                $data->hasOfferLines() ? Opportunity::findOrFail($quote->opportunity_id) : null,
                $data->offerLines,
                $data->costLines,
            );

            if ($data->commercialIdSubmitted || $data->reporterIdSubmitted || $data->supervisorIdSubmitted) {
                $quote->offerLines()->get()->each(
                    fn ($line) => $this->commissionWriter->sync($line, null),
                );
            }

            $this->persistAggregates($quote);
        });

        return $this->loadDetail($quote);
    }

    /**
     * Delete the quote. `quote_lines` cascade away via their own FK
     * (AC-026); the linked Opportunity/Product/VatRate rows are untouched.
     */
    public function delete(Quote $quote): void
    {
        $quote->delete();
    }

    /**
     * Overwrite $attributes' D-3 snapshot fields with the Opportunity's
     * CURRENT value for every one NOT submitted by the client (AC-020); a
     * submitted value — even null — always wins (AC-021).
     *
     * `operational_site_id` (user directive 2026-07-30) is inherited on the
     * same terms as the 3 commercial roles: the Opportunity owns one (spec
     * 0056) and a new quote starts from it, then diverges freely.
     * `company_id`/`company_site_id` are NOT here: the Opportunity has no
     * such columns to inherit from.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applySnapshotDefaults(CreateQuoteData $data, Opportunity $opportunity): array
    {
        $attributes = $data->attributes();

        $attributes['commercial_id'] = $data->commercialIdSubmitted ? $data->commercialId : $opportunity->commercial_id;
        $attributes['reporter_id'] = $data->reporterIdSubmitted ? $data->reporterId : $opportunity->reporter_id;
        $attributes['supervisor_id'] = $data->supervisorIdSubmitted ? $data->supervisorId : $opportunity->supervisor_id;
        $attributes['operational_site_id'] = $data->operationalSiteIdSubmitted
            ? $data->operationalSiteId
            : $opportunity->operational_site_id;

        return $attributes;
    }

    /**
     * Writes whichever of $offerLines/$costLines is non-null (the caller's
     * "was this key submitted" signal) — $offerLines additionally covers
     * $opportunity's product lines (D-7) before the REVENUE rows are
     * written, so a coverage failure leaves NOTHING persisted for this tab.
     *
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    private function writeSubmittedLines(Quote $quote, ?Opportunity $opportunity, ?array $offerLines, ?array $costLines): void
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
     * enforced by the single call site in writeSubmittedLines().
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

    /**
     * Recalculates and persists the 5 header aggregates (D-9) from the
     * lines currently in the database — always, independent of which (if
     * any) line set this write actually touched. The columns are outside
     * Quote's #[Fillable] (D-9), so they are written via forceFill(),
     * mirroring OpportunityService's own `name` assignment.
     */
    private function persistAggregates(Quote $quote): void
    {
        $totals = $this->totalsCalculator->aggregates($quote);

        $quote->forceFill([
            'revenue_net' => $totals['revenue_net'],
            'revenue_vat' => $totals['revenue_vat'],
            'cost_net' => $totals['cost_net'],
            'cost_vat' => $totals['cost_vat'],
            'margin_net' => $totals['margin_net'],
        ])->save();
    }
}
