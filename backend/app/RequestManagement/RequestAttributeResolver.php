<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Quote;
use Illuminate\Support\Collection;

/**
 * The "Informazioni aggiuntive" set/layout for a Gestione Richieste record
 * (user directive 2026-08-07): same shape, same context and same storage as
 * the Offerte form (`AttributeContext::Quote`, values on
 * `quotes.attribute_values`) — only the CATEGORY SOURCE differs.
 *
 * D-1 (approved 2026-08-07): the categories are the UNION of
 *  - the parent Opportunity's product lines (`product_category_id`), and
 *  - the Offerta's own revenue lines' products' categories.
 *
 * Why not the offer lines alone, as App\Quotes\QuoteAttributeResolver does: a
 * request is born with NO offer lines (spec 0086 AC-028), so that rule would
 * leave the section empty on the create form and on nearly every panel. The
 * product lines are this module's own headline classification and always
 * carry at least one row (ValidatesProductLines' `min:1` on both write
 * channels). The offer lines stay in the union so a line added from the
 * Offerte module — where the picker can be unlocked past the opportunity's
 * categories — never hides fields that module can show.
 *
 * Thin caller of the generalized AttributeSetResolver/AttributeLayoutMerger,
 * exactly like the Quote and Product resolvers: this class only turns a
 * record into an ordered, deduped category-id list.
 */
final class RequestAttributeResolver
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
        return $this->setResolver->resolve($this->categoryIdsFor($quote), AttributeContext::Quote);
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function layout(Quote $quote, FormMode $formMode): ?array
    {
        return $this->layoutMerger->resolve($this->categoryIdsFor($quote), AttributeContext::Quote, $formMode);
    }

    /**
     * The `POST /api/request-management/form-context` shape: both blocks for
     * a set of categories the create form has picked but not yet saved —
     * there is no record to resolve them from.
     *
     * @param  array<int, int>  $categoryIds
     * @return array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}
     */
    public function forCategories(array $categoryIds, FormMode $formMode): array
    {
        return [
            'applicable_attributes' => $this->setResolver->resolve($categoryIds, AttributeContext::Quote),
            'attribute_layout' => $this->layoutMerger->resolve($categoryIds, AttributeContext::Quote, $formMode),
        ];
    }

    /**
     * Opportunity product lines first, offer lines after: the merge keeps a
     * code's first-seen position, so the module's own classification drives
     * the order and an offer-only category can only append.
     *
     * `loadMissing` (never a bare relation access) so an unloaded relation
     * still resolves under Model::preventLazyLoading() outside production.
     *
     * @return array<int, int>
     */
    private function categoryIdsFor(Quote $quote): array
    {
        $quote->loadMissing(['opportunity.productLines', 'offerLines.product.category']);

        return $quote->opportunity->productLines
            ->pluck('product_category_id')
            ->concat($quote->offerLines->pluck('product.category.id'))
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
