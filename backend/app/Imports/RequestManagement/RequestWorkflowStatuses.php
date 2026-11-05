<?php

namespace App\Imports\RequestManagement;

use App\Enums\WorkflowStatusGroup;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\QuoteWorkflows\WorkflowStatusWriter;
use App\Support\Import\ImportValueMappings;
use Illuminate\Support\Collection;

/**
 * The working statuses the request import can give an Offerta (spec 0348).
 *
 * The workflow is resolved BEFORE the Offerta exists, the way
 * OpportunityDefaultStatusResolver does for a quote-less Opportunity: a
 * transient Offerta (never saved) carries a transient Opportunity with the
 * run's single product line, so QuoteWorkflowResolver applies the very same
 * criteria it will apply to the real Offerta. The Offerta's own source is a
 * criterion too, so the probe carries the run's global source; a workflow
 * keyed on a per-row source is caught later by QuoteWorkflowStatusWriter's
 * set-membership check, which fails that row alone.
 */
final class RequestWorkflowStatuses
{
    public function __construct(
        private readonly QuoteWorkflowResolver $resolver,
        private readonly CategoryHierarchy $hierarchy,
        private readonly WorkflowStatusWriter $statusWriter,
    ) {}

    /**
     * The workflow set's statuses in display order, for the chosen category.
     *
     * @return Collection<int, QuoteWorkflowStatus>
     */
    public function statuses(int $categoryId, ?int $sourceId): Collection
    {
        return $this->resolver->statusesFor($this->workflow($categoryId, $sourceId));
    }

    /**
     * The id of the status named $name in the workflow set, creating it (as a
     * custom row of $group, before the closing rows) when absent. Idempotent
     * for (workflow, name ignoring case and spacing): a second run asking for
     * the same name finds the row the first one created.
     */
    public function findOrCreate(int $categoryId, ?int $sourceId, string $name, WorkflowStatusGroup $group): int
    {
        $workflow = $this->workflow($categoryId, $sourceId);
        $key = ImportValueMappings::normalize($name);

        $existing = QuoteWorkflowStatus::query()
            ->where('quote_workflow_id', $workflow?->id)
            ->get(['id', 'name'])
            ->first(static fn (QuoteWorkflowStatus $status): bool => ImportValueMappings::normalize($status->name) === $key);

        if ($existing !== null) {
            return $existing->id;
        }

        $created = $this->statusWriter->appendCustom($workflow?->id, trim($name), $group);
        $this->resolver->forgetCaches();

        return $created->id;
    }

    private function workflow(int $categoryId, ?int $sourceId): ?QuoteWorkflow
    {
        return $this->resolver->resolve($this->probe($categoryId, $sourceId));
    }

    private function probe(int $categoryId, ?int $sourceId): Quote
    {
        $line = (new OpportunityProductLine)->forceFill([
            'product_category_id' => $categoryId,
            'business_function_id' => $this->hierarchy->effectiveBusinessFunction(ProductCategory::query()->findOrFail($categoryId))['id'] ?? null,
        ]);

        $opportunity = new Opportunity;
        $opportunity->setRelation('productLines', new Collection([$line]));
        $opportunity->setRelation('customFieldValueRow', null);

        $quote = new Quote;
        $quote->source_id = $sourceId;
        $quote->setRelation('opportunity', $opportunity);
        $quote->setRelation('offerLines', new Collection);

        return $quote;
    }
}
