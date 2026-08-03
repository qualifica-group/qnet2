<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use Database\Seeders\Concerns\ResolvesCategoryBusinessFunction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * Unit coverage for the demo seeders' coherence helper (spec 0023 REV): the
 * DemoProjectSeeder/DemoCampaignSeeder DERIVE each row's business function from
 * its category's EFFECTIVE one via this trait, so the seeded data satisfies the
 * same invariant the FormRequests enforce. A full DemoDataSeeder run cannot be
 * exercised here — it imports the geo dataset (`locations:add`) with SQL the
 * SQLite test driver rejects — so the pairing logic is verified directly.
 */
function coherencePairsResolver(): object
{
    return new class
    {
        use ResolvesCategoryBusinessFunction;

        /**
         * @param  Collection<int, ProductCategory>  $categories
         * @return Collection<int, array{product_category_id: int, business_function_id: int}>
         */
        public function pairs(Collection $categories): Collection
        {
            return $this->coherentClassificationPairs($categories);
        }
    };
}

it('derives each category\'s own or inherited business function and drops those with none', function () {
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();

    $ownA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    // Child of a B-owned parent, no own function -> inherits B (transitive).
    $parentB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);
    $inheritedB = ProductCategory::factory()->childOf($parentB)->create();
    // No function anywhere up the chain -> dropped from the coherent set.
    $orphan = ProductCategory::factory()->create(['business_function_id' => null]);

    $pairs = coherencePairsResolver()->pairs(ProductCategory::all());

    $byCategory = $pairs->keyBy('product_category_id');

    expect($byCategory->has($orphan->id))->toBeFalse();
    expect($byCategory[$ownA->id]['business_function_id'])->toBe($functionA->id);
    expect($byCategory[$parentB->id]['business_function_id'])->toBe($functionB->id);
    expect($byCategory[$inheritedB->id]['business_function_id'])->toBe($functionB->id);
});

it('drops unselectable containers, keeping the targets under them', function () {
    $function = BusinessFunction::factory()->create();

    $container = ProductCategory::factory()->create([
        'business_function_id' => $function->id,
        'is_selectable' => false,
    ]);
    $target = ProductCategory::factory()->childOf($container)->create(['is_selectable' => true]);

    $pairs = coherencePairsResolver()->pairs(ProductCategory::all());

    $byCategory = $pairs->keyBy('product_category_id');

    expect($byCategory->has($container->id))->toBeFalse()
        ->and($byCategory[$target->id]['business_function_id'])->toBe($function->id);
});
