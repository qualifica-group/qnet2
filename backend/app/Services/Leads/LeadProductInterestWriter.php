<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Models\CampaignProductLine;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProjectProductLine;
use App\Services\Opportunities\ProductCategoryCoherence;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for a Lead's "prodotti di interesse" (spec 0094,
 * D-5), mirroring App\Services\Opportunities\OpportunityProductInterestWriter
 * verbatim: normalize + exist-check the submitted set, refuse anything not
 * covered by the record's classification (ProductCategoryCoherence), then
 * replace the collection.
 *
 * A Lead carries no product lines of its OWN — the categories it must stay
 * coherent with are its Campaign's EFFECTIVE ones (the linked Project's rows
 * when it has one, else the Campaign's own; BR-2, the same project-first
 * precedence CampaignResource::summarizeProductLines already applies).
 */
final class LeadProductInterestWriter
{
    public function __construct(private readonly ProductCategoryCoherence $coherence) {}

    /**
     * Replaces the whole collection (authoritative sync).
     *
     * @param  array<int, int>  $productIds
     *
     * @throws ValidationException a submitted product does not exist, or its category is not covered by the lead's campaign
     */
    public function sync(Lead $lead, array $productIds): void
    {
        // Step 1: normalize the submitted set and check it exists in one query.
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $productIds)));
        $this->assertProductsExist($ids);

        // Step 2: refuse anything the campaign's effective lines do not cover.
        $this->coherence->assert(
            $ids,
            $this->coveredCategoryIds($lead),
            'products_of_interest',
            ProductCategoryCoherence::LEAD_MESSAGE,
        );

        // Step 3: replace the collection.
        $lead->productsOfInterest()->sync($ids);
        $lead->unsetRelation('productsOfInterest');
    }

    /**
     * The product categories $lead's campaign classifies itself with — the
     * SAME resolution LeadService::assertPersistedProductsStayCovered()
     * (AC-034) reuses, so it lives in exactly one place. Always reloaded
     * (never `loadMissing`): a caller may have changed `campaign_id` right
     * before this call, and a stale already-loaded `campaign` relation would
     * silently resolve against the WRONG campaign.
     *
     * @return array<int, int>
     */
    public function coveredCategoryIds(Lead $lead): array
    {
        $lead->load(['campaign.productLines', 'campaign.project.productLines']);

        $campaign = $lead->campaign;

        if ($campaign === null) {
            return [];
        }

        /** @var Collection<int, CampaignProductLine|ProjectProductLine> $lines */
        $lines = $campaign->project !== null ? $campaign->project->productLines : $campaign->productLines;

        return $lines->pluck('product_category_id')->map(intval(...))->all();
    }

    /**
     * @param  array<int, int>  $ids
     *
     * @throws ValidationException
     */
    private function assertProductsExist(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        if (Product::query()->whereIn('id', $ids)->count() === count($ids)) {
            return;
        }

        throw ValidationException::withMessages([
            'products_of_interest' => ['One of the selected products does not exist.'],
        ]);
    }
}
