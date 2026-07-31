<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\OpportunityWorkflowStatus;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\OpportunityAttributeLayoutResolver;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use Illuminate\Support\Collection;

/**
 * The three blocks the CREATE form needs before anything is persisted (user
 * directive 2026-07-31, "la create il piu' simile possibile al pannello"):
 * the working-status set, the applicable dynamic attributes and their merged
 * layout — resolved for the criteria the operator has typed so far, not for a
 * stored record.
 *
 * All three come from the SAME resolvers the work panel reads
 * (RequestManagementService::loadWorkPanel), fed a TRANSIENT, never-persisted
 * Opportunity carrying the submitted source_id/product_lines — the exact
 * pattern App\Http\Requests\Concerns\ValidatesWorkflowStatus already uses to
 * validate a create against the set it is about to belong to. Nothing is
 * re-implemented here and nothing is duplicated frontend-side: the create form
 * and the panel can never offer different statuses or different fields for the
 * same criteria.
 *
 * FormMode::Create for the layout, matching what RequestCreationService asks
 * for right after the insert (spec 0062 D3): the form previewed here IS the
 * create form.
 */
final class RequestFormContextResolver
{
    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly OpportunityAttributeLayoutResolver $layoutResolver,
        private readonly OpportunityWorkflowResolver $workflowResolver,
    ) {}

    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $productLines  complete rows only — a half-filled row scopes nothing (RequestFormContextRequest drops it)
     * @return array{applicable_attributes: Collection<int, ApplicableAttribute>, attribute_layout: array<string, mixed>|null, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}
     */
    public function resolve(?int $sourceId, array $productLines): array
    {
        $opportunity = $this->transientOpportunity($sourceId, $productLines);

        return [
            'applicable_attributes' => $this->attributesResolver->resolve($opportunity),
            'attribute_layout' => $this->layoutResolver->resolve($opportunity, FormMode::Create),
            'workflow_statuses' => $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity)),
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
    private function transientOpportunity(?int $sourceId, array $productLines): Opportunity
    {
        $opportunity = new Opportunity;
        $opportunity->source_id = $sourceId;
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
