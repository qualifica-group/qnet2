<?php

use App\Http\Controllers\RequestManagement\RequestStatisticsCategoryPreferencesController;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserCategoryTabPreference;
use App\RequestManagement\RequestModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET/PUT /api/request-management/report/category-tab-preferences (spec 0385).

uses(RefreshDatabase::class);

const STATISTICS_PREFERENCES_URL = '/api/request-management/report/category-tab-preferences';

if (! function_exists('statisticsPreferencesActor')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function statisticsPreferencesActor(array $permissions = [RequestModule::STATISTICS_PERMISSION]): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

it('returns no favourites and the switch off when nothing was saved (AC-001)', function () {
    Sanctum::actingAs(statisticsPreferencesActor());

    $this->getJson(STATISTICS_PREFERENCES_URL)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.preferences.favorite_category_ids', [])
        ->assertJsonPath('data.preferences.show_only_favorites', false)
        ->assertJsonPath('data.preferences.is_default', true);
});

it('saves report categories and subcategories as favourites on a row of their own (AC-002)', function () {
    $actor = statisticsPreferencesActor([RequestModule::STATISTICS_PERMISSION, 'request-management.viewAny']);
    $parent = ProductCategory::factory()->reportable()->create(['name' => 'Formazione']);
    $child = ProductCategory::factory()->childOf($parent)->create(['name' => 'GOL']);
    Sanctum::actingAs($actor);

    $this->putJson(STATISTICS_PREFERENCES_URL, [
        'favorite_category_ids' => [$child->id, $parent->id],
        'show_only_favorites' => true,
    ])->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$parent->id, $child->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true)
        ->assertJsonPath('data.preferences.is_default', false);

    $this->getJson(STATISTICS_PREFERENCES_URL)
        ->assertJsonPath('data.preferences.favorite_category_ids', [$parent->id, $child->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true);
    expect(UserCategoryTabPreference::query()->where('user_id', $actor->id)->pluck('module')->all())
        ->toBe([RequestStatisticsCategoryPreferencesController::PREFERENCE_MODULE]);

    // Gestione Richieste's own strip is untouched by the statistics favourites.
    $this->getJson('/api/request-management/category-tab-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', []);
});

it('clears the favourites with an empty list (AC-003)', function () {
    Sanctum::actingAs(statisticsPreferencesActor());
    $category = ProductCategory::factory()->reportable()->create();

    $this->putJson(STATISTICS_PREFERENCES_URL, ['favorite_category_ids' => [$category->id], 'show_only_favorites' => true])->assertOk();
    $this->putJson(STATISTICS_PREFERENCES_URL, ['favorite_category_ids' => [], 'show_only_favorites' => false])
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [])
        ->assertJsonPath('data.preferences.show_only_favorites', false);
});

it('403s both verbs without request-statistics.view, whatever request-management grant is held (AC-004)', function () {
    Sanctum::actingAs(statisticsPreferencesActor(['request-management.viewAny', 'request-management.viewAll']));
    $category = ProductCategory::factory()->reportable()->create();

    $this->getJson(STATISTICS_PREFERENCES_URL)->assertForbidden();
    $this->putJson(STATISTICS_PREFERENCES_URL, ['favorite_category_ids' => [$category->id], 'show_only_favorites' => false])
        ->assertForbidden();
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
});

it('401s a guest (AC-004)', function () {
    $this->getJson(STATISTICS_PREFERENCES_URL)->assertUnauthorized();
});

it('422s a category the statistics do not show, or one that does not exist (AC-005)', function (string $case) {
    Sanctum::actingAs(statisticsPreferencesActor());
    $reportable = ProductCategory::factory()->reportable()->create();
    $hidden = ProductCategory::factory()->create(['is_reportable' => false]);
    $id = $case === 'not reportable' ? $hidden->id : $hidden->id + 1000;

    $this->putJson(STATISTICS_PREFERENCES_URL, [
        'favorite_category_ids' => [$reportable->id, $id],
        'show_only_favorites' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors(['favorite_category_ids.1']);
    expect(UserCategoryTabPreference::query()->count())->toBe(0);
})->with(['not reportable', 'unknown']);

it('422s a payload missing a field or with a non-integer id (AC-005)', function (array $payload, string $field) {
    Sanctum::actingAs(statisticsPreferencesActor());
    ProductCategory::factory()->reportable()->create();

    $this->putJson(STATISTICS_PREFERENCES_URL, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing favourites' => [['show_only_favorites' => true], 'favorite_category_ids'],
    'missing switch' => [['favorite_category_ids' => []], 'show_only_favorites'],
    'non-integer id' => [['favorite_category_ids' => ['abc'], 'show_only_favorites' => true], 'favorite_category_ids.0'],
]);
