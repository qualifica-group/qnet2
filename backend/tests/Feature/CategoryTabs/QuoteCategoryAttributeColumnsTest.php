<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// attr.<code> columns on the quotes grid category tab (spec 0350, D-1): AC-001, 002, 003, 008.

uses(RefreshDatabase::class);

const QUOTE_ATTR_PAGE = ['startRow' => 0, 'endRow' => 25];

function quoteAttrRows(array $extra = []): array
{
    return test()->postJson('/api/tables/quotes/rows', [...QUOTE_ATTR_PAGE, ...$extra])->assertOk()->json('items');
}

it('appends the category attribute after the native columns, only with a category tab (AC-001)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata', 'name' => 'Durata']);
    $root = $category;
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $tab = $this->getJson("/api/tables/quotes/columns?product_category_id={$category->id}")->assertOk()->json('data.columns');
    $ids = array_column($tab, 'id');

    expect(categoryTabColumn($tab, 'attr.durata'))->toMatchArray(['label' => 'Durata', 'type' => 'text', 'filterType' => 'text', 'visible' => true])
        ->and(array_search('attr.durata', $ids, true))->toBe(count($ids) - 1);

    $without = array_column($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'), 'id');
    $branch = array_column($this->getJson("/api/tables/quotes/columns?product_category_branch_id={$root->id}")->assertOk()->json('data.columns'), 'id');

    expect($without)->not->toContain('attr.durata')->and($branch)->not->toContain('attr.durata');
});

it('ignores attributes of another context (AC-001)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'only_wo']);
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $ids = array_column($this->getJson("/api/tables/quotes/columns?product_category_id={$category->id}")->json('data.columns'), 'id');

    expect($ids)->not->toContain('attr.only_wo');
});

it('reads, filters, sorts and lists distinct values of the attribute (AC-002)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $beta = categoryTabQuote([$category]);
    $alpha = categoryTabQuote([$category]);
    $empty = categoryTabQuote([$category]);
    $beta->forceFill(['attribute_values' => ['durata' => 'beta']])->save();
    $alpha->forceFill(['attribute_values' => ['durata' => 'alpha']])->save();
    $outsider = categoryTabQuote([]);
    $outsider->forceFill(['attribute_values' => ['durata' => 'zzz']])->save();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $scope = ['productCategoryId' => $category->id];

    $rows = quoteAttrRows($scope);
    expect(collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['attr.durata']])->all())->toBe([$beta->id => 'beta', $alpha->id => 'alpha', $empty->id => null]);

    $sorted = quoteAttrRows([...$scope, 'sortModel' => [['colId' => 'attr.durata', 'sort' => 'asc']]]);
    expect(array_column($sorted, 'id'))->toContain($alpha->id)
        ->and(array_search($alpha->id, array_column($sorted, 'id'), true))->toBeLessThan(array_search($beta->id, array_column($sorted, 'id'), true));

    $filtered = quoteAttrRows([...$scope, 'filterModel' => ['attr.durata' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alp']]]);
    expect(array_column($filtered, 'id'))->toBe([$alpha->id]);

    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'attr.durata', 'productCategoryId' => $category->id])->assertOk()->json('data.values');
    expect($values)->toContain('alpha', 'beta')->not->toContain('zzz');

    $this->postJson('/api/tables/quotes/rows', [...QUOTE_ATTR_PAGE, ...$scope, 'sortModel' => [['colId' => 'attr.unknown', 'sort' => 'asc']]])->assertUnprocessable();
});

it('writes the cell on quotes.attribute_values with a sparse merge and an activity log (AC-003)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'durata']);
    $quote = categoryTabQuote([$category]);
    $quote->forceFill(['attribute_values' => ['other' => 'kept']])->save();
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'update']));

    $columns = $this->getJson("/api/tables/quotes/columns?product_category_id={$category->id}")->json('data.columns');
    expect(categoryTabColumn($columns, 'attr.durata')['editable'])->toBeTrue();

    $response = $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 90])->assertOk();

    expect($response->json('data')['attr.durata'])->toBe(90)
        ->and($quote->fresh()->attribute_values)->toBe(['other' => 'kept', 'durata' => 90])
        ->and(Quote::find($quote->id)->activities()->count())->toBeGreaterThan(0);
});

it('refuses a wrong type, a code not applicable to the quote and a table attribute (AC-003)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'durata']);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'foreign']);
    $quote = categoryTabQuote([$category]);
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'update']));
    $patch = fn (string $column, mixed $value) => $this->patchJson("/api/tables/quotes/rows/{$quote->id}", compact('column', 'value'));

    $patch('attr.durata', 'not-a-number')->assertUnprocessable();
    $patch('attr.foreign', 'x')->assertUnprocessable();
    $patch('attr.missing', 'x')->assertUnprocessable();

    expect($quote->fresh()->attribute_values)->toBeNull();
});

it('keeps a table attribute read-only (AC-003)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'table', ['code' => 'rows', 'config' => inspectionTableConfig()]);
    $quote = categoryTabQuote([$category]);
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'update']));

    $columns = $this->getJson("/api/tables/quotes/columns?product_category_id={$category->id}")->json('data.columns');

    expect(categoryTabColumn($columns, 'attr.rows')['editable'])->toBeFalse();
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.rows', 'value' => ['rows' => []]])->assertUnprocessable();
});

it('is read-only without the field permission or quotes.update (AC-003)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'durata']);
    $quote = categoryTabQuote([$category]);
    $readOnly = categoryTabAttributeRoleActor('quotes', editable: false);
    Sanctum::actingAs($readOnly);

    $columns = $this->getJson("/api/tables/quotes/columns?product_category_id={$category->id}")->json('data.columns');
    expect(categoryTabColumn($columns, 'attr.durata')['editable'])->toBeFalse();
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 5])->assertForbidden();

    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'attr.durata', 'value' => 5])->assertForbidden();

    expect($quote->fresh()->attribute_values)->toBeNull();
});

it('accepts preferences and filters naming the attribute of any category (AC-008)', function () {
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'field_a']);
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $this->postJson('/api/tables/quotes/preferences', ['columns' => [['id' => 'attr.field_a', 'visible' => false, 'order' => 1]]])->assertOk();
    $this->postJson('/api/tables/quotes/filters', ['filterModel' => ['attr.field_a' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']]])->assertOk();

    $category = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'field_b'])[0];
    $columns = $this->getJson("/api/tables/quotes/columns?product_category_id={$category->id}")->json('data.columns');
    expect(categoryTabColumn($columns, 'attr.field_b')['visible'])->toBeTrue();
});

it('keeps a hidden attribute column hidden on reload (AC-008)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    categoryTabAssertAttributeStaysHidden('quotes', $category, 'durata');
});

it('shows rows and distinct values of the visible quotes only (AC-010)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $actor = categoryTabGridActor('quotes', viewAll: false);
    $mine = categoryTabQuote([$category]);
    $mine->forceFill(['attribute_values' => ['durata' => 'visible-value'], 'supervisor_id' => $actor->id])->save();
    categoryTabQuote([$category])->forceFill(['attribute_values' => ['durata' => 'hidden-value']])->save();
    Sanctum::actingAs($actor);

    $rows = quoteAttrRows(['productCategoryId' => $category->id]);
    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'attr.durata', 'productCategoryId' => $category->id])->assertOk()->json('data.values');

    expect(array_column($rows, 'id'))->toBe([$mine->id])
        ->and($values)->toContain('visible-value')->not->toContain('hidden-value');
});
