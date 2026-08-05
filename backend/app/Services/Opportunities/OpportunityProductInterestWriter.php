<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for an opportunity's "prodotti di interesse" (user
 * directive 2026-07-22), shared by EVERY channel that can set them: the
 * opportunities CRUD (OpportunityService), the opportunities grid inline
 * editor (OpportunitiesTableDefinition), the operative work panel and its own
 * inline editor (RequestManagementService). One writer, so the rule below can
 * never diverge between them.
 *
 * THE RULE (user directive 2026-07-31 for request-management, extended to the
 * opportunities module by the user directive 2026-08-05): every product of
 * interest must belong to a product category the record already carries. A
 * product from ANOTHER category is REFUSED (ProductCategoryCoherence) — the
 * operator adds the categoria prodotto first, or drops the product. The
 * frontend prunes and locks its picker so that refusal is rarely reached, but
 * the guarantee lives here, for any client that does neither.
 *
 * This REPLACED the previous "a cross-category pick adds the matching
 * funzione-aziendale + categoria-prodotto row" behaviour on this path;
 * OpportunityProductLineCoverage, which implements it, now serves quotes
 * only.
 */
final class OpportunityProductInterestWriter
{
    public function __construct(private readonly ProductCategoryCoherence $coherence) {}

    /**
     * Replaces the whole collection (authoritative sync).
     *
     * @param  array<int, int>  $productIds
     *
     * @throws ValidationException a submitted product does not exist, or its category is not covered by the record's product lines
     */
    public function sync(Opportunity $opportunity, array $productIds): void
    {
        // Step 1: normalize the submitted set and check it exists in one query.
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $productIds)));
        $this->assertProductsExist($ids);

        // Step 2: refuse anything the record's product lines do not cover.
        $this->coherence->assert(
            $ids,
            $opportunity->productLines()->pluck('product_category_id')->map(intval(...))->all(),
            'products_of_interest',
        );

        // Step 3: replace the collection.
        $opportunity->productsOfInterest()->sync($ids);
        $opportunity->unsetRelation('productsOfInterest');
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
