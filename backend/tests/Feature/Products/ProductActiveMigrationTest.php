<?php

use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0366 D-3: the legacy catalogue ("[OLD] " names) is inactive, once via
 * the schema migration and from then on through the `is_active` the legacy
 * sends to the products and cost-products sources.
 */
uses(RefreshDatabase::class);

function productActiveMigration(): Migration
{
    return require database_path('migrations/2026_11_25_100000_add_is_active_to_products_table.php');
}

function productActiveImport(string $source, array $item): void
{
    seedMigrationsConfig();
    Http::fake([fakeMigrationsBaseUrl()."/{$source}*" => Http::response(['items' => [$item], 'pagination' => ['total' => 1]])]);

    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');

    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => $source]);
    (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
}

it('AC-003: deactivates the "[OLD] " products only, and rolls back', function () {
    $migration = productActiveMigration();
    $migration->down();

    $legacy = Product::factory()->create(['name' => '[OLD] Legacy service']);
    $native = Product::factory()->create(['name' => 'OLD style but native']);

    $migration->up();

    expect((bool) DB::table('products')->where('id', $legacy->id)->value('is_active'))->toBeFalse()
        ->and((bool) DB::table('products')->where('id', $native->id)->value('is_active'))->toBeTrue();

    $migration->down();
    expect(Schema::hasColumn('products', 'is_active'))->toBeFalse();

    $migration->up();
});

it('AC-009: the products source writes the is_active the legacy sends, absent = active', function (array $extra, bool $expected) {
    ProductCategory::factory()->create(['old_id' => 5]);

    productActiveImport('products', ['id' => 1, 'name' => '[OLD] Service', 'cost' => 0, 'price' => 10, 'category_id' => 5, 'product_type' => 'SERVICE', ...$extra]);

    expect(Product::query()->where('old_id', 1)->sole()->is_active)->toBe($expected);
})->with([
    'sent false' => [['is_active' => false], false],
    'absent' => [[], true],
]);

it('AC-009: the cost-products source writes the is_active the legacy sends, absent = active', function (array $extra, bool $expected) {
    productActiveImport('cost-products', ['id' => 'products:7', 'source' => 'products', 'source_id' => 7, 'name' => '[OLD] Paper', 'cost' => 1, 'price' => 1, ...$extra]);

    expect(Product::query()->where('old_id', 7)->sole()->is_active)->toBe($expected);
})->with([
    'sent false' => [['is_active' => false], false],
    'absent' => [[], true],
]);
