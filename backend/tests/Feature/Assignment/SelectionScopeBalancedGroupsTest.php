<?php

use App\Enums\ImportStatus;
use App\Models\Address;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\City;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0168, AC-001..AC-004 — `POST /api/assignment/selection-scope`'s
 * `balanced_groups`/`balanced_unassignable_count`: the operators a
 * "Smistamento equo" selection would distribute among, grouped by Sede.
 */
if (! function_exists('balancedGroupsActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function balancedGroupsActor(array $abilities): User
    {
        foreach (['leads.viewAny', 'leads.import', 'request-management.viewAny', 'request-management.viewAll'] as $ability) {
            Permission::findOrCreate($ability);
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo($ability);
        }

        return $actor;
    }
}

if (! function_exists('balancedGroupsSite')) {
    /**
     * A Sede with a real address so its label ("{line1} - {city}") is
     * deterministic and sortable on the city name alone — a FIXED line1,
     * unlike OperationalSiteFactory::withAddress()'s random street.
     */
    function balancedGroupsSite(string $cityName, string $line1 = 'Via Test 1'): OperationalSite
    {
        $city = City::factory()->create(['name' => $cityName]);
        $site = OperationalSite::factory()->create();
        Address::factory()->primary()->forCity($city)->for($site, 'addressable')->create(['line1' => $line1]);

        return $site->fresh(['addresses.city']);
    }
}

if (! function_exists('balancedGroupsSiteLabel')) {
    function balancedGroupsSiteLabel(OperationalSite $site): string
    {
        return OperationalSiteLabel::compose($site->fresh(['addresses.city'])->primaryAddress);
    }
}

if (! function_exists('balancedGroupsOperator')) {
    /**
     * An operator employed at $site, competent for $categories under
     * $function when both are given — a rowless membership when they are
     * not (INV-4a: keeps the whole Sede when nothing is required of it).
     */
    function balancedGroupsOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $operator = User::factory()->create();

        $profile = EmploymentProfile::factory()->for($operator)->physicalSite($site);

        if ($function !== null) {
            $profile = $profile->competentIn($function, ...$categories);
        }

        $profile->create();

        return $operator;
    }
}

if (! function_exists('balancedGroupsCategory')) {
    function balancedGroupsCategory(BusinessFunction $function): ProductCategory
    {
        return ProductCategory::factory()->create(['business_function_id' => $function->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-001 — two Sedi, ordered groups, own operators, record_count and load.
// ---------------------------------------------------------------------------

it('0168 AC-001: leads across two Sedi answer two ordered groups with their own operators, record_count and load', function () {
    $actor = balancedGroupsActor(['leads.viewAny']);
    $function = BusinessFunction::factory()->create();
    $categoryNapoli = balancedGroupsCategory($function);
    $categoryRoma = balancedGroupsCategory($function);

    $napoli = balancedGroupsSite('Napoli');
    $roma = balancedGroupsSite('Roma');

    $operatorNapoli = balancedGroupsOperator($napoli, $function, $categoryNapoli);
    $operatorRoma = balancedGroupsOperator($roma, $function, $categoryRoma);

    // operatorNapoli already carries 2 real leads: the load the distribution
    // would start from.
    Lead::factory()->count(2)->create(['operator_id' => $operatorNapoli->id]);

    $campaignNapoli = Campaign::factory()->create(['operational_site_id' => $napoli->id]);
    $campaignRoma = Campaign::factory()->create(['operational_site_id' => $roma->id]);
    $productNapoli = Product::factory()->create(['category_id' => $categoryNapoli->id]);
    $productRoma = Product::factory()->create(['category_id' => $categoryRoma->id]);

    $leadNapoliOne = Lead::factory()->create(['campaign_id' => $campaignNapoli->id]);
    $leadNapoliOne->productsOfInterest()->sync([$productNapoli->id]);
    $leadNapoliTwo = Lead::factory()->create(['campaign_id' => $campaignNapoli->id]);
    $leadNapoliTwo->productsOfInterest()->sync([$productNapoli->id]);
    $leadRoma = Lead::factory()->create(['campaign_id' => $campaignRoma->id]);
    $leadRoma->productsOfInterest()->sync([$productRoma->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadNapoliOne->id, $leadNapoliTwo->id, $leadRoma->id],
    ])->assertOk();

    $response->assertJsonPath('data.balanced_unassignable_count', 0)
        ->assertJsonPath('data.balanced_groups.0.operational_site_id', $napoli->id)
        ->assertJsonPath('data.balanced_groups.0.operational_site_label', balancedGroupsSiteLabel($napoli))
        ->assertJsonPath('data.balanced_groups.0.record_count', 2)
        ->assertJsonPath('data.balanced_groups.0.operators', [
            ['id' => $operatorNapoli->id, 'label' => $operatorNapoli->name, 'avatar_url' => null, 'load' => 2],
        ])
        ->assertJsonPath('data.balanced_groups.1.operational_site_id', $roma->id)
        ->assertJsonPath('data.balanced_groups.1.operational_site_label', balancedGroupsSiteLabel($roma))
        ->assertJsonPath('data.balanced_groups.1.record_count', 1)
        ->assertJsonPath('data.balanced_groups.1.operators', [
            ['id' => $operatorRoma->id, 'label' => $operatorRoma->name, 'avatar_url' => null, 'load' => 0],
        ]);
});

// ---------------------------------------------------------------------------
// AC-002 — an operator of two Sedi appears in both groups.
// ---------------------------------------------------------------------------

it('0168 AC-002: an operator member of two Sedi appears in both their groups', function () {
    $actor = balancedGroupsActor(['leads.viewAny']);
    $napoli = balancedGroupsSite('Napoli');
    $roma = balancedGroupsSite('Roma');

    // Wildcard competence (spec 0129): a lead falling back to its campaign's
    // own product categories (LeadCompetence) must not turn this into a
    // competence test — membership alone is what AC-002 is about.
    $operator = User::factory()->create();
    EmploymentProfile::factory()->for($operator)->physicalSite($napoli)->remoteSites($roma)
        ->coversAllProductCategories()->create();

    $campaignNapoli = Campaign::factory()->create(['operational_site_id' => $napoli->id]);
    $campaignRoma = Campaign::factory()->create(['operational_site_id' => $roma->id]);
    $leadNapoli = Lead::factory()->create(['campaign_id' => $campaignNapoli->id]);
    $leadRoma = Lead::factory()->create(['campaign_id' => $campaignRoma->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadNapoli->id, $leadRoma->id],
    ])->assertOk()
        ->assertJsonPath('data.balanced_groups.0.operators.0.id', $operator->id)
        ->assertJsonPath('data.balanced_groups.1.operators.0.id', $operator->id);
});

// ---------------------------------------------------------------------------
// AC-003 — no Sede / empty pool: no group, counted as unassignable.
// ---------------------------------------------------------------------------

it('0168 AC-003: records with no Sede or an empty pool generate no group and count as unassignable', function () {
    $actor = balancedGroupsActor(['leads.viewAny']);
    $function = BusinessFunction::factory()->create();
    $category = balancedGroupsCategory($function);

    $covered = balancedGroupsSite('Napoli');
    $operator = balancedGroupsOperator($covered, $function, $category);
    $campaignCovered = Campaign::factory()->create(['operational_site_id' => $covered->id]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $leadCovered = Lead::factory()->create(['campaign_id' => $campaignCovered->id]);
    $leadCovered->productsOfInterest()->sync([$product->id]);

    // No Sede at all: the campaign carries none.
    $campaignless = Campaign::factory()->create(['operational_site_id' => null]);
    $leadWithoutSite = Lead::factory()->create(['campaign_id' => $campaignless->id]);

    // A Sede with no operator at all: an empty pool.
    $empty = balancedGroupsSite('Torino');
    $campaignEmpty = Campaign::factory()->create(['operational_site_id' => $empty->id]);
    $leadWithEmptyPool = Lead::factory()->create(['campaign_id' => $campaignEmpty->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadCovered->id, $leadWithoutSite->id, $leadWithEmptyPool->id],
    ])->assertOk()
        ->assertJsonCount(1, 'data.balanced_groups')
        ->assertJsonPath('data.balanced_groups.0.operational_site_id', $covered->id)
        ->assertJsonPath('data.balanced_groups.0.record_count', 1)
        ->assertJsonPath('data.balanced_unassignable_count', 2);
});

// ---------------------------------------------------------------------------
// AC-004 — quotes/enrollees load counts offers, D-3 scope, import_rows'
// grouping follows the row's campaign.
// ---------------------------------------------------------------------------

it('0168 AC-004: domain=quotes answers a load counting the operator OFFERS, and skips out-of-scope offers', function () {
    $actor = balancedGroupsActor(['request-management.viewAny']);
    $function = BusinessFunction::factory()->create();
    $category = balancedGroupsCategory($function);
    $site = balancedGroupsSite('Napoli');
    $operator = balancedGroupsOperator($site, $function, $category);

    // 3 offers already operated by $operator: the load the distribution
    // would start from — NOT the leads count.
    Quote::factory()->count(3)->create(['operator_id' => $operator->id]);
    Lead::factory()->count(9)->create(['operator_id' => $operator->id]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);
    // In scope only because the actor IS its GA2 Operatore (D-3 tier 2):
    // `viewAny` alone grants no Sede-wide visibility.
    $inScope = Quote::factory()->create([
        'opportunity_id' => $opportunity->id,
        'operational_site_id' => $site->id,
        'operator_id' => $actor->id,
    ]);

    // Out of scope for an actor with only `viewAny`: operated by someone
    // else, so D-3 drops it before it can contribute a group at all.
    $outOfScope = Quote::factory()->create([
        'operational_site_id' => $site->id,
        'operator_id' => User::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$inScope->id, $outOfScope->id],
    ])->assertOk()
        ->assertJsonPath('data.balanced_groups.0.record_count', 1)
        ->assertJsonPath('data.balanced_groups.0.operators.0.load', 3)
        ->assertJsonPath('data.balanced_unassignable_count', 0);
});

it('0168 AC-004: domain=import_rows groups follow the Sede of each row own campaign', function () {
    $actor = balancedGroupsActor(['leads.import']);
    $function = BusinessFunction::factory()->create();
    $category = balancedGroupsCategory($function);
    $site = balancedGroupsSite('Napoli');
    $operator = balancedGroupsOperator($site, $function, $category);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $campaign = Campaign::factory()->create(['operational_site_id' => $site->id]);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'global_config' => [],
    ]);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create([
        'mapped_values' => ['campaign_id' => $campaign->id],
        'product_ids' => [$product->id],
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.balanced_groups.0.operational_site_id', $site->id)
        ->assertJsonPath('data.balanced_groups.0.record_count', 1)
        ->assertJsonPath('data.balanced_groups.0.operators.0.id', $operator->id)
        ->assertJsonPath('data.balanced_unassignable_count', 0);
});
