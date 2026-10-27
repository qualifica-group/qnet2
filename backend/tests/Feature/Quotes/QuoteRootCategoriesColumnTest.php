<?php

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Categoria madre" on the `quotes` grid (user directive 2026-10-15): the
 * ROOT categories of the REVENUE-line products, read-only, right after
 * `offer_lines` (App\Tables\Shared\RootCategoriesColumn).
 */
uses(RefreshDatabase::class);

function quoteRootActor(): User
{
    Permission::findOrCreate('quotes.viewAny');
    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.viewAny', Permission::findOrCreate('quotes.viewAll', 'web')]);

    return $user;
}

/**
 * A row whose offer carries one line per given category (REVENUE, or COST when flagged).
 *
 * @param  array<int, ProductCategory>  $revenueCategories
 * @param  array<int, ProductCategory>  $costCategories
 * @return int the grid row id
 */
function quoteRootRow(array $revenueCategories = [], array $costCategories = []): int
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

    return $quote->id;
}

/**
 * @param  array<string, mixed>  $filterModel
 * @return array<int, int>
 */
function quoteRootFilteredIds(mixed $test, array $filterModel): array
{
    return collect($test->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [], 'filterModel' => $filterModel,
    ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('declares root_categories read-only, set-filterable and not sortable', function () {
    Sanctum::actingAs(quoteRootActor());

    $columns = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'));
    $column = $columns->firstWhere('id', 'root_categories');

    expect($column['label'])->toBe('quotes.columns.rootCategories')
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
    $rowId = quoteRootRow([$sicurezza, $childOfFormazione, $formazione], [$cost]);
    $emptyId = quoteRootRow();
    Sanctum::actingAs(quoteRootActor());

    $items = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));

    expect($items->firstWhere('id', $rowId)['root_categories'])->toBe([
        ['id' => $formazione->id, 'name' => 'Formazione'],
        ['id' => $sicurezza->id, 'name' => 'Sicurezza'],
    ])->and($items->firstWhere('id', $emptyId)['root_categories'])->toBe([]);
});

it('filters by root name, matching a product in a deep child category, and by the blank entry', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $deep = ProductCategory::factory()->childOf(ProductCategory::factory()->childOf($formazione)->create())->create();
    $sicurezza = ProductCategory::factory()->create(['name' => 'Sicurezza']);
    $inFormazione = quoteRootRow([$deep]);
    $inSicurezza = quoteRootRow([$sicurezza]);
    $blank = quoteRootRow();
    Sanctum::actingAs(quoteRootActor());

    expect(quoteRootFilteredIds($this, ['root_categories' => ['filterType' => 'set', 'values' => ['Formazione']]]))
        ->toBe([$inFormazione])
        ->and(quoteRootFilteredIds($this, ['root_categories' => ['filterType' => 'set', 'values' => [null]]]))
        ->toBe([$blank])
        ->and(quoteRootFilteredIds($this, ['root_categories' => ['filterType' => 'set', 'values' => ['Sicurezza', null]]]))
        ->toBe(collect([$inSicurezza, $blank])->sort()->values()->all());
});

it('enumerates the distinct root names with the blank entry first', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $child = ProductCategory::factory()->childOf($formazione)->create();
    $sicurezza = ProductCategory::factory()->create(['name' => 'Sicurezza']);
    quoteRootRow([$child, $sicurezza]);
    quoteRootRow([$formazione]);
    quoteRootRow();
    Sanctum::actingAs(quoteRootActor());

    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'root_categories'])->assertOk()->json('data.values');

    expect($values)->toBe([null, 'Formazione', 'Sicurezza']);
});
