<?php

use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\Campaign;
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
 * Spec 0110, AC-032/AC-033: POST /api/assignment/selection-scope — the
 * UNION of the competence requirements of a SELECTION (D-14), for the three
 * assignment surfaces, answered ordered and deduplicated. The endpoint mints
 * no permission of its own: it reuses the READ gate of the requested domain,
 * 404s on another actor's run, and never lets an out-of-scope offer
 * contribute its categories.
 */
if (! function_exists('selectionScopeActor')) {
    /**
     * @param  array<int, string>  $abilities  fully-qualified ability names
     */
    function selectionScopeActor(array $abilities): User
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

if (! function_exists('selectionScopeProduct')) {
    /** A product in its own category, itself owning a business function. */
    function selectionScopeProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

if (! function_exists('selectionScopeRun')) {
    /**
     * @param  array<string, mixed>  $globalConfig  the run's configuration-step values
     */
    function selectionScopeRun(User $owner, array $globalConfig = []): ImportRun
    {
        return ImportRun::factory()->create([
            'user_id' => $owner->id,
            'resource' => 'leads',
            'status' => ImportStatus::Reviewing,
            'global_config' => $globalConfig,
        ]);
    }
}

if (! function_exists('selectionScopeCampaign')) {
    /** A standalone campaign carrying (or not) its own Sede. */
    function selectionScopeCampaign(?OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site?->id]);
    }
}

if (! function_exists('selectionScopeCampaignRow')) {
    /** A staged row whose OWN campaign is $campaign (per-row campaign, spec 0108). */
    function selectionScopeCampaignRow(ImportRun $run, Campaign $campaign): ImportRunRow
    {
        return ImportRunRow::factory()->for($run, 'importRun')->create([
            'mapped_values' => ['campaign_id' => $campaign->id],
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-032 — the union, per domain, with the AG Grid selection semantics.
// ---------------------------------------------------------------------------

it('0110 AC-032: domain=import_rows answers the ordered, deduplicated union of the targeted rows', function () {
    $actor = selectionScopeActor(['leads.import']);
    $run = selectionScopeRun($actor);
    $first = selectionScopeProduct();
    $second = selectionScopeProduct();
    $untargeted = selectionScopeProduct();

    $rowOne = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$first->id]]);
    $rowTwo = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$second->id]]);
    // Same category as rowOne: the union deduplicates.
    $rowThree = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$first->id]]);
    ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$untargeted->id]]);
    Sanctum::actingAs($actor);

    $expected = collect([$first->category_id, $second->category_id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$rowOne->id, $rowTwo->id, $rowThree->id],
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.product_category_ids', $expected);
});

it('0110 AC-032: domain=import_rows reads select_all=true as "every row except row_ids"', function () {
    $actor = selectionScopeActor(['leads.import']);
    $run = selectionScopeRun($actor);
    $kept = selectionScopeProduct();
    $excluded = selectionScopeProduct();

    ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$kept->id]]);
    $excludedRow = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$excluded->id]]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'select_all' => true,
        'row_ids' => [$excludedRow->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', [$kept->category_id]);
});

it('0110 AC-032: a selection demanding nothing answers an empty list, so the caller filters nothing', function () {
    $actor = selectionScopeActor(['leads.import']);
    $run = selectionScopeRun($actor);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', []);
});

it('0110 AC-032: domain=leads answers the union of the selected leads products of interest', function () {
    $actor = selectionScopeActor(['leads.viewAny']);
    $first = selectionScopeProduct();
    $second = selectionScopeProduct();

    $leadOne = Lead::factory()->create();
    $leadOne->productsOfInterest()->sync([$first->id]);
    $leadTwo = Lead::factory()->create();
    $leadTwo->productsOfInterest()->sync([$second->id, $first->id]);
    Sanctum::actingAs($actor);

    $expected = collect([$first->category_id, $second->category_id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadOne->id, $leadTwo->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', $expected);
});

it('0110 AC-032: domain=quotes answers the union of the opportunity product lines categories', function () {
    $actor = selectionScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $function = BusinessFunction::factory()->create();
    $categoryOne = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $categoryTwo = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $opportunity = Opportunity::factory()->create();
    foreach ([$categoryOne, $categoryTwo] as $category) {
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);
    }
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $expected = collect([$categoryOne->id, $categoryTwo->id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', $expected);
});

it('0110 AC-032: an unknown domain, and an import_rows selection without rows, are 422', function () {
    $actor = selectionScopeActor(['leads.import']);
    $run = selectionScopeRun($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', ['domain' => 'contracts', 'ids' => [1]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('domain');

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
    ])->assertStatus(422)->assertJsonValidationErrors('row_ids');

    $this->postJson('/api/assignment/selection-scope', ['domain' => 'leads'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

// ---------------------------------------------------------------------------
// AC-033 — the domain's own read gate, and no leak across actors.
// ---------------------------------------------------------------------------

it('0110 AC-033: domain=import_rows is 403 without the import ability', function () {
    $actor = selectionScopeActor([]);
    $run = selectionScopeRun($actor);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertForbidden();
});

it('0110 AC-033: another actor import run is a 404, never a 403 and never its categories', function () {
    $actor = selectionScopeActor(['leads.import']);
    $stranger = selectionScopeActor(['leads.import']);
    $run = selectionScopeRun($stranger);
    $product = selectionScopeProduct();
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$product->id]]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertNotFound();

    expect($response->json('data'))->toBeNull();
});

it('0110 AC-033: a non-existent import run is the same 404', function () {
    $actor = selectionScopeActor(['leads.import']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => 999999,
        'row_ids' => [1],
    ])->assertNotFound();
});

it('0110 AC-033: domain=leads is 403 without leads.viewAny', function () {
    $actor = selectionScopeActor([]);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$lead->id],
    ])->assertForbidden();
});

it('0110 AC-033: domain=quotes is 403 without request-management.viewAny', function () {
    $actor = selectionScopeActor([]);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertForbidden();
});

it('0110 AC-033: an offer outside the actor scope contributes nothing to the union (D-3)', function () {
    $actor = selectionScopeActor(['request-management.viewAny']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);
    // Operated by someone else, and the actor holds neither viewAll nor viewSite.
    $quote = Quote::factory()->create([
        'opportunity_id' => $opportunity->id,
        'operator_id' => User::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', []);
});

// ---------------------------------------------------------------------------
// AC-022 / AC-023 — the Sede SHARED by the selection, and the campaigns it
// spans (domain = import_rows).
// ---------------------------------------------------------------------------

it('0113 AC-022: rows of ONE campaign answer that campaign Sede and a single campaign id', function () {
    $actor = selectionScopeActor(['leads.import']);
    $site = OperationalSite::factory()->create();
    $campaign = selectionScopeCampaign($site);
    $run = selectionScopeRun($actor, ['campaign_id' => $campaign->id]);

    $rowOne = ImportRunRow::factory()->for($run, 'importRun')->create();
    $rowTwo = ImportRunRow::factory()->for($run, 'importRun')->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$rowOne->id, $rowTwo->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.campaign_ids', [$campaign->id]);
});

it('0113 AC-023: rows of two campaigns with DIFFERENT Sedi answer a null Sede and both campaigns, ascending', function () {
    $actor = selectionScopeActor(['leads.import']);
    $first = selectionScopeCampaign(OperationalSite::factory()->create());
    $second = selectionScopeCampaign(OperationalSite::factory()->create());
    $run = selectionScopeRun($actor);

    $firstRow = selectionScopeCampaignRow($run, $first);
    $secondRow = selectionScopeCampaignRow($run, $second);
    Sanctum::actingAs($actor);

    $expected = collect([$first->id, $second->id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', null)
        ->assertJsonPath('data.campaign_ids', $expected);
});

it('0113 AC-023: two campaigns SHARING one Sede still answer that Sede, and both campaigns', function () {
    $actor = selectionScopeActor(['leads.import']);
    $site = OperationalSite::factory()->create();
    $first = selectionScopeCampaign($site);
    $second = selectionScopeCampaign($site);
    $run = selectionScopeRun($actor);

    $firstRow = selectionScopeCampaignRow($run, $first);
    $secondRow = selectionScopeCampaignRow($run, $second);
    Sanctum::actingAs($actor);

    $expected = collect([$first->id, $second->id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.campaign_ids', $expected);
});

it('0113 AC-022: ONE row whose Sede is unresolvable is enough to answer a null Sede', function () {
    $actor = selectionScopeActor(['leads.import']);
    $site = OperationalSite::factory()->create();
    $sited = selectionScopeCampaign($site);
    $siteless = selectionScopeCampaign(null);
    $run = selectionScopeRun($actor);

    $sitedRow = selectionScopeCampaignRow($run, $sited);
    $sitelessRow = selectionScopeCampaignRow($run, $siteless);
    Sanctum::actingAs($actor);

    $expected = collect([$sited->id, $siteless->id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$sitedRow->id, $sitelessRow->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', null)
        ->assertJsonPath('data.campaign_ids', $expected);
});

// ---------------------------------------------------------------------------
// AC-023 — real leads take the Sede of their OWN campaign (D-3).
// ---------------------------------------------------------------------------

it('0113 AC-023: domain=leads answers the campaigns of the selection and the Sede they share', function () {
    $actor = selectionScopeActor(['leads.viewAny']);
    $site = OperationalSite::factory()->create();
    $first = selectionScopeCampaign($site);
    $second = selectionScopeCampaign($site);
    $elsewhere = selectionScopeCampaign(OperationalSite::factory()->create());

    // `leads.operational_site_id` is a decoy: the Sede comes from the campaign.
    $leadOne = Lead::factory()->create(['campaign_id' => $first->id, 'operational_site_id' => $elsewhere->operational_site_id]);
    $leadTwo = Lead::factory()->create(['campaign_id' => $second->id]);
    $leadElsewhere = Lead::factory()->create(['campaign_id' => $elsewhere->id]);
    Sanctum::actingAs($actor);

    $shared = collect([$first->id, $second->id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadOne->id, $leadTwo->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.campaign_ids', $shared);

    $mixed = collect([$first->id, $elsewhere->id])->sort()->values()->all();

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'leads',
        'ids' => [$leadOne->id, $leadElsewhere->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', null)
        ->assertJsonPath('data.campaign_ids', $mixed);
});

// ---------------------------------------------------------------------------
// AC-024 — the offers: no campaign at all, Sede read off the offer itself.
// ---------------------------------------------------------------------------

it('0113 AC-024: domain=quotes answers an empty campaign list and the Sede the offers share', function () {
    $actor = selectionScopeActor(['request-management.viewAny', 'request-management.viewAll']);
    $site = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);

    $first = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'operational_site_id' => $site->id]);
    $second = Quote::factory()->create(['operational_site_id' => $site->id]);
    $elsewhere = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$first->id, $second->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.campaign_ids', [])
        ->assertJsonPath('data.product_category_ids', [$category->id]);

    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$first->id, $elsewhere->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', null)
        ->assertJsonPath('data.campaign_ids', []);
});

it('0113 AC-025: an offer outside the actor scope contributes no Sede either (D-3)', function () {
    $actor = selectionScopeActor(['request-management.viewAny']);
    $site = OperationalSite::factory()->create();

    // In scope only because the actor IS its GA2 Operatore.
    $own = Quote::factory()->create(['operational_site_id' => $site->id, 'operator_id' => $actor->id]);
    $foreign = Quote::factory()->create([
        'operational_site_id' => OperationalSite::factory()->create()->id,
        'operator_id' => User::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    // Without the D-3 filter the two Sedi would differ and answer null.
    $this->postJson('/api/assignment/selection-scope', [
        'domain' => 'quotes',
        'ids' => [$own->id, $foreign->id],
    ])->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id);
});
