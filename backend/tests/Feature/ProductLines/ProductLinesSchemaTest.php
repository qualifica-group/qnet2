<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Behavioral coverage of the schema introduced by spec 0094 (D-1/D-2/D-5):
 * `project_product_lines`, `campaign_product_lines` (AC-001) and
 * `lead_product` (AC-005). Real DB inserts/deletes, not introspection.
 *
 * AC-002/003/004 (backfill + reversibility of the two moving migrations) are
 * deliberately NOT covered here: they were verified manually (backfill run
 * against real data, rollback exercised during the migration-count fix of
 * QuoteWorkflowMigrationTest) rather than with an automated up/down replay
 * test. `project_product_lines`/`campaign_product_lines` sit UNDER two
 * further migrations that both up() (backfill) and down() (drop + repopulate
 * the dropped scalar columns) touch `projects`/`campaigns`+the two new
 * tables together; replaying that pair in isolation on SQLite in-memory,
 * inside a suite that never runs `migrate:fresh`, would require re-running
 * the FULL migration set from a specific historical point and restoring it
 * after — the same class of fragility QuoteWorkflowMigrationTest already
 * carries a long docblock warning about for a single migration group. A
 * fragile, easy-to-break replay test is worse than a documented gap.
 */

// ---------------------------------------------------------------------------
// project_product_lines — AC-001
// ---------------------------------------------------------------------------

it('project_product_lines: unique triplet, project_id cascades, business_function/product_category restrict (AC-001)', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $project = Project::factory()->create();
    $project->productLines()->delete();

    $project->productLines()->create([
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);

    // Same triplet twice -> UNIQUE violation.
    expect(fn () => DB::table('project_product_lines')->insert([
        'project_id' => $project->id,
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // The referenced business function/category are in use -> restrictOnDelete.
    expect(fn () => $function->delete())->toThrow(QueryException::class);
    expect(fn () => $category->delete())->toThrow(QueryException::class);

    // Deleting the project cascades its own rows.
    $project->delete();
    $this->assertDatabaseMissing('project_product_lines', ['project_id' => $project->id]);
});

// ---------------------------------------------------------------------------
// campaign_product_lines — AC-001
// ---------------------------------------------------------------------------

it('campaign_product_lines: unique triplet, campaign_id cascades, business_function/product_category restrict (AC-001)', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $campaign = Campaign::factory()->create();
    $campaign->productLines()->delete();

    $campaign->productLines()->create([
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
    ]);

    // Same triplet twice -> UNIQUE violation.
    expect(fn () => DB::table('campaign_product_lines')->insert([
        'campaign_id' => $campaign->id,
        'business_function_id' => $function->id,
        'product_category_id' => $category->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // The referenced business function/category are in use -> restrictOnDelete.
    expect(fn () => $function->delete())->toThrow(QueryException::class);
    expect(fn () => $category->delete())->toThrow(QueryException::class);

    // Deleting the campaign cascades its own rows.
    $campaign->delete();
    $this->assertDatabaseMissing('campaign_product_lines', ['campaign_id' => $campaign->id]);
});

// ---------------------------------------------------------------------------
// lead_product — AC-005
// ---------------------------------------------------------------------------

it('lead_product: unique(lead_id, product_id), cascades on lead delete (AC-005)', function () {
    $lead = Lead::factory()->create();
    $product = Product::factory()->create();

    $lead->productsOfInterest()->attach($product->id);

    // Same pair twice -> UNIQUE violation.
    expect(fn () => DB::table('lead_product')->insert([
        'lead_id' => $lead->id,
        'product_id' => $product->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // Deleting the lead cascades the pivot row.
    $lead->delete();
    $this->assertDatabaseMissing('lead_product', ['lead_id' => $lead->id]);
});
