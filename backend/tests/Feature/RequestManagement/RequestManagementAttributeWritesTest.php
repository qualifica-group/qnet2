<?php

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0064 (docs/specs/0064-request-management-category-tabs.xml), §M2/§M4:
// `POST .../values` (AC-014), `PATCH .../rows/{row}` inline edit of
// `attr.<code>` (AC-015..017) and the preferences/filters allow-list union
// (AC-018). Split out of RequestManagementAttributeColumnsTest.php for the
// file-size budget (engineering.md §6).

uses(RefreshDatabase::class);

if (! function_exists('attributeWritesUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeWritesUserWith(array $abilities): User
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

if (! function_exists('attributeWritesActorWithMatrixRow')) {
    /**
     * A role-bearing actor carrying one role_field_permissions row for the
     * `attribute_values` field — mirrors RequestManagementInlineEditorsTest's
     * own helper (the DB matrix only ever restricts actors reached via role).
     */
    function attributeWritesActorWithMatrixRow(bool $editable): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'attr-writes-role-'.uniqid()]);
        $role->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
        $role->fieldPermissions()->create([
            'resource' => 'request-management',
            'field' => 'attribute_values',
            'visible' => true,
            'editable' => $editable,
            'required' => false,
        ]);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('attributeWritesCategory')) {
    /**
     * A fresh ProductCategory with one attribute of $type attached in the
     * Opportunity context.
     *
     * @param  array<string, mixed>  $attributeOverrides
     * @return array{0: ProductCategory, 1: Attribute}
     */
    function attributeWritesCategory(string $type, array $attributeOverrides = []): array
    {
        $category = ProductCategory::factory()->create();
        $attribute = $type === 'enum'
            ? Attribute::factory()->enum(2)->create($attributeOverrides)
            : Attribute::factory()->ofType($type)->create($attributeOverrides);

        $category->attributes()->attach($attribute->id, [
            'is_required' => false,
            'sort_order' => 0,
            'context' => 'opportunity',
        ]);

        return [$category, $attribute];
    }
}

if (! function_exists('attributeWritesOpportunityInCategory')) {
    function attributeWritesOpportunityInCategory(ProductCategory $category, array $attributes = []): Opportunity
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
// AC-014 — POST /values for an enum attr.<code>
// ---------------------------------------------------------------------------

it('AC-014: POST /values with columnId attr.<code> (enum) and the matching category returns distinct values', function () {
    $actor = attributeWritesUserWith(['viewAny', 'viewAll']);
    [$category, $attribute] = attributeWritesCategory('enum', ['code' => 'stato_corso']);
    $option = $attribute->options()->first();

    attributeWritesOpportunityInCategory($category, ['attribute_values' => ['stato_corso' => $option->value]]);

    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/request-management/values', [
        'columnId' => 'attr.stato_corso',
        'productCategoryId' => $category->id,
    ])->assertOk()->json('data.values');

    expect($values)->toContain($option->value);
});

it('AC-014: POST /values with columnId attr.<code> and no productCategoryId returns 422', function () {
    $actor = attributeWritesUserWith(['viewAny', 'viewAll']);
    attributeWritesCategory('enum', ['code' => 'stato_corso']);

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/values', [
        'columnId' => 'attr.stato_corso',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-015 — PATCH attr.<code> persists, other codes untouched
// ---------------------------------------------------------------------------

it('AC-015: PATCH column attr.<code> persists the value and leaves other codes untouched', function () {
    $actor = attributeWritesUserWith(['viewAny', 'update', 'viewAll']);
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $opportunity = attributeWritesOpportunityInCategory($category, ['attribute_values' => ['other_code' => 'kept']]);

    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'attr.durata_corso',
        'value' => 90,
    ])->assertOk();

    // Dot-notation lookup would misparse a key that itself contains a dot
    // (`attr.durata_corso`) as nested `attr` -> `durata_corso` — read the
    // `data` array directly instead.
    expect($response->json('data')['attr.durata_corso'])->toBe(90);

    $fresh = $opportunity->fresh();
    expect($fresh->attribute_values['durata_corso'])->toBe(90)
        ->and($fresh->attribute_values['other_code'])->toBe('kept');
});

// ---------------------------------------------------------------------------
// AC-016 — invalid value -> 422, row unmodified
// ---------------------------------------------------------------------------

it('AC-016: PATCH attr.<code> with a value invalid for the type returns 422 and leaves the row unmodified', function () {
    $actor = attributeWritesUserWith(['viewAny', 'update', 'viewAll']);
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $opportunity = attributeWritesOpportunityInCategory($category, ['attribute_values' => ['durata_corso' => 5]]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'attr.durata_corso',
        'value' => 'not-a-number',
    ])->assertStatus(422);

    expect($opportunity->fresh()->attribute_values['durata_corso'])->toBe(5);
});

it('AC-016: PATCH attr.<code> (enum) with an option outside the catalogue returns 422', function () {
    $actor = attributeWritesUserWith(['viewAny', 'update', 'viewAll']);
    [$category] = attributeWritesCategory('enum', ['code' => 'stato_corso']);
    $opportunity = attributeWritesOpportunityInCategory($category);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'attr.stato_corso',
        'value' => 'not-a-catalogued-option',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-017 — attribute_values not editable in role_field_permissions -> 403
// ---------------------------------------------------------------------------

it('AC-017: PATCH on an attr.<code> column by an actor whose role has attribute_values non-editable returns 403', function () {
    $actor = attributeWritesActorWithMatrixRow(editable: false);
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $opportunity = attributeWritesOpportunityInCategory($category);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'attr.durata_corso',
        'value' => 10,
    ])->assertForbidden();
});

it('editable attribute_values field permission allows the PATCH through', function () {
    $actor = attributeWritesActorWithMatrixRow(editable: true);
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $opportunity = attributeWritesOpportunityInCategory($category);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'attr.durata_corso',
        'value' => 10,
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-018 — preferences/filters save includes attr.* of any category -> never 422
// ---------------------------------------------------------------------------

it('AC-018: saving column preferences including an attr.<code> of any category returns 200 (no allow-list 422)', function () {
    $actor = attributeWritesUserWith(['viewAny', 'viewAll']);
    attributeWritesCategory('text', ['code' => 'field_a']);
    attributeWritesCategory('integer', ['code' => 'field_b']);

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [
            ['id' => 'attr.field_a', 'visible' => true, 'order' => 1],
            ['id' => 'attr.field_b', 'visible' => false, 'order' => 2],
        ],
    ])->assertOk();
});

it('AC-018 (filters twin): saving filter state including an attr.<code> of any category returns 200', function () {
    $actor = attributeWritesUserWith(['viewAny', 'viewAll']);
    attributeWritesCategory('text', ['code' => 'field_a']);

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/filters', [
        'filterModel' => ['attr.field_a' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']],
    ])->assertOk();
});
