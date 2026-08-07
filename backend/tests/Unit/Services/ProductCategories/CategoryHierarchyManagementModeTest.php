<?php

use App\Enums\CategoryManagementMode;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategories\CategoryTreeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// rootManagementModesFor — forma batch (nessun walk per riga)
// ---------------------------------------------------------------------------

it('resolves root id + management_mode for a root, a child and a grandchild in ONE call', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => CategoryManagementMode::Single]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['management_mode' => CategoryManagementMode::Single]);

    $result = (new CategoryHierarchy)->rootManagementModesFor([$root->id, $child->id, $grandchild->id]);

    expect($result[$root->id])->toBe(['root_id' => $root->id, 'management_mode' => CategoryManagementMode::Single])
        ->and($result[$child->id])->toBe(['root_id' => $root->id, 'management_mode' => CategoryManagementMode::Single])
        ->and($result[$grandchild->id])->toBe(['root_id' => $root->id, 'management_mode' => CategoryManagementMode::Single]);
});

it('resolves TWO independent branches without cross-contamination', function () {
    $singleRoot = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $multipleRoot = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Multiple]);
    $singleChild = ProductCategory::factory()->childOf($singleRoot)->create(['management_mode' => CategoryManagementMode::Single]);
    $multipleChild = ProductCategory::factory()->childOf($multipleRoot)->create(['management_mode' => CategoryManagementMode::Multiple]);

    $result = (new CategoryHierarchy)->rootManagementModesFor([$singleChild->id, $multipleChild->id]);

    expect($result[$singleChild->id])->toBe(['root_id' => $singleRoot->id, 'management_mode' => CategoryManagementMode::Single])
        ->and($result[$multipleChild->id])->toBe(['root_id' => $multipleRoot->id, 'management_mode' => CategoryManagementMode::Multiple]);
});

it('resolves a single-id lookup the same way a batch call would', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Multiple]);

    $result = (new CategoryHierarchy)->rootManagementModesFor([$root->id]);

    expect($result)->toBe([$root->id => ['root_id' => $root->id, 'management_mode' => CategoryManagementMode::Multiple]]);
});

it('returns null for an id that does not exist', function () {
    $result = (new CategoryHierarchy)->rootManagementModesFor([999999]);

    expect($result)->toBe([999999 => null]);
});

it('runs a bounded number of queries regardless of how many ids are requested', function () {
    $root = ProductCategory::factory()->create(['management_mode' => CategoryManagementMode::Single]);
    $children = ProductCategory::factory()->childOf($root)->count(5)->create(['management_mode' => CategoryManagementMode::Single]);

    DB::enableQueryLog();
    (new CategoryHierarchy)->rootManagementModesFor($children->pluck('id')->all());
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBe(1);
});

// ---------------------------------------------------------------------------
// tree() — ogni nodo espone management_mode (denormalizzato, nessun walk)
// ---------------------------------------------------------------------------

it('tree() nodes carry their own effective management_mode', function () {
    $root = ProductCategory::factory()->create(['name' => 'Root', 'management_mode' => CategoryManagementMode::Single]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'Child', 'management_mode' => CategoryManagementMode::Single]);

    $tree = (new CategoryTreeBuilder)->tree();
    $rootNode = collect($tree)->firstWhere('name', 'Root');

    expect($rootNode['management_mode'])->toBe('single')
        ->and($rootNode['children'][0]['management_mode'])->toBe('single');
});
