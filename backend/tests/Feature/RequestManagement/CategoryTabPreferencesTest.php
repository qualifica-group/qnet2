<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Models\UserCategoryTabPreference;
use Illuminate\Database\Eloquent\Factories\Factory;
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
        ->assertJsonPath('data.preferences.show_only_favorites', false)
        ->assertJsonPath('data.preferences.is_default', true);
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

// Competence default (spec 0193).

if (! function_exists('categoryTabDefaultsQuote')) {
    /** A quote visible on the strip under $category; $statusKey picks the workflow status. */
    function categoryTabDefaultsQuote(ProductCategory $category, string $statusKey = 'open'): void
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);
        $statusId = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', $statusKey)->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($statusKey)->create()->id;
        Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => $statusId]);
    }
}

if (! function_exists('categoryTabDefaultsActor')) {
    function categoryTabDefaultsActor(string $module = 'request-management'): User
    {
        return categoryTabPreferencesActor(["{$module}.viewAny", "{$module}.viewAll"]);
    }
}

if (! function_exists('categoryTabDefaultsProfile')) {
    function categoryTabDefaultsProfile(User $user): Factory
    {
        return EmploymentProfile::factory()->for($user);
    }
}

it('defaults the favourites to the strip categories the competence covers (AC-001, AC-003)', function () {
    $actor = categoryTabDefaultsActor();
    $function = BusinessFunction::factory()->create();
    $covered = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $coveredNoRequests = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $other = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    categoryTabDefaultsProfile($actor)->competentIn($function, $covered, $coveredNoRequests)->create();
    categoryTabDefaultsQuote($covered);
    categoryTabDefaultsQuote($other);
    Sanctum::actingAs($actor);

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$covered->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true)
        ->assertJsonPath('data.preferences.is_default', true);
});

it('covers sub-categories through a parent row and a null-category row by function (AC-002)', function () {
    $function = BusinessFunction::factory()->create();
    $parent = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $viaParent = categoryTabDefaultsActor();
    categoryTabDefaultsProfile($viaParent)->competentIn($function, $parent)->create();
    $viaFunction = categoryTabDefaultsActor();
    categoryTabDefaultsProfile($viaFunction)->competentInEveryCategoryOf($function)->create();
    categoryTabDefaultsQuote($child);

    foreach ([$viaParent, $viaFunction] as $actor) {
        Sanctum::actingAs($actor);

        $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
            ->assertJsonPath('data.preferences.favorite_category_ids', [$child->id])
            ->assertJsonPath('data.preferences.show_only_favorites', true)
            ->assertJsonPath('data.preferences.is_default', true);
    }
});

it('keeps the old default for a wildcard, a user without competence and a competence off the strip (AC-004)', function () {
    $function = BusinessFunction::factory()->create();
    $onStrip = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $offStrip = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    categoryTabDefaultsQuote($onStrip);

    $wildcard = categoryTabDefaultsActor();
    categoryTabDefaultsProfile($wildcard)->coversAllProductCategories()->create();
    $noCompetence = categoryTabDefaultsActor();
    $offStripUser = categoryTabDefaultsActor();
    categoryTabDefaultsProfile($offStripUser)->competentIn(BusinessFunction::factory()->create(), $offStrip)->create();

    foreach ([$wildcard, $noCompetence, $offStripUser] as $actor) {
        Sanctum::actingAs($actor);

        $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
            ->assertOk()
            ->assertJsonPath('data.preferences.favorite_category_ids', [])
            ->assertJsonPath('data.preferences.show_only_favorites', false)
            ->assertJsonPath('data.preferences.is_default', true);
    }
});

it('returns the saved row, even empty, ignoring the competence (AC-005)', function () {
    $actor = categoryTabDefaultsActor();
    $function = BusinessFunction::factory()->create();
    $covered = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    categoryTabDefaultsProfile($actor)->competentIn($function, $covered)->create();
    categoryTabDefaultsQuote($covered);
    UserCategoryTabPreference::query()->create([
        'user_id' => $actor->id,
        'module' => 'request-management',
        'favorite_category_ids' => [],
        'show_only_favorites' => false,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)
        ->assertJsonPath('data.preferences.favorite_category_ids', [])
        ->assertJsonPath('data.preferences.show_only_favorites', false)
        ->assertJsonPath('data.preferences.is_default', false);
});

it('never writes on GET and answers is_default false on PUT (AC-006)', function () {
    $actor = categoryTabDefaultsActor();
    $function = BusinessFunction::factory()->create();
    $covered = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    categoryTabDefaultsProfile($actor)->competentIn($function, $covered)->create();
    categoryTabDefaultsQuote($covered);
    Sanctum::actingAs($actor);

    $this->getJson(CATEGORY_TAB_PREFERENCES_URL)->assertOk();
    expect(UserCategoryTabPreference::query()->count())->toBe(0);

    $this->putJson(CATEGORY_TAB_PREFERENCES_URL, [
        'favorite_category_ids' => [$covered->id],
        'show_only_favorites' => true,
    ])->assertOk()->assertJsonPath('data.preferences.is_default', false);
    expect(UserCategoryTabPreference::query()->count())->toBe(1);
});

it('computes the default on the module\'s own strip (AC-007)', function () {
    $actor = categoryTabDefaultsActor('enrollee-management');
    $function = BusinessFunction::factory()->create();
    $enrolled = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $stillOpen = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    categoryTabDefaultsProfile($actor)->competentIn($function, $enrolled, $stillOpen)->create();
    categoryTabDefaultsQuote($enrolled, 'closed_won');
    categoryTabDefaultsQuote($stillOpen, 'open');
    Sanctum::actingAs($actor);

    $this->getJson('/api/enrollee-management/category-tab-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.favorite_category_ids', [$enrolled->id])
        ->assertJsonPath('data.preferences.show_only_favorites', true)
        ->assertJsonPath('data.preferences.is_default', true);
});
