<?php

use App\Enums\QuoteLineType;
use App\Models\Contract;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Categoria madre" on the `contracts` grid (user directive 2026-10-15): the
 * ROOT categories of the REVENUE-line products, read-only, right after
 * `offer_lines` (App\Tables\Shared\RootCategoriesColumn).
 */
uses(RefreshDatabase::class);

function contractRootActor(): User
{
    Permission::findOrCreate('contracts.viewAny');
    $user = User::factory()->create();
    $user->givePermissionTo('contracts.viewAny');

    return $user;
}

/**
 * A row whose offer carries one line per given category (REVENUE, or COST when flagged).
 *
 * @param  array<int, ProductCategory>  $revenueCategories
 * @param  array<int, ProductCategory>  $costCategories
 * @return int the grid row id
 */
function contractRootRow(array $revenueCategories = [], array $costCategories = []): int
{
    $quote = Quote::factory()->create();

    foreach ([[$revenueCategories, QuoteLineType::Revenue], [$costCategories, QuoteLineType::Cost]] as [$categories, $type]) {
        foreach ($categories as $category) {
            QuoteLine::factory()->create([
                'quote_id' => $quote->id,
                'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
                'line_type' => $type,
            ]);
        }
    }

    return Contract::factory()->create(['quote_id' => $quote->id])->id;
}

/**
 * @param  array<string, mixed>  $filterModel
 * @return array<int, int>
 */
function contractRootFilteredIds(mixed $test, array $filterModel): array
{
    return collect($test->postJson('/api/tables/contracts/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [], 'filterModel' => $filterModel,
    ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('declares root_categories read-only, set-filterable and not sortable', function () {
    Sanctum::actingAs(contractRootActor());

    $columns = collect($this->getJson('/api/tables/contracts/columns')->assertOk()->json('data.columns'));
    $column = $columns->firstWhere('id', 'root_categories');

    expect($column['label'])->toBe('contracts.columns.rootCategories')
        ->and($column['type'])->toBe('text')
        ->and($column['visible'])->toBeTrue()
        ->and($column['sortable'])->toBeFalse()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        ->and($column['editable'] ?? false)->toBeFalse()
        ->and($column)->not->toHaveKey('editor')
        ->and($columns->pluck('id')->search('root_categories'))->toBe($columns->pluck('id')->search('offer_lines') + 1);
});

it('projects the distinct roots of the revenue categories, ordered by name, cost lines excluded', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $childOfFormazione = ProductCategory::factory()->childOf($formazione)->create();
    $sicurezza = ProductCategory::factory()->create(['name' => 'Sicurezza']);
    $cost = ProductCategory::factory()->create(['name' => 'Costi']);
    $rowId = contractRootRow([$sicurezza, $childOfFormazione, $formazione], [$cost]);
    $emptyId = contractRootRow();
    Sanctum::actingAs(contractRootActor());

    $items = collect($this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));

    expect($items->firstWhere('id', $rowId)['root_categories'])->toBe([
        ['id' => $formazione->id, 'name' => 'Formazione'],
        ['id' => $sicurezza->id, 'name' => 'Sicurezza'],
    ])->and($items->firstWhere('id', $emptyId)['root_categories'])->toBe([]);
});

it('filters by root name, matching a product in a deep child category, and by the blank entry', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $deep = ProductCategory::factory()->childOf(ProductCategory::factory()->childOf($formazione)->create())->create();
    $sicurezza = ProductCategory::factory()->create(['name' => 'Sicurezza']);
    $inFormazione = contractRootRow([$deep]);
    $inSicurezza = contractRootRow([$sicurezza]);
    $blank = contractRootRow();
    Sanctum::actingAs(contractRootActor());

    expect(contractRootFilteredIds($this, ['root_categories' => ['filterType' => 'set', 'values' => ['Formazione']]]))
        ->toBe([$inFormazione])
        ->and(contractRootFilteredIds($this, ['root_categories' => ['filterType' => 'set', 'values' => [null]]]))
        ->toBe([$blank])
        ->and(contractRootFilteredIds($this, ['root_categories' => ['filterType' => 'set', 'values' => ['Sicurezza', null]]]))
        ->toBe(collect([$inSicurezza, $blank])->sort()->values()->all());
});

it('enumerates the distinct root names with the blank entry first', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $child = ProductCategory::factory()->childOf($formazione)->create();
    $sicurezza = ProductCategory::factory()->create(['name' => 'Sicurezza']);
    contractRootRow([$child, $sicurezza]);
    contractRootRow([$formazione]);
    contractRootRow();
    Sanctum::actingAs(contractRootActor());

    $values = $this->postJson('/api/tables/contracts/values', ['columnId' => 'root_categories'])->assertOk()->json('data.values');

    expect($values)->toBe([null, 'Formazione', 'Sicurezza']);
});
