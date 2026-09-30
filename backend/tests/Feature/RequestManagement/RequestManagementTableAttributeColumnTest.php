<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0180 AC-015: a `table` attribute column in the request-management grid.

uses(RefreshDatabase::class);

/**
 * @return array{0: ProductCategory, 1: Attribute}
 */
function tableColumnCategory(): array
{
    foreach (['viewAny', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    Sanctum::actingAs(User::factory()->create()->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']));

    $attribute = Attribute::factory()->ofType('table')->create(['code' => 'inspections', 'config' => inspectionTableConfig()]);
    $category = ProductCategory::factory()->create();
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    return [$category, $attribute];
}

function tableColumnQuote(ProductCategory $category, ?string $summary): Quote
{
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create(['opportunity_id' => $opportunity->id, 'product_category_id' => $category->id]);
    $quote = Quote::factory()->for($opportunity)->create();
    $quote->forceFill(['attribute_values' => ['inspections' => ['rows' => [], 'summary' => $summary]]])->save();

    return $quote;
}

it('AC-015: emits a read-only table column', function () {
    [$category] = tableColumnCategory();

    $column = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id')['attr.inspections'];

    expect($column['type'])->toBe('table')
        ->and($column['filterType'])->toBe('text')
        ->and($column['editable'])->toBeFalse();
});

it('AC-015: refuses a cell edit and leaves attribute_values untouched', function () {
    [$category] = tableColumnCategory();
    $quote = tableColumnQuote($category, '2026-10-12');
    $before = $quote->fresh()->attribute_values;

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'attr.inspections',
        'value' => ['rows' => [['inspection_date' => '2026-12-01']]],
    ])->assertStatus(422);

    expect($quote->fresh()->attribute_values)->toBe($before);
});

it('AC-015: sorts and filters on the summary and lists no distinct values', function () {
    [$category] = tableColumnCategory();
    $late = tableColumnQuote($category, '2026-12-01');
    $early = tableColumnQuote($category, '2026-01-01');

    $rows = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'sortModel' => [['colId' => 'attr.inspections', 'sort' => 'desc']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$late->id, $early->id]);

    $filtered = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.inspections' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '2026-01']],
    ])->assertOk()->json('items');

    expect(array_column($filtered, 'id'))->toBe([$early->id]);
});

it('AC-025: the table column carries the attribute config, other columns do not', function () {
    [$category] = tableColumnCategory();
    $text = Attribute::factory()->ofType('text')->create(['code' => 'plain']);
    $category->attributes()->attach($text->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['attr.inspections']['table'])->toBe(inspectionTableConfig())
        ->and($columns['attr.plain'])->not->toHaveKey('table');
});
