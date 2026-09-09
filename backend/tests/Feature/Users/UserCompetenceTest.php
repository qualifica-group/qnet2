<?php

use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0110 — the assignment competence on the employment profile: the
 * `employment_profile_product_category` pivot, its tri-state wire semantics
 * and its field-permission key. AC-001..AC-005.
 */
if (! function_exists('competenceActor')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>|null  $matrixRow
     */
    function competenceActor(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $role = Role::create(['name' => 'competence-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "users.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('competenceTargetWith')) {
    /**
     * A target user whose employment profile already holds the given
     * competence categories.
     */
    function competenceTargetWith(ProductCategory ...$categories): User
    {
        $target = User::factory()->create();
        EmploymentProfile::factory()->for($target)->competentIn(...$categories)->create();

        return $target;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — the submitted set is persisted on the pivot and read back.
// ---------------------------------------------------------------------------

it('0110 AC-001: submitting product_category_ids persists exactly those pivot rows and echoes them back', function () {
    $actor = competenceActor(['update']);
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();
    $target = competenceTargetWith();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_category_ids' => [$categoryA->id, $categoryB->id]],
    ])->assertOk();

    $profileId = $target->fresh()->employment->id;

    expect(DB::table('employment_profile_product_category')->where('employment_profile_id', $profileId)->count())->toBe(2);
    foreach ([$categoryA, $categoryB] as $category) {
        $this->assertDatabaseHas('employment_profile_product_category', [
            'employment_profile_id' => $profileId,
            'product_category_id' => $category->id,
        ]);
    }

    expect($response->json('data.employment.product_category_ids'))->toEqualCanonicalizing([$categoryA->id, $categoryB->id]);
});

// ---------------------------------------------------------------------------
// AC-002 — key absent leaves the pivot untouched (tri-state).
// ---------------------------------------------------------------------------

it('0110 AC-002: omitting product_category_ids leaves the existing competence untouched', function () {
    $actor = competenceActor(['update']);
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();
    $target = competenceTargetWith($categoryA, $categoryB);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['job_description' => 'Untouched by this write'],
    ])->assertOk();

    $profileId = $target->fresh()->employment->id;

    expect(DB::table('employment_profile_product_category')->where('employment_profile_id', $profileId)->pluck('product_category_id')->all())
        ->toEqualCanonicalizing([$categoryA->id, $categoryB->id]);
});

// ---------------------------------------------------------------------------
// AC-003 — an explicit empty array clears the pivot.
// ---------------------------------------------------------------------------

it('0110 AC-003: submitting an empty product_category_ids array clears the competence', function () {
    $actor = competenceActor(['update']);
    $category = ProductCategory::factory()->create();
    $target = competenceTargetWith($category);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_category_ids' => []],
    ])->assertOk();

    $profileId = $target->fresh()->employment->id;

    expect(DB::table('employment_profile_product_category')->where('employment_profile_id', $profileId)->count())->toBe(0);
    expect($response->json('data.employment.product_category_ids'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-004 — both sides of the pivot cascade on delete.
// ---------------------------------------------------------------------------

it('0110 AC-004: deleting the category drops its competence rows and leaves the others alone', function () {
    $doomed = ProductCategory::factory()->create();
    $kept = ProductCategory::factory()->create();
    $target = competenceTargetWith($doomed, $kept);
    $profileId = $target->employment->id;

    $doomed->delete();

    expect(DB::table('employment_profile_product_category')->where('employment_profile_id', $profileId)->pluck('product_category_id')->all())
        ->toBe([$kept->id]);
});

it('0110 AC-004: deleting the employment profile drops its competence rows', function () {
    $category = ProductCategory::factory()->create();
    $target = competenceTargetWith($category);
    $profileId = $target->employment->id;

    $target->employment->delete();

    expect(DB::table('employment_profile_product_category')->where('employment_profile_id', $profileId)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-005 — the field-permission matrix governs the new key.
// ---------------------------------------------------------------------------

it('0110 AC-005: a CHANGED competence on a non-editable field is rejected (422) and nothing is written', function () {
    $actor = competenceActor(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'employment.product_category_ids', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $current = ProductCategory::factory()->create();
    $other = ProductCategory::factory()->create();
    $target = competenceTargetWith($current);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_category_ids' => [$other->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('employment.product_category_ids');

    expect(DB::table('employment_profile_product_category')->where('employment_profile_id', $target->employment->id)->pluck('product_category_id')->all())
        ->toBe([$current->id]);
});

it('0110 AC-005: re-submitting the SAME competence on a non-editable field is a no-op (200)', function () {
    $actor = competenceActor(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'employment.product_category_ids', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();
    $target = competenceTargetWith($categoryA, $categoryB);
    Sanctum::actingAs($actor);

    // Deliberately reversed: the permission check canonicalizes the array, so
    // order must not read as a change.
    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_category_ids' => [$categoryB->id, $categoryA->id]],
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// Validation guards on the new key.
// ---------------------------------------------------------------------------

it('0110: a non-existent or duplicated category id is rejected (422)', function () {
    $actor = competenceActor(['update']);
    $category = ProductCategory::factory()->create();
    $target = competenceTargetWith();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_category_ids' => [999999]],
    ])->assertStatus(422)->assertJsonValidationErrors('employment.product_category_ids.0');

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_category_ids' => [$category->id, $category->id]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_category_ids.0',
        'employment.product_category_ids.1',
    ]);
});
