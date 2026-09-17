<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\DataObjects\Quotes\CreateQuoteData;
use App\Enums\CategoryManagementMode;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Quotes\ProductOfferLineResolver;
use App\Services\QuoteService;
use Illuminate\Validation\ValidationException;

/**
 * The ONE collegata Offerta generated when an Opportunity is born from a Lead
 * (spec 0094 D-3/AC-062, spec 0102 D-1/AC-020, spec 0140). Called by
 * OpportunityService::create() whenever `lead_id` is set, so every conversion
 * path — creation checkbox, import auto-convert, bulk action AND the "Converti
 * lead" prefilled form — produces the same Offerta through the same
 * QuoteService::create() the quotes module uses.
 *
 * D-6/AC-067: a `single` management-mode derivation accepts one offer row
 * only; checked here, once the Opportunity's product lines are persisted —
 * mirrors RequestCreationService::assertOfferLinesFitManagementMode().
 */
final class LeadConversionOfferCreator
{
    public function __construct(
        private readonly OpportunityProductLineCoverage $coverage,
        private readonly QuoteService $quoteService,
        private readonly ProductOfferLineResolver $offerLineResolver,
    ) {}

    /**
     * @param  array<int, int>  $productIds  one REVENUE line each; [] creates the Offerta with zero lines
     * @param  ?User  $actor  QuoteService::create() requires one, so a null actor skips the Offerta
     *
     * @throws ValidationException more than one distinct product on a `single`-managed derivation
     */
    public function create(Opportunity $opportunity, array $productIds, ?User $actor): void
    {
        $productIds = array_values(array_unique(array_map(intval(...), $productIds)));

        // Step 1 (D-6/AC-067): reject before writing the Offerta.
        $this->assertOfferLinesFitManagementMode($opportunity, $productIds);

        if ($actor === null) {
            return;
        }

        // Step 2: the Offerta, one REVENUE line per product (or none).
        $this->quoteService->create(new CreateQuoteData(
            code: null,
            title: $opportunity->name,
            opportunityId: $opportunity->id,
            workflowStatusId: null,
            note: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            offerLines: $this->offerLineResolver->resolve($productIds),
        ), $actor);
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function assertOfferLinesFitManagementMode(Opportunity $opportunity, array $productIds): void
    {
        if (count($productIds) < 2) {
            return;
        }

        if ($this->coverage->managementModeOf($opportunity) !== CategoryManagementMode::Single) {
            return;
        }

        throw ValidationException::withMessages([
            'offer_lines' => [__(OpportunityProductLineCoverage::SINGLE_OFFER_LINE_MESSAGE)],
        ]);
    }
}
