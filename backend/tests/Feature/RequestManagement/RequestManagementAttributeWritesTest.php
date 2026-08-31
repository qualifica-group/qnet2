<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The `attr.<code>` WRITE path (spec 0064 §M4, restored by the user directive
// 2026-08-31 on the Offerta): inline `PATCH .../rows/{row}`, its
// `role_field_permissions` gate, and the preferences/filters allow-list union
// that must never 422 depending on which category tab was open when the
// client saved. Split out of RequestManagementAttributeColumnsTest.php for
// the file-size budget (engineering.md §6).
//
// Every write goes through RequestManagementService::updateWork() ->
// QuoteAttributeValueWriter, the SAME choke point the work panel reaches, so
// validation/normalization/merge can never diverge between the two channels.

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
     * QUOTE context (spec 0084, D-1).
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
            'context' => 'quote',
        ]);

        return [$category, $attribute];
    }
}

if (! function_exists('attributeWritesQuoteInCategory')) {
    /**
     * @param  array<string, mixed>  $attributeValues
     */
    function attributeWritesQuoteInCategory(ProductCategory $category, array $attributeValues = []): Quote
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
// The inline write: sparse merge, never a whole-map replace
// ---------------------------------------------------------------------------

it('persists an attr.<code> value and leaves the other codes untouched', function () {
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $quote = attributeWritesQuoteInCategory($category, ['other_code' => 'kept']);

    Sanctum::actingAs(attributeWritesUserWith(['viewAny', 'update', 'viewAll']));

    $response = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'attr.durata_corso',
        'value' => 90,
    ])->assertOk();

    // Dot-notation lookup would misparse a key that itself contains a dot
    // (`attr.durata_corso`) as nested `attr` -> `durata_corso` — read the
    // `data` array directly instead.
    expect($response->json('data')['attr.durata_corso'])->toBe(90);

    $fresh = $quote->fresh();

    expect($fresh->attribute_values['durata_corso'])->toBe(90)
        ->and($fresh->attribute_values['other_code'])->toBe('kept');
});

it('refuses a value invalid for the attribute type and leaves the row unmodified', function () {
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $quote = attributeWritesQuoteInCategory($category, ['durata_corso' => 5]);

    Sanctum::actingAs(attributeWritesUserWith(['viewAny', 'update', 'viewAll']));

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'attr.durata_corso',
        'value' => 'not-a-number',
    ])->assertStatus(422);

    expect($quote->fresh()->attribute_values['durata_corso'])->toBe(5);
});

it('refuses an enum option outside the attribute catalogue', function () {
    [$category] = attributeWritesCategory('enum', ['code' => 'stato_corso']);
    $quote = attributeWritesQuoteInCategory($category);

    Sanctum::actingAs(attributeWritesUserWith(['viewAny', 'update', 'viewAll']));

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'attr.stato_corso',
        'value' => 'not-a-catalogued-option',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// The role_field_permissions gate on the single `attribute_values` key
// ---------------------------------------------------------------------------

it('refuses the write when the role matrix marks attribute_values non-editable', function () {
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $quote = attributeWritesQuoteInCategory($category);

    Sanctum::actingAs(attributeWritesActorWithMatrixRow(editable: false));

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'attr.durata_corso',
        'value' => 10,
    ])->assertForbidden();

    expect($quote->fresh()->attribute_values)->toBeNull();
});

it('lets the write through when the role matrix marks attribute_values editable', function () {
    [$category] = attributeWritesCategory('integer', ['code' => 'durata_corso']);
    $quote = attributeWritesQuoteInCategory($category);

    Sanctum::actingAs(attributeWritesActorWithMatrixRow(editable: true));

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'attr.durata_corso',
        'value' => 10,
    ])->assertOk();

    expect($quote->fresh()->attribute_values['durata_corso'])->toBe(10);
});

// ---------------------------------------------------------------------------
// D-4: the persistence allow-list is the UNION across every category
// ---------------------------------------------------------------------------

it('saves column preferences naming an attr.<code> of any category', function () {
    attributeWritesCategory('text', ['code' => 'field_a']);
    attributeWritesCategory('integer', ['code' => 'field_b']);

    Sanctum::actingAs(attributeWritesUserWith(['viewAny', 'viewAll']));

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [
            ['id' => 'attr.field_a', 'visible' => true, 'order' => 1],
            ['id' => 'attr.field_b', 'visible' => false, 'order' => 2],
        ],
    ])->assertOk();
});

it('saves filter state naming an attr.<code> of any category', function () {
    attributeWritesCategory('text', ['code' => 'field_a']);

    Sanctum::actingAs(attributeWritesUserWith(['viewAny', 'viewAll']));

    $this->postJson('/api/tables/request-management/filters', [
        'filterModel' => ['attr.field_a' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']],
    ])->assertOk();
});
