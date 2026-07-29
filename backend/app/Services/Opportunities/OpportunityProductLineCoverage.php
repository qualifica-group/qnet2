<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Product;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * THE RULE (extracted from OpportunityProductInterestWriter, spec 0065 D-7/
 * AC-054): given an Opportunity and a set of Products, ensure every product's
 * category is covered by the opportunity's `opportunity_product_lines` —
 * creating the missing (funzione aziendale effettiva, categoria) row when it
 * is not. Shared by TWO write paths that must never diverge:
 * OpportunityProductInterestWriter (the "prodotti di interesse" picker, user
 * directive 2026-07-22) and QuoteService (a REVENUE quote line whose product
 * sits outside the covered categories, spec 0065 AC-050/051/052/053).
 *
 * A product whose category has no EFFECTIVE business function (own or
 * inherited) cannot produce a valid row — `opportunity_product_lines`
 * requires both ids — so it is rejected as a 422 rather than silently
 * dropped.
 */
final class OpportunityProductLineCoverage
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * Creates the missing funzione-aziendale + categoria-prodotto rows for
     * the categories of $products the opportunity does not already carry.
     * Existing rows are never touched (the pair is unique, so a duplicate is
     * impossible by construction).
     *
     * @param  Collection<int, Product>  $products
     * @param  string  $errorField  the validator error key used when a
     *                              product's category resolves to no business
     *                              function — each caller names it after its
     *                              OWN payload shape (`products_of_interest`
     *                              for the picker, an offer-line path for
     *                              quotes).
     * @return array<int, array{business_function_id: int, product_category_id: int}> the rows that had to be created
     *
     * @throws ValidationException a product's category resolves to no business function
     */
    public function ensure(Opportunity $opportunity, Collection $products, string $errorField = 'products_of_interest'): array
    {
        $coveredCategoryIds = $opportunity->productLines()->pluck('product_category_id')->all();
        $added = [];

        foreach ($products as $product) {
            $category = $product->category;

            if ($category === null || in_array($category->id, $coveredCategoryIds, true)) {
                continue;
            }

            $businessFunction = $this->hierarchy->effectiveBusinessFunction($category);

            if ($businessFunction === null) {
                throw ValidationException::withMessages([
                    $errorField => ["The category of product \"{$product->name}\" has no business function: it cannot be added to this opportunity."],
                ]);
            }

            $line = [
                'business_function_id' => (int) $businessFunction['id'],
                'product_category_id' => (int) $category->id,
            ];

            $opportunity->productLines()->create($line);
            $coveredCategoryIds[] = $category->id;
            $added[] = $line;
        }

        if ($added !== []) {
            $opportunity->unsetRelation('productLines');
        }

        return $added;
    }
}
