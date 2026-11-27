<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Columns with more distinct values than the checklist limit are marked
 * `tooManyFilterValues` in the table config (user decision 2026-11-27): the
 * grid then offers only their condition filter. Spec 0005.
 */
function largeValueColumn(array $data, string $id): ?array
{
    return collect($data['columns'])->firstWhere('id', $id);
}

it('marks a text column with more values than the limit', function () {
    config(['table-filter-values.limit' => 3]);
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->count(4)->create();
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/users/columns')->assertOk()->json('data');

    expect(largeValueColumn($data, 'email')['tooManyFilterValues'] ?? null)->toBeTrue();
});

it('keeps the checklist for a column within the limit', function () {
    config(['table-filter-values.limit' => 100]);
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->count(4)->create();
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/users/columns')->assertOk()->json('data');

    expect(largeValueColumn($data, 'email'))->not->toHaveKey('tooManyFilterValues');
});

it('never marks a set column, whose filter is always the checklist', function () {
    config(['table-filter-values.limit' => 1]);
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->count(3)->create(['is_active' => true]);
    User::factory()->count(3)->create(['is_active' => false]);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/users/columns')->assertOk()->json('data');

    expect(largeValueColumn($data, 'is_active'))->not->toHaveKey('tooManyFilterValues');
});

it('marks the same columns in the config returned by a preferences save', function () {
    config(['table-filter-values.limit' => 3]);
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->count(4)->create();
    Sanctum::actingAs($actor);

    $data = $this->postJson('/api/tables/users/preferences', ['columns' => [['id' => 'email', 'width' => 200]]])
        ->assertOk()
        ->json('data');

    expect(largeValueColumn($data, 'email')['tooManyFilterValues'] ?? null)->toBeTrue();
});

it('lets the values endpoint ask for up to the configured limit', function () {
    config(['table-filter-values.limit' => 3]);
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/values', ['columnId' => 'email', 'limit' => 3])->assertOk();
    $this->postJson('/api/tables/users/values', ['columnId' => 'email', 'limit' => 4])
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
