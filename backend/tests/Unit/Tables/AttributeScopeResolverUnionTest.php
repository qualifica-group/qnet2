<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Tables\RequestManagement\AttributeScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function assignAttribute(ProductCategory $category, Attribute $attribute, int $sortOrder, AttributeContext $context = AttributeContext::Quote): void
{
    $category->attributes()->attach($attribute->id, [
        'context' => $context->value,
        'is_required' => false,
        'sort_order' => $sortOrder,
    ]);
}

function unionQueryCount(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(AttributeScopeResolver::class)->union();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('resolves the union as the code-deduped merge of every category own effective set', function (): void {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $barrier = ProductCategory::factory()->childOf($root)->notInheritingIn(AttributeContext::Quote)->create();

    $inherited = Attribute::factory()->create();
    $enum = Attribute::factory()->enum(2)->create();
    $barrierOwn = Attribute::factory()->create();
    $productOnly = Attribute::factory()->create();

    assignAttribute($root, $inherited, 1);
    assignAttribute($child, $enum, 2);
    assignAttribute($child, $inherited, 3);
    assignAttribute($barrier, $barrierOwn, 4);
    assignAttribute($root, $productOnly, 5, AttributeContext::Product);

    $expected = [];
    $perCategory = app(AttributeScopeResolver::class);
    foreach ([$root, $child, $barrier] as $category) {
        foreach ($perCategory->forCategory($category->id) as $row) {
            $expected[$row['code']] ??= $row;
        }
    }

    $union = app(AttributeScopeResolver::class)->union();

    expect($union->pluck('code')->sort()->values()->all())
        ->toBe(collect($expected)->keys()->sort()->values()->all())
        ->and($union->pluck('code'))->not->toContain($productOnly->code)
        ->and($union->firstWhere('code', $enum->code)['options'])->toHaveCount(2);

    foreach ($union as $row) {
        expect($row)->toBe($expected[$row['code']]);
    }
});

it('resolves the union with a query count independent of the number of categories', function (): void {
    $root = ProductCategory::factory()->create();
    assignAttribute($root, Attribute::factory()->enum()->create(), 1);
    $child = ProductCategory::factory()->childOf($root)->create();
    assignAttribute($child, Attribute::factory()->create(), 2);

    $baseline = unionQueryCount();

    ProductCategory::factory()->count(25)->childOf($child)->create()
        ->each(fn (ProductCategory $leaf, int $index) => assignAttribute($leaf, Attribute::factory()->create(), $index + 3));

    expect(unionQueryCount())->toBe($baseline);
});
