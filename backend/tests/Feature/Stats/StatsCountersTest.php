<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * AC-005 for the counters added when the stat count was uniformed to exactly 4
 * per module (requirement change on top of spec 0026). Each case pins the value
 * of a new counter on a known factory dataset; the helpers (statsWidgets /
 * statsWidget / statsUserWith) are the ones already used by the other Stats
 * tests.
 */
if (! function_exists('statsUserCompetentIn')) {
    /**
     * An employment profile owning one competence row per given function
     * (spec 0111), each on its own category so two rows on the SAME function
     * never collide with the unique (profile, function, category) constraint.
     */
    function statsUserCompetentIn(BusinessFunction ...$functions): EmploymentProfile
    {
        $profile = EmploymentProfile::factory()->create();

        foreach ($functions as $function) {
            $profile->productLines()->create([
                'business_function_id' => $function->id,
                'product_category_id' => ProductCategory::factory()->create()->id,
            ]);
        }

        return $profile;
    }
}

// ---------------------------------------------------------------------------
// registries · referents
// ---------------------------------------------------------------------------

it('registries: `agreed` counts only the registries whose agreement is signed (AC-005)', function () {
    Registry::factory()->count(2)->create(['agreement_status' => 'agreed']);
    Registry::factory()->create(['agreement_status' => 'negotiating']);
    Registry::factory()->create(['agreement_status' => null]);

    $widgets = statsWidgets('registries');

    expect(statsWidget($widgets, 'total')['value'])->toBe(4)
        ->and(statsWidget($widgets, 'agreed'))->toMatchArray([
            'type' => 'stat', 'label' => 'registries.stats.agreed', 'value' => 2, 'format' => 'number',
        ]);
});

it('referents: `assigned` counts the referents linked to at least one registry (AC-005)', function () {
    $linked = Referent::factory()->count(2)->create();
    Referent::factory()->create();

    Registry::factory()->create()->referents()->attach($linked->pluck('id'));

    // A second registry on the SAME referent must not count it twice.
    Registry::factory()->create()->referents()->attach($linked->first()->id);

    $widgets = statsWidgets('referents');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'assigned'))->toMatchArray([
            'label' => 'referents.stats.assigned', 'value' => 2,
        ]);
});

// ---------------------------------------------------------------------------
// companies · company-sites
// ---------------------------------------------------------------------------

it('companies: `withSites` and `sites` count the company-site footprint (AC-005)', function () {
    $company = Company::factory()->create();
    CompanySite::factory()->count(2)->create(['company_id' => $company->id]);
    Company::factory()->create();
    // An orphan site (company_id is nullable) belongs to no company.
    CompanySite::factory()->create(['company_id' => null]);

    $widgets = statsWidgets('companies');

    expect(statsWidget($widgets, 'total')['value'])->toBe(2)
        ->and(statsWidget($widgets, 'with_sites'))->toMatchArray([
            'label' => 'companies.stats.withSites', 'value' => 1,
        ])
        ->and(statsWidget($widgets, 'sites'))->toMatchArray([
            'label' => 'companies.stats.sites', 'value' => 3,
        ]);
});

it('company-sites: `withBank` and `companies` (distinct, non-null) (AC-005)', function () {
    $company = Company::factory()->create();
    $banked = CompanySite::factory()->create(['company_id' => $company->id]);
    CompanySite::factory()->create(['company_id' => $company->id]);
    CompanySite::factory()->create(['company_id' => null]);

    // Two banks on the same site: the site still counts once.
    CompanySiteBank::factory()->count(2)->create(['company_site_id' => $banked->id]);

    $widgets = statsWidgets('company-sites');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'with_bank'))->toMatchArray([
            'label' => 'companySites.stats.withBank', 'value' => 1,
        ])
        ->and(statsWidget($widgets, 'companies'))->toMatchArray([
            'label' => 'companySites.stats.companies', 'value' => 1,
        ]);
});

// ---------------------------------------------------------------------------
// operational-sites
// ---------------------------------------------------------------------------

it('operational-sites: `withAddress`, `staffed` and `leads` (AC-005, AC-020)', function () {
    $addressed = OperationalSite::factory()->withAddress()->create();
    $bare = OperationalSite::factory()->create();
    $remoteOnly = OperationalSite::factory()->create();

    // Staff assignment lives on the employment_profile_operational_site
    // pivot, not on a column of either side (spec 0103, D-9/D-12): two
    // memberships (one physical, one remote) on the SAME site, one lone
    // remote membership on another site (AC-020), and an unstaffed profile.
    $onSite = EmploymentProfile::factory()->create();
    $remoteOnSameSite = EmploymentProfile::factory()->create();
    $remoteOnlyProfile = EmploymentProfile::factory()->create();
    EmploymentProfile::factory()->create();

    DB::table('employment_profile_operational_site')->insert([
        ['employment_profile_id' => $onSite->id, 'operational_site_id' => $addressed->id, 'is_primary' => true],
        ['employment_profile_id' => $remoteOnSameSite->id, 'operational_site_id' => $addressed->id, 'is_primary' => false],
        ['employment_profile_id' => $remoteOnlyProfile->id, 'operational_site_id' => $remoteOnly->id, 'is_primary' => false],
    ]);

    // `$bare` gets no membership at all: it must NOT be counted as staffed.
    Lead::factory()->count(3)->create(['operational_site_id' => $bare->id]);
    Lead::factory()->create(['operational_site_id' => null]);

    $widgets = statsWidgets('operational-sites');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'with_address'))->toMatchArray([
            'label' => 'operationalSites.stats.withAddress', 'value' => 1,
        ])
        // Two profiles on the SAME site: the site counts once. The
        // remote-only site counts too (D-12); the unstaffed `$bare` site
        // does not.
        ->and(statsWidget($widgets, 'staffed'))->toMatchArray([
            'label' => 'operationalSites.stats.staffed', 'value' => 2,
        ])
        ->and(statsWidget($widgets, 'leads'))->toMatchArray([
            'label' => 'operationalSites.stats.leads', 'value' => 3,
        ]);
});

it('operational-sites: every counter is 0 on an empty module (AC-005)', function () {
    $widgets = statsWidgets('operational-sites');

    expect(statsWidget($widgets, 'with_address')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'staffed')['value'])->toBe(0)
        ->and(statsWidget($widgets, 'leads')['value'])->toBe(0);
});

// ---------------------------------------------------------------------------
// products · product-categories
// ---------------------------------------------------------------------------

it('products: `averageCost` is an SQL AVG, null on an empty catalogue (AC-005)', function () {
    Product::factory()->create(['price' => 100, 'cost' => 40]);
    Product::factory()->create(['price' => 200, 'cost' => 60]);

    $widgets = statsWidgets('products');

    expect(statsWidget($widgets, 'average_cost'))->toMatchArray([
        'label' => 'products.stats.averageCost', 'value' => 50.0, 'format' => 'currency',
    ]);
});

it('products: `averageCost` is null on an empty catalogue (AC-005)', function () {
    expect(statsWidget(statsWidgets('products'), 'average_cost')['value'])->toBeNull();
});

it('product-categories: `withProducts` and `inheritsAttributes` (AC-005)', function () {
    $stocked = ProductCategory::factory()->create();
    ProductCategory::factory()->create();
    ProductCategory::factory()->notInheriting()->create();

    Product::factory()->count(2)->create(['category_id' => $stocked->id]);

    $widgets = statsWidgets('product-categories');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        // Two products in the SAME category: the category counts once.
        ->and(statsWidget($widgets, 'with_products'))->toMatchArray([
            'label' => 'productCategories.stats.withProducts', 'value' => 1,
        ])
        ->and(statsWidget($widgets, 'inherits_attributes'))->toMatchArray([
            'label' => 'productCategories.stats.inheritsAttributes', 'value' => 2,
        ]);
});

// ---------------------------------------------------------------------------
// leads · business-functions · users
// ---------------------------------------------------------------------------

it('leads: `assigned` counts the leads with an operator (AC-005)', function () {
    $operator = User::factory()->create();
    Lead::factory()->count(2)->create(['operator_id' => $operator->id]);
    Lead::factory()->create(['operator_id' => null]);

    $widgets = statsWidgets('leads');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'assigned'))->toMatchArray([
            'label' => 'leads.stats.assigned', 'value' => 2,
        ]);
});

it('leads: `withSource` and `withSite` count leads with a non-null source/site (AC-005)', function () {
    $source = Source::factory()->create();
    $site = OperationalSite::factory()->create();
    Lead::factory()->count(2)->create(['source_id' => $source->id, 'operational_site_id' => $site->id]);
    Lead::factory()->create(['source_id' => null, 'operational_site_id' => null]);

    $widgets = statsWidgets('leads');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'with_source'))->toMatchArray([
            'label' => 'leads.stats.withSource', 'value' => 2,
        ])
        ->and(statsWidget($widgets, 'with_site'))->toMatchArray([
            'label' => 'leads.stats.withSite', 'value' => 2,
        ]);
});

// ---------------------------------------------------------------------------
// projects
// ---------------------------------------------------------------------------

it('projects: `allocatedBudget` sums total_budget of campaigns linked to a project (AC-005)', function () {
    $project = Project::factory()->create();
    Campaign::factory()->forProject($project)->create(['total_budget' => 300]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 200]);
    // A standalone campaign (project_id null) is not allocated to any project.
    Campaign::factory()->create(['total_budget' => 1000]);

    $widgets = statsWidgets('projects');

    expect(statsWidget($widgets, 'allocated_budget'))->toMatchArray([
        'label' => 'projects.stats.allocatedBudget', 'value' => 500.0, 'format' => 'currency',
    ]);
});

it('business-functions: `withManager` counts the functions with a manager (AC-005)', function () {
    $manager = User::factory()->create();
    BusinessFunction::factory()->count(2)->create(['manager_id' => $manager->id]);
    BusinessFunction::factory()->create(['manager_id' => null]);

    $widgets = statsWidgets('business-functions');

    expect(statsWidget($widgets, 'total')['value'])->toBe(3)
        ->and(statsWidget($widgets, 'with_manager'))->toMatchArray([
            'label' => 'businessFunctions.stats.withManager', 'value' => 2,
        ]);
});

it('users: `by_business_function` counts DISTINCT users per function, not competence rows (spec 0111 AC-020)', function () {
    $sales = BusinessFunction::factory()->create(['name' => 'Sales']);
    $legal = BusinessFunction::factory()->create(['name' => 'Legal']);

    // Two rows on the SAME function must weigh 1, two rows on DIFFERENT
    // functions must weigh 1 on each.
    statsUserCompetentIn($sales, $sales);
    statsUserCompetentIn($sales, $legal);

    $widgets = statsWidgets('users');

    // 2 users from the profiles + the acting user (statsUserWith).
    expect(statsWidget($widgets, 'by_business_function')['total'])->toBe(3)
        ->and(statsWidget($widgets, 'by_business_function')['items'])->toBe([
            ['key' => (string) $sales->id, 'label' => 'Sales', 'value' => 2, 'color' => null],
            ['key' => (string) $legal->id, 'label' => 'Legal', 'value' => 1, 'color' => null],
        ]);
});

it('users: `managers` counts the employment profiles flagged as manager (AC-005)', function () {
    EmploymentProfile::factory()->count(2)->manager()->create();
    EmploymentProfile::factory()->create();

    $widgets = statsWidgets('users');

    // 3 users from the profiles + the acting user (statsUserWith).
    expect(statsWidget($widgets, 'total')['value'])->toBe(4)
        ->and(statsWidget($widgets, 'managers'))->toMatchArray([
            'label' => 'users.stats.managers', 'value' => 2,
        ]);
});
