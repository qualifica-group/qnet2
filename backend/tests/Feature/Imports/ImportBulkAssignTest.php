<?php

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0045 bulk increment, extended to a COMBINED operator+site assignment
 * — PATCH /api/imports/{domain}/{importRun}/rows/assign: bulk-assign an
 * operator and/or an operational site to many staged rows in a single mass
 * UPDATE, AG Grid `getServerSideSelectionState()` semantics (`row_ids` are
 * the targeted rows when `select_all` is false, the EXCLUDED rows when
 * true). Distinct from the single-row PATCH .../rows/{row} (see
 * ImportRowOverrideTest / ImportOpportunityConversionTest).
 */

/**
 * @param  array<int, string>  $abilities
 */
function bulkAssignActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

it('assigns the operator and is_edited to the targeted row_ids only', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $row2 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    $untouched = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'row_ids' => [$row1->id, $row2->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect($row1->fresh()->operator_id)->toBe($operator->id)
        ->and($row1->fresh()->is_edited)->toBeTrue()
        ->and($row2->fresh()->operator_id)->toBe($operator->id)
        ->and($row2->fresh()->is_edited)->toBeTrue()
        ->and($untouched->fresh()->operator_id)->toBeNull()
        ->and($untouched->fresh()->is_edited)->toBeFalse();
});

it('assigns the operational site and is_edited to the targeted row_ids only', function () {
    $actor = bulkAssignActor(['import']);
    $site = OperationalSite::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $untouched = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operational_site_id' => $site->id,
        'row_ids' => [$row1->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1);

    expect($row1->fresh()->operational_site_id)->toBe($site->id)
        ->and($row1->fresh()->is_edited)->toBeTrue()
        ->and($untouched->fresh()->operational_site_id)->toBeNull()
        ->and($untouched->fresh()->is_edited)->toBeFalse();
});

it('assigns BOTH operator and operational site in a single request', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $site = OperationalSite::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'operational_site_id' => $site->id,
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1);

    expect($row->fresh()->operator_id)->toBe($operator->id)
        ->and($row->fresh()->operational_site_id)->toBe($site->id)
        ->and($row->fresh()->is_edited)->toBeTrue();
});

it('select_all=true assigns the operator to every row in the run', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $row2 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'select_all' => true,
    ])->assertOk()
        ->assertJsonPath('data.updated', 2);

    expect($row1->fresh()->operator_id)->toBe($operator->id)
        ->and($row1->fresh()->is_edited)->toBeTrue()
        ->and($row2->fresh()->operator_id)->toBe($operator->id)
        ->and($row2->fresh()->is_edited)->toBeTrue();
});

it('select_all=true with row_ids excludes those rows from the assignment', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $included = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $excluded = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'select_all' => true,
        'row_ids' => [$excluded->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1);

    expect($included->fresh()->operator_id)->toBe($operator->id)
        ->and($excluded->fresh()->operator_id)->toBeNull()
        ->and($excluded->fresh()->is_edited)->toBeFalse();
});

it('422 when a row_id belongs to another run, and nothing is modified (anti-IDOR)', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $otherRun = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $ownRow = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $foreignRow = ImportRunRow::factory()->create(['import_run_id' => $otherRun->id, 'row_number' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'row_ids' => [$ownRow->id, $foreignRow->id],
    ])->assertStatus(422)->assertJsonValidationErrors('row_ids');

    expect($ownRow->fresh()->operator_id)->toBeNull()
        ->and($foreignRow->fresh()->operator_id)->toBeNull();
});

it('422 when the run is not in reviewing, no row modified', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Staging]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'row_ids' => [$row->id],
    ])->assertStatus(422);

    expect($row->fresh()->operator_id)->toBeNull();
});

it('403 without leads.import, no row modified', function () {
    $actor = bulkAssignActor([]);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'row_ids' => [$row->id],
    ])->assertForbidden();

    expect($row->fresh()->operator_id)->toBeNull();
});

it('422 when select_all is false and row_ids is empty', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'row_ids' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('row_ids');
});

it('422 when neither operator_id nor operational_site_id is submitted', function () {
    $actor = bulkAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", ['row_ids' => [$row->id]])
        ->assertStatus(422)->assertJsonValidationErrors('operator_id');
});

it('422 when operator_id is not an existing user', function () {
    $actor = bulkAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", ['operator_id' => 999999, 'row_ids' => [$row->id]])
        ->assertStatus(422)->assertJsonValidationErrors('operator_id');
});

it('422 when operational_site_id is not an existing operational site', function () {
    $actor = bulkAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", ['operational_site_id' => 999999, 'row_ids' => [$row->id]])
        ->assertStatus(422)->assertJsonValidationErrors('operational_site_id');
});

// ---------------------------------------------------------------------------
// spec 0094 increment — bulk `product_ids` ("Prodotti di interesse")
// ---------------------------------------------------------------------------

it('assigns product_ids and is_edited to the targeted row_ids only, cast back to ints on read', function () {
    $actor = bulkAssignActor(['import']);
    $campaign = Campaign::factory()->create();
    $category = $campaign->productLines()->first()->productCategory;
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row1 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $row2 = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    $untouched = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'product_ids' => [$product->id],
        'row_ids' => [$row1->id, $row2->id],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    expect($row1->fresh()->product_ids)->toBe([$product->id])
        ->and($row1->fresh()->is_edited)->toBeTrue()
        ->and($row2->fresh()->product_ids)->toBe([$product->id])
        ->and($row2->fresh()->is_edited)->toBeTrue()
        ->and($untouched->fresh()->product_ids)->toBeNull()
        ->and($untouched->fresh()->is_edited)->toBeFalse();
});

it('select_all=true with row_ids excludes those rows from the product_ids assignment', function () {
    $actor = bulkAssignActor(['import']);
    $campaign = Campaign::factory()->create();
    $category = $campaign->productLines()->first()->productCategory;
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $included = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    $excluded = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'product_ids' => [$product->id],
        'select_all' => true,
        'row_ids' => [$excluded->id],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    expect($included->fresh()->product_ids)->toBe([$product->id])
        ->and($excluded->fresh()->product_ids)->toBeNull()
        ->and($excluded->fresh()->is_edited)->toBeFalse();
});

it('assigns operator_id and product_ids together in a single request', function () {
    $actor = bulkAssignActor(['import']);
    $operator = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $category = $campaign->productLines()->first()->productCategory;
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'product_ids' => [$product->id],
        'row_ids' => [$row->id],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    expect($row->fresh()->operator_id)->toBe($operator->id)
        ->and($row->fresh()->product_ids)->toBe([$product->id])
        ->and($row->fresh()->is_edited)->toBeTrue();
});

it("422 when a bulk product is outside the run's campaign covered categories", function () {
    $actor = bulkAssignActor(['import']);
    $campaign = Campaign::factory()->create();
    $outsideProduct = Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id, 'name' => 'Fibra 1000']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'product_ids' => [$outsideProduct->id],
        'row_ids' => [$row->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_ids');

    expect($row->fresh()->product_ids)->toBeNull();
});

it('422 when product_ids is an empty array (bulk-only ASSIGNS, no clearing)', function () {
    $actor = bulkAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", ['product_ids' => [], 'row_ids' => [$row->id]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids');
});

it('422 when product_ids is null', function () {
    $actor = bulkAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", ['product_ids' => null, 'row_ids' => [$row->id]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids');
});

it('422 when product_ids.* is not an existing product', function () {
    $actor = bulkAssignActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", ['product_ids' => [999999], 'row_ids' => [$row->id]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids.0');
});
