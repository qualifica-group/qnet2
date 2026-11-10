<?php

use App\Http\Resources\QuoteLineResource;
use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Quotes\QuoteTypologySummaryCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Product typology badge color (spec 0204): AC-001 (module), AC-002
 * (migration), AC-003 (every payload exposing a product's typology).
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions
 */
function colorActor(array $permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
        $user->givePermissionTo($permission);
    }

    return $user;
}

function typologyColorMigration(): object
{
    return require database_path('migrations/2026_10_07_120000_add_color_to_product_typologies_table.php');
}

// AC-001

it('AC-001: create without color or with a color outside the token set is a 422', function () {
    Sanctum::actingAs(colorActor(['product-typologies.create']));

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training'])
        ->assertUnprocessable()->assertJsonValidationErrors('color');

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'fuchsia'])
        ->assertUnprocessable()->assertJsonValidationErrors('color');

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'teal'])
        ->assertCreated()->assertJsonPath('data.color', 'teal');
});

it('AC-001: update validates color when present, show and the table expose it', function () {
    Sanctum::actingAs(colorActor(['product-typologies.update', 'product-typologies.view', 'product-typologies.viewAny']));
    $typology = ProductTypology::factory()->create(['color' => 'gray']);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['color' => 'nope'])
        ->assertUnprocessable()->assertJsonValidationErrors('color');
    $this->patchJson("/api/product-typologies/{$typology->id}", ['color' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('color');

    $this->patchJson("/api/product-typologies/{$typology->id}", ['color' => 'pink'])
        ->assertOk()->assertJsonPath('data.color', 'pink');
    $this->getJson("/api/product-typologies/{$typology->id}")->assertOk()->assertJsonPath('data.color', 'pink');

    // A rename alone leaves the color untouched.
    $this->patchJson("/api/product-typologies/{$typology->id}", ['name' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.color', 'pink');

    $columns = collect($this->getJson('/api/tables/product-typologies/columns')->assertOk()->json('data.columns'))->pluck('id');
    expect($columns)->toContain('color');

    $rows = collect($this->postJson('/api/tables/product-typologies/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));
    expect($rows->firstWhere('id', $typology->id)['color'])->toBe('pink');
});

it('AC-001: the for-select meta carries the color', function () {
    $typology = ProductTypology::factory()->create(['color' => 'indigo']);
    Sanctum::actingAs(colorActor([]));

    $items = collect($this->getJson('/api/product-typologies/for-select')->assertOk()->json('items'));

    expect($items->firstWhere('id', $typology->id)['meta'])->toBe(['code' => $typology->code, 'color' => 'indigo']);
});

// AC-002

it('AC-002: the migration colors institution violet, consultancy blue and the others gray, and is reversible', function () {
    ProductTypology::factory()->create(['code' => 'consultancy', 'color' => 'red']);
    $other = ProductTypology::factory()->create(['color' => 'red']);
    $migration = typologyColorMigration();

    $migration->down();
    expect(Schema::hasColumn('product_typologies', 'color'))->toBeFalse();

    $migration->up();

    $colors = DB::table('product_typologies')->pluck('color', 'code');
    expect($colors['institution'])->toBe('violet')
        ->and($colors['consultancy'])->toBe('blue')
        ->and($colors[$other->code])->toBe('gray');
});

// AC-003

it('AC-003: the product resource, table row and for-select expose the typology color', function () {
    $typology = ProductTypology::factory()->create(['color' => 'amber']);
    $product = Product::factory()->create(['product_typology_id' => $typology->id]);
    Sanctum::actingAs(colorActor(['products.view', 'products.viewAny']));
    $expected = ['id' => $typology->id, 'name' => $typology->name, 'color' => 'amber'];

    $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.product_typology', $expected);
    $this->getJson('/api/products/for-select?ids[]='.$product->id)->assertOk()->assertJsonPath('items.0.meta.product_typology', $expected);

    $rows = collect($this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));
    expect($rows->firstWhere('id', $product->id)['product_typology'])->toBe($expected);
});

it('AC-003: the quote line and the per-typology summary expose the color', function () {
    $typology = ProductTypology::factory()->create(['color' => 'emerald']);
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create(['product_typology_id' => $typology->id])->id,
    ]);

    $actor = colorActor([]);
    $request = Request::create('/')->setUserResolver(fn (): User => $actor);
    $resolved = (new QuoteLineResource($line->load('product.productTypology')))->resolve($request);
    $summary = collect(app(QuoteTypologySummaryCalculator::class)->totals($quote))->firstWhere('id', $typology->id);

    expect($resolved['product']['product_typology'])->toBe(['id' => $typology->id, 'name' => $typology->name, 'color' => 'emerald'])
        ->and($summary['color'])->toBe('emerald');
});

it('AC-003: the commessa contract data exposes the color on lines and totals', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 1000);
    ProductTypology::where('code', 'consultancy')->update(['color' => 'blue']);
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    $data = $this->getJson("/api/work-orders/{$workOrder->id}/contract-data")->assertOk()->json('data');

    expect(collect($data['lines'])->firstWhere('quote_line_id', $line->id)['typology']['color'])->toBe('blue')
        ->and(collect($data['totals']['typologies'])->firstWhere('name', 'Consultancy')['color'])->toBe('blue');
});
