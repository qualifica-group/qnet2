<?php

namespace Database\Seeders\Support;

use App\DataObjects\Quotes\CreateQuoteData;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Quotes\QuoteRootScope;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\Quotes\QuoteWorkflowStatusWriter;
use App\Services\QuoteService;
use App\Services\RequestManagement\RequestProductLineWriter;

/**
 * A sheet row whose client already has an open opportunity (user decision
 * 2026-11-07, "Offerta sull'esistente"): the rule allows one open opportunity per
 * anagrafica, so the row becomes a new Offerta ON that opportunity, the row's
 * category added to its product lines - what the refusal message itself asks.
 * Every write goes through the services the work panel and the Offerte module use.
 */
final class RequestSheetOfferAppender
{
    public function __construct(
        private readonly RequestProductLineWriter $productLines,
        private readonly QuoteService $quotes,
        private readonly QuoteWorkflowResolver $workflows,
        private readonly QuoteWorkflowStatusWriter $statusWriter,
        private readonly QuoteRootScope $rootScope,
    ) {}

    /**
     * @param  list<ProductCategory>  $categories  the row's categories, under one root category
     * @param  array<string, mixed>  $row
     * @param  array{source_id: int, reporter_id: ?int, commercial_id: ?int, commercial_partner_id: ?int, operational_site_id: ?int}  $refs
     * @param  array<int, int|null>  $managerSlots
     */
    public function append(User $actor, Opportunity $opportunity, array $categories, array $row, array $refs, array $managerSlots): void
    {
        // Step 1: the row's categories among the opportunity's product lines.
        $this->addProductLines($opportunity, $categories);

        // Step 2: the Offerta, born on the default status of its workflow.
        $quote = $this->quotes->create(new CreateQuoteData(
            code: null,
            title: null,
            opportunityId: $opportunity->id,
            workflowStatusId: null,
            note: null,
            commercialId: $refs['commercial_id'],
            commercialIdSubmitted: true,
            commercialPartnerId: $refs['commercial_partner_id'],
            commercialPartnerIdSubmitted: true,
            reporterId: $refs['reporter_id'],
            reporterIdSubmitted: true,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            operationalSiteId: $refs['operational_site_id'],
            operationalSiteIdSubmitted: true,
            managerSlots: $managerSlots,
            promoteManagersToOpportunity: true,
            sourceId: $refs['source_id'],
            // An offer holds categories of one root only (spec 0411): the row's.
            rootProductCategoryId: $this->rootScope->rootIdOf($categories[0]->id),
        ), $actor);

        // Step 3: the sheet's status, by name, in the workflow THIS offer resolves
        // (classified by every line of the opportunity, not by the row's alone).
        $statusId = $this->workflows->statusesFor($this->workflows->resolve($quote))->firstWhere('name', $row['status'])?->id;

        if ($statusId !== null) {
            $this->statusWriter->apply($quote, $statusId, $actor, null);
        }

        $quote->next_callback_at = isset($row['callback']) ? $row['callback'].' '.config('imports.request_management.callback_time') : null;
        $quote->save();

        // Step 4: the row's notes, appended to the opportunity's, under the offer's code.
        if (isset($row['notes'])) {
            $opportunity->update(['general_notes' => trim($opportunity->general_notes."\n\n".$quote->code.":\n".$row['notes'])]);
        }
    }

    /**
     * @param  list<ProductCategory>  $categories
     */
    private function addProductLines(Opportunity $opportunity, array $categories): void
    {
        $lines = $opportunity->productLines()->pluck('product_category_id')
            ->map(static fn (mixed $id): array => ['product_category_id' => (int) $id])
            ->push(...array_map(static fn (ProductCategory $category): array => ['product_category_id' => $category->id], $categories))
            ->unique('product_category_id')
            ->values()
            ->all();

        $changed = [];
        $old = [];
        $this->productLines->apply($opportunity, $lines, $changed, $old);
    }
}
