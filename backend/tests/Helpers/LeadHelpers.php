<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('leadConversionActor')) {
    /**
     * @param  array<int, string>  $leadAbilities
     * @param  array<int, string>  $opportunityAbilities
     */
    function leadConversionActor(array $leadAbilities, array $opportunityAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('convertibleLeadFixture')) {
    /**
     * A POST /api/leads payload with `convert_to_opportunity: true` plus
     * every reference needed to make the derivation succeed: a campaign, an
     * operator, and a site. `source_id` is the LEAD's own (the campaign no
     * longer carries a source; the campaign-source fallback was removed).
     *
     * Spec 0094, D-1/D-2: `campaigns.business_function_id`/
     * `product_category_id` no longer exist as columns — CampaignFactory's
     * default (standalone) campaign always carries ONE coherent
     * `campaign_product_lines` row of its own, created in its own
     * `afterCreating()` hook, so `businessFunction`/`productCategory` below
     * are read OFF that persisted row rather than passed into create().
     * Requirement changed by spec 0094, not test tampering.
     *
     * Returns both the payload and the models tests assert against.
     *
     * @return array{payload: array<string, mixed>, registry: Registry, source: Source, businessFunction: BusinessFunction, productCategory: ProductCategory, operator: User, site: OperationalSite}
     */
    function convertibleLeadFixture(): array
    {
        $registry = Registry::factory()->create();
        $source = Source::factory()->create();
        $campaign = Campaign::factory()->create();
        $line = $campaign->productLines()->with(['businessFunction', 'productCategory'])->firstOrFail();
        $operator = User::factory()->create();
        $site = OperationalSite::factory()->create();

        return [
            'payload' => [
                'registry_id' => $registry->id,
                'source_id' => $source->id,
                'campaign_id' => $campaign->id,
                'operator_id' => $operator->id,
                'operational_site_id' => $site->id,
                'convert_to_opportunity' => true,
            ],
            'registry' => $registry,
            'source' => $source,
            'businessFunction' => $line->businessFunction,
            'productCategory' => $line->productCategory,
            'operator' => $operator,
            'site' => $site,
        ];
    }
}
