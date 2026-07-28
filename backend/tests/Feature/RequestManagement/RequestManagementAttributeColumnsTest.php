<?php

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0064 (docs/specs/0064-request-management-category-tabs.xml), §M2:
// category-scoped `attr.<code>` columns on `GET .../columns` and
// `POST .../rows` (config shape, mapping table, D-2 EXISTS scoping, sort/
// filter allow-list). AC-004..013 (AC-001..003 and AC-019..022 belong to
// other lanes/M3 — see ProductCategoryTabsTest.php and the frontend Vitest
// suites; AC-014..018 live in RequestManagementAttributeWritesTest.php,
// split for the file-size budget, engineering.md §6).

uses(RefreshDatabase::class);

if (! function_exists('attributeColumnsUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeColumnsUserWith(array $abilities): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('attributeCategory')) {
    /**
     * A fresh ProductCategory with one attribute of $type attached in the
     * Opportunity context.
     *
     * @param  array<string, mixed>  $attributeOverrides
     * @return array{0: ProductCategory, 1: Attribute}
     */
    function attributeCategory(string $type, array $attributeOverrides = [], int $sortOrder = 0, bool $required = false): array
    {
        $category = ProductCategory::factory()->create();
        $attribute = $type === 'enum'
            ? Attribute::factory()->enum(2)->create($attributeOverrides)
            : Attribute::factory()->ofType($type)->create($attributeOverrides);

        $category->attributes()->attach($attribute->id, [
            'is_required' => $required,
            'sort_order' => $sortOrder,
            'context' => 'opportunity',
        ]);

        return [$category, $attribute];
    }
}

if (! function_exists('opportunityInCategory')) {
    function opportunityInCategory(ProductCategory $category, array $attributes = []): Opportunity
    {
        $opportunity = Opportunity::factory()->create($attributes);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-004 — "Tutte" tab: zero attr.* columns
// ---------------------------------------------------------------------------

it('AC-004: GET columns without product_category_id emits no attr.* column', function () {
    [$category] = attributeCategory('text');
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    Sanctum::actingAs($actor);

    $columns = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns');

    $attrColumns = array_filter($columns, static fn (array $column): bool => str_starts_with($column['id'], 'attr.'));
    expect($attrColumns)->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-005 — own + inherited attributes, ordered, source/visible
// ---------------------------------------------------------------------------

it('AC-005: 2 own + 1 inherited attribute appear as 3 attr.* columns, ordered by [sort_order, code]', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);

    $parent = ProductCategory::factory()->create();
    $parentAttribute = Attribute::factory()->create(['code' => 'inherited_one']);
    $parent->attributes()->attach($parentAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $ownA = Attribute::factory()->create(['code' => 'own_b']);
    $ownB = Attribute::factory()->create(['code' => 'own_a']);
    $child->attributes()->attach($ownA->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'opportunity']);
    $child->attributes()->attach($ownB->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'opportunity']);

    Sanctum::actingAs($actor);

    $columns = $this->getJson("/api/tables/request-management/columns?product_category_id={$child->id}")
        ->assertOk()->json('data.columns');

    $attrColumns = array_values(array_filter($columns, static fn (array $column): bool => str_starts_with($column['id'], 'attr.')));

    expect($attrColumns)->toHaveCount(3);
    expect(array_column($attrColumns, 'id'))->toBe(['attr.inherited_one', 'attr.own_a', 'attr.own_b']);

    foreach ($attrColumns as $column) {
        expect($column['visible'])->toBeTrue()
            ->and($column['source'])->toBe('attribute');
    }

    // in coda alle native: ogni attr.* order supera ogni colonna non-attr.
    $nativeMaxOrder = max(array_column(array_filter($columns, static fn (array $c): bool => ! str_starts_with($c['id'], 'attr.')), 'order'));
    $attrMinOrder = min(array_column($attrColumns, 'order'));
    expect($attrMinOrder)->toBeGreaterThan($nativeMaxOrder);
});

// ---------------------------------------------------------------------------
// AC-006 — a Product-context attribute never appears on the Opportunity side
// ---------------------------------------------------------------------------

it('AC-006: an attribute assigned in Product context does not appear', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'product_only']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);

    Sanctum::actingAs($actor);

    $columns = $this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns');

    expect(array_column($columns, 'id'))->not->toContain('attr.product_only');
});

// ---------------------------------------------------------------------------
// AC-007 — type -> (type, filterType, editor) mapping table
// ---------------------------------------------------------------------------

it('AC-007: attribute type maps to the frozen (type, filterType, editor) triad', function (
    string $attrType,
    array $config,
    ?array $relationTarget,
    string $expectedType,
    string $expectedFilterType,
    ?string $expectedEditor,
) {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create();

    $attribute = $attrType === 'enum'
        ? Attribute::factory()->enum(2)->create(['config' => $config])
        : Attribute::factory()->ofType($attrType)->create(['config' => $config, 'relation_target' => $relationTarget]);

    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    Sanctum::actingAs($actor);

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    $column = $columns["attr.{$attribute->code}"];

    expect($column['type'])->toBe($expectedType)
        ->and($column['filterType'])->toBe($expectedFilterType)
        ->and($column['editor'] ?? null)->toBe($expectedEditor);
})->with([
    'text' => ['text', [], null, 'text', 'text', null],
    'textarea' => ['textarea', [], null, 'text', 'text', null],
    'email' => ['email', [], null, 'text', 'text', null],
    'url' => ['url', [], null, 'text', 'text', null],
    'color' => ['color', [], null, 'text', 'text', null],
    'time' => ['time', [], null, 'text', 'text', null],
    'integer' => ['integer', [], null, 'number', 'number', null],
    'decimal' => ['decimal', [], null, 'number', 'number', null],
    'boolean' => ['boolean', [], null, 'boolean', 'set', null],
    'enum single' => ['enum', [], null, 'enum', 'set', 'select'],
    'enum multiselect' => ['enum', ['display' => 'multiselect'], null, 'tags', 'set', 'tags'],
    'relation cardinality one' => ['relation', [], ['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents'], 'text', 'set', 'relation'],
    'relation cardinality many' => ['relation', [], ['entity_type' => 'referents', 'cardinality' => 'many', 'for_select_resource' => 'referents'], 'tags', 'set', 'multiselect'],
    'date' => ['date', [], null, 'text', 'date', 'date'],
    'datetime' => ['datetime', [], null, 'datetime', 'date', 'datetime'],
]);

// ---------------------------------------------------------------------------
// AC-008 — nonexistent category -> 422
// ---------------------------------------------------------------------------

it('AC-008: columns?product_category_id=9999 (inesistente) restituisce 422', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/request-management/columns?product_category_id=9999')->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-009 — D-2: EXISTS on product lines, a multi-category request in every tab
// ---------------------------------------------------------------------------

it('AC-009: rows scoped to category A returns only requests with a line on A; a two-category request appears in both', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$categoryA] = attributeCategory('text');
    $categoryB = ProductCategory::factory()->create();

    $onlyA = opportunityInCategory($categoryA);
    $onlyB = opportunityInCategory($categoryB);

    $both = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create(['opportunity_id' => $both->id, 'product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $both->id, 'product_category_id' => $categoryB->id]);

    Sanctum::actingAs($actor);

    $rowsA = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryA->id,
    ])->assertOk()->json('items');
    $idsA = array_column($rowsA, 'id');

    expect($idsA)->toContain($onlyA->id)
        ->and($idsA)->toContain($both->id)
        ->and($idsA)->not->toContain($onlyB->id);

    $rowsB = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryB->id,
    ])->assertOk()->json('items');
    $idsB = array_column($rowsB, 'id');

    expect($idsB)->toContain($onlyB->id)
        ->and($idsB)->toContain($both->id)
        ->and($idsB)->not->toContain($onlyA->id);
});

// ---------------------------------------------------------------------------
// AC-010 — attr.<code> value on the row payload
// ---------------------------------------------------------------------------

it('AC-010: an item carries attr.<code> from attribute_values, null when unset', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$category] = attributeCategory('integer', ['code' => 'durata_corso']);

    $withValue = opportunityInCategory($category, ['attribute_values' => ['durata_corso' => 120]]);
    $withoutValue = opportunityInCategory($category);

    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
    ])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$withValue->id]['attr.durata_corso'])->toBe(120)
        ->and($rows[$withoutValue->id]['attr.durata_corso'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-011 — sort by attr.<code>
// ---------------------------------------------------------------------------

it('AC-011: sortModel on attr.<code> orders rows by that JSON value', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$category] = attributeCategory('integer', ['code' => 'durata_corso']);

    $high = opportunityInCategory($category, ['attribute_values' => ['durata_corso' => 300]]);
    $low = opportunityInCategory($category, ['attribute_values' => ['durata_corso' => 10]]);
    $mid = opportunityInCategory($category, ['attribute_values' => ['durata_corso' => 100]]);

    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'sortModel' => [['colId' => 'attr.durata_corso', 'sort' => 'asc']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$low->id, $mid->id, $high->id]);
});

// ---------------------------------------------------------------------------
// AC-012 — filterModel on attr.<code>: text contains, number equals, set in
// ---------------------------------------------------------------------------

it('AC-012: filterModel text-contains on an attr.<code> text column filters rows', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$category] = attributeCategory('text', ['code' => 'note_corso']);

    $matching = opportunityInCategory($category, ['attribute_values' => ['note_corso' => 'Corso Serale']]);
    $other = opportunityInCategory($category, ['attribute_values' => ['note_corso' => 'Altro']]);

    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.note_corso' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Serale']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$matching->id])
        ->and(array_column($rows, 'id'))->not->toContain($other->id);
});

it('AC-012: filterModel number-equals on an attr.<code> number column filters rows', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$category] = attributeCategory('integer', ['code' => 'durata_corso']);

    $matching = opportunityInCategory($category, ['attribute_values' => ['durata_corso' => 50]]);
    $other = opportunityInCategory($category, ['attribute_values' => ['durata_corso' => 99]]);

    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.durata_corso' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 50]],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$matching->id]);
});

it('AC-012: filterModel set-in on an attr.<code> enum column filters rows', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$category, $attribute] = attributeCategory('enum', ['code' => 'stato_corso']);
    $option = $attribute->options()->first();

    $matching = opportunityInCategory($category, ['attribute_values' => ['stato_corso' => $option->value]]);
    $other = opportunityInCategory($category, ['attribute_values' => ['stato_corso' => 'zzz-not-in-set']]);

    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.stato_corso' => ['filterType' => 'set', 'values' => [$option->value]]],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// AC-013 — out-of-scope attr.* colId/filterModel key -> 422
// ---------------------------------------------------------------------------

it('AC-013: sortModel referencing an attr.<code> outside the requested category returns 422', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    [$categoryA] = attributeCategory('text', ['code' => 'only_on_a']);
    $categoryB = ProductCategory::factory()->create();

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryB->id,
        'sortModel' => [['colId' => 'attr.only_on_a', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('AC-013: a filterModel key attr.<code> without productCategoryId returns 422', function () {
    $actor = attributeColumnsUserWith(['viewAny', 'viewAll']);
    attributeCategory('text', ['code' => 'only_on_a']);

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['attr.only_on_a' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']],
    ])->assertStatus(422);
});
