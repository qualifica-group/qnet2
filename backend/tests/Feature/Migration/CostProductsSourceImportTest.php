<?php

use App\Enums\AttributeContext;
use App\Enums\MigrationStatus;
use App\Enums\ProductUsage;
use App\Jobs\RunMigrationJob;
use App\Migrations\Support\CostProductCatalogue;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
use App\Services\MigrationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

/**
 * One legacy cost row in the `/cost-products` contract (spec 0174), with every
 * optional field absent unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyCostRow(string $source, int $sourceId, string $name, array $overrides = []): array
{
    return [
        'id' => "{$source}:{$sourceId}",
        'source' => $source,
        'source_id' => $sourceId,
        'name' => $name,
        'cost' => 0,
        'price' => 0,
        ...$overrides,
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $items
 */
function runCostProductsImport(array $items): MigrationRun
{
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/cost-products*' => Http::response([
            'items' => $items,
            'pagination' => ['total' => count($items)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'cost-products']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function costCategory(string $name, ?int $parentId): ?ProductCategory
{
    return ProductCategory::query()->where('name', $name)->where('parent_id', $parentId)->first();
}

// ---------------------------------------------------------------------------
// AC-001 — (old_source, old_id) uniqueness
// ---------------------------------------------------------------------------

it('lets two legacy tables share an old_id but rejects a duplicate pair', function () {
    $service = Product::factory()->create();
    $service->forceFill(['old_id' => 3, 'old_source' => 'services'])->save();

    $vehicle = Product::factory()->create();
    $vehicle->forceFill(['old_id' => 3, 'old_source' => 'vehicles'])->save();

    expect(Product::query()->where('old_id', 3)->count())->toBe(2)
        ->and(fn () => Product::factory()->create()->forceFill(['old_id' => 3, 'old_source' => 'vehicles'])->save())
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// AC-002 / AC-003 / AC-004 — branch, cost-only products, attributes
// ---------------------------------------------------------------------------

it('files each legacy cost table under the Costi branch as a cost-only product', function () {
    $vatRate = VatRate::factory()->create(['old_id' => 6]);

    $run = runCostProductsImport([
        legacyCostRow('products', 2, 'Oust', [
            'code' => 'P01', 'cost' => 10, 'price' => 20, 'vat_rate_id' => 6, 'category_name' => 'Igienizzanti',
            'brand' => 'OUST', 'barcode' => '800123', 'ministerial_code' => 'MIN-1', 'storage_position' => 'scaffale 5',
        ]),
        legacyCostRow('vehicles', 3, 'Tagliando Smart', ['cost' => 140, 'price' => 140, 'license_plate' => 'FE700FV']),
        legacyCostRow('equipment', 1, 'Motosega', ['cost' => 100, 'price' => 100, 'brand' => 'Stihl', 'model' => 'GHT', 'serial_number' => '123']),
        legacyCostRow('expense_reports', 6, 'FOR_Commissione', ['description' => "Commissione d'esame", 'cost' => 400, 'price' => 400]),
    ]);

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(4)
        ->and($run->report)->toBeNull();

    $root = costCategory(CostProductCatalogue::ROOT, null);
    expect($root)->not->toBeNull()
        ->and($root->business_function_id)->toBeNull();

    foreach (CostProductCatalogue::BRANCHES as $branchName) {
        expect(costCategory($branchName, $root->id))->not->toBeNull();
    }

    $articles = costCategory('Articoli', $root->id);
    $article = Product::query()->where('old_source', 'products')->where('old_id', 2)->firstOrFail();

    expect($article->category_id)->toBe(costCategory('Igienizzanti', $articles->id)->id)
        ->and($article->code)->toBe('P01')
        ->and((float) $article->cost)->toBe(10.0)
        ->and((float) $article->price)->toBe(20.0)
        ->and($article->vat_rate_id)->toBe($vatRate->id)
        ->and($article->usages->all())->toBe([ProductUsage::Cost])
        ->and($article->attribute_values)->toBe([
            'cost_brand' => 'OUST',
            'cost_barcode' => '800123',
            'cost_ministerial_code' => 'MIN-1',
            'cost_storage_position' => 'scaffale 5',
        ]);

    $vehicle = Product::query()->where('old_source', 'vehicles')->where('old_id', 3)->firstOrFail();
    expect($vehicle->category_id)->toBe(costCategory('Veicoli', $root->id)->id)
        ->and($vehicle->attribute_values)->toBe(['cost_license_plate' => 'FE700FV'])
        ->and($vehicle->code)->toStartWith('PRD-');

    $equipment = Product::query()->where('old_source', 'equipment')->where('old_id', 1)->firstOrFail();
    expect($equipment->attribute_values)->toBe(['cost_brand' => 'Stihl', 'cost_model' => 'GHT', 'cost_serial_number' => '123']);

    $expense = Product::query()->where('old_source', 'expense_reports')->where('old_id', 6)->firstOrFail();
    expect($expense->category_id)->toBe(costCategory('Note spese', $root->id)->id)
        ->and($expense->description)->toBe("Commissione d'esame")
        ->and((float) $expense->cost)->toBe(400.0)
        ->and($expense->attribute_values)->toBeNull();

    $vehicleAttributes = costCategory('Veicoli', $root->id)->attributes()
        ->wherePivot('context', AttributeContext::Product->value)
        ->pluck('code')
        ->all();
    expect($vehicleAttributes)->toBe(['cost_license_plate']);
});

it('keeps a warehouse article without a legacy category on the Articoli branch', function () {
    runCostProductsImport([legacyCostRow('products', 9, 'Varie')]);

    $root = costCategory(CostProductCatalogue::ROOT, null);

    expect(Product::query()->where('old_source', 'products')->value('category_id'))
        ->toBe(costCategory('Articoli', $root->id)->id);
});

it('ignores a category name on a non-warehouse row', function () {
    runCostProductsImport([legacyCostRow('vehicles', 1, 'Furgone', ['category_name' => 'Mezzi'])]);

    expect(ProductCategory::query()->where('name', 'Mezzi')->exists())->toBeFalse();
});

it('warns and nulls an unmigrated VAT rate and the un-remappable supplier', function () {
    $run = runCostProductsImport([legacyCostRow('equipment', 1, 'Motosega', ['vat_rate_id' => 3, 'supplier_id' => 5])]);

    $product = Product::query()->where('old_source', 'equipment')->firstOrFail();

    expect($product->vat_rate_id)->toBeNull()
        ->and($product->supplier_id)->toBeNull()
        ->and($run->created_rows)->toBe(1)
        ->and(collect($run->report)->where('level', 'warning'))->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// AC-005 / AC-006 — idempotence keyed on (old_source, old_id)
// ---------------------------------------------------------------------------

it('re-importing is idempotent and never duplicates the branch', function () {
    $items = [
        legacyCostRow('products', 1, 'Alpha', ['category_name' => 'Veleni', 'brand' => 'ACME']),
        legacyCostRow('vehicles', 1, 'Smart'),
    ];

    runCostProductsImport($items);
    $second = runCostProductsImport($items);

    expect($second->skipped_rows)->toBe(2)
        ->and($second->created_rows)->toBe(0)
        ->and(Product::query()->count())->toBe(2)
        ->and(ProductCategory::query()->where('name', CostProductCatalogue::ROOT)->count())->toBe(1)
        ->and(ProductCategory::query()->where('name', 'Veleni')->count())->toBe(1)
        ->and(costCategory('Articoli', costCategory(CostProductCatalogue::ROOT, null)->id)->attributes()->count())->toBe(4);
});

it('creates a cost whose legacy id matches an already migrated service', function () {
    $service = Product::factory()->create();
    $service->forceFill(['old_id' => 4, 'old_source' => 'services'])->save();

    $run = runCostProductsImport([legacyCostRow('expense_reports', 4, 'Pagamento Fornitore')]);

    expect($run->created_rows)->toBe(1)
        ->and(Product::query()->where('old_id', 4)->pluck('old_source')->sort()->values()->all())
        ->toBe(['expense_reports', 'services']);
});

it('lets the products source import a service whose id matches a migrated cost', function () {
    $cost = Product::factory()->create();
    $cost->forceFill(['old_id' => 7, 'old_source' => 'vehicles'])->save();
    ProductCategory::factory()->create(['old_id' => 5]);
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/products*' => Http::response([
            'items' => [['id' => 7, 'name' => 'Audit', 'category_id' => 5]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'products']);
    runMigrationJobFor($run);

    expect($run->fresh()->created_rows)->toBe(1)
        ->and(Product::query()->where('old_source', 'services')->where('old_id', 7)->value('name'))->toBe('Audit');
});

// ---------------------------------------------------------------------------
// AC-007 — per-row failures and code clashes
// ---------------------------------------------------------------------------

it('generates a sequential code when the legacy one is already taken', function () {
    Product::factory()->create()->forceFill(['code' => 'P01'])->save();

    $run = runCostProductsImport([legacyCostRow('products', 2, 'Oust', ['code' => 'P01'])]);

    expect(Product::query()->where('old_source', 'products')->value('code'))->toStartWith('PRD-')
        ->and(collect($run->report)->where('level', 'warning'))->toHaveCount(1);
});

it('fails a nameless row or an unknown legacy table without blocking a valid one', function () {
    $run = runCostProductsImport([
        legacyCostRow('vehicles', 1, '   '),
        legacyCostRow('boats', 1, 'Gommone'),
        legacyCostRow('vehicles', 2, 'Smart'),
    ]);

    expect($run->created_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(2)
        ->and(Product::query()->where('old_source', 'vehicles')->where('old_id', 2)->exists())->toBeTrue();
});
