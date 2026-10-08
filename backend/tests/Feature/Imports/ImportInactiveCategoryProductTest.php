<?php

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0208 (AC-014): the lead import's `product_ids` (per-row PATCH and
 * bulk assign) refuse a NEW product of an inactive category; the products
 * already on the row stay valid (D-2).
 */
uses(RefreshDatabase::class);

/** @return array{actor: User, run: ImportRun, product: Product} */
function inactiveCategoryImportScenario(): array
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['leads.import', 'leads.update']);
    grantImportRunsPermissions($actor, ['update']);

    $campaign = Campaign::factory()->create();
    $product = Product::factory()->create(['category_id' => $campaign->productLines()->first()->productCategory->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);

    return ['actor' => $actor, 'run' => $run, 'product' => $product];
}

it('AC-014: the per-row PATCH keeps the products on the row and refuses a NEW one of an inactive category', function () {
    ['actor' => $actor, 'run' => $run, 'product' => $product] = inactiveCategoryImportScenario();
    $row = ImportRunRow::factory()->create([
        'import_run_id' => $run->id, 'status' => ImportRowStatus::Valid, 'product_ids' => [$product->id],
    ]);
    $product->category->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$product->id]])->assertOk();

    $other = Product::factory()->create(['category_id' => $product->category_id]);
    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$product->id, $other->id]])
        ->assertStatus(422)->assertJsonValidationErrors('product_ids.1');

    expect($response->json('errors')['product_ids.1'])->toBe([__('This product belongs to an inactive category.')])
        ->and($row->fresh()->product_ids)->toBe([$product->id]);
});

it('AC-014: bulk assign refuses a product of an inactive category', function () {
    ['actor' => $actor, 'run' => $run, 'product' => $product] = inactiveCategoryImportScenario();
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    $product->category->update(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'product_ids' => [$product->id],
        'row_ids' => [$row->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_ids.0');

    expect($row->fresh()->product_ids)->toBeNull();
});
