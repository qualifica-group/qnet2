<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0180 AC-012: a `table` attribute in the work_order context.

uses(RefreshDatabase::class);

/**
 * @return array{0: int, 1: Attribute} work order id + its table attribute
 */
function tableAttributeWorkOrder(bool $required = false): array
{
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.create', 'work-orders.update', 'work-orders.view', 'work-orders.viewAll']);
    Sanctum::actingAs($actor);

    $attribute = Attribute::factory()->ofType('table')->create(['code' => 'inspections', 'config' => inspectionTableConfig()]);
    $category = ProductCategory::factory()->create();
    $category->attributes()->attach($attribute->id, ['is_required' => $required, 'sort_order' => 0, 'context' => 'work_order']);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

    $created = test()->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => $quote->id, 'title' => 'Commessa', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertCreated();

    return [$created->json('data.id'), $attribute];
}

it('AC-012: PATCH stores a normalized TableFieldValue with ids and summary', function () {
    [$id] = tableAttributeWorkOrder();

    $response = $this->patchJson("/api/work-orders/{$id}", ['attribute_values' => ['inspections' => ['rows' => [
        ['inspection_date' => '2026-10-12', 'inspector' => '  Rossi ', 'findings' => '3', 'active' => true, 'ignored' => 'x'],
        ['inspection_date' => '2026-11-01', 'site' => 'on_site'],
    ]]]])->assertOk();

    $value = $response->json('data.attribute_values.inspections');

    expect($value['rows'])->toHaveCount(2)
        ->and($value['rows'][0]['id'])->toBeUuid()
        ->and($value['rows'][0]['id'])->not->toBe($value['rows'][1]['id'])
        ->and($value['rows'][0]['inspector'])->toBe('Rossi')
        ->and($value['rows'][0]['findings'])->toBe(3)
        ->and($value['rows'][0])->not->toHaveKey('ignored')
        ->and($value['rows'][1]['active'])->toBeFalse()
        ->and($value['summary'])->toBe('2026-10-12');
});

it('AC-012: an invalid cell is a 422 keyed on the cell path', function () {
    [$id] = tableAttributeWorkOrder();

    $this->patchJson("/api/work-orders/{$id}", ['attribute_values' => ['inspections' => ['rows' => [
        ['inspection_date' => '2026-10-12'],
        ['inspection_date' => 'not-a-date'],
    ]]]])->assertStatus(422)->assertJsonValidationErrors(['attribute_values.inspections.rows.1.inspection_date']);
});

it('AC-012: two selected rows are a 422 on rows, and a required table refuses zero rows', function () {
    [$id] = tableAttributeWorkOrder(required: true);

    $this->patchJson("/api/work-orders/{$id}", ['attribute_values' => ['inspections' => ['rows' => [
        ['inspection_date' => '2026-10-12', 'active' => true],
        ['inspection_date' => '2026-10-13', 'active' => true],
    ]]]])->assertStatus(422)->assertJsonValidationErrors(['attribute_values.inspections.rows']);

    $this->patchJson("/api/work-orders/{$id}", ['attribute_values' => ['inspections' => ['rows' => []]]])
        ->assertStatus(422);
});

it('AC-012: re-sending the stored value keeps its row ids', function () {
    [$id] = tableAttributeWorkOrder();

    $first = $this->patchJson("/api/work-orders/{$id}", ['attribute_values' => ['inspections' => ['rows' => [
        ['inspection_date' => '2026-10-12'],
    ]]]])->assertOk()->json('data.attribute_values.inspections');

    $second = $this->patchJson("/api/work-orders/{$id}", ['attribute_values' => ['inspections' => $first]])
        ->assertOk()->json('data.attribute_values.inspections');

    expect($second['rows'][0]['id'])->toBe($first['rows'][0]['id']);
});
