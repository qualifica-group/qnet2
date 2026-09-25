<?php

use App\Models\User;
use App\Models\UserTableFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Create the standard users.* permissions and return a freshly-created user
 * granted the requested subset. Guarded so it can coexist with the same helper
 * in the other Table feature test files within one Pest run.
 *
 * @param  array<int, string>  $abilities
 */
/** A real filterable column id for the users domain (resolved from the config). */
function firstFilterableColumnId(object $test): string
{
    $columns = $test->getJson('/api/tables/users/columns')->json('data.columns');

    foreach ($columns as $column) {
        if ($column['filterable'] ?? false) {
            return $column['id'];
        }
    }

    return $columns[0]['id'];
}

/** A sample AG Grid text filter model value. */
function sampleFilter(): array
{
    return ['filterType' => 'text', 'type' => 'contains', 'filter' => 'foo'];
}

it('reports no saved filters in the default config', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $data = $this->getJson('/api/tables/users/columns')->json('data');

    expect($data['filtersCustomized'])->toBeFalse()
        ->and($data['filterState'])->toBe([]); // {} decodes to an empty array
});

it('flags the config as filter-customized only when saved filters exist', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);
    $columnId = firstFilterableColumnId($this);

    expect($this->getJson('/api/tables/users/columns')->json('data.filtersCustomized'))
        ->toBeFalse();

    $this->postJson('/api/tables/users/filters', [
        'filterModel' => [$columnId => sampleFilter()],
    ])->assertOk()->assertJsonPath('data.filtersCustomized', true);

    // Persisted → still customized on a fresh read.
    expect($this->getJson('/api/tables/users/columns')->json('data.filtersCustomized'))
        ->toBeTrue();

    // Reset → back to not customized.
    $this->deleteJson('/api/tables/users/filters')->assertNoContent();
    expect($this->getJson('/api/tables/users/columns')->json('data.filtersCustomized'))
        ->toBeFalse();
});

it('requires authentication to save or reset filters', function () {
    $this->postJson('/api/tables/users/filters', ['filterModel' => []])
        ->assertUnauthorized();

    $this->deleteJson('/api/tables/users/filters')->assertUnauthorized();
});

it('returns 404 when saving filters for an unregistered domain', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/nonexistent-domain/filters', ['filterModel' => []])
        ->assertNotFound()
        ->assertJson(['success' => false]);
});

it('returns 403 when saving filters without users.viewAny', function () {
    Sanctum::actingAs(userWithUserAbilities([])); // no abilities

    $this->postJson('/api/tables/users/filters', ['filterModel' => []])
        ->assertForbidden();
});

it('persists the applied filter model and merges it back into the config', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);
    $columnId = firstFilterableColumnId($this);
    $model = [$columnId => sampleFilter()];

    $this->postJson('/api/tables/users/filters', ['filterModel' => $model])
        ->assertOk()
        ->assertJsonPath('data.filterState', $model);

    $stored = UserTableFilter::query()
        ->where('user_id', $user->id)->where('domain', 'users')->firstOrFail();

    expect($stored->filters)->toBe($model)
        ->and($this->getJson('/api/tables/users/columns')->json('data.filterState'))
        ->toBe($model);
});

it('rejects a filter on a non-filterable column with 422', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/filters', [
        'filterModel' => ['ghost' => sampleFilter()],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filterModel.ghost');
});

it('ignores a stored filter for a column no longer filterable in PHP', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);
    $columnId = firstFilterableColumnId($this);

    // Simulate a filter saved for a column that was later removed/renamed.
    UserTableFilter::query()->create([
        'user_id' => $user->id,
        'domain' => 'users',
        'filters' => [
            'ghost' => sampleFilter(),
            $columnId => sampleFilter(),
        ],
    ]);

    $filterState = $this->getJson('/api/tables/users/columns')->json('data.filterState');

    expect($filterState)->toBe([$columnId => sampleFilter()]);
});

it('clears the saved filters when an empty model is saved', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);
    $columnId = firstFilterableColumnId($this);

    $this->postJson('/api/tables/users/filters', [
        'filterModel' => [$columnId => sampleFilter()],
    ])->assertOk();

    // An empty model (the user cleared every filter) removes the saved row.
    $this->postJson('/api/tables/users/filters', ['filterModel' => []])
        ->assertOk()
        ->assertJsonPath('data.filtersCustomized', false);

    $this->assertDatabaseMissing('user_table_filters', [
        'user_id' => $user->id,
        'domain' => 'users',
    ]);
});

it('resets filters and removes the row', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);
    $columnId = firstFilterableColumnId($this);

    UserTableFilter::query()->create([
        'user_id' => $user->id,
        'domain' => 'users',
        'filters' => [$columnId => sampleFilter()],
    ]);

    $this->deleteJson('/api/tables/users/filters')->assertNoContent();

    $this->assertDatabaseMissing('user_table_filters', [
        'user_id' => $user->id,
        'domain' => 'users',
    ]);

    expect($this->getJson('/api/tables/users/columns')->json('data.filtersCustomized'))
        ->toBeFalse();
});

it('keeps each user filters isolated from other users', function () {
    $userA = userWithUserAbilities(['viewAny']);
    $userB = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($userA);
    $columnId = firstFilterableColumnId($this);

    UserTableFilter::query()->create([
        'user_id' => $userA->id,
        'domain' => 'users',
        'filters' => [$columnId => sampleFilter()],
    ]);

    // User B sees no saved filters, unaffected by user A.
    Sanctum::actingAs($userB);
    expect($this->getJson('/api/tables/users/columns')->json('data.filtersCustomized'))
        ->toBeFalse();
});
