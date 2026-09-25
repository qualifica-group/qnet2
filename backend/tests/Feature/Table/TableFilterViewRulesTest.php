<?php

use App\Models\TableFilterView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Custom filter rules on a saved TableFilterView (spec 0158, contract
 * "Viste"): `rules` present forces `filters`/`advanced_filters` to be saved
 * empty; the resource exposes `rules` + `is_favorite`; a stale rule field is
 * dropped on read.
 */
if (! function_exists('userWithUserAbilitiesForRules')) {
    function userWithUserAbilitiesForRules(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

it('storing rules saves filters/advanced_filters empty regardless of what is submitted', function () {
    $actor = userWithUserAbilitiesForRules(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Custom filter view',
        'filters' => ['name' => ['filterType' => 'text', 'filter' => 'ignored']],
        'visibility' => 'private',
        'rules' => ['and' => [['field' => 'name', 'operator' => 'contains', 'value' => 'Al']], 'or' => []],
    ])->assertCreated();

    $response->assertJsonPath('data.filters', [])
        ->assertJsonPath('data.rules.and.0.field', 'name')
        ->assertJsonPath('data.rules.and.0.operator', 'contains')
        ->assertJsonPath('data.is_favorite', false);

    $this->assertDatabaseHas('table_filter_views', [
        'name' => 'Custom filter view',
        'filters' => '[]',
        'advanced_filters' => '[]',
    ]);
});

it('422 with rules.<key> error keys for an invalid rules payload', function () {
    Sanctum::actingAs(userWithUserAbilitiesForRules(['viewAny']));

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Bad rules',
        'filters' => [],
        'visibility' => 'private',
        'rules' => ['and' => [['field' => 'ghost-field', 'operator' => 'equals', 'value' => 'x']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('rules.and.0.field');
});

it('a rule whose field is no longer filterable is dropped on read; rules becomes null when none remain', function () {
    $actor = userWithUserAbilitiesForRules(['viewAny']);

    $view = TableFilterView::factory()->create([
        'user_id' => $actor->id,
        'domain' => 'users',
        'filters' => [],
        'rules' => ['and' => [['field' => 'ghost-field', 'operator' => 'equals', 'value' => 'x']], 'or' => []],
    ]);

    Sanctum::actingAs($actor);

    $data = collect($this->getJson('/api/tables/users/filter-views')->assertOk()->json('data'))->keyBy('id');
    expect($data[$view->id]['rules'])->toBeNull();
});
