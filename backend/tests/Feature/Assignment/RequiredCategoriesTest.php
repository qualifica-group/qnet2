<?php

use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
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
 * Spec 0110, AC-032/AC-033: POST /api/assignment/required-categories — the
 * UNION of the competence requirements of a SELECTION (D-14), for the three
 * assignment surfaces, answered ordered and deduplicated. The endpoint mints
 * no permission of its own: it reuses the READ gate of the requested domain,
 * 404s on another actor's run, and never lets an out-of-scope offer
 * contribute its categories.
 */
if (! function_exists('requiredCategoriesActor')) {
    /**
     * @param  array<int, string>  $abilities  fully-qualified ability names
     */
    function requiredCategoriesActor(array $abilities): User
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

if (! function_exists('requiredCategoriesProduct')) {
    /** A product in its own category, itself owning a business function. */
    function requiredCategoriesProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

if (! function_exists('requiredCategoriesRun')) {
    function requiredCategoriesRun(User $owner): ImportRun
    {
        return ImportRun::factory()->create([
            'user_id' => $owner->id,
            'resource' => 'leads',
            'status' => ImportStatus::Reviewing,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-032 — the union, per domain, with the AG Grid selection semantics.
// ---------------------------------------------------------------------------

it('0110 AC-032: domain=import_rows answers the ordered, deduplicated union of the targeted rows', function () {
    $actor = requiredCategoriesActor(['leads.import']);
    $run = requiredCategoriesRun($actor);
    $first = requiredCategoriesProduct();
    $second = requiredCategoriesProduct();
    $untargeted = requiredCategoriesProduct();

    $rowOne = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$first->id]]);
    $rowTwo = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$second->id]]);
    // Same category as rowOne: the union deduplicates.
    $rowThree = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$first->id]]);
    ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$untargeted->id]]);
    Sanctum::actingAs($actor);

    $expected = collect([$first->category_id, $second->category_id])->sort()->values()->all();

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$rowOne->id, $rowTwo->id, $rowThree->id],
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.product_category_ids', $expected);
});

it('0110 AC-032: domain=import_rows reads select_all=true as "every row except row_ids"', function () {
    $actor = requiredCategoriesActor(['leads.import']);
    $run = requiredCategoriesRun($actor);
    $kept = requiredCategoriesProduct();
    $excluded = requiredCategoriesProduct();

    ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$kept->id]]);
    $excludedRow = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$excluded->id]]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'select_all' => true,
        'row_ids' => [$excludedRow->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', [$kept->category_id]);
});

it('0110 AC-032: a selection demanding nothing answers an empty list, so the caller filters nothing', function () {
    $actor = requiredCategoriesActor(['leads.import']);
    $run = requiredCategoriesRun($actor);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', []);
});

it('0110 AC-032: domain=leads answers the union of the selected leads products of interest', function () {
    $actor = requiredCategoriesActor(['leads.viewAny']);
    $first = requiredCategoriesProduct();
    $second = requiredCategoriesProduct();

    $leadOne = Lead::factory()->create();
    $leadOne->productsOfInterest()->sync([$first->id]);
    $leadTwo = Lead::factory()->create();
    $leadTwo->productsOfInterest()->sync([$second->id, $first->id]);
    Sanctum::actingAs($actor);

    $expected = collect([$first->category_id, $second->category_id])->sort()->values()->all();

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'leads',
        'ids' => [$leadOne->id, $leadTwo->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', $expected);
});

it('0110 AC-032: domain=quotes answers the union of the opportunity product lines categories', function () {
    $actor = requiredCategoriesActor(['request-management.viewAny', 'request-management.viewAll']);
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

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', $expected);
});

it('0110 AC-032: an unknown domain, and an import_rows selection without rows, are 422', function () {
    $actor = requiredCategoriesActor(['leads.import']);
    $run = requiredCategoriesRun($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', ['domain' => 'contracts', 'ids' => [1]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('domain');

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
    ])->assertStatus(422)->assertJsonValidationErrors('row_ids');

    $this->postJson('/api/assignment/required-categories', ['domain' => 'leads'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

// ---------------------------------------------------------------------------
// AC-033 — the domain's own read gate, and no leak across actors.
// ---------------------------------------------------------------------------

it('0110 AC-033: domain=import_rows is 403 without the import ability', function () {
    $actor = requiredCategoriesActor([]);
    $run = requiredCategoriesRun($actor);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertForbidden();
});

it('0110 AC-033: another actor import run is a 404, never a 403 and never its categories', function () {
    $actor = requiredCategoriesActor(['leads.import']);
    $stranger = requiredCategoriesActor(['leads.import']);
    $run = requiredCategoriesRun($stranger);
    $product = requiredCategoriesProduct();
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$product->id]]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => $run->id,
        'row_ids' => [$row->id],
    ])->assertNotFound();

    expect($response->json('data'))->toBeNull();
});

it('0110 AC-033: a non-existent import run is the same 404', function () {
    $actor = requiredCategoriesActor(['leads.import']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'import_rows',
        'import_run_id' => 999999,
        'row_ids' => [1],
    ])->assertNotFound();
});

it('0110 AC-033: domain=leads is 403 without leads.viewAny', function () {
    $actor = requiredCategoriesActor([]);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'leads',
        'ids' => [$lead->id],
    ])->assertForbidden();
});

it('0110 AC-033: domain=quotes is 403 without request-management.viewAny', function () {
    $actor = requiredCategoriesActor([]);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertForbidden();
});

it('0110 AC-033: an offer outside the actor scope contributes nothing to the union (D-3)', function () {
    $actor = requiredCategoriesActor(['request-management.viewAny']);
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

    $this->postJson('/api/assignment/required-categories', [
        'domain' => 'quotes',
        'ids' => [$quote->id],
    ])->assertOk()
        ->assertJsonPath('data.product_category_ids', []);
});
