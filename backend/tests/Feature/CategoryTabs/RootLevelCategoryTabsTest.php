<?php

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// GET /api/{module}/product-categories?level=root on the five strips (spec 0337, AC-002..004).

uses(RefreshDatabase::class);

it('lists only the root categories, each counting the distinct records of its branch (AC-002)', function (string $module) {
    [$root, $child, $grandchild, $other] = categoryTabTree();
    ProductCategory::factory()->create(['name' => 'T empty root']);
    categoryTabRecord($module, [$child, $grandchild]);
    categoryTabRecord($module, [$grandchild]);
    categoryTabRecord($module, [$other]);
    Sanctum::actingAs(categoryTabGridActor($module));
    $count = categoryTabCountKey($module);

    $this->getJson("/api/{$module}/product-categories?level=root")
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'OK',
            'data' => ['categories' => [
                ['id' => $root->id, 'name' => 'R root', $count => 2],
                ['id' => $other->id, 'name' => 'S other root', $count => 1],
            ]],
        ]);

    $all = collect($this->getJson("/api/{$module}/product-categories")->assertOk()->json('data.categories'))->pluck($count, 'id')->all();
    expect($all)->toBe([$child->id => 1, $grandchild->id => 2, $other->id => 1]);
})->with(categoryTabModules());

it('counts a record on the root and on its descendant once (AC-002)', function (string $module) {
    [$root, $child] = categoryTabTree();
    categoryTabRecord($module, [$root, $child]);
    Sanctum::actingAs(categoryTabGridActor($module));

    $this->getJson("/api/{$module}/product-categories?level=root")
        ->assertOk()
        ->assertJsonPath('data.categories.0.id', $root->id)
        ->assertJsonPath('data.categories.0.'.categoryTabCountKey($module), 1);
})->with(categoryTabModules());

it('keeps the visibility scope of the module at the root level (AC-003)', function (string $module) {
    [, $child] = categoryTabTree();
    categoryTabRecord($module, [$child]);

    Sanctum::actingAs(categoryTabGridActor($module, viewAll: false));
    $this->getJson("/api/{$module}/product-categories?level=root")
        ->assertOk()
        ->assertJsonPath('data.categories', []);

    Sanctum::actingAs(categoryTabGridActor($module));
    $this->getJson("/api/{$module}/product-categories?level=root")
        ->assertOk()
        ->assertJsonCount(1, 'data.categories');
})->with(categoryTabModules());

it('rejects an unknown level with 422 and still requires viewAny for the root level (AC-004)', function (string $module) {
    Sanctum::actingAs(categoryTabGridActor($module));
    $this->getJson("/api/{$module}/product-categories?level=foo")->assertUnprocessable();

    Sanctum::actingAs(categoryTabGridActor($module, viewAll: false, viewAny: false));
    $this->getJson("/api/{$module}/product-categories?level=root")->assertForbidden();
})->with(categoryTabModules());
