<?php

use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserCategoryTabPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET/PUT /api/{module}/category-tab-preferences (spec 0184).

uses(RefreshDatabase::class);

const CATEGORY_TAB_PREFERENCES_URL = '/api/request-management/category-tab-preferences';

if (! function_exists('categoryTabPreferencesActor')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function categoryTabPreferencesActor(array $permissions = ['request-management.viewAny']): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

it('returns no favourites and the switch off when nothing was saved (AC-001)', function () {
    Sanctum::actingAs(categoryTabPreferencesActor());

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.preferences.favorite_category_ids', [])
        ->assertJsonPath('data.preferences.show_only_favorites', false);
});

it('saves the preferences and replaces them on a second write, keeping one row (AC-002)', function () {
    $actor = categoryTabPreferencesActor();
    [$first, $second, $third] = ProductCategory::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, [
        'favorite_category_ids' => [$second->id, $first->id],
        'show_only_favorites' => true,
    ])->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$first->id, $second->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true);

    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, [
        'favorite_category_ids' => [$third->id],
        'show_only_favorites' => false,
    ])->assertOk();

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
        ->assertJsonPath('data.preferences.favorite_category_ids', [$third->id])
        ->assertJsonPath('data.preferences.show_only_favorites', false);
    expect(UserCategoryTabPreference::query()->where('user_id', $actor->id)->count())->toBe(1);
});

it('keeps each module\'s preferences apart (AC-003)', function () {
    Sanctum::actingAs(categoryTabPreferencesActor(['request-management.viewAny', 'enrollee-management.viewAny']));
    $category = ProductCategory::factory()->create();

    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, [
        'favorite_category_ids' => [$category->id],
        'show_only_favorites' => true,
    ])->assertOk();

    $this->getJson('/api/enrollee-management/category-tab-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [])
        ->assertJsonPath('data.preferences.show_only_favorites', false);
});

it('never shows one user\'s preferences to another (AC-004)', function () {
    $category = ProductCategory::factory()->create();
    $owner = categoryTabPreferencesActor();
    $other = categoryTabPreferencesActor();
    Sanctum::actingAs($owner);
    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, [
        'favorite_category_ids' => [$category->id],
        'show_only_favorites' => true,
    ])->assertOk();

    Sanctum::actingAs($other);

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
        ->assertJsonPath('data.preferences.favorite_category_ids', []);
});

it('forbids reading and writing without the module viewAny (AC-005)', function () {
    Sanctum::actingAs(categoryTabPreferencesActor(['enrollee-management.viewAny']));

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)->assertForbidden();
    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, [
        'favorite_category_ids' => [],
        'show_only_favorites' => false,
    ])->assertForbidden();
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
});

it('rejects unknown or repeated ids and missing fields (AC-006)', function (array $payload, string $errorKey) {
    Sanctum::actingAs(categoryTabPreferencesActor());
    $category = ProductCategory::factory()->create();
    $payload = json_decode(str_replace('"CATEGORY"', (string) $category->id, json_encode($payload)), true);

    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);
})->with([
    'unknown id' => [['favorite_category_ids' => [999999], 'show_only_favorites' => false], 'favorite_category_ids.0'],
    'repeated id' => [['favorite_category_ids' => ['CATEGORY', 'CATEGORY'], 'show_only_favorites' => false], 'favorite_category_ids.0'],
    'missing ids' => [['show_only_favorites' => false], 'favorite_category_ids'],
    'missing switch' => [['favorite_category_ids' => []], 'show_only_favorites'],
]);

it('drops a favourite whose category was deleted since (AC-007)', function () {
    $actor = categoryTabPreferencesActor();
    [$kept, $deleted] = ProductCategory::factory()->count(2)->create();
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id,
        'module' => 'request-management',
        'favorite_category_ids' => [$kept->id, $deleted->id],
        'show_only_favorites' => true,
    ]);
    $deleted->delete();
    Sanctum::actingAs($actor);

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
        ->assertJsonPath('data.preferences.favorite_category_ids', [$kept->id]);
});
