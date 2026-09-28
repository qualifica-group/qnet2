<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('opportunityFromLeadActor')) {
    /**
     * @param  array<int, string>  $opportunityAbilities
     * @param  array<int, string>  $leadAbilities
     */
    function opportunityFromLeadActor(array $opportunityAbilities, array $leadAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('completeLead')) {
    /**
     * A lead with its own registry AND its own source (spec 0041 D-3), so
     * both locked fields lock — `source_id` derives ONLY from the lead's own
     * source (the campaign no longer carries one, campaign-source fallback
     * removed). The lead's own `operational_site_id` (unrelated to
     * Opportunity, which no longer has that field) still carries a real
     * site, mirroring the common-case fixture.
     *
     * Spec 0094, D-1/D-2: `campaigns.business_function_id`/
     * `product_category_id` no longer exist as columns — CampaignFactory's
     * default (standalone) campaign auto-creates ONE coherent
     * `campaign_product_lines` row of its own (its OWN business function
     * matches its OWN category, satisfying the same
     * CategoryHierarchy::effectiveBusinessFunction() check the write path
     * enforces), so this fixture no longer builds the pair itself.
     * Requirement changed by spec 0094, not test tampering. Shared with
     * OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).
     */
    function completeLead(): Lead
    {
        $registry = Registry::factory()->create();
        $source = Source::factory()->create();
        $campaign = Campaign::factory()->create();

        return Lead::factory()->create([
            'campaign_id' => $campaign->id,
            'registry_id' => $registry->id,
            'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
            'source_id' => $source->id,
        ]);
    }
}

if (! function_exists('nonDerivableOpportunityFks')) {
    /**
     * Since the user directive 2026-07-17 makes `product_lines` mandatory to
     * create, a valid one-row collection ships here for every from-lead POST
     * (tests that assert a specific product_lines payload merge
     * the helper FIRST so their own value wins). `company_id`/
     * `company_site_id` were the former mandatory-but-never-derivable fields
     * (amendment rev.1 A-2) — REMOVED entirely per user directive
     * 2026-07-17. Spec 0082 removed the status field entirely (it is
     * computed from the quotes). The supervisor is mandatory only on create.
     *
     * @return array{supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function nonDerivableOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            // User directive 2026-07-23: products_of_interest is mandatory too;
            // the product belongs to the row's OWN category, so this payload
            // never triggers a cross-category product-line addition.
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}
