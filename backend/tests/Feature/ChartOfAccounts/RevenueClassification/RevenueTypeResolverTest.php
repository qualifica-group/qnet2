<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ChartOfAccounts\RevenueTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('AC-002: product, then own category, then closest ancestor, then null', function () {
    [$own, $viaCategory, $viaAncestor] = [revenueSubAccount(), revenueSubAccount(), revenueSubAccount()];
    $root = ProductCategory::factory()->create(['revenue_type_id' => $viaAncestor->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    $withCategory = ProductCategory::factory()->create(['revenue_type_id' => $viaCategory->id]);
    $bare = ProductCategory::factory()->create();

    $items = [
        'own' => Product::factory()->create(['category_id' => $withCategory->id, 'revenue_type_id' => $own->id]),
        'category' => Product::factory()->create(['category_id' => $withCategory->id]),
        'ancestor' => Product::factory()->create(['category_id' => $child->id]),
        'none' => Product::factory()->create(['category_id' => $bare->id]),
    ];
    $proposals = app(RevenueTypeResolver::class)->resolve(array_map(
        fn (Product $product): array => ['product_id' => $product->id, 'institution' => false],
        $items,
    ) + ['free' => ['product_id' => null, 'institution' => false]]);

    expect($proposals)->toBe([
        'own' => $own->id, 'category' => $viaCategory->id, 'ancestor' => $viaAncestor->id, 'none' => null, 'free' => null,
    ]);
});

it('AC-002: an institution line takes the commission default even if the product has another sub-account', function () {
    $default = institutionCommissionDefault();
    $product = Product::factory()->create(['revenue_type_id' => revenueSubAccount()->id]);

    $proposals = app(RevenueTypeResolver::class)->resolve([
        'ente' => ['product_id' => $product->id, 'institution' => true],
        'sale' => ['product_id' => $product->id, 'institution' => false],
    ]);

    expect($proposals['ente'])->toBe($default->id)->and($proposals['sale'])->toBe($product->revenue_type_id);
});

it('AC-002: the number of queries does not grow with the number of lines', function () {
    $root = ProductCategory::factory()->create(['revenue_type_id' => revenueSubAccount()->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    $count = function (int $lines) use ($child): int {
        $items = [];
        foreach (Product::factory()->count($lines)->create(['category_id' => $child->id]) as $product) {
            $items[] = ['product_id' => $product->id, 'institution' => false];
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(RevenueTypeResolver::class)->resolve($items);

        return count(DB::getQueryLog());
    };

    expect($count(30))->toBe($count(2));
});

it('AC-002: fillMissing keeps a present key, even null, and fills only the absent ones', function () {
    $type = revenueSubAccount();
    $product = Product::factory()->create(['revenue_type_id' => $type->id]);

    $lines = app(RevenueTypeResolver::class)->fillMissing([
        ['product_id' => $product->id],
        ['product_id' => $product->id, 'revenue_type_id' => null],
    ], false);

    expect($lines[0]['revenue_type_id'])->toBe($type->id)->and($lines[1]['revenue_type_id'])->toBeNull();
});
