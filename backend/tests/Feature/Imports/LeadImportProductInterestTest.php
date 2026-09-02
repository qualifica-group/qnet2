<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\LeadsImportDefinition;
use App\Models\Campaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0094 (D-4): the import wizard's "Prodotti di interesse" — a GLOBAL
 * multi-value configuration field (AC-050/AC-051/AC-052), overridable per
 * staged row (AC-054), applied at persist time (AC-053) via the SAME
 * coherence rule the Lead endpoint itself enforces
 * (App\Services\Opportunities\ProductCategoryCoherence, LEAD_MESSAGE).
 */

/**
 * @param  array<int, string>  $abilities
 */
if (! function_exists('importProductActorWith')) {
    function importProductActorWith(array $abilities): User
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
}

if (! function_exists('importCoveredCategory')) {
    /**
     * The ONE product category a standalone Campaign::factory() row already
     * covers — CampaignFactory::configure()'s afterCreating() attaches it.
     */
    function importCoveredCategory(Campaign $campaign): ProductCategory
    {
        return $campaign->productLines()->first()->productCategory;
    }
}

if (! function_exists('productInterestStagedRow')) {
    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, int>|null  $productIds
     */
    function productInterestStagedRow(array $mapped, ?array $productIds = null): ImportRunRow
    {
        return ImportRunRow::factory()->create([
            'import_run_id' => ImportRun::factory()->create(['resource' => 'leads']),
            'mapped_values' => $mapped,
            'product_ids' => $productIds,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-050 — global_fields declares multiple/depends_on
// ---------------------------------------------------------------------------

it('AC-050: global_fields exposes product_ids as multiple with depends_on campaign_id; campaign_id/source_id stay single', function () {
    $actor = importProductActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => [['name' => 'Email', 'index' => 0, 'duplicate' => false]],
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.global_fields.0.id', 'campaign_id')
        ->assertJsonPath('data.import_run.global_fields.0.multiple', false)
        ->assertJsonPath('data.import_run.global_fields.0.depends_on', null)
        ->assertJsonPath('data.import_run.global_fields.1.id', 'source_id')
        ->assertJsonPath('data.import_run.global_fields.1.multiple', false)
        ->assertJsonPath('data.import_run.global_fields.2.id', 'product_ids')
        ->assertJsonPath('data.import_run.global_fields.2.multiple', true)
        ->assertJsonPath('data.import_run.global_fields.2.depends_on', 'campaign_id');
});

// ---------------------------------------------------------------------------
// AC-051 — configure validates global_config.product_ids against the campaign
// ---------------------------------------------------------------------------

it("AC-051: configure with product_ids outside the campaign's covered categories -> 422 on global_config.product_ids", function () {
    Queue::fake();
    $actor = importProductActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $outsideProduct = Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id, 'name' => 'Fibra 1000']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => [['name' => 'Email', 'index' => 0, 'duplicate' => false]],
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'],
        'global_config' => ['campaign_id' => $campaign->id, 'product_ids' => [$outsideProduct->id]],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['global_config.product_ids']);

    expect($run->fresh()->status)->toBe(ImportStatus::Configuring);
});

it("AC-051: configure with product_ids inside the campaign's covered categories persists global_config and stages", function () {
    Queue::fake();
    $actor = importProductActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => [['name' => 'Email', 'index' => 0, 'duplicate' => false]],
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'],
        'global_config' => ['campaign_id' => $campaign->id, 'product_ids' => [$product->id]],
        'dedup_strategy' => 'create_new',
    ])->assertOk()
        ->assertJsonPath('data.import_run.status', 'staging');

    expect($run->fresh()->global_config)->toBe(['campaign_id' => $campaign->id, 'product_ids' => [$product->id]]);
});

// ---------------------------------------------------------------------------
// AC-052 — a required global field submitted as [] is treated as missing
// ---------------------------------------------------------------------------

it('AC-052: a required global field submitted as an empty array is treated as missing', function () {
    Queue::fake();
    $actor = importProductActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Configuring,
        'detected_columns' => [['name' => 'Email', 'index' => 0, 'duplicate' => false]],
    ]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Email' => 'email'],
        'global_config' => ['campaign_id' => []],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['global_config.campaign_id']);
});

// ---------------------------------------------------------------------------
// AC-053 — persistRow applies the global product_ids when no row override
// ---------------------------------------------------------------------------

it('AC-053: persistRow with no row override applies the global product_ids to the created Lead', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $row = productInterestStagedRow(['first_name' => 'Mario', 'last_name' => 'Rossi']);

    app(LeadsImportDefinition::class)->persistRow($actor, $row, [
        'campaign_id' => $campaign->id,
        'product_ids' => [$product->id],
    ], ImportDedupMode::CreateNew->value);

    $lead = Lead::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($lead->productsOfInterest()->pluck('products.id')->all())->toBe([$product->id]);
});

it('AC-053: persistRow with no global product_ids at all leaves the created Lead with none', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $row = productInterestStagedRow(['first_name' => 'Luigi', 'last_name' => 'Verdi']);

    app(LeadsImportDefinition::class)->persistRow($actor, $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::CreateNew->value);

    $lead = Lead::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($lead->productsOfInterest()->pluck('products.id')->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-054 — per-row product_ids override, PATCH .../rows/{row}
// ---------------------------------------------------------------------------

it('AC-054: sets the per-row product_ids override without touching status/mapped_values', function () {
    $actor = importProductActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi'],
        'status' => ImportRowStatus::Valid,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$product->id]])
        ->assertOk()
        ->assertJsonPath('data.row.product_ids', [$product->id])
        ->assertJsonPath('data.row.products.0.id', $product->id)
        ->assertJsonPath('data.row.products.0.label', $product->name)
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.status', 'valid');

    $row->refresh();
    expect($row->product_ids)->toBe([$product->id])
        ->and($row->mapped_values)->toBe(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

it('AC-054: clears the per-row product_ids override with an explicit null — reverts to inheriting the global value', function () {
    $actor = importProductActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'product_ids' => [$product->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => null])
        ->assertOk()
        ->assertJsonPath('data.row.product_ids', null)
        ->assertJsonPath('data.row.products', null);

    expect($row->fresh()->product_ids)->toBeNull();
});

it('AC-054: an explicit [] override is DIFFERENT from null — it persists as "no products on this row", not "inherit"', function () {
    $actor = importProductActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'product_ids' => null]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.row.product_ids', [])
        ->assertJsonPath('data.row.products', []);

    $row->refresh();
    expect($row->product_ids)->toBe([])
        ->and($row->product_ids)->not->toBeNull();
});

it("AC-054: PATCH product_ids outside the run's campaign covered categories -> 422 on product_ids", function () {
    $actor = importProductActorWith(['import']);
    $campaign = Campaign::factory()->create();
    $outsideProduct = Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id, 'name' => 'Fibra 1000']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$outsideProduct->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_ids']);

    expect($row->fresh()->product_ids)->toBeNull();
});

it('403 without leads.import on the per-row product_ids override', function () {
    $actor = importProductActorWith([]);
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => $campaign->id],
    ]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    // A COHERENT product: the request must pass FormRequest validation so
    // the 403 below is genuinely the controller's authorize() gate, never a
    // 422 the coherence check would raise first.
    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['product_ids' => [$product->id]])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-054 — the row override wins over the global value at persist time
// ---------------------------------------------------------------------------

it("AC-054: persistRow uses the row's own product_ids override instead of the global ones", function () {
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $globalProduct = Product::factory()->create(['category_id' => $category->id]);
    $rowProduct = Product::factory()->create(['category_id' => $category->id]);
    $row = productInterestStagedRow(['first_name' => 'Mario', 'last_name' => 'Rossi'], productIds: [$rowProduct->id]);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
        'product_ids' => [$globalProduct->id],
    ], ImportDedupMode::CreateNew->value);

    $lead = Lead::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($lead->productsOfInterest()->pluck('products.id')->all())->toBe([$rowProduct->id]);
});

it("AC-054: persistRow with the row's product_ids left null inherits the run's global value", function () {
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $row = productInterestStagedRow(['first_name' => 'Mario', 'last_name' => 'Rossi'], productIds: null);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
        'product_ids' => [$product->id],
    ], ImportDedupMode::CreateNew->value);

    $lead = Lead::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($lead->productsOfInterest()->pluck('products.id')->all())->toBe([$product->id]);
});

it("AC-054: persistRow with the row's product_ids explicitly [] persists no products, even with a non-empty global value", function () {
    $campaign = Campaign::factory()->create();
    $category = importCoveredCategory($campaign);
    $globalProduct = Product::factory()->create(['category_id' => $category->id]);
    $row = productInterestStagedRow(['first_name' => 'Mario', 'last_name' => 'Rossi'], productIds: []);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
        'product_ids' => [$globalProduct->id],
    ], ImportDedupMode::CreateNew->value);

    $lead = Lead::query()->where('campaign_id', $campaign->id)->firstOrFail();
    expect($lead->productsOfInterest()->pluck('products.id')->all())->toBe([]);
});

it('AC-054: an incoherent row product_ids override fails the row like any other validation error, never silently', function () {
    $campaign = Campaign::factory()->create();
    $outsideProduct = Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id]);
    $row = productInterestStagedRow(['first_name' => 'Mario', 'last_name' => 'Rossi'], productIds: [$outsideProduct->id]);

    expect(fn () => app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::CreateNew->value))->toThrow(ValidationException::class);

    expect(Lead::query()->where('campaign_id', $campaign->id)->exists())->toBeFalse();
});
