<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for an opportunity's "prodotti di interesse" (user
 * directive 2026-07-22), shared by BOTH channels that can set them: the
 * opportunities CRUD (OpportunityService) and the operative work panel
 * (RequestManagementService). One writer, so the rule below can never
 * diverge between them.
 *
 * THE RULE (user directive): the picker is scoped by default to the products
 * of the opportunity's own product-line categories, but the operator may
 * unlock the whole catalogue. Picking a product from ANOTHER category is
 * therefore legal, and it must ADD the matching funzione-aziendale +
 * categoria-prodotto row to the opportunity — the frontend warns before
 * doing it, this writer is what actually performs it, so the invariant holds
 * even for a client that never showed the warning. The coverage rule itself
 * (spec 0065, D-7/AC-054) is extracted into OpportunityProductLineCoverage,
 * shared verbatim with QuoteService.
 */
final class OpportunityProductInterestWriter
{
    public function __construct(private readonly OpportunityProductLineCoverage $coverage) {}

    /**
     * Replaces the whole collection (authoritative sync) and returns the
     * product-line rows that had to be created to keep the invariant.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     *
     * @throws ValidationException a submitted product does not exist, or its category resolves to no business function
     */
    public function sync(Opportunity $opportunity, array $productIds): array
    {
        // Step 1: normalize the submitted set and resolve it in one query.
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $productIds)));
        $products = $this->resolveProducts($ids);

        // Step 2: cover every selected product's category with a product line
        // (the cross-category pick the frontend warns about).
        $addedLines = $this->coverage->ensure($opportunity, $products);

        // Step 3: replace the collection.
        $opportunity->productsOfInterest()->sync($ids);
        $opportunity->unsetRelation('productsOfInterest');

        return $addedLines;
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     *
     * @throws ValidationException
     */
    private function resolveProducts(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()->with('category')->whereIn('id', $ids)->get();

        if ($products->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'products_of_interest' => ['One of the selected products does not exist.'],
            ]);
        }

        return $products;
    }
}
