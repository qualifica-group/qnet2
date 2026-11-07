<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Services\Assignment\CompetenceReach;
use App\Services\Assignment\OperatorCompetence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0367: a competence row on "Tutte" chooses the new categories, the
 * legacy ones ([OLD], `is_legacy`) or both. Both is today's behaviour.
 */
uses(RefreshDatabase::class);

function categoryAgeReach(User $user): CompetenceReach
{
    return app(OperatorCompetence::class)->reachOf($user);
}

/** @return array{0: BusinessFunction, 1: ProductCategory, 2: ProductCategory, 3: ProductCategory} function, new category, legacy root, legacy child */
function categoryAgeTaxonomy(): array
{
    $function = BusinessFunction::factory()->create();
    $current = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $legacyRoot = ProductCategory::factory()->create(['business_function_id' => $function->id, 'is_legacy' => true, 'is_active' => false]);
    $legacyChild = ProductCategory::factory()->childOf($legacyRoot)->create(['is_legacy' => true, 'is_active' => false]);

    return [$function, $current, $legacyRoot, $legacyChild];
}

function categoryAgeRow(User $user, BusinessFunction $function, bool $current, bool $legacy): void
{
    grantCompetenceOnEveryCategoryOf($user, $function)->productLines()->update(['covers_current_categories' => $current, 'covers_legacy_categories' => $legacy]);
}

function categoryAgeUsersActor(): User
{
    foreach (['users.viewAny', 'users.view', 'users.update'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::create(['name' => 'category-age-role']);
    $role->givePermissionTo(['users.viewAny', 'users.view', 'users.update']);
    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

it('AC-001: a "Tutte" row stores the chosen ages, the resource exposes them, a specific category always stores both', function (): void {
    [$function, $current] = categoryAgeTaxonomy();
    $target = EmploymentProfile::factory()->competentInEveryCategoryOf($function)->memberOf($function)->create()->user;
    Sanctum::actingAs(categoryAgeUsersActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        ['business_function_id' => $function->id, 'product_category_id' => null, 'covers_current_categories' => true, 'covers_legacy_categories' => false, 'share_percentage' => 100],
    ]]])->assertOk()
        ->assertJsonPath('data.employment.product_lines.0.covers_current_categories', true)
        ->assertJsonPath('data.employment.product_lines.0.covers_legacy_categories', false);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        ['business_function_id' => $function->id, 'product_category_id' => $current->id, 'covers_current_categories' => false, 'covers_legacy_categories' => false, 'share_percentage' => 100],
    ]]])->assertOk()
        ->assertJsonPath('data.employment.product_lines.0.covers_current_categories', true)
        ->assertJsonPath('data.employment.product_lines.0.covers_legacy_categories', true);
});

it('AC-002: a "Tutte" row with neither age is rejected; an absent flag reads as chosen', function (): void {
    [$function] = categoryAgeTaxonomy();
    $target = EmploymentProfile::factory()->competentInEveryCategoryOf($function)->memberOf($function)->create()->user;
    Sanctum::actingAs(categoryAgeUsersActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        ['business_function_id' => $function->id, 'product_category_id' => null, 'covers_current_categories' => false, 'covers_legacy_categories' => false, 'share_percentage' => 100],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['employment.product_lines.0.covers_current_categories']);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [
        ['business_function_id' => $function->id, 'product_category_id' => null, 'share_percentage' => 100],
    ]]])->assertOk()
        ->assertJsonPath('data.employment.product_lines.0.covers_legacy_categories', true);
});

it('AC-003: the reach follows the ages of a "Tutte" row', function (bool $current, bool $legacy, array $expected): void {
    $taxonomy = categoryAgeTaxonomy();
    $user = User::factory()->create();
    categoryAgeRow($user, $taxonomy[0], $current, $legacy);

    $ids = array_map(static fn (int $index): int => $taxonomy[$index]->id, $expected);
    $reach = categoryAgeReach($user);

    expect($reach->categoryIds)->toEqualCanonicalizing($ids);
})->with([
    'both' => [true, true, [1, 2, 3]],
    'new only' => [true, false, [1]],
    'old only' => [false, true, [2, 3]],
]);

it('AC-004: an explicit row on a legacy root still covers its branch next to a "new only" row', function (): void {
    [$function, $current, $legacyRoot, $legacyChild] = categoryAgeTaxonomy();
    $user = User::factory()->create();
    categoryAgeRow($user, $function, true, false);
    competenceVisibilityProfile($user)->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => $legacyRoot->id, 'share_percentage' => 1]);

    expect(categoryAgeReach($user)->categoryIds)->toEqualCanonicalizing([$current->id, $legacyRoot->id, $legacyChild->id]);
});

it('AC-005: the assignment pool follows the same ages', function (): void {
    [$function, $current, , $legacyChild] = categoryAgeTaxonomy();
    $newOnly = EmploymentProfile::factory()->competentInEveryCategoryOf($function)->create();
    $newOnly->productLines()->update(['covers_legacy_categories' => false]);
    $both = EmploymentProfile::factory()->competentInEveryCategoryOf($function)->create();
    $competence = fn () => app(OperatorCompetence::class);

    expect($competence()->competentUserIds([$current->id]))->toEqualCanonicalizing([$newOnly->user_id, $both->user_id])
        ->and($competence()->competentUserIds([$legacyChild->id]))->toBe([$both->user_id]);
});

it('AC-006: Gestione Richieste hides a legacy request reached only by a "new only" row, keeps the one the actor operates', function (): void {
    [$function, $current, , $legacyChild] = categoryAgeTaxonomy();
    $actor = competenceVisibilityActor('request-management', ['viewAny', 'view', 'viewCompetence']);
    categoryAgeRow($actor, $function, true, false);
    $currentQuote = categoryTabRequestQuote([$current]);
    $legacyQuote = categoryTabRequestQuote([$legacyChild]);
    $operatedLegacyQuote = categoryTabRequestQuote([$legacyChild]);
    Quote::query()->whereKey($operatedLegacyQuote->id)->update(['operator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$currentQuote->id, $operatedLegacyQuote->id])->sort()->values()->all());
    $this->getJson("/api/request-management/{$legacyQuote->id}")->assertForbidden();
});
