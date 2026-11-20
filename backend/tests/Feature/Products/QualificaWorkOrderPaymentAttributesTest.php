<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Database\Seeders\QualificaWorkOrderPaymentAttributesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// One-shot: every category sees the legacy payment fields of the commessa
// ("Stato Pagamento", "Accordo sui Pagamenti", "Insoluti") in the Commessa
// context, not only the [OLD] ones they were migrated onto.

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
function workOrderPaymentCodes(ProductCategory $category): array
{
    return app(ProductCategoryService::class)
        ->effectiveAttributes($category->fresh(), AttributeContext::WorkOrder)
        ->pluck('code')->sort()->values()->all();
}

function workOrderPaymentAssignments(): int
{
    return DB::table('attribute_category')->where('context', AttributeContext::WorkOrder->value)->count();
}

it('makes every category see the payment fields, assigning only roots and barrier categories', function (): void {
    $attributes = collect(QualificaWorkOrderPaymentAttributesSeeder::ATTRIBUTE_CODES)
        ->map(fn (string $code): Attribute => Attribute::factory()->create(['code' => $code]));
    $legacy = ProductCategory::factory()->create(['name' => '[OLD] ISO']);
    $legacy->attributes()->attach($attributes->pluck('id')->all(), ['context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => 0]);
    $root = ProductCategory::factory()->create(['name' => 'ISO']);
    $child = ProductCategory::factory()->childOf($root)->create();
    $barrier = ProductCategory::factory()->childOf($root)->notInheritingIn(AttributeContext::WorkOrder)->create();
    $quoteOnly = Attribute::factory()->create(['code' => 'other_field']);
    $root->attributes()->attach($quoteOnly->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);

    test()->seed(QualificaWorkOrderPaymentAttributesSeeder::class);

    $expected = collect(QualificaWorkOrderPaymentAttributesSeeder::ATTRIBUTE_CODES)->sort()->values()->all();

    foreach ([$legacy, $root, $child, $barrier] as $category) {
        expect(workOrderPaymentCodes($category))->toBe($expected);
    }

    expect($child->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->count())->toBe(0)
        ->and(workOrderPaymentAssignments())->toBe(count(QualificaWorkOrderPaymentAttributesSeeder::ATTRIBUTE_CODES) * 3);
});

// REQUIREMENT CHANGED (spec 0402): "Stato Pagamento" is the commessa's own column, never assigned again.
it('is idempotent, skips an attribute missing from the catalogue and never assigns stato_pagamento', function (): void {
    Attribute::factory()->create(['code' => 'accordo_pagamento']);
    Attribute::factory()->create(['code' => 'stato_pagamento']);
    ProductCategory::factory()->create();

    test()->seed(QualificaWorkOrderPaymentAttributesSeeder::class);
    test()->seed(QualificaWorkOrderPaymentAttributesSeeder::class);

    expect(workOrderPaymentAssignments())->toBe(1)
        ->and(Attribute::query()->where('code', 'insoluti')->count())->toBe(0)
        ->and(DB::table('attribute_category')->where('attribute_id', Attribute::query()->where('code', 'stato_pagamento')->value('id'))->count())->toBe(0);
});
