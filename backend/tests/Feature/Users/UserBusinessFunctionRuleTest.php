<?php

use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\Users\BusinessFunctionCoverageGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Spec 0271 D-6: an active, non-service-account person must carry at least
 * one competence row (or the covers-all flag). Guard against a partial write
 * is part of every error-path assertion below.
 */
function businessFunctionRuleActor(): User
{
    return userWithUserAbilities(['create', 'update', 'view']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function businessFunctionRulePayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'rule@example.com',
        'locale' => 'it',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => ['type' => 'individual', 'first_name' => 'Rule', 'last_name' => 'Subject'],
    ], $overrides);
}

function businessFunctionRuleLine(): array
{
    return ['business_function_id' => BusinessFunction::factory()->create()->id, 'product_category_id' => null];
}

it('0271 AC-009: creating an active person without competence rows is a 422 on employment.product_lines and creates nothing', function () {
    Sanctum::actingAs(businessFunctionRuleActor());

    $this->postJson('/api/users', businessFunctionRulePayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employment.product_lines']);

    $this->postJson('/api/users', businessFunctionRulePayload(['employment' => ['is_manager' => false]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employment.product_lines'])
        ->assertJsonPath('message', __('users.employment.business_function_required'));

    $this->assertDatabaseMissing('users', ['email' => 'rule@example.com']);
});

it('0271 AC-009: creation succeeds with a row, with covers_all, or when inactive', function () {
    Sanctum::actingAs(businessFunctionRuleActor());

    $this->postJson('/api/users', businessFunctionRulePayload([
        'email' => 'row@example.com',
        'employment' => ['product_lines' => [businessFunctionRuleLine()]],
    ]))->assertCreated();

    $this->postJson('/api/users', businessFunctionRulePayload([
        'email' => 'all@example.com',
        'employment' => ['covers_all_product_categories' => true],
    ]))->assertCreated();

    $this->postJson('/api/users', businessFunctionRulePayload(['email' => 'inactive@example.com', 'is_active' => false]))
        ->assertCreated();
});

it('0271 AC-010: removing the last row (or sending employment: null) on an active person is a 422 and the row stays', function () {
    Sanctum::actingAs(businessFunctionRuleActor());
    $target = User::factory()->create();
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [businessFunctionRuleLine()]]])->assertOk();

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => []]])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.product_lines']);
    expect($target->employment()->first()->productLines()->count())->toBe(1);

    $this->patchJson("/api/users/{$target->id}", ['employment' => null])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.product_lines']);
    expect($target->employment()->exists())->toBeTrue();
});

it('0271 AC-010: deactivating and removing the row together succeeds; activating an inactive person without rows is a 422', function () {
    Sanctum::actingAs(businessFunctionRuleActor());
    $target = User::factory()->create();
    $this->patchJson("/api/users/{$target->id}", ['employment' => ['product_lines' => [businessFunctionRuleLine()]]])->assertOk();

    $this->patchJson("/api/users/{$target->id}", ['is_active' => false, 'employment' => ['product_lines' => []]])->assertOk();
    expect($target->fresh()->is_active)->toBeFalse();

    $this->patchJson("/api/users/{$target->id}", ['is_active' => true])
        ->assertStatus(422)->assertJsonValidationErrors(['employment.product_lines']);
    expect($target->fresh()->is_active)->toBeFalse();

    $this->patchJson("/api/users/{$target->id}", ['is_active' => true, 'employment' => ['covers_all_product_categories' => true]])->assertOk();
});

it('0271 AC-010: the guard exempts a service account and an inactive user', function () {
    $guard = app(BusinessFunctionCoverageGuard::class);

    $guard->assertCovered(User::factory()->serviceAccount()->create());
    $guard->assertCovered(User::factory()->inactive()->create());

    expect(fn () => $guard->assertCovered(User::factory()->create()))->toThrow(ValidationException::class);
});

it('0271 AC-011: an update that touches neither employment nor is_active never trips the rule on legacy data', function () {
    Sanctum::actingAs(businessFunctionRuleActor());
    $target = User::factory()->withEmployment()->create(['name' => 'Legacy']);

    expect($target->employment->productLines()->count())->toBe(0);

    $this->patchJson("/api/users/{$target->id}", ['email' => 'renamed-legacy@example.com'])->assertOk();
    $this->patchJson("/api/users/{$target->id}", ['is_active' => true])->assertOk();
});
