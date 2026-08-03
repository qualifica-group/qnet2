<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * THE COHERENCE RULE of the request-management module (user directive
 * 2026-07-31): if the request carries products of interest, every one of them
 * must belong to one of the product categories the request classifies itself
 * with (`product_lines`). Enforced on BOTH write channels — POST (create) and
 * PATCH (the work panel and, through it, the inline-edit engine) — so the two
 * can never diverge.
 *
 * This DELIBERATELY overrides, for this module only, the "cross-category pick
 * adds the missing product line" behaviour of OpportunityProductLineCoverage
 * (user directive 2026-07-22): here the mismatch is refused and the operator
 * resolves it explicitly, either by adding the product category to the
 * request or by dropping the product. The opportunities CRUD keeps the
 * auto-add rule. Because this guard runs BEFORE
 * OpportunityProductInterestWriter, the coverage step it shares with that
 * module finds everything already covered and adds nothing.
 *
 * Two entry points because the two channels report differently: create
 * collects the failure into its FormRequest validator (alongside the other
 * payload errors), update throws from the service, where the sets being
 * compared are half persisted.
 */
final class RequestProductCategoryCoherence
{
    /**
     * The products sitting outside the covered categories, as
     * `"name" (category)` labels — empty when the classification is coherent.
     *
     * @param  array<int, int>  $productIds  the products of interest the write leaves persisted
     * @param  array<int, int>  $categoryIds  the product categories the request's lines cover
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
     */
    public function message(array $offendingProducts): string
    {
        return __(
            'These products of interest belong to a product category the request does not carry: :products. Add that product category to the request, or remove the product.',
            ['products' => implode(', ', $offendingProducts)],
        );
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $categoryIds
     * @param  string  $errorField  the payload key the 422 lands on — the caller names the one the actor actually submitted
     *
     * @throws ValidationException at least one product sits outside the covered categories
     */
    public function assert(array $productIds, array $categoryIds, string $errorField): void
    {
        $offending = $this->offendingProducts($productIds, $categoryIds);

        if ($offending === []) {
            return;
        }

        throw ValidationException::withMessages([$errorField => [$this->message($offending)]]);
    }
}
