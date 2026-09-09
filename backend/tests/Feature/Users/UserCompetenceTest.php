<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductLines\ProductLineSetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0111 — the assignment competence as {funzione aziendale, categoria
 * prodotto} ROWS on `employment_product_lines`, replacing the single
 * `business_function_id` column plus the category-only pivot of spec 0110:
 * the write contract, its tri-state, the pair rules and the field-permission
 * key. AC-001..AC-008 and AC-026.
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
     * A target user whose employment profile already holds one competence row
     * per category, all paired with $function.
     */
    function competenceTargetWith(?BusinessFunction $function = null, ProductCategory ...$categories): User
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

if (! function_exists('competenceCategoryUnder')) {
    /**
     * A selectable category owning $function, so a row pairing the two
     * satisfies the business-function match.
     */
    function competenceCategoryUnder(BusinessFunction $function): ProductCategory
    {
        return ProductCategory::factory()->create(['business_function_id' => $function->id]);
    }
}

if (! function_exists('competenceRowsOf')) {
    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    function competenceRowsOf(User $target): array
    {
        return DB::table('employment_product_lines')
            ->where('employment_profile_id', $target->fresh()->employment?->id)
            ->orderBy('id')
            ->get(['business_function_id', 'product_category_id'])
            ->map(static fn (object $row): array => [
                'business_function_id' => (int) $row->business_function_id,
                'product_category_id' => (int) $row->product_category_id,
            ])
            ->all();
    }
}

// ---------------------------------------------------------------------------
// AC-001 — the submitted rows are persisted and read back in insertion order.
// ---------------------------------------------------------------------------

it('0111 AC-001: submitting product_lines persists exactly those rows and echoes them back in order', function () {
    $actor = competenceActor(['update']);
    $functionA = BusinessFunction::factory()->create(['name' => 'Engineering']);
    $functionB = BusinessFunction::factory()->create(['name' => 'Sales']);
    $categoryA = competenceCategoryUnder($functionA);
    $categoryB = competenceCategoryUnder($functionB);
    $target = competenceTargetWith();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id],
            ['business_function_id' => $functionB->id, 'product_category_id' => $categoryB->id],
        ]],
    ])->assertOk();

    expect(competenceRowsOf($target))->toBe([
        ['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id],
        ['business_function_id' => $functionB->id, 'product_category_id' => $categoryB->id],
    ]);

    $response->assertJsonCount(2, 'data.employment.product_lines')
        ->assertJsonPath('data.employment.product_lines.0.business_function.name', 'Engineering')
        ->assertJsonPath('data.employment.product_lines.0.product_category.id', $categoryA->id)
        ->assertJsonPath('data.employment.product_lines.1.business_function.name', 'Sales')
        ->assertJsonPath('data.employment.product_lines.1.product_category.id', $categoryB->id);
});

// ---------------------------------------------------------------------------
// AC-002 — key absent leaves the rows untouched (tri-state).
// ---------------------------------------------------------------------------

it('0111 AC-002: omitting product_lines leaves the existing competence untouched', function () {
    $actor = competenceActor(['update']);
    $function = BusinessFunction::factory()->create();
    $categoryA = competenceCategoryUnder($function);
    $categoryB = competenceCategoryUnder($function);
    $target = competenceTargetWith($function, $categoryA, $categoryB);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['job_description' => 'Untouched by this write'],
    ])->assertOk();

    expect(competenceRowsOf($target))->toBe([
        ['business_function_id' => $function->id, 'product_category_id' => $categoryA->id],
        ['business_function_id' => $function->id, 'product_category_id' => $categoryB->id],
    ]);
});

// ---------------------------------------------------------------------------
// AC-003 — an explicit empty array clears the rows (D-8: no `min:1` here).
// ---------------------------------------------------------------------------

it('0111 AC-003: submitting an empty product_lines array clears the competence', function () {
    $actor = competenceActor(['update']);
    $function = BusinessFunction::factory()->create();
    $target = competenceTargetWith($function, competenceCategoryUnder($function));
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => []],
    ])->assertOk();

    expect(competenceRowsOf($target))->toBe([]);
    expect($response->json('data.employment.product_lines'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-004/AC-005 — the pair rules of ProductLineSetValidator apply here too.
// ---------------------------------------------------------------------------

it('0111 AC-004: a repeated (function, category) pair is rejected on the row that repeats it', function () {
    $actor = competenceActor(['update']);
    $function = BusinessFunction::factory()->create();
    $category = competenceCategoryUnder($function);
    $target = competenceTargetWith();
    Sanctum::actingAs($actor);

    $row = ['business_function_id' => $function->id, 'product_category_id' => $category->id];

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [$row, $row]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.1.product_category_id' => __(ProductLineSetValidator::DUPLICATE_PAIR_MESSAGE),
    ]);

    expect(competenceRowsOf($target))->toBe([]);
});

it('0111 AC-005: a category whose EFFECTIVE function differs is rejected on business_function_id', function () {
    $actor = competenceActor(['update']);
    $owning = BusinessFunction::factory()->create();
    $other = BusinessFunction::factory()->create();
    $category = competenceCategoryUnder($owning);
    $target = competenceTargetWith();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $other->id, 'product_category_id' => $category->id],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.product_lines.0.business_function_id' => __(ProductLineSetValidator::BUSINESS_FUNCTION_MISMATCH_MESSAGE),
    ]);
});

// ---------------------------------------------------------------------------
// AC-006 — spec 0074 selectability, with the already-persisted exemption.
// ---------------------------------------------------------------------------

it('0111 AC-006: a non-selectable category is rejected unless it is already persisted on the profile', function () {
    $actor = competenceActor(['update']);
    $function = BusinessFunction::factory()->create();
    $container = ProductCategory::factory()->create([
        'business_function_id' => $function->id,
        'is_selectable' => false,
    ]);

    $newcomer = competenceTargetWith();
    Sanctum::actingAs($actor);

    $payload = ['employment' => ['product_lines' => [
        ['business_function_id' => $function->id, 'product_category_id' => $container->id],
    ]]];

    $this->patchJson("/api/users/{$newcomer->id}", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('employment.product_lines.0.product_category_id');

    // Same category, but already on the target's profile (spec 0074 D-3b).
    $grandfathered = competenceTargetWith($function, $container);

    $this->patchJson("/api/users/{$grandfathered->id}", $payload)->assertOk();

    expect(competenceRowsOf($grandfathered))->toBe([
        ['business_function_id' => $function->id, 'product_category_id' => $container->id],
    ]);
});

// ---------------------------------------------------------------------------
// AC-007 — the card cap of spec 0077 does NOT apply to a user's competence.
// ---------------------------------------------------------------------------

it('0111 AC-007: two categories under single-mode roots are accepted (no card cap here)', function () {
    $actor = competenceActor(['update']);
    $function = BusinessFunction::factory()->create();
    $rootA = ProductCategory::factory()->create([
        'business_function_id' => $function->id,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $rootB = ProductCategory::factory()->create([
        'business_function_id' => $function->id,
        'management_mode' => CategoryManagementMode::Single,
    ]);
    $target = competenceTargetWith();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $rootA->id],
            ['business_function_id' => $function->id, 'product_category_id' => $rootB->id],
        ]],
    ])->assertOk();

    expect(competenceRowsOf($target))->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// AC-008 — deleting the profile cascades onto its rows.
// ---------------------------------------------------------------------------

it('0111 AC-008: submitting employment: null deletes the profile and its competence rows', function () {
    $actor = competenceActor(['update']);
    $function = BusinessFunction::factory()->create();
    $target = competenceTargetWith($function, competenceCategoryUnder($function));
    $profileId = $target->employment->id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => null])->assertOk();

    expect($target->fresh()->employment)->toBeNull();
    expect(DB::table('employment_product_lines')->where('employment_profile_id', $profileId)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-026 — the field-permission matrix governs the new key, symmetrically.
// ---------------------------------------------------------------------------

it('0111: a CHANGED competence on a non-editable field is rejected (422) and nothing is written', function () {
    $actor = competenceActor(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'employment.product_lines', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $function = BusinessFunction::factory()->create();
    $current = competenceCategoryUnder($function);
    $other = competenceCategoryUnder($function);
    $target = competenceTargetWith($function, $current);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => [
            ['business_function_id' => $function->id, 'product_category_id' => $other->id],
        ]],
    ])->assertStatus(422)->assertJsonValidationErrors('employment.product_lines');

    expect(competenceRowsOf($target))->toBe([
        ['business_function_id' => $function->id, 'product_category_id' => $current->id],
    ]);
});

it('0111 AC-026: re-submitting the SAME rows on a non-editable field is a no-op (200)', function () {
    $actor = competenceActor(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'employment.product_lines', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $function = BusinessFunction::factory()->create();
    $categoryA = competenceCategoryUnder($function);
    $categoryB = competenceCategoryUnder($function);
    $target = competenceTargetWith($function, $categoryA, $categoryB);
    Sanctum::actingAs($actor);

    // Deliberately reversed, and carrying the row `id` the read side emits:
    // the permission check compares the rows' FILLABLES (D-7), order- and
    // key-insensitively, so neither may read as a change.
    $rows = $target->employment->productLines()->orderByDesc('id')->get();

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['product_lines' => $rows
            ->map(static fn ($line): array => [
                'id' => $line->id,
                'business_function_id' => $line->business_function_id,
                'product_category_id' => $line->product_category_id,
            ])
            ->all()],
    ])->assertOk();

    expect(competenceRowsOf($target))->toHaveCount(2);
});
