<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Product;
use App\Models\Quote;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeLayoutMerger;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Support\Collection;

/**
 * The Quote-level "applicable attributes"/layout (spec 0084, D-5): resolved
 * from the DISTINCT product categories of THIS quote's own offer lines
 * (`offerLines[].product.category_id`), never the parent Opportunity's
 * product lines — the "Informazioni aggiuntive" section moved from the
 * Opportunity to the Offerta (D-1).
 *
 * Thin caller of the generalized App\RequestManagement\AttributeSetResolver /
 * AttributeLayoutMerger (D-4): this class only knows how to turn a Quote (or
 * a transient list of product ids, for the not-yet-saved `form-context`
 * preview) into an ordered, deduped category-id list; the union/merge logic
 * itself lives entirely in the two generalized resolvers, shared with the
 * Product side.
 *
 * N+1-free: `resolve()`/`layout()` rely on `offerLines.product.category`
 * already being eager-loaded (QuoteService::DETAIL_RELATIONS); `formContext()`
 * loads every referenced Product (with its category) in ONE query.
 */
final class QuoteAttributeResolver
{
    public function __construct(
        private readonly AttributeSetResolver $setResolver,
        private readonly AttributeLayoutMerger $layoutMerger,
    ) {}

    /**
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(Quote $quote): Collection
    {
        return $this->setResolver->resolve($this->categoryIdsForQuote($quote), AttributeContext::Quote);
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function layout(Quote $quote, FormMode $formMode): ?array
    {
        return $this->layoutMerger->resolve($this->categoryIdsForQuote($quote), AttributeContext::Quote, $formMode);
    }

    /**
     * The `POST /api/quotes/form-context` shape (spec 0084): both blocks,
     * resolved once for the SAME category-id list — the not-yet-saved offer
     * lines' products, not a persisted Quote.
     *
     * @param  array<int, int>  $productIds
     * @return array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}
     */
    public function formContext(array $productIds, FormMode $formMode): array
    {
        $categoryIds = $this->categoryIdsForProductIds($productIds);

        return [
            'applicable_attributes' => $this->setResolver->resolve($categoryIds, AttributeContext::Quote),
            'attribute_layout' => $this->layoutMerger->resolve($categoryIds, AttributeContext::Quote, $formMode),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function categoryIdsForQuote(Quote $quote): array
    {
        $quote->loadMissing('offerLines.product.category');

        return $quote->offerLines
            ->pluck('product.category.id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    private function categoryIdsForProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()->whereIn('id', $productIds)->with('category')->get()->keyBy('id');

        return collect($productIds)
            ->map(fn (int $id): ?int => $products->get($id)?->category?->id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
