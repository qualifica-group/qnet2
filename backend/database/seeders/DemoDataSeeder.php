<?php

namespace Database\Seeders;

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Fill every table with fake fixtures for local development and demos. It first
 * runs the default seed (reference data, roles/permissions and the demo user)
 * so it is self-contained on a fresh database, then layers the generated users
 * and their related records on top. Run on demand:
 * `php artisan db:seed --class=DemoDataSeeder`.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        // Clear the most-downstream demo entities first: a Quote restricts
        // (never cascades) its Opportunity, which itself restrict-references
        // half the graph (registries, companies, sites, referents, leads),
        // and a Lead restricts referents/campaigns/sites — so on a re-run
        // the upstream delete-and-recreate seeders (e.g. DemoReferentSeeder)
        // would trip the FK restriction before the downstream seeders get a
        // chance to clear their own rows. All three tables are re-seeded
        // below (same pre-clear pattern as DemoProjectSeeder with campaigns).
        Quote::query()->delete();
        Opportunity::query()->delete();
        Lead::query()->delete();

        $this->call(DemoReferentTypeSeeder::class);
        $this->call(DemoReferentSeeder::class);
        $this->call(DemoSourceSeeder::class);
        $this->call(DemoVatRateSeeder::class);
        $this->call(DemoSectorSeeder::class);
        $this->call(DemoTagSeeder::class);
        $this->call(DemoRolesSeeder::class);
        $this->call(DemoUsersSeeder::class);
        $this->call(DemoPersonalDataSeeder::class);
        $this->call(DemoUserContactSeeder::class);
        $this->call(DemoUserAddressSeeder::class);
        $this->call(DemoOperationalSiteSeeder::class);
        $this->call(DemoCompanySeeder::class);
        $this->call(DemoCompanySiteSeeder::class);
        $this->call(DemoBusinessFunctionSeeder::class);
        // The demo category tree with its attributes (both contexts) and form
        // sections: depends on DemoBusinessFunctionSeeder for the branch
        // function, and everything downstream that classifies a record
        // (projects, campaigns, opportunities) depends on IT.
        $this->call(DemoProductCategorySeeder::class);
        // The offer sold under those categories — what "prodotti di interesse"
        // (mandatory on the opportunity form) is picked from.
        $this->call(DemoProductSeeder::class);
        $this->call(DemoEmploymentProfileSeeder::class);
        // Depends on sources/sectors/referents (lookups, seeded above) and
        // users (internal managers, seeded above) — must run after all of them.
        $this->call(DemoRegistrySeeder::class);
        $this->call(DemoPipelineStatusSeeder::class);
        // Depends on pipeline-statuses/registries/sources/business-functions/
        // product-categories/referents (lookups, all seeded above) and
        // `locations:add` (states, run by DatabaseSeeder) — must run after
        // all of them.
        $this->call(DemoProjectSeeder::class);
        // Depends on DemoProjectSeeder for the linked shape, plus the same
        // classification lookups for the standalone shape.
        $this->call(DemoCampaignSeeder::class);
        // Depends on DemoReferentSeeder/DemoCampaignSeeder (mandatory, BR-1)
        // plus DemoOperationalSiteSeeder/DemoSourceSeeder/
        // DemoUsersSeeder (optional) — must run after all of them.
        $this->call(DemoLeadSeeder::class);
        $this->call(DemoOpportunityStatusSeeder::class);
        // Standalone anagraphic (spec 0058): no dependency on anything above,
        // no producer referencing it yet (BR-3) — order here is arbitrary.
        $this->call(DemoRewardTypeSeeder::class);
        // Standalone anagraphic (spec 0060): no dependency on anything above,
        // upserts on top of the migration-seeded system row — order here is
        // arbitrary.
        $this->call(DemoRewardStatusSeeder::class);
        // Depends on DemoSourceSeeder (mandatory criterion values) and
        // DemoBusinessFunctionSeeder (optional, two-criteria workflow), both
        // seeded above. MUST run before DemoOpportunitySeeder so opportunities
        // whose source matches a workflow resolve to that workflow's own
        // statuses at creation time (the "reference opportunities").
        $this->call(DemoOpportunityWorkflowSeeder::class);
        // The per-category "stati di lavorazione": MUST run after the seeder
        // above (which clears every workflow before seeding its own) and
        // before DemoOpportunitySeeder, whose rows resolve their working-state
        // at creation time.
        $this->call(DemoCategoryWorkflowSeeder::class);
        // Depends on DemoRegistrySeeder (mandatory) plus every optional lookup
        // above (company/company-sites/operational-sites/business-functions/
        // referents/users/sources/product-categories), DemoLeadSeeder (for
        // the BR-1 from-lead batch) and DemoOpportunityStatusSeeder (spec
        // 0043, opportunity_status_id is mandatory) — must run after all of
        // them.
        $this->call(DemoOpportunitySeeder::class);
        // Walks those opportunities through their working statuses and fills
        // the opportunity-context attributes: needs the rows, the pick lists
        // and the attributes, so it runs after all three.
        $this->call(DemoOpportunityLifecycleSeeder::class);
        // One quote per opportunity (spec 0065): depends on DemoOpportunitySeeder
        // for the opportunities themselves and on DemoProductSeeder for the
        // offer/cost line products — must run after both.
        $this->call(DemoQuoteSeeder::class);
        // Depends on DemoRewardTypeSeeder (catalogue) and DemoOpportunitySeeder
        // (reporters to reward, D-3) — must run after both.
        $this->call(DemoRewardSeeder::class);
        // Needs users (avatars) and company sites (logos) already seeded above;
        // attaches demo files through the real HasAttachments write path.
        $this->call(DemoAttachmentSeeder::class);
        $this->call(DemoNotificationSeeder::class);
        // Last: needs every entity's rows already seeded (it populates custom
        // field values on them) and companies for the relation target.
        // $this->call(DemoCustomFieldSeeder::class);
    }
}
