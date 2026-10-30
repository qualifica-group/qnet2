<?php

use App\Models\ProductCategory;
use App\Products\ProductCategoryBranches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Spec 0337, AC-001.

uses(TestCase::class, RefreshDatabase::class);

it('returns the root and every descendant at any depth, and the root of a grandchild (AC-001)', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    $grandchild = ProductCategory::factory()->create(['parent_id' => $child->id]);
    $otherRoot = ProductCategory::factory()->create();
    $otherChild = ProductCategory::factory()->create(['parent_id' => $otherRoot->id]);
    $branches = new ProductCategoryBranches;

    $ids = $branches->branchIds($root->id);
    sort($ids);

    expect($ids)->toBe([$root->id, $child->id, $grandchild->id])
        ->and($branches->branchIds($otherRoot->id))->toBe([$otherRoot->id, $otherChild->id])
        ->and($branches->rootIdOf($grandchild->id))->toBe($root->id)
        ->and($branches->rootIdOf($root->id))->toBe($root->id)
        ->and($branches->rootIds())->toBe([$root->id, $otherRoot->id]);
});

it('reads the tree once per instance', function () {
    $root = ProductCategory::factory()->create();
    $branches = new ProductCategoryBranches;

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });
    $branches->branchIds($root->id);
    $branches->rootIdOf($root->id);
    $branches->rootIds();

    expect($queries)->toBe(1);
});
