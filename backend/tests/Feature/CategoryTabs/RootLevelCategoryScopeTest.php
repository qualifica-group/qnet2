<?php

use App\Models\Attribute;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// POST /api/tables/{domain}/rows|values with productCategoryBranchId (spec 0337, AC-005, AC-006).

uses(RefreshDatabase::class);

const ROOT_SCOPE_PAGE = ['startRow' => 0, 'endRow' => 25];

/** A text column every domain lists, to read distinct values from. */
const ROOT_SCOPE_VALUE_COLUMN = [
    'request-management' => 'quote_workflow_status',
    'enrollee-management' => 'quote_workflow_status',
    'opportunities' => 'name',
    'quotes' => 'code',
    'contracts' => 'registry',
];

it('returns the rows of the whole branch once each, matching the strip count (AC-005)', function (string $module) {
    [$root, $child, $grandchild, $other] = categoryTabTree();
    $a = categoryTabRecord($module, [$child, $grandchild]);
    $b = categoryTabRecord($module, [$grandchild]);
    categoryTabRecord($module, [$other]);
    Sanctum::actingAs(categoryTabGridActor($module));

    $rows = $this->postJson("/api/tables/{$module}/rows", [...ROOT_SCOPE_PAGE, 'productCategoryBranchId' => $root->id])->assertOk();
    $strip = $this->getJson("/api/{$module}/product-categories?level=root")->json('data.categories');

    expect(collect($rows->json('items'))->pluck('id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and($rows->json('pagination.total'))->toBe(collect($strip)->firstWhere('id', $root->id)[categoryTabCountKey($module)])
        ->and($this->postJson("/api/tables/{$module}/rows", ROOT_SCOPE_PAGE)->json('pagination.total'))->toBe(3);
})->with(categoryTabModules());

it('rejects a branch sent together with a single category and an unknown branch (AC-005)', function (string $module) {
    [$root, $child] = categoryTabTree();
    Sanctum::actingAs(categoryTabGridActor($module));

    $this->postJson("/api/tables/{$module}/rows", [...ROOT_SCOPE_PAGE, 'productCategoryBranchId' => $root->id, 'productCategoryId' => $child->id])
        ->assertUnprocessable();
    $this->postJson("/api/tables/{$module}/rows", [...ROOT_SCOPE_PAGE, 'productCategoryBranchId' => 999999])
        ->assertUnprocessable();
    $this->postJson("/api/tables/{$module}/values", ['columnId' => ROOT_SCOPE_VALUE_COLUMN[$module], 'productCategoryBranchId' => $root->id, 'productCategoryId' => $child->id])
        ->assertUnprocessable();
})->with(categoryTabModules());

it('computes distinct values only on the branch rows (AC-005)', function (string $module) {
    [$root, , $grandchild, $other] = categoryTabTree();
    categoryTabRecord($module, [$grandchild]);
    // The request modules list the workflow status: the record outside the branch gets another one.
    if (in_array($module, ['request-management', 'enrollee-management'], true)) {
        categoryTabRequestQuote([$other], 'validated');
    } else {
        categoryTabRecord($module, [$other]);
    }
    Sanctum::actingAs(categoryTabGridActor($module));
    $column = ROOT_SCOPE_VALUE_COLUMN[$module];

    $values = fn (array $extra) => $this->postJson("/api/tables/{$module}/values", ['columnId' => $column, ...$extra])->assertOk()->json('data.values');

    expect($values(['productCategoryBranchId' => $root->id]))->toHaveCount(1)
        ->and($values([]))->toHaveCount(2);
})->with(categoryTabModules());

it('leaves Gestione Richieste without attr columns and relabels at a root tab (AC-006)', function () {
    [$root, $child] = categoryTabTree();
    $attribute = Attribute::factory()->ofType('text')->create();
    $root->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $quote = categoryTabRecord('request-management', [$child]);
    $actor = categoryTabGridActor('request-management');
    Sanctum::actingAs($actor);

    $configOf = function (callable $scope) use ($actor): array {
        $definition = app(TableRegistry::class)->resolve('request-management');
        $scope($definition);

        return $definition->resolveConfig($actor)['columns'];
    };
    $attrIds = fn (array $columns): array => array_values(array_filter(array_column($columns, 'id'), static fn (string $id): bool => str_starts_with($id, 'attr.')));

    $unscoped = $configOf(static fn () => null);
    $branch = $configOf(static fn ($definition) => $definition->scopeToProductCategoryBranch($root->id));

    expect($attrIds($configOf(static fn ($definition) => $definition->scopeToProductCategory($root->id))))->not->toBe([])
        ->and($attrIds($branch))->toBe([])
        ->and($branch)->toBe($unscoped);

    $rows = $this->postJson('/api/tables/request-management/rows', [...ROOT_SCOPE_PAGE, 'productCategoryBranchId' => $root->id])->assertOk();
    $keys = array_keys($rows->json('items.0'));

    expect($rows->json('items.0.id'))->toBe($quote->id)
        ->and(array_filter($keys, static fn (string $key): bool => str_starts_with($key, 'attr.')))->toBe([]);
});
