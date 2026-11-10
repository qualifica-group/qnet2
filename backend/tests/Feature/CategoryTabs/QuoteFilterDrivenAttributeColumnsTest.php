<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// attr.<code> columns of the quotes grid, driven by the category filter (spec 0374): AC-006, AC-008, AC-012.

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $extra
 * @return array<int, array<string, mixed>>
 */
function quoteAttrRows(array $extra = []): array
{
    return test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25, ...$extra])->assertOk()->json('items');
}

it('reads, filters, sorts and lists distinct values of the attribute of the filtered category (AC-006)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $beta = categoryTabQuote([$category]);
    $alpha = categoryTabQuote([$category]);
    $empty = categoryTabQuote([$category]);
    $beta->forceFill(['attribute_values' => ['durata' => 'beta']])->save();
    $alpha->forceFill(['attribute_values' => ['durata' => 'alpha']])->save();
    categoryTabQuote([])->forceFill(['attribute_values' => ['durata' => 'zzz']])->save();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $scope = ['advancedFilters' => categoryTabFilters($category)];

    $rows = quoteAttrRows($scope);
    expect(collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['attr.durata']])->all())->toBe([$beta->id => 'beta', $alpha->id => 'alpha', $empty->id => null]);

    $sorted = array_column(quoteAttrRows([...$scope, 'sortModel' => [['colId' => 'attr.durata', 'sort' => 'asc']]]), 'id');
    expect(array_search($alpha->id, $sorted, true))->toBeLessThan(array_search($beta->id, $sorted, true));

    $filtered = quoteAttrRows([...$scope, 'filterModel' => ['attr.durata' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alp']]]);
    expect(array_column($filtered, 'id'))->toBe([$alpha->id]);

    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'attr.durata'])->assertOk()->json('data.values');
    expect($values)->toContain('alpha', 'beta');
});

it('writes the cell on quotes.attribute_values with a sparse merge and an activity log (AC-008)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'durata']);
    $quote = categoryTabQuote([$category]);
    $quote->forceFill(['attribute_values' => ['other' => 'kept']])->save();
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'update']));
    categoryTabSaveFilters('quotes', categoryTabFilters($category))->assertOk();

    expect(categoryTabColumn(categoryTabConfigColumns('quotes'), 'attr.durata')['editable'])->toBeTrue();

    $response = $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 90])->assertOk();

    expect($response->json('data')['attr.durata'])->toBe(90)
        ->and($quote->fresh()->attribute_values)->toBe(['other' => 'kept', 'durata' => 90])
        ->and(Quote::find($quote->id)->activities()->count())->toBeGreaterThan(0);
});

it('refuses a wrong type, a code not applicable to the quote and a table attribute (AC-008)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'durata']);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'foreign']);
    categoryTabAttribute(AttributeContext::Quote, 'table', ['code' => 'rows', 'config' => inspectionTableConfig()], $category);
    $quote = categoryTabQuote([$category]);
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'update']));
    categoryTabSaveFilters('quotes', categoryTabFilters($category))->assertOk();
    $patch = fn (string $column, mixed $value) => $this->patchJson("/api/tables/quotes/rows/{$quote->id}", compact('column', 'value'));

    expect(categoryTabColumn(categoryTabConfigColumns('quotes'), 'attr.rows')['editable'])->toBeFalse();
    $patch('attr.durata', 'not-a-number')->assertUnprocessable();
    $patch('attr.foreign', 'x')->assertUnprocessable();
    $patch('attr.rows', ['rows' => []])->assertUnprocessable();

    expect($quote->fresh()->attribute_values)->toBeNull();
});

it('is read-only without the field permission or quotes.update (AC-008)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'durata']);
    $quote = categoryTabQuote([$category]);
    Sanctum::actingAs(categoryTabAttributeRoleActor('quotes', editable: false));
    categoryTabSaveFilters('quotes', categoryTabFilters($category))->assertOk();

    expect(categoryTabColumn(categoryTabConfigColumns('quotes'), 'attr.durata')['editable'])->toBeFalse();
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 5])->assertForbidden();

    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 5])->assertForbidden();

    expect($quote->fresh()->attribute_values)->toBeNull();
});

it('shows rows and distinct values of the visible quotes only (AC-012)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $actor = categoryTabGridActor('quotes', viewAll: false);
    $mine = categoryTabQuote([$category]);
    $mine->forceFill(['attribute_values' => ['durata' => 'visible-value'], 'supervisor_id' => $actor->id])->save();
    categoryTabQuote([$category])->forceFill(['attribute_values' => ['durata' => 'hidden-value']])->save();
    Sanctum::actingAs($actor);

    $rows = quoteAttrRows(['advancedFilters' => categoryTabFilters($category)]);
    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'attr.durata'])->assertOk()->json('data.values');

    expect(array_column($rows, 'id'))->toBe([$mine->id])
        ->and($values)->toContain('visible-value')->not->toContain('hidden-value');
});
