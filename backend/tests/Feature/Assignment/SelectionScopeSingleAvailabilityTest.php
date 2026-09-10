<?php

use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\Campaign;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Direttiva utente 2026-09-10 — the `single_operator_available` half of
 * POST /api/assignment/selection-scope (spec 0113). Split off
 * SelectionScopeTest, which the rest of the contract already fills to its
 * line budget.
 *
 * The field says whether ONE operator can take the WHOLE selection: the
 * INTERSECTION of the per-record candidate pools is not empty. It is not
 * derivable from `product_category_ids`, which is a UNION and therefore an
 * OR — an operator competent for one of the selected records passes that
 * filter and is then refused by `mode=single` on Gestione richieste (rev.3),
 * which demands an AND.
 */
if (! function_exists('singleScopeActor')) {
    /**
     * @param  array<int, string>  $abilities  fully-qualified ability names
     */
    function singleScopeActor(array $abilities): User
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

if (! function_exists('singleScopeOperator')) {
    /**
     * An operator employed at $site, competent for $categories under
     * $function. Called with no category the profile stays rowless: a member
     * of the Sede competent for nothing (spec 0111 D-9).
     */
    function singleScopeOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($site);

        if ($function !== null && $categories !== []) {
            $factory = $factory->competentIn($function, ...$categories);
        }

        $factory->create();

        return $operator;
    }
}

if (! function_exists('singleScopeOffer')) {
    /**
     * An offer at $site demanding exactly $category. A null $category leaves
     * the opportunity without product lines: an offer demanding nothing,
     * which constrains the competence half not at all (INV-4a) while the
     * Sede half still applies.
     */
    function singleScopeOffer(?OperationalSite $site, ?ProductCategory $category = null, ?User $operator = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        if ($category !== null) {
            OpportunityProductLine::factory()->create([
                'opportunity_id' => $opportunity->id,
                'business_function_id' => $category->business_function_id,
                'product_category_id' => $category->id,
            ]);
        }

        return Quote::factory()->for($opportunity)->create([
            'operational_site_id' => $site?->id,
            'operator_id' => $operator?->id,
        ]);
    }
}

if (! function_exists('singleScopeCategory')) {
    function singleScopeCategory(BusinessFunction $function): ProductCategory
    {
        return ProductCategory::factory()->create(['business_function_id' => $function->id]);
    }
}

// ---------------------------------------------------------------------------
// domain = quotes — the surface the directive is about.
// ---------------------------------------------------------------------------

it('quotes: a selection one operator covers entirely answers true', function () {
    $actor = singleScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $category = singleScopeCategory($function);
    singleScopeOperator($site, $function, $category);

    $first = singleScopeOffer($site, $category);
    $second = singleScopeOffer($site, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$first->id, $second->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);
});

it('quotes: offers in different Sedi with no operator in common answer false', function () {
    $actor = singleScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $function = BusinessFunction::factory()->create();
    $category = singleScopeCategory($function);

    $here = OperationalSite::factory()->create();
    $elsewhere = OperationalSite::factory()->create();
    singleScopeOperator($here, $function, $category);
    singleScopeOperator($elsewhere, $function, $category);

    $first = singleScopeOffer($here, $category);
    $second = singleScopeOffer($elsewhere, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$first->id, $second->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', false);
});

it('quotes: offers demanding different categories no operator covers together answer false', function () {
    $actor = singleScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $first = singleScopeCategory($function);
    $second = singleScopeCategory($function);

    // Each operator covers exactly one of the two: the union of the
    // requirements would offer both of them, the intersection neither.
    singleScopeOperator($site, $function, $first);
    singleScopeOperator($site, $function, $second);

    $firstOffer = singleScopeOffer($site, $first);
    $secondOffer = singleScopeOffer($site, $second);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$firstOffer->id, $secondOffer->id],
    ])->assertOk()
        ->assertJsonPath('data.single_operator_available', false)
        // The union stays what it was: the new field is the only difference.
        ->assertJsonPath('data.product_category_ids', collect([$first->id, $second->id])->sort()->values()->all());
});

it('quotes: offers demanding different categories ONE operator covers answer true', function () {
    $actor = singleScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $first = singleScopeCategory($function);
    $second = singleScopeCategory($function);

    singleScopeOperator($site, $function, $first, $second);
    singleScopeOperator($site, $function, $first);

    $firstOffer = singleScopeOffer($site, $first);
    $secondOffer = singleScopeOffer($site, $second);
    Sanctum::actingAs($actor);

    // Different categories are NOT by themselves a "no": the rule is exact.
    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$firstOffer->id, $secondOffer->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);
});

it('quotes: an offer demanding no category keeps its Sede operators, and no more', function () {
    $actor = singleScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $function = BusinessFunction::factory()->create();
    $category = singleScopeCategory($function);

    $covered = OperationalSite::factory()->create();
    singleScopeOperator($covered, $function, $category);
    $coveredOffers = [singleScopeOffer($covered)->id, singleScopeOffer($covered, $category)->id];

    // Same pair of offers, but the Sede's only operator is competent for
    // nothing: the categoryless offer keeps them, the other one does not.
    $rowless = OperationalSite::factory()->create();
    singleScopeOperator($rowless);
    $rowlessOffers = [singleScopeOffer($rowless)->id, singleScopeOffer($rowless, $category)->id];
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', ['domain' => 'quotes', 'ids' => $coveredOffers])
        ->assertOk()->assertJsonPath('data.single_operator_available', true);

    $this->postJson('/api/assignment/selection-scope', ['domain' => 'quotes', 'ids' => $rowlessOffers])
        ->assertOk()->assertJsonPath('data.single_operator_available', false);
});

it('quotes: an offer without Sede answers false, whatever the rest of the selection', function () {
    $actor = singleScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $category = singleScopeCategory($function);
    singleScopeOperator($site, $function, $category);

    $sited = singleScopeOffer($site, $category);
    $siteless = singleScopeOffer(null, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$sited->id, $siteless->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', false);
});

it('quotes: an offer outside the actor D-3 scope cannot make the answer false', function () {
    $actor = singleScopeActor(['request-management.viewAny']);
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $category = singleScopeCategory($function);
    singleScopeOperator($site, $function, $category);

    // In scope only because the actor IS its GA2 Operatore.
    $own = singleScopeOffer($site, $category, $actor);
    // Another Sede, no operator at all: it would empty any intersection it
    // took part in — and it must not take part in one it does not exist in.
    $foreign = singleScopeOffer(OperationalSite::factory()->create(), $category, User::factory()->create());
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$own->id, $foreign->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);

    // A selection reduced to nothing by the scope has nothing to cover.
    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$foreign->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);
});

// ---------------------------------------------------------------------------
// The field is answered on the three domains, not only where it is consumed.
// ---------------------------------------------------------------------------

it('leads: the intersection follows the campaign Sede and the leads products of interest', function () {
    $actor = singleScopeActor(['leads.viewAny']);
    $function = BusinessFunction::factory()->create();
    $first = singleScopeCategory($function);
    $second = singleScopeCategory($function);

    $site = OperationalSite::factory()->create();
    $campaign = Campaign::factory()->create(['operational_site_id' => $site->id]);
    $elsewhere = Campaign::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);
    singleScopeOperator($site, $function, $first, $second);

    $leadOne = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $leadOne->productsOfInterest()->sync([Product::factory()->create(['category_id' => $first->id])->id]);
    $leadTwo = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $leadTwo->productsOfInterest()->sync([Product::factory()->create(['category_id' => $second->id])->id]);
    $leadElsewhere = Lead::factory()->create(['campaign_id' => $elsewhere->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadOne->id, $leadTwo->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadOne->id, $leadElsewhere->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', false);
});

it('import_rows: the answer follows the campaign of each row, and an empty selection is true', function () {
    $actor = singleScopeActor(['leads.import']);
    $function = BusinessFunction::factory()->create();
    $category = singleScopeCategory($function);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $site = OperationalSite::factory()->create();
    $campaign = Campaign::factory()->create(['operational_site_id' => $site->id]);
    $elsewhere = Campaign::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);
    singleScopeOperator($site, $function, $category);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'global_config' => [],
    ]);
    $covered = ImportRunRow::factory()->for($run, 'importRun')->create([
        'mapped_values' => ['campaign_id' => $campaign->id],
        'product_ids' => [$product->id],
    ]);
    $foreign = ImportRunRow::factory()->for($run, 'importRun')->create([
        'mapped_values' => ['campaign_id' => $elsewhere->id],
        'product_ids' => [$product->id],
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$covered->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$covered->id, $foreign->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', false);

    // select_all excluding every row: nothing to cover, nothing to disable.
    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'select_all' => true,
        'row_ids' => [$covered->id, $foreign->id],
    ])->assertOk()->assertJsonPath('data.single_operator_available', true);
});
