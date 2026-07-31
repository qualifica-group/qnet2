<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
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
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/product-categories/for-select')->assertUnauthorized();
});

it('allows actors without product-categories.viewAny (200 — ADR 0011 amended)', function () {
    $actor = productCategoryUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select')->assertOk();
});

it('allows actors with product-categories.viewAny (200) and returns the paginated envelope', function () {
    $actor = productCategoryUserWith(['viewAny']);
    ProductCategory::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + search
// ---------------------------------------------------------------------------

it('maps a product category to { id, label: name, meta }', function () {
    // Requirement changed by spec 0040 BR-4: every item now unconditionally
    // carries `meta` (effective business function) — see the AC-053 block
    // below for its shape/null cases.
    $actor = productCategoryUserWith(['viewAny']);
    $target = ProductCategory::factory()->create(['name' => 'Office Supplies']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Office Supplies')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Office Supplies'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('searches by name', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $match = ProductCategory::factory()->create(['name' => 'Alphonse Target']);
    ProductCategory::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $searchMatch = ProductCategory::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = ProductCategory::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

// ---------------------------------------------------------------------------
// AC-053 — meta.business_function (spec 0040 BR-4, own-or-inherited)
// ---------------------------------------------------------------------------

it('exposes meta.business_function from the category\'s OWN assignment', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $target = ProductCategory::factory()->create(['name' => 'Own Function', 'business_function_id' => $function->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Own Function')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray([
        'business_function' => ['id' => $function->id, 'name' => 'Sales'],
    ]);
});

it('exposes meta.business_function INHERITED from an ancestor', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Marketing']);
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $target = ProductCategory::factory()->childOf($parent)->create(['name' => 'Inherited Function']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Inherited Function')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray([
        'business_function' => ['id' => $function->id, 'name' => 'Marketing'],
    ]);
});

it('exposes meta.business_function as null when neither own nor inherited', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $target = ProductCategory::factory()->create(['name' => 'No Function']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=No Function')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toMatchArray(['business_function' => null]);
});

// ---------------------------------------------------------------------------
// AC-104 — business_function_id param (spec 0040 amendment rev.3): additive,
// scopes results to categories whose EFFECTIVE business function matches
// ---------------------------------------------------------------------------

it('business_function_id filters to categories whose OWN business function matches (AC-104)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $matching = ProductCategory::factory()->create(['name' => 'Matching Own', 'business_function_id' => $function->id]);
    ProductCategory::factory()->create(['name' => 'Other Own', 'business_function_id' => $otherFunction->id]);
    ProductCategory::factory()->create(['name' => 'No Function']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/for-select?business_function_id={$function->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toBe([$matching->id]);
});

it('business_function_id filters to categories whose INHERITED business function matches (AC-104)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($parent)->create(['name' => 'Inherited Child']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/for-select?business_function_id={$function->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($parent->id)
        ->and($ids)->toContain($child->id);
});

it('without business_function_id, the behaviour is IDENTICAL to before (retrocompatible, AC-104)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    ProductCategory::factory()->create(['name' => 'Alpha']);
    ProductCategory::factory()->create(['name' => 'Beta', 'business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select')->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

it('an invalid business_function_id -> 422 (exists)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select?business_function_id=999999')
        ->assertStatus(422)->assertJsonValidationErrors('business_function_id');
});
