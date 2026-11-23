<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\RequestManagement\AttributeSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Spec 0425: "Sincronizzato con l'offerta" on a Commessa field assignment
// (AC-001, AC-002) and the `is_synced` mark of the applicable set (AC-010).

uses(RefreshDatabase::class);

it('AC-001: stores the flag on a Commessa field and shows it own and inherited', function () {
    $attribute = Attribute::factory()->ofType('text')->create();
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->patchJson("/api/product-categories/{$parent->id}", ['attributes' => [
        ['attribute_id' => $attribute->id, 'context' => 'quote'],
        ['attribute_id' => $attribute->id, 'context' => 'work_order', 'syncs_with_quote' => true],
    ]])->assertOk()
        ->assertJsonPath('data.attributes.0.syncs_with_quote', false)
        ->assertJsonPath('data.attributes.1.syncs_with_quote', true);

    expect(DB::table('attribute_category')->where('category_id', $parent->id)->pluck('syncs_with_quote', 'context')->map(fn ($flag) => (bool) $flag)->all())
        ->toBe(['quote' => false, 'work_order' => true]);

    $inherited = collect($this->getJson("/api/product-categories/{$child->id}")->assertOk()->json('data.inherited_attributes'))
        ->keyBy('context');
    $effective = $this->getJson("/api/product-categories/{$child->id}/effective-attributes?context=work_order")->assertOk();

    expect($inherited['work_order']['syncs_with_quote'])->toBeTrue()
        ->and($inherited['quote']['syncs_with_quote'])->toBeFalse()
        ->and($effective->json('data.0.syncs_with_quote'))->toBeTrue();
});

it('AC-002: refuses the flag on an Offerta or Prodotto field and changes nothing', function (string $context) {
    $attribute = Attribute::factory()->ofType('text')->create();
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->patchJson("/api/product-categories/{$category->id}", ['attributes' => [
        ['attribute_id' => $attribute->id, 'context' => $context, 'syncs_with_quote' => true],
    ]])->assertUnprocessable()->assertJsonValidationErrors(['attributes.0.syncs_with_quote']);

    expect(DB::table('attribute_category')->where('category_id', $category->id)->count())->toBe(0);
})->with(['quote', 'product']);

it('AC-010: marks is_synced on the offer, the work order and their form contexts, only for the synced code', function () {
    $category = syncedAttributeCategory('sync_registry');
    $quote = categoryTabQuote([$category]);
    $workOrder = syncedAttributeWorkOrder($quote);
    Sanctum::actingAs(workOrderUserWith(['view', 'create']));

    $syncedOf = fn (array $attributes): array => collect($attributes)->filter(fn (array $attribute): bool => $attribute['is_synced'])->pluck('code')->all();

    $workOrderSet = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk()->json('data.applicable_attributes');
    $workOrderContext = $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => [$quote->offerLines()->sole()->id]])->assertOk()->json('data.applicable_attributes');
    $quoteSet = app(AttributeSetResolver::class)->resolve([$category->id], AttributeContext::Quote)->map->toArray()->all();

    expect($syncedOf($workOrderSet))->toBe(['sync_registry'])
        ->and($syncedOf($workOrderContext))->toBe(['sync_registry'])
        ->and($syncedOf($quoteSet))->toBe(['sync_registry'])
        ->and(collect($workOrderSet)->pluck('code')->all())->toContain('sync_registry_work_order_only');
});

it('AC-010: a flag on a code the offer does not have, or a flag turned off, marks nothing', function () {
    $category = syncedAttributeCategory('sync_registry', synced: false);
    $lonely = Attribute::factory()->ofType('text')->create(['code' => 'sync_lonely']);
    $category->attributes()->attach($lonely->id, ['context' => 'work_order', 'is_required' => false, 'sort_order' => 0, 'syncs_with_quote' => true]);

    $resolver = app(AttributeSetResolver::class);

    expect($resolver->resolve([$category->id], AttributeContext::WorkOrder)->filter->isSynced->all())->toBe([])
        ->and($resolver->resolve([$category->id], AttributeContext::Quote)->filter->isSynced->all())->toBe([]);
});
