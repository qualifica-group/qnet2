<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\OpportunityAttributeLayoutResolver;
use Illuminate\Support\Collection;

/**
 * The two blocks the CREATE form needs before anything is persisted (user
 * directive 2026-07-31, "la create il piu' simile possibile al pannello"):
 * the applicable dynamic attributes and their merged layout — resolved for
 * the criteria the operator has typed so far, not for a stored record.
 *
 * Both come from the SAME resolvers the work panel reads
 * (RequestManagementService::loadWorkPanel), fed a TRANSIENT, never-persisted
 * Opportunity carrying the submitted product_lines. Nothing is
 * re-implemented here and nothing is duplicated frontend-side: the create form
 * and the panel can never offer different fields for the same criteria.
 *
 * FormMode::Create for the layout, matching what RequestCreationService asks
 * for right after the insert (spec 0062 D3): the form previewed here IS the
 * create form.
 *
 * Spec 0083, D-2: the working-status block is GONE — the Opportunity created
 * by this form no longer resolves any working-state dimension of its own.
 */
final class RequestFormContextResolver
{
    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly OpportunityAttributeLayoutResolver $layoutResolver,
    ) {}

    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines  complete rows only — a half-filled row scopes nothing (RequestFormContextRequest drops it)
     * @return array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null}
     */
    public function resolve(array $productLines): array
    {
        $opportunity = $this->transientOpportunity($productLines);

        return [
            'applicable_attributes' => $this->attributesResolver->resolve($opportunity),
            'attribute_layout' => $this->layoutResolver->resolve($opportunity, FormMode::Create),
        ];
    }

    /**
     * The relation is SET (never loaded): `setRelation` makes every
     * `loadMissing('productLines…')` inside the resolvers a no-op on the
     * parent, so a model with no key never reaches the database for it. The
     * nested `productCategory` still resolves normally — a belongsTo eager
     * load keys off the row's own `product_category_id`, which these transient
     * rows carry.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines
     */
    private function transientOpportunity(array $productLines): Opportunity
    {
        $opportunity = new Opportunity;
        $opportunity->setRelation(
            'productLines',
            collect($productLines)
                ->map(static fn (array $row): OpportunityProductLine => new OpportunityProductLine([
                    'business_function_id' => $row['business_function_id'],
                    'product_category_id' => $row['product_category_id'],
                ]))
                ->values(),
        );

        return $opportunity;
    }
}
