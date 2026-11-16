<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Quote;
use App\Quotes\QuoteAttributeResolver;
use Illuminate\Support\Collection;

/**
 * The "Informazioni aggiuntive" set/layout for a Gestione Richieste record
 * (user directive 2026-08-07): same shape, same context and same storage as
 * the Offerte form (`AttributeContext::Quote`, values on
 * `quotes.attribute_values`).
 *
 * D-1 (approved 2026-08-07): the categories are the UNION of the parent
 * Opportunity's product lines and the Offerta's own revenue lines. A request
 * is born with NO offer lines (spec 0086 AC-028), so the product lines carry
 * the set. Since 2026-11-11 the Offerte module resolves that same union for
 * the same record (App\Quotes\QuoteAttributeResolver), so a persisted
 * request delegates to it: the two modules can no longer show different sets
 * for one values map. Only the create preview stays here, resolved from the
 * categories the form has picked before any record exists.
 */
final class RequestAttributeResolver
{
    public function __construct(
        private readonly QuoteAttributeResolver $quoteResolver,
        private readonly AttributeSetResolver $setResolver,
        private readonly AttributeLayoutMerger $layoutMerger,
    ) {}

    /**
     * @return Collection<int, ApplicableAttribute>
     */
    public function resolve(Quote $quote): Collection
    {
        return $this->quoteResolver->resolve($quote);
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function layout(Quote $quote, FormMode $formMode): ?array
    {
        return $this->quoteResolver->layout($quote, $formMode);
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
}
