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

// Category-scoped `attr.<code>` columns on `GET .../columns` and
// `POST .../rows` (spec 0064 §M2: config shape, mapping table, D-2 EXISTS
// scoping, sort/filter allow-list), RESTORED by the user directive
// 2026-08-31 on the Offerta — the record this grid IS since spec 0086 and
// the one "Informazioni aggiuntive" lives on since spec 0084 D-1. Hence the
// `quote` attribute context and `quotes.attribute_values` throughout: the
// grid must show exactly the set the work panel and the Offerte form resolve.
//
// The write path (inline `attr.*` cell edits) lives in
// RequestManagementAttributeWritesTest.php, the date-range filter in
// RequestManagementAttributeDateFilterTest.php — split for the file-size
// budget (engineering.md §6).

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
     * QUOTE context (spec 0084, D-1).
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
            'context' => 'quote',
        ]);

        return [$category, $attribute];
    }
}

if (! function_exists('quoteInCategory')) {
    /**
     * A request row: an Offerta whose Opportunity carries a product line on
     * $category — the classification the category tabs scope by (D-2) — with
     * the given `attribute_values` (never mass-assignable, spec 0084).
     *
     * @param  array<string, mixed>  $attributeValues
     */
    function quoteInCategory(ProductCategory $category, array $attributeValues = []): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'product_category_id' => $category->id,
        ]);

        $quote = Quote::factory()->for($opportunity)->create();

        if ($attributeValues !== []) {
            $quote->forceFill(['attribute_values' => $attributeValues])->save();
        }

        return $quote;
    }
}

// ---------------------------------------------------------------------------
// The "Tutte" tab: zero attr.* columns (D-3)
// ---------------------------------------------------------------------------

it('emits no attr.* column when no category tab is selected', function () {
    attributeCategory('text');
    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $columns = $this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns');

    expect(array_filter($columns, static fn (array $column): bool => str_starts_with($column['id'], 'attr.')))->toBe([]);
});

// ---------------------------------------------------------------------------
// Own + inherited attributes, ordered [sort_order, code], appended last
// ---------------------------------------------------------------------------

it('appends 2 own + 1 inherited attribute as 3 attr.* columns ordered by [sort_order, code]', function () {
    $parent = ProductCategory::factory()->create();
    $parentAttribute = Attribute::factory()->create(['code' => 'inherited_one']);
    $parent->attributes()->attach($parentAttribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $ownA = Attribute::factory()->create(['code' => 'own_b']);
    $ownB = Attribute::factory()->create(['code' => 'own_a']);
    $child->attributes()->attach($ownA->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);
    $child->attributes()->attach($ownB->id, ['is_required' => false, 'sort_order' => 1, 'context' => 'quote']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $columns = $this->getJson("/api/tables/request-management/columns?product_category_id={$child->id}")
        ->assertOk()->json('data.columns');

    $attrColumns = array_values(array_filter($columns, static fn (array $column): bool => str_starts_with($column['id'], 'attr.')));

    expect($attrColumns)->toHaveCount(3)
        ->and(array_column($attrColumns, 'id'))->toBe(['attr.inherited_one', 'attr.own_a', 'attr.own_b']);

    foreach ($attrColumns as $column) {
        expect($column['visible'])->toBeTrue()
            ->and($column['source'])->toBe('attribute');
    }

    $nativeMaxOrder = max(array_column(array_filter($columns, static fn (array $c): bool => ! str_starts_with($c['id'], 'attr.')), 'order'));

    expect(min(array_column($attrColumns, 'order')))->toBeGreaterThan($nativeMaxOrder);
});

it('never shows an attribute assigned in the product or opportunity context', function () {
    $category = ProductCategory::factory()->create();
    $productOnly = Attribute::factory()->create(['code' => 'product_only']);
    $opportunityOnly = Attribute::factory()->create(['code' => 'opportunity_only']);
    $category->attributes()->attach($productOnly->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'product']);
    $category->attributes()->attach($opportunityOnly->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'opportunity']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $ids = array_column(
        $this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")->assertOk()->json('data.columns'),
        'id',
    );

    expect($ids)->not->toContain('attr.product_only')
        ->and($ids)->not->toContain('attr.opportunity_only');
});

// ---------------------------------------------------------------------------
// The frozen type -> (type, filterType, editor) mapping table
// ---------------------------------------------------------------------------

it('maps an attribute type to the frozen (type, filterType, editor) triad', function (
    string $attrType,
    array $config,
    ?array $relationTarget,
    string $expectedType,
    string $expectedFilterType,
    ?string $expectedEditor,
) {
    $category = ProductCategory::factory()->create();

    $attribute = $attrType === 'enum'
        ? Attribute::factory()->enum(2)->create(['config' => $config])
        : Attribute::factory()->ofType($attrType)->create(['config' => $config, 'relation_target' => $relationTarget]);

    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $column = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id')["attr.{$attribute->code}"];

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
    'date' => ['date', [], null, 'datetime', 'date', 'date'],
    'datetime' => ['datetime', [], null, 'datetime', 'date', 'datetime'],
]);

it('refuses a nonexistent product_category_id', function () {
    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $this->getJson('/api/tables/request-management/columns?product_category_id=9999')->assertStatus(422);
});

// ---------------------------------------------------------------------------
// D-2: EXISTS on the opportunity's product lines
// ---------------------------------------------------------------------------

it('scopes rows to the tab category, showing a two-category request in both tabs', function () {
    [$categoryA] = attributeCategory('text');
    $categoryB = ProductCategory::factory()->create();

    $onlyA = quoteInCategory($categoryA);
    $onlyB = quoteInCategory($categoryB);

    $both = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create(['opportunity_id' => $both->id, 'product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $both->id, 'product_category_id' => $categoryB->id]);
    $bothQuote = Quote::factory()->for($both)->create();

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $idsA = array_column($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryA->id,
    ])->assertOk()->json('items'), 'id');

    expect($idsA)->toContain($onlyA->id)
        ->and($idsA)->toContain($bothQuote->id)
        ->and($idsA)->not->toContain($onlyB->id);

    $idsB = array_column($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryB->id,
    ])->assertOk()->json('items'), 'id');

    expect($idsB)->toContain($onlyB->id)
        ->and($idsB)->toContain($bothQuote->id)
        ->and($idsB)->not->toContain($onlyA->id);
});

// ---------------------------------------------------------------------------
// Row payload, sort and filters over the JSON column
// ---------------------------------------------------------------------------

it('carries attr.<code> from the OFFER attribute_values, null when unset', function () {
    [$category] = attributeCategory('integer', ['code' => 'durata_corso']);

    $withValue = quoteInCategory($category, ['durata_corso' => 120]);
    $withoutValue = quoteInCategory($category);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $rows = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
    ])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$withValue->id]['attr.durata_corso'])->toBe(120)
        ->and($rows[$withoutValue->id]['attr.durata_corso'])->toBeNull();
});

it('sorts rows by an attr.<code> JSON value', function () {
    [$category] = attributeCategory('integer', ['code' => 'durata_corso']);

    $high = quoteInCategory($category, ['durata_corso' => 300]);
    $low = quoteInCategory($category, ['durata_corso' => 10]);
    $mid = quoteInCategory($category, ['durata_corso' => 100]);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $rows = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'sortModel' => [['colId' => 'attr.durata_corso', 'sort' => 'asc']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$low->id, $mid->id, $high->id]);
});

it('filters an attr.<code> text column by contains', function () {
    [$category] = attributeCategory('text', ['code' => 'note_corso']);

    $matching = quoteInCategory($category, ['note_corso' => 'Corso Serale']);
    $other = quoteInCategory($category, ['note_corso' => 'Altro']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $ids = array_column($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.note_corso' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Serale']],
    ])->assertOk()->json('items'), 'id');

    expect($ids)->toBe([$matching->id])
        ->and($ids)->not->toContain($other->id);
});

it('filters an attr.<code> number column by equals', function () {
    [$category] = attributeCategory('integer', ['code' => 'durata_corso']);

    $matching = quoteInCategory($category, ['durata_corso' => 50]);
    quoteInCategory($category, ['durata_corso' => 99]);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $ids = array_column($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.durata_corso' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 50]],
    ])->assertOk()->json('items'), 'id');

    expect($ids)->toBe([$matching->id]);
});

it('filters an attr.<code> enum column by set membership', function () {
    [$category, $attribute] = attributeCategory('enum', ['code' => 'stato_corso']);
    $option = $attribute->options()->first();

    $matching = quoteInCategory($category, ['stato_corso' => $option->value]);
    quoteInCategory($category, ['stato_corso' => 'zzz-not-in-set']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $ids = array_column($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
        'filterModel' => ['attr.stato_corso' => ['filterType' => 'set', 'values' => [$option->value]]],
    ])->assertOk()->json('items'), 'id');

    expect($ids)->toBe([$matching->id]);
});

it('serves the distinct values of an attr.<code> column', function () {
    [$category] = attributeCategory('text', ['code' => 'note_corso']);
    quoteInCategory($category, ['note_corso' => 'Corso Serale']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $values = $this->postJson('/api/tables/request-management/values', [
        'columnId' => 'attr.note_corso',
        'productCategoryId' => $category->id,
    ])->assertOk()->json('data.values');

    expect($values)->toContain('Corso Serale');
});

// ---------------------------------------------------------------------------
// The SSRM allow-list: an out-of-scope attr.* id is a 422, never a silent pass
// ---------------------------------------------------------------------------

it('refuses a sortModel colId outside the requested category', function () {
    attributeCategory('text', ['code' => 'only_on_a']);
    $categoryB = ProductCategory::factory()->create();

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $categoryB->id,
        'sortModel' => [['colId' => 'attr.only_on_a', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('refuses an attr.<code> filterModel key sent with no category scope', function () {
    attributeCategory('text', ['code' => 'only_on_a']);

    Sanctum::actingAs(attributeColumnsUserWith(['viewAny', 'viewAll']));

    $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['attr.only_on_a' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']],
    ])->assertStatus(422);
});
