<?php

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductCategories\CategoryManagerLabelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0080: per-position "Gestore Account" label overrides on
// product_categories — round-trip, normalization, key validation, field
// permission, barrier inheritance and the effective-manager-labels endpoint.

uses(RefreshDatabase::class);

if (! function_exists('managerLabelUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function managerLabelUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Configuration round-trip / normalization (AC-001, AC-002)
// ---------------------------------------------------------------------------

it('AC-001: create with manager_labels -> 201, rereads identical', function (): void {
    $actor = managerLabelUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', [
        'name' => 'Elettronica',
        'manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore'],
    ])->assertCreated()
        ->assertJsonPath('data.manager_labels.1', 'Commerciale')
        ->assertJsonPath('data.manager_labels.2', 'Operatore');

    $category = ProductCategory::where('name', 'Elettronica')->firstOrFail();

    $this->getJson("/api/product-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels.1', 'Commerciale')
        ->assertJsonPath('data.manager_labels.2', 'Operatore');
});

it('AC-002: an empty or whitespace-only label is removed, not saved as ""', function (): void {
    $actor = managerLabelUserWith(['update']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$category->id}", [
        'manager_labels' => ['1' => 'Commerciale', '2' => '   '],
    ])->assertOk()
        ->assertJsonPath('data.manager_labels.1', 'Commerciale')
        ->assertJsonMissingPath('data.manager_labels.2');

    expect($category->fresh()->manager_labels)->toBe(['1' => 'Commerciale']);
});

it('AC-002: an object that normalizes to fully empty saves as null', function (): void {
    $actor = managerLabelUserWith(['update']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$category->id}", [
        'manager_labels' => ['1' => '   '],
    ])->assertOk()->assertJsonPath('data.manager_labels', []);

    expect($category->fresh()->manager_labels)->toBeNull();
});

// ---------------------------------------------------------------------------
// Key validation (AC-003) / length validation (AC-004)
// ---------------------------------------------------------------------------

it('AC-003/AC-051: a key of "0", "13" (beyond the spec 0080 amendment A1 cap of 12) or non-numeric -> 422, no write', function (): void {
    $actor = managerLabelUserWith(['create']);
    Sanctum::actingAs($actor);

    foreach (['0' => 'x', '13' => 'x', 'abc' => 'x'] as $key => $label) {
        $this->postJson('/api/product-categories', [
            'name' => "Invalid-{$key}",
            'manager_labels' => [$key => $label],
        ])->assertStatus(422)->assertJsonValidationErrors("manager_labels.{$key}");

        expect(ProductCategory::where('name', "Invalid-{$key}")->exists())->toBeFalse();
    }
});

it('AC-050/AC-051: positions beyond the 4th (up to 12, the cap) are accepted, saved and reread', function (): void {
    $actor = managerLabelUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/product-categories', [
        'name' => 'Beyond Four',
        'manager_labels' => ['5' => 'GA Cinque', '12' => 'GA Dodici'],
    ])->assertCreated()
        ->assertJsonPath('data.manager_labels.5', 'GA Cinque')
        ->assertJsonPath('data.manager_labels.12', 'GA Dodici');

    $category = ProductCategory::where('name', 'Beyond Four')->firstOrFail();

    $this->getJson("/api/product-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.manager_labels.5', 'GA Cinque')
        ->assertJsonPath('data.manager_labels.12', 'GA Dodici');
});

it('AC-004: a label over 60 characters -> 422', function (): void {
    $actor = managerLabelUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', [
        'name' => 'TooLong',
        'manager_labels' => ['1' => str_repeat('a', 61)],
    ])->assertStatus(422)->assertJsonValidationErrors('manager_labels.1');
});

// ---------------------------------------------------------------------------
// Authorization (AC-005)
// ---------------------------------------------------------------------------

it('AC-005: 403 without product-categories.update', function (): void {
    $actor = managerLabelUserWith([]);
    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['manager_labels' => ['1' => 'X']])
        ->assertForbidden();
});

it('AC-005: manager_labels editable:false for the actor\'s role -> 422 "field not editable", no write', function (): void {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("product-categories.{$ability}");
    }

    $role = Role::create(['name' => 'category-manager-labels-locked']);
    $role->givePermissionTo(['product-categories.view', 'product-categories.update']);
    $role->fieldPermissions()->create([
        'resource' => 'product-categories',
        'field' => 'manager_labels',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['manager_labels' => ['1' => 'X']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_labels');

    expect($target->fresh()->manager_labels)->toBeNull();
});

// ---------------------------------------------------------------------------
// Non-interference with other fields (AC-006)
// ---------------------------------------------------------------------------

it('AC-006: saving manager_labels does not alter attributes, inherits_*_attributes, requires_quote, management_mode or is_selectable', function (): void {
    $actor = managerLabelUserWith(['update']);
    $attribute = Attribute::factory()->create();
    $category = ProductCategory::factory()->create([
        'inherits_product_attributes' => false,
        'inherits_quote_attributes' => false,
        'requires_quote' => true,
        'is_selectable' => false,
    ]);
    $category->attributes()->attach($attribute->id, ['is_required' => true, 'sort_order' => 3, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$category->id}", [
        'manager_labels' => ['1' => 'Commerciale'],
    ])->assertOk();

    $fresh = $category->fresh();
    expect($fresh->inherits_product_attributes)->toBeFalse()
        ->and($fresh->inherits_quote_attributes)->toBeFalse()
        ->and($fresh->requires_quote)->toBeTrue()
        ->and($fresh->management_mode->value)->toBe('multiple')
        ->and($fresh->is_selectable)->toBeFalse();

    expect(DB::table('attribute_category')->where('category_id', $category->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Inheritance (AC-010..AC-013) + endpoint (AC-014), via
// GET .../effective-manager-labels
// ---------------------------------------------------------------------------

it('AC-010: inherits=true and no own labels -> effective = the parent\'s', function (): void {
    $actor = managerLabelUserWith(['view']);
    $root = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    $child = ProductCategory::factory()->childOf($root)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$child->id}/effective-manager-labels")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['1' => 'Commerciale', '2' => 'Operatore']);
});

it('AC-011: granular merge — a child overriding only GA2 still inherits GA1', function (): void {
    $actor = managerLabelUserWith(['view']);
    $root = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    $child = ProductCategory::factory()->childOf($root)->create(['manager_labels' => ['2' => 'Consulente']]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$child->id}/effective-manager-labels")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['1' => 'Commerciale', '2' => 'Consulente']);
});

it('pinning: effectiveManagerLabels() always returns keys in ascending order, regardless of merge insertion order', function (): void {
    // The merge walks ancestors ROOT-FIRST then self: here the ANCESTOR
    // contributes position 2 (inserted first) and the category's OWN row
    // contributes position 1 (inserted second) — insertion order is [2, 1].
    // OpportunityManagerLabelResolver's uniqueness check (===) relies on
    // every resolved array coming back key-ordered regardless of this, so
    // this pins the invariant where it is PRODUCED, not just consumed.
    $root = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore']]);
    $child = ProductCategory::factory()->childOf($root)->create(['manager_labels' => ['1' => 'Commerciale']]);

    // Refresh: unlike route-model-binding (always a fresh SELECT), the
    // in-memory model straight out of create() never re-read the DB-default
    // `inherits_manager_labels` column — calling the resolver on it directly
    // would wrongly read it as falsy and treat the child as opted out.
    $result = app(CategoryManagerLabelResolver::class)->effectiveManagerLabels($child->refresh());

    expect(array_keys($result))->toBe([1, 2])
        ->and($result)->toBe(['1' => 'Commerciale', '2' => 'Operatore']);
});

it('AC-012: inherits_manager_labels=false is a barrier — own labels only, and it stops grandchildren too', function (): void {
    $actor = managerLabelUserWith(['view']);
    $root = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale']]);
    $child = ProductCategory::factory()->childOf($root)->create([
        'manager_labels' => ['2' => 'Operatore'],
        'inherits_manager_labels' => false,
    ]);
    $grandchild = ProductCategory::factory()->childOf($child)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$child->id}/effective-manager-labels")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['2' => 'Operatore']);

    // The grandchild inherits from $child (its own labels count) but the
    // climb stops there — $root's GA1 never reaches it.
    $this->getJson("/api/product-categories/{$grandchild->id}/effective-manager-labels")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['2' => 'Operatore']);
});

it('AC-013: a 3-level chain without barriers — the most specific wins per position', function (): void {
    $actor = managerLabelUserWith(['view']);
    $grandparent = ProductCategory::factory()->create(['manager_labels' => ['1' => 'A']]);
    $parent = ProductCategory::factory()->childOf($grandparent)->create(['manager_labels' => ['1' => 'B']]);
    $child = ProductCategory::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$child->id}/effective-manager-labels")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['1' => 'B']);
});

it('AC-014: effective-manager-labels — 403 without any of the permissive abilities', function (): void {
    $category = ProductCategory::factory()->create();
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-manager-labels")->assertForbidden();
});

it('AC-014: effective-manager-labels — 404 for a non-existent category', function (): void {
    $actor = managerLabelUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/999999/effective-manager-labels')->assertNotFound();
});

it('AC-014: effective-manager-labels — allowed via products.view even without product-categories.view', function (): void {
    Permission::findOrCreate('products.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('products.view');
    $category = ProductCategory::factory()->create(['manager_labels' => ['3' => 'Tutor']]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/product-categories/{$category->id}/effective-manager-labels")
        ->assertOk()
        ->assertJsonPath('data.manager_labels', ['3' => 'Tutor']);
});

// ---------------------------------------------------------------------------
// show — inherited_manager_labels (excludes own)
// ---------------------------------------------------------------------------

it('show: inherited_manager_labels carries the ancestors\' own resolved labels, own ones stay separate', function (): void {
    $actor = managerLabelUserWith(['view']);
    $root = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale']]);
    $child = ProductCategory::factory()->childOf($root)->create(['manager_labels' => ['2' => 'Consulente']]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$child->id}")->assertOk();

    expect($response->json('data.manager_labels'))->toBe(['2' => 'Consulente'])
        ->and($response->json('data.inherited_manager_labels'))->toBe(['1' => 'Commerciale']);
});

it('show: inherited_manager_labels is the ancestor\'s FULL own set even on a position the child overrides', function (): void {
    $actor = managerLabelUserWith(['view']);
    $root = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale', '2' => 'Operatore']]);
    $child = ProductCategory::factory()->childOf($root)->create(['manager_labels' => ['2' => 'Consulente']]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$child->id}")->assertOk();

    // Own (effective, including the override) vs inherited (the root's OWN
    // set, unaffected by the child's override) are two DISTINCT read-only
    // views — mirrors inherited_attributes' pre-existing behavior.
    expect($response->json('data.manager_labels'))->toBe(['2' => 'Consulente'])
        ->and($response->json('data.inherited_manager_labels'))->toBe(['1' => 'Commerciale', '2' => 'Operatore']);
});

// ---------------------------------------------------------------------------
// AC-054 — removing a label only touches the denomination, never a pivot row
// ---------------------------------------------------------------------------

it('AC-054: removing a manager_labels position resets its denomination and never touches assigned managers', function (): void {
    $actor = managerLabelUserWith(['update']);
    $category = ProductCategory::factory()->create([
        'manager_labels' => ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '5' => 'E', '6' => 'F'],
    ]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $managers = User::factory()->count(6)->create();
    $opportunity->managers()->sync(
        $managers->mapWithKeys(fn (User $manager, int $index): array => [$manager->id => ['position' => $index + 1]])->all(),
    );
    $pivotBefore = DB::table('opportunity_user')->where('opportunity_id', $opportunity->id)
        ->orderBy('position')->get()->map(fn (object $row): array => (array) $row)->all();

    Sanctum::actingAs($actor);

    // Drop position 5's label only — every other position resubmitted as-is.
    $this->patchJson("/api/product-categories/{$category->id}", [
        'manager_labels' => ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D', '6' => 'F'],
    ])->assertOk()
        ->assertJsonMissingPath('data.manager_labels.5')
        ->assertJsonPath('data.manager_labels.6', 'F');

    $pivotAfter = DB::table('opportunity_user')->where('opportunity_id', $opportunity->id)
        ->orderBy('position')->get()->map(fn (object $row): array => (array) $row)->all();

    expect($pivotAfter)->toBe($pivotBefore)
        ->and($opportunity->fresh()->managers)->toHaveCount(6);
});
