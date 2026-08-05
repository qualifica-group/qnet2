<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * THE COHERENCE RULE of the products of interest (user directive 2026-07-31,
 * extended to the opportunities module by the user directive 2026-08-05): if
 * the record carries products of interest, every one of them must belong to
 * one of the product categories the record classifies itself with
 * (`opportunity_product_lines`). A product outside them is REFUSED and the
 * operator resolves it explicitly — either by adding the product category to
 * the record, or by dropping the product.
 *
 * Both modules write the SAME Opportunity rows through the same collections,
 * so the rule lives here (with the entity) and is enforced on EVERY write
 * channel of either module: request-management POST/PATCH (create form, work
 * panel, inline cell edit) and opportunities POST/PATCH (form, inline cell
 * edit). The `products_of_interest` half is enforced once, inside
 * OpportunityProductInterestWriter — the one writer every channel reaches;
 * the `product_lines` half (a category leaving the record orphans a persisted
 * product) is asserted by the two services that own that write.
 *
 * This DELIBERATELY replaced, on the opportunities module too, the
 * "cross-category pick adds the missing product line" behaviour of
 * OpportunityProductLineCoverage — which now serves quotes only, its other
 * caller.
 *
 * The message names the record the way its own module does: two templates,
 * one rule.
 */
final class ProductCategoryCoherence
{
    /** The request-management wording ("la richiesta"), used by that module's channels. */
    public const string REQUEST_MESSAGE = 'These products of interest belong to a product category the request does not carry: :products. Add that product category to the request, or remove the product.';

    /** The opportunities wording ("l'opportunità"), used by that module's channels. */
    public const string OPPORTUNITY_MESSAGE = 'These products of interest belong to a product category the opportunity does not carry: :products. Add that product category to the opportunity, or remove the product.';

    /**
     * The products sitting outside the covered categories, as
     * `"name" (category)` labels — empty when the classification is coherent.
     *
     * @param  array<int, int>  $productIds  the products of interest the write leaves persisted
     * @param  array<int, int>  $categoryIds  the product categories the record's lines cover
     * @return array<int, string>
     */
    public function offendingProducts(array $productIds, array $categoryIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->with('category:id,name')
            ->whereIn('id', $productIds)
            ->whereNotIn('category_id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'category_id'])
            ->map(static fn (Product $product): string => "\"{$product->name}\" ({$product->category?->name})")
            ->all();
    }

    /**
     * @param  array<int, string>  $offendingProducts
     * @param  string  $template  one of the two constants above — the caller names its own record
     */
    public function message(array $offendingProducts, string $template = self::OPPORTUNITY_MESSAGE): string
    {
        return __($template, ['products' => implode(', ', $offendingProducts)]);
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $categoryIds
     * @param  string  $errorField  the payload key the 422 lands on — the caller names the one the actor actually submitted
     *
     * @throws ValidationException at least one product sits outside the covered categories
     */
    public function assert(array $productIds, array $categoryIds, string $errorField, string $template = self::OPPORTUNITY_MESSAGE): void
    {
        $offending = $this->offendingProducts($productIds, $categoryIds);

        if ($offending === []) {
            return;
        }

        throw ValidationException::withMessages([$errorField => [$this->message($offending, $template)]]);
    }
}
