<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Product `code` (spec 0065, D-1/D-1b/D-1c): manual-on-create/read-only-on-update
 * sequential code, mirroring the pattern already in production on Projects/
 * Campaigns (spec 0025). File-size split out of ProductCrudTest.php
 * (engineering.md §6 — the host file was at the 500-line hard limit).
 */
uses(RefreshDatabase::class);

if (! function_exists('productUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('productGenericFields')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function productGenericFields(array $overrides = []): array
    {
        return array_merge(['cost' => 10, 'price' => 20, 'product_type' => 'SERVICE'], $overrides);
    }
}

// ---------------------------------------------------------------------------
// create — `code` (spec 0065, D-1b, pattern of spec 0025)
// ---------------------------------------------------------------------------

it('create: no `code` in the payload -> code is server-generated PRD-0001 (AC-001)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields(['name' => 'First', 'category_id' => $category->id]))
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRD-0001');
});

it('create: `code` as null or empty string -> code is server-generated (AC-001)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields(['name' => 'NullCode', 'category_id' => $category->id, 'code' => null]))
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRD-0001');

    $this->postJson('/api/products', productGenericFields(['name' => 'EmptyCode', 'category_id' => $category->id, 'code' => '']))
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRD-0002');
});

it('create: an explicit `code` is persisted as-is and does not break the sequence (AC-002)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields(['name' => 'Manual', 'category_id' => $category->id, 'code' => 'ACME-2026']))
        ->assertCreated()
        ->assertJsonPath('data.code', 'ACME-2026');

    $this->assertDatabaseHas('products', ['code' => 'ACME-2026']);

    $this->postJson('/api/products', productGenericFields(['name' => 'Generated', 'category_id' => $category->id]))
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRD-0001');
});

it('create: a duplicate `code` -> 422 on the `code` field (AC-003)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['code' => 'ACME-2026']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields(['name' => 'Dup', 'category_id' => $category->id, 'code' => 'ACME-2026']))
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a `code` of 33+ characters -> 422 (AC-004)', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields(['name' => 'TooLong', 'category_id' => $category->id, 'code' => str_repeat('A', 33)]))
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

// ---------------------------------------------------------------------------
// update — `code` is immutable (AC-005)
// ---------------------------------------------------------------------------

it('update: a `code` different from the persisted one -> 422, code unchanged (AC-005)', function () {
    $actor = productUserWith(['update']);
    $product = Product::factory()->create(['code' => 'PRD-0001']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['code' => 'PRD-9999'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'code' => 'PRD-0001']);
});

it('update: resubmitting the SAME persisted `code` is a no-op, not rejected (AC-005)', function () {
    $actor = productUserWith(['update']);
    $product = Product::factory()->create(['code' => 'PRD-0001']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['code' => 'PRD-0001', 'name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.code', 'PRD-0001')
        ->assertJsonPath('data.name', 'Renamed');
});

// ---------------------------------------------------------------------------
// meta — permissions.fields.code (AC-006)
// ---------------------------------------------------------------------------

it('meta: permissions.fields.code is editable+required in create, readonly in show (AC-006)', function () {
    $actor = productUserWith(['viewAny', 'create', 'view']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/products')
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', true)
        ->assertJsonPath('permissions.fields.code.required', true)
        ->assertJsonPath('permissions.fields.code.readonly', false);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', false)
        ->assertJsonPath('permissions.fields.code.readonly', true);
});

// ---------------------------------------------------------------------------
// next-code — GET /api/products/next-code (spec 0065, auto-fill suggestion)
// ---------------------------------------------------------------------------

it('next-code: suggests PRD-0001 on an empty table, then the following sequence (AC-007)', function () {
    $actor = productUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/products/next-code')
        ->assertOk()->assertJsonPath('data.code', 'PRD-0001');

    Product::factory()->create(['code' => 'PRD-0007']);

    $this->getJson('/api/products/next-code')
        ->assertOk()->assertJsonPath('data.code', 'PRD-0008');
});

it('next-code: 403 without products.create (AC-007)', function () {
    Sanctum::actingAs(productUserWith(['viewAny']));

    $this->getJson('/api/products/next-code')->assertForbidden();
});

// ---------------------------------------------------------------------------
// migration backfill — `code` on PRE-EXISTING rows (AC-008)
// ---------------------------------------------------------------------------

it('AC-008: the code migration backfills pre-existing rows with distinct PRD-* codes in id order', function () {
    $migration = require database_path('migrations/2026_07_29_100000_add_code_to_products_table.php');

    // Step 1: undo the migration, back to the pre-migration schema (no
    // `code` column). `quote_lines.product_id` FKs on `products.id`, never
    // `code`, so nothing downstream depends on the column being present.
    // Spec 0088 later added `unit_of_measure_id` (NOT NULL) to `products`,
    // so a raw pre-existing-row insert below must supply it explicitly.
    $migration->down();

    // Step 2: seed rows exactly as a pre-existing install would have them —
    // a raw insert, since the `code` column does not exist at this point.
    $category = ProductCategory::factory()->create();
    $unitOfMeasureId = UnitOfMeasure::where('code', 'unit')->value('id');
    $ids = collect(['Gamma', 'Alpha', 'Beta'])->map(
        fn (string $name) => DB::table('products')->insertGetId([
            'name' => $name,
            'category_id' => $category->id,
            'product_type' => 'SERVICE',
            'unit_of_measure_id' => $unitOfMeasureId,
            'created_at' => now(),
            'updated_at' => now(),
        ])
    );

    // Step 3: re-run the migration against the pre-existing rows.
    $migration->up();

    $codes = DB::table('products')->orderBy('id')->pluck('code', 'id');

    expect($codes->keys()->all())->toEqual($ids->all())
        ->and($codes->values()->all())->toEqual(['PRD-0001', 'PRD-0002', 'PRD-0003']);

    // NOT NULL enforced.
    expect(fn () => DB::table('products')->insert([
        'name' => 'NullCode', 'category_id' => $category->id, 'product_type' => 'SERVICE',
        'unit_of_measure_id' => $unitOfMeasureId, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // UNIQUE enforced.
    expect(fn () => DB::table('products')->insert([
        'name' => 'DupeCode', 'category_id' => $category->id, 'product_type' => 'SERVICE', 'code' => 'PRD-0001',
        'unit_of_measure_id' => $unitOfMeasureId, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
