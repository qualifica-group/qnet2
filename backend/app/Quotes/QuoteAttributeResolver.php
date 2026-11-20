<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\Quote;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeLayoutMerger;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Support\Collection;

/**
 * The Quote-level "applicable attributes"/layout (spec 0084, D-5): resolved
 * from the UNION of the parent Opportunity's product lines'
 * `product_category_id` and THIS quote's own offer lines' product categories.
 *
 * The union, not the offer lines alone (user report 2026-11-11, request
 * 29395): Offerte and Gestione Richieste show the SAME `quotes` record and the
 * SAME `attribute_values` map, so they must resolve the same set. A request
 * is born with no offer lines, and the offer-lines-only rule left its values
 * invisible here and, worse, editable as empty fields once a line was added.
 * Opportunity categories come first: the merge keeps a code's first-seen
 * position, so the record's own classification drives the order.
 *
 * Thin caller of the generalized App\RequestManagement\AttributeSetResolver /
 * AttributeLayoutMerger (D-4): this class only knows how to turn a Quote (or
 * a transient opportunity + product ids, for the not-yet-saved
 * `form-context` preview) into an ordered, deduped category-id list; the
 * union/merge logic itself lives entirely in the two generalized resolvers,
 * shared with the Product side.
 *
 * N+1-free: `resolve()`/`layout()` rely on `opportunity.productLines` and
 * `offerLines.product.category` already being eager-loaded
 * (QuoteService::DETAIL_RELATIONS); `formContext()` runs one query per source.
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
     * resolved once for the SAME category-id list a save would produce — the
     * picked Opportunity's product lines plus the not-yet-saved offer lines'
     * products, not a persisted Quote.
     *
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $extraCategoryIds  categories of an opportunity not saved yet (spec 0396, D-7)
     * @return array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}
     */
    public function formContext(?int $opportunityId, array $productIds, FormMode $formMode, array $extraCategoryIds = []): array
    {
        $categoryIds = $this->unionOf(
            [...$this->categoryIdsForOpportunityId($opportunityId), ...$extraCategoryIds],
            $this->categoryIdsForProductIds($productIds),
        );

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
        $quote->loadMissing(['opportunity.productLines', 'offerLines.product.category']);

        return $this->unionOf(
            $quote->opportunity->productLines->pluck('product_category_id')->all(),
            $quote->offerLines->pluck('product.category.id')->all(),
        );
    }

    /**
     * @return array<int, int>
     */
    private function categoryIdsForOpportunityId(?int $opportunityId): array
    {
        if ($opportunityId === null) {
            return [];
        }

        return OpportunityProductLine::query()
            ->where('opportunity_id', $opportunityId)
            ->orderBy('id')
            ->pluck('product_category_id')
            ->all();
    }

    /**
     * @param  array<int, mixed>  $opportunityCategoryIds
     * @param  array<int, mixed>  $offerCategoryIds
     * @return array<int, int>
     */
    private function unionOf(array $opportunityCategoryIds, array $offerCategoryIds): array
    {
        return collect($opportunityCategoryIds)
            ->concat($offerCategoryIds)
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
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
            ->all();
    }
}
