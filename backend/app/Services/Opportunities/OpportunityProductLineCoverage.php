<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\CategoryManagementMode;
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
 *
 * Spec 0077 D-6: the auto-add above is the `multiple`-mode behaviour only.
 * When the opportunity's resolved management mode is `single`, coverage
 * widening is disabled — a product outside the one covered category is
 * refused (AC-020), not silently added (AC-021 keeps the `multiple`/
 * indeterminate path exactly as it was).
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

        if ($this->resolvedManagementMode($coveredCategoryIds) === CategoryManagementMode::Single) {
            $this->rejectOffCategoryProducts($products, $coveredCategoryIds, $errorField);

            return [];
        }

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

    /**
     * The management mode governing the opportunity, resolved from the root
     * of its FIRST covered category — INV-1 guarantees every existing
     * `opportunity_product_lines` row already shares the same root, so one
     * id is enough. A single batched lookup (CategoryHierarchy::
     * rootManagementModesFor), never a query per row.
     *
     * Null (indeterminate) when the opportunity carries no product line yet,
     * or when the covered category's root cannot be resolved: both fall back
     * to the pre-existing auto-add behaviour (D-8's `multiple` default),
     * never to the `single` rejection.
     *
     * @param  array<int, int>  $coveredCategoryIds
     */
    private function resolvedManagementMode(array $coveredCategoryIds): ?CategoryManagementMode
    {
        if ($coveredCategoryIds === []) {
            return null;
        }

        $rootCategoryId = $coveredCategoryIds[0];
        $root = $this->hierarchy->rootManagementModesFor([$rootCategoryId])[$rootCategoryId] ?? null;

        return $root['management_mode'] ?? null;
    }

    /**
     * D-6: in `single` mode the auto-add is disabled — a product whose
     * category sits outside the opportunity's one covered category is
     * refused instead of silently widening the coverage (AC-020). The
     * message names every offending product the same way
     * RequestProductCategoryCoherence::message() does, so the operator sees
     * a consistent "which products, which category" shape across modules.
     *
     * @param  Collection<int, Product>  $products
     * @param  array<int, int>  $coveredCategoryIds
     *
     * @throws ValidationException at least one product's category is not covered
     */
    private function rejectOffCategoryProducts(Collection $products, array $coveredCategoryIds, string $errorField): void
    {
        $offending = $products
            ->filter(static fn (Product $product): bool => $product->category !== null && ! in_array($product->category->id, $coveredCategoryIds, true))
            ->map(static fn (Product $product): string => "\"{$product->name}\" ({$product->category?->name})")
            ->values()
            ->all();

        if ($offending === []) {
            return;
        }

        throw ValidationException::withMessages([
            $errorField => [__(
                'This opportunity accepts a single product category: :products belongs to a different one. Add it to the covered category, or remove it from the offer.',
                ['products' => implode(', ', $offending)],
            )],
        ]);
    }
}
