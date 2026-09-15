<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use App\Services\Assignment\CompetenceLineSetValidator;
use App\Services\ProductLines\ProductLineSetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0129 — three more ways to widen a user's competence beyond
 * UserCompetenceTest's spec 0111 coverage: the profile-wide wildcard flag
 * (D-1/D-2, AC-008..AC-010), a (function, null) row (D-3, AC-011..AC-013), a
 * row on a non-selectable MOTHER category (D-6/D-7, AC-014..AC-016) and the
 * field catalogue's new key (AC-017). Split into its own file to keep
 * UserCompetenceTest under the file-size limit (engineering.md §6).
 */
if (! function_exists('scopeActor')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>|null  $matrixRow
     */
    function scopeActor(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $role = Role::create(['name' => 'competence-scope-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "users.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('scopeTargetWith')) {
    /**
     * A target user whose employment profile already holds one competence row
     * per category, all paired with $function.
     */
    function scopeTargetWith(?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $target = User::factory()->create();
        $profile = EmploymentProfile::factory()->for($target);

        if ($function !== null) {
            $profile = $profile->competentIn($function, ...$categories);
        }

        $profile->create();

        return $target;
    }
}

if (! function_exists('scopeCategoryUnder')) {
    function scopeCategoryUnder(BusinessFunction $function): ProductCategory
    {
        return ProductCategory::factory()->create(['business_function_id' => $function->id]);
    }
}

if (! function_exists('scopeRowsOf')) {
    /**
     * @return array<int, array{business_function_id: int, product_category_id: int|null}>
     */
    function scopeRowsOf(User $target): array
    {
        return DB::table('employment_product_lines')
            ->where('employment_profile_id', $target->fresh()->employment?->id)
            ->orderBy('id')
            ->get(['business_function_id', 'product_category_id'])
            ->map(static fn (object $row): array => [
                'business_function_id' => (int) $row->business_function_id,
                'product_category_id' => $row->product_category_id === null ? null : (int) $row->product_category_id,
            ])
            ->all();
    }
}

// ---------------------------------------------------------------------------
// AC-008..AC-010 — the wildcard flag (D-1/D-2).
// ---------------------------------------------------------------------------

it('0129 AC-008: flag true with product_lines: [] persists the flag and clears the 2 existing rows', function () {
    $actor = scopeActor(['view', 'update']);
    $function = BusinessFunction::factory()->create();
    $target = scopeTargetWith($function, scopeCategoryUnder($function), scopeCategoryUnder($function));
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['covers_all_product_categories' => true, 'product_lines' => []],
    ])->assertOk();

    expect(scopeRowsOf($target))->toBe([]);
    $response->assertJsonPath('data.employment.covers_all_product_categories', true)
        ->assertJsonPath('data.employment.product_lines', []);

    $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.covers_all_product_categories', true)
        ->assertJsonPath('data.employment.product_lines', []);
});

it('0129 AC-009: flag true WITHOUT the product_lines key still clears the existing rows (D-2)', function () {
    $actor = scopeActor(['update']);
    $function = BusinessFunction::factory()->create();
    $target = scopeTargetWith($function, scopeCategoryUnder($function));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['covers_all_product_categories' => true],
    ])->assertOk();

    expect(scopeRowsOf($target))->toBe([]);
    expect($target->fresh()->employment->covers_all_product_categories)->toBeTrue();
});

it('0129 AC-010: flag true with a non-empty product_lines is a 422 on employment.product_lines, nothing persisted', function () {
    $actor = scopeActor(['update']);
    $function = BusinessFunction::factory()->create();
    $category = scopeCategoryUnder($function);
    $target = scopeTargetWith($function, $category);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => [
            'covers_all_product_categories' => true,
            'product_lines' => [['business_function_id' => $function->id, 'product_category_id' => $category->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines' => __(CompetenceLineSetValidator::ALL_CATEGORIES_WITH_LINES_MESSAGE),
    ]);

    expect($target->fresh()->employment->covers_all_product_categories)->toBeFalse();
    expect(scopeRowsOf($target))->toBe([
        ['business_function_id' => $function->id, 'product_category_id' => $category->id],
    ]);
});

// ---------------------------------------------------------------------------
// AC-011..AC-013 — a (function, null) row (D-3).
// ---------------------------------------------------------------------------

it('0129 AC-011: a row with a null category is persisted and read back as product_category: null', function () {
    $actor = scopeActor(['view', 'update']);
    $function = BusinessFunction::factory()->create();
    $target = scopeTargetWith();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => null],
        ]],
    ])->assertOk();

    expect(scopeRowsOf($target))->toBe([
        ['business_function_id' => $function->id, 'product_category_id' => null],
    ]);
    $response->assertJsonPath('data.employment.product_lines.0.product_category', null)
        ->assertJsonPath('data.employment.product_lines.0.business_function.id', $function->id);

    $this->getJson("/api/users/{$target->id}")->assertOk()
        ->assertJsonPath('data.employment.product_lines.0.product_category', null);
});

it('0129 AC-012: two (function, null) rows are rejected as a duplicate pair on the second row', function () {
    $actor = scopeActor(['update']);
    $function = BusinessFunction::factory()->create();
    $target = scopeTargetWith();
    Sanctum::actingAs($actor);

    $row = ['business_function_id' => $function->id, 'product_category_id' => null];

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [$row, $row]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.1.product_category_id' => __(ProductLineSetValidator::DUPLICATE_PAIR_MESSAGE),
    ]);

    expect(scopeRowsOf($target))->toBe([]);
});

it('0129 AC-013: (function, null) plus (function, category) is 422 redundancy on the specific row, regardless of order', function () {
    $actor = scopeActor(['update']);
    $function = BusinessFunction::factory()->create();
    $category = scopeCategoryUnder($function);
    $target = scopeTargetWith();
    Sanctum::actingAs($actor);

    $wildcardRow = ['business_function_id' => $function->id, 'product_category_id' => null];
    $specificRow = ['business_function_id' => $function->id, 'product_category_id' => $category->id];

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [$wildcardRow, $specificRow]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.1.product_category_id' => __(CompetenceLineSetValidator::REDUNDANT_LINE_MESSAGE),
    ]);

    // Order swapped: the error still lands on the SPECIFIC row (now index 0),
    // never on the general (function, null) one.
    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [$specificRow, $wildcardRow]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.0.product_category_id' => __(CompetenceLineSetValidator::REDUNDANT_LINE_MESSAGE),
    ]);

    // A different function's category does not collide.
    $otherFunction = BusinessFunction::factory()->create();
    $otherCategory = scopeCategoryUnder($otherFunction);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            $wildcardRow,
            ['business_function_id' => $otherFunction->id, 'product_category_id' => $otherCategory->id],
        ]],
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-014..AC-016 — a row on a non-selectable MOTHER (D-6/D-7/D-8).
// ---------------------------------------------------------------------------

it('0129 AC-014: a row on the MOTHER plus a row on one of its children is 422 redundancy on the CHILD row (D-8)', function () {
    $actor = scopeActor(['update']);
    $function = BusinessFunction::factory()->create();
    $mother = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $mother->id]);
    $target = scopeTargetWith();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $mother->id],
            ['business_function_id' => $function->id, 'product_category_id' => $child->id],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.1.product_category_id' => __(CompetenceLineSetValidator::REDUNDANT_LINE_MESSAGE),
    ]);
});

it('0129 AC-015: a non-selectable MOTHER row passes here, but the same category on an opportunity product line stays 422 (non-regression)', function () {
    $actor = scopeActor(['update']);
    $function = BusinessFunction::factory()->create();
    $mother = ProductCategory::factory()->create([
        'business_function_id' => $function->id,
        'is_selectable' => false,
    ]);
    $target = scopeTargetWith();

    // Both actors' permissions are built BEFORE any Sanctum::actingAs() call:
    // Sanctum::actingAs() mutates auth.defaults.guard for the rest of the
    // test (Auth::shouldUse), and Permission::findOrCreate() without an
    // explicit guard resolves against that same config — creating a
    // permission under the wrong guard once it has been mutated.
    foreach (['opportunities.viewAny', 'opportunities.create'] as $ability) {
        Permission::findOrCreate($ability);
    }
    $opportunityActor = User::factory()->create();
    $opportunityActor->givePermissionTo(['opportunities.viewAny', 'opportunities.create']);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $mother->id],
        ]],
    ])->assertOk();

    expect(scopeRowsOf($target))->toBe([
        ['business_function_id' => $function->id, 'product_category_id' => $mother->id],
    ]);

    // Non-regression: offers/projects/campaigns keep spec 0074's constraint.
    Sanctum::actingAs($opportunityActor);

    $this->postJson('/api/opportunities', [
        'name' => 'Rejected lines',
        'registry_id' => Registry::factory()->create()->id,
        'supervisor_id' => User::factory()->create()->id,
        'product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $mother->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $mother->id])->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');
});

it('0129 AC-016: a NEUTRAL mother (no own function, one child at F) admits F but rejects a mismatching function (D-7)', function () {
    $actor = scopeActor(['update']);
    $matchingFunction = BusinessFunction::factory()->create();
    $mismatchingFunction = BusinessFunction::factory()->create();
    $mother = ProductCategory::factory()->create(['business_function_id' => null]);
    ProductCategory::factory()->create(['parent_id' => $mother->id, 'business_function_id' => $matchingFunction->id]);
    $target = scopeTargetWith();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $matchingFunction->id, 'product_category_id' => $mother->id],
        ]],
    ])->assertOk();

    expect(scopeRowsOf($target))->toBe([
        ['business_function_id' => $matchingFunction->id, 'product_category_id' => $mother->id],
    ]);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $mismatchingFunction->id, 'product_category_id' => $mother->id],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.0.business_function_id' => __(ProductLineSetValidator::BUSINESS_FUNCTION_MISMATCH_MESSAGE),
    ]);
});

// ---------------------------------------------------------------------------
// AC-017 — the field catalogue's new key.
// ---------------------------------------------------------------------------

it('0129 AC-017: the field catalogue exposes employment.covers_all_product_categories (boolean, employment) and 14 employment.* keys', function () {
    // GET /api/authorization/fields is gated on roles.create/roles.update
    // (you manage roles), not on the users.* abilities the rest of this file
    // exercises (FieldCatalogueController::authorizeManagesRoles()).
    Permission::findOrCreate('roles.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.update');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')->assertOk();
    $usersFields = collect($response->json('data.resources'))->firstWhere('resource', 'users')['fields'];
    $byKey = collect($usersFields)->keyBy('key');

    expect($byKey->get('employment.covers_all_product_categories'))
        ->toMatchArray(['type' => 'boolean', 'group' => 'employment']);
    expect($byKey->keys()->filter(fn (string $key): bool => str_starts_with($key, 'employment.')))
        ->toHaveCount(14);
});

it('0129 AC-017: a readonly employment.covers_all_product_categories resubmitting the identical value is a no-op (200)', function () {
    $actor = scopeActor(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'employment.covers_all_product_categories', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $target = User::factory()->withEmployment(fn ($f) => $f->coversAllProductCategories())->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['covers_all_product_categories' => true],
    ])->assertOk();
});
