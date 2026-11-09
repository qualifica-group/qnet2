<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// attr.<code> columns of a parent-category tab: the root's own effective set (spec 0350 D-4, rev.3).

uses(RefreshDatabase::class);

dataset('root attribute grids', ['request-management', 'enrollee-management', 'quotes', 'work-orders', 'contracts']);

/**
 * Root with `durata`, child with `child_only` on top of what it inherits.
 *
 * @return array{0: ProductCategory, 1: ProductCategory}
 */
function rootAttrTree(string $grid): array
{
    [$root, $child] = categoryTabTree();
    $context = $grid === 'work-orders' ? AttributeContext::WorkOrder : AttributeContext::Quote;
    categoryTabAttribute($context, 'text', ['code' => 'durata', 'name' => 'Durata'], $root);
    categoryTabAttribute($context, 'text', ['code' => 'child_only'], $child);

    return [$root, $child];
}

/**
 * @param  array<string, mixed>  $values
 */
function rootAttrStore(string $grid, Model $record, array $values): void
{
    ($grid === 'contracts' ? $record->quote : $record)->forceFill(['attribute_values' => $values])->save();
}

/**
 * @return array<int, string>
 */
function rootAttrColumnIds(array $columns): array
{
    return array_values(array_filter(array_column($columns, 'id'), static fn (string $id): bool => str_starts_with($id, 'attr.')));
}

it('lists the root attributes on a parent tab and not those of a subcategory only', function (string $grid) {
    [$root] = rootAttrTree($grid);
    Sanctum::actingAs(categoryTabGridActor($grid));

    $columns = $this->getJson("/api/tables/{$grid}/columns?product_category_branch_id={$root->id}")->assertOk()->json('data.columns');

    expect(rootAttrColumnIds($columns))->toBe(['attr.durata'])
        ->and(categoryTabColumn($columns, 'attr.durata'))->toMatchArray(['label' => 'Durata', 'visible' => true]);
})->with('root attribute grids');

it('adds no column for a parent whose fields live only on its subcategories', function (string $grid) {
    [, $child] = categoryTabTree();
    categoryTabAttribute($grid === 'work-orders' ? AttributeContext::WorkOrder : AttributeContext::Quote, 'text', ['code' => 'child_only'], $child);
    Sanctum::actingAs(categoryTabGridActor($grid));

    $columns = $this->getJson("/api/tables/{$grid}/columns?product_category_branch_id={$child->parent_id}")->assertOk()->json('data.columns');

    expect(rootAttrColumnIds($columns))->toBe([]);
})->with('root attribute grids');

it('reads, filters, sorts and lists the values of records on a subcategory', function (string $grid) {
    [$root, $child] = rootAttrTree($grid);
    $beta = categoryTabRecord($grid, [$child]);
    $alpha = categoryTabRecord($grid, [$child]);
    rootAttrStore($grid, $beta, ['durata' => 'beta']);
    rootAttrStore($grid, $alpha, ['durata' => 'alpha']);
    Sanctum::actingAs(categoryTabGridActor($grid));
    $page = ['startRow' => 0, 'endRow' => 25, 'productCategoryBranchId' => $root->id];
    $rowsOf = fn (array $extra = []): array => $this->postJson("/api/tables/{$grid}/rows", [...$page, ...$extra])->assertOk()->json('items');

    expect(collect($rowsOf())->mapWithKeys(fn (array $row): array => [$row['id'] => $row['attr.durata']])->all())->toEqualCanonicalizing([$beta->id => 'beta', $alpha->id => 'alpha'])
        ->and(array_column($rowsOf(['sortModel' => [['colId' => 'attr.durata', 'sort' => 'asc']]]), 'id'))->toBe([$alpha->id, $beta->id])
        ->and(array_column($rowsOf(['filterModel' => ['attr.durata' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alp']]]), 'id'))->toBe([$alpha->id]);

    $values = $this->postJson("/api/tables/{$grid}/values", ['columnId' => 'attr.durata', 'productCategoryBranchId' => $root->id])->assertOk()->json('data.values');
    expect($values)->toEqualCanonicalizing(['alpha', 'beta']);

    $this->postJson("/api/tables/{$grid}/rows", [...$page, 'sortModel' => [['colId' => 'attr.child_only', 'sort' => 'asc']]])->assertUnprocessable();
})->with('root attribute grids');

it('keeps the root columns in the config returned by a save, and a hidden one stays hidden', function (string $grid) {
    [$root] = rootAttrTree($grid);
    Sanctum::actingAs(categoryTabGridActor($grid));

    $saved = $this->postJson("/api/tables/{$grid}/preferences", [
        'columns' => [['id' => 'attr.durata', 'visible' => false, 'order' => 1]],
        'product_category_branch_id' => $root->id,
    ])->assertOk()->json('data.columns');
    $filtered = $this->postJson("/api/tables/{$grid}/filters", [
        'filterModel' => [],
        'product_category_branch_id' => $root->id,
    ])->assertOk()->json('data.columns');
    $reloaded = $this->getJson("/api/tables/{$grid}/columns?product_category_branch_id={$root->id}")->assertOk()->json('data.columns');

    expect(categoryTabColumn($saved, 'attr.durata'))->not->toBeNull()
        ->and(categoryTabColumn($filtered, 'attr.durata'))->not->toBeNull()
        ->and(categoryTabColumn($reloaded, 'attr.durata')['visible'])->toBeFalse();
})->with('root attribute grids');

it('rejects a branch sent with a single category or pointing nowhere', function (string $method, string $path, array $body) {
    [$root] = rootAttrTree('quotes');
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $both = ['product_category_id' => $root->id, 'product_category_branch_id' => $root->id];
    $missing = ['product_category_branch_id' => 999999];

    if ($method === 'get') {
        $this->getJson($path.'?'.http_build_query($both))->assertUnprocessable();
        $this->getJson($path.'?'.http_build_query($missing))->assertUnprocessable();

        return;
    }

    $this->postJson($path, [...$body, ...$both])->assertUnprocessable();
    $this->postJson($path, [...$body, ...$missing])->assertUnprocessable();
})->with([
    'columns' => ['get', '/api/tables/quotes/columns', []],
    'preferences' => ['post', '/api/tables/quotes/preferences', ['columns' => [['id' => 'code', 'visible' => true, 'order' => 1]]]],
    'filters' => ['post', '/api/tables/quotes/filters', ['filterModel' => []]],
]);

it('edits a root attribute in the cell for a record on a subcategory', function () {
    [$root, $child] = rootAttrTree('quotes');
    $quote = categoryTabQuote([$child]);
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'update']));

    $columns = $this->getJson("/api/tables/quotes/columns?product_category_branch_id={$root->id}")->assertOk()->json('data.columns');
    expect(categoryTabColumn($columns, 'attr.durata')['editable'])->toBeTrue();

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 'gamma'])->assertOk();

    expect($quote->fresh()->attribute_values)->toBe(['durata' => 'gamma']);
});

it('keeps the contracts columns read-only on a parent tab', function () {
    [$root] = rootAttrTree('contracts');
    Sanctum::actingAs(categoryTabGridActor('contracts'));

    $columns = $this->getJson("/api/tables/contracts/columns?product_category_branch_id={$root->id}")->assertOk()->json('data.columns');

    expect(categoryTabColumn($columns, 'attr.durata')['editable'])->toBeFalse();
});
