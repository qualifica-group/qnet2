<?php

use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Bugfix coverage (spec 0004/0005): the COMPUTED columns exposed through
 * AG Grid's `agMultiColumnFilter` (`primary_address`/`primary_contact` on
 * users, `users_count` on roles) send `{filterType:'multi', filterModels:
 * [set|null, condition|null]}` to POST /rows. Before the fix, applyDerivedFilter
 * read only the top-level `filter`/`type` keys, so BOTH the condition (a
 * regression vs the pre-multi flat shape) and the Set selection were silently
 * ignored. These tests reproduce both breakages and assert the fix.
 */

/**
 * Standard users.* permissions + a user granted the requested subset. Mirror
 * of the helper in TableRowsTest (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('rowsPayload')) {
    function rowsPayload(array $overrides = []): array
    {
        return array_merge([
            'startRow' => 0,
            'endRow' => 25,
        ], $overrides);
    }
}

/**
 * A non super-admin actor granted exactly the given `roles.*` abilities.
 * Mirror of the helper in RolesTableRowsTest (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
it('applies a FLAT condition on primary_address (contains) — conditions-only, no multi wrap', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $needle = User::factory()->create();
    PersonalData::factory()->individual()->for($needle, 'personable')->create()
        ->addresses()->create(['line1' => 'Via Garibaldi 42', 'is_primary' => true]);
    $other = User::factory()->create();
    PersonalData::factory()->individual()->for($other, 'personable')->create()
        ->addresses()->create(['line1' => 'Main Street 1', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    // primary_address is CONDITIONS-ONLY (spec 0005): the frontend renders a
    // plain typed filter, never agMultiColumnFilter, so the payload is the
    // flat pre-multi shape — applyAddressFilter is called directly.
    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'primary_address' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Garibaldi'],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($needle->id);
});

it('applies a multi CONDITION sub-model on primary_contact (contains) — regression', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $needle = User::factory()->create();
    PersonalData::factory()->individual()->for($needle, 'personable')->create()
        ->contacts()->create(['type' => 'email', 'value' => 'needle@example.com', 'is_primary' => true]);
    $other = User::factory()->create();
    PersonalData::factory()->individual()->for($other, 'personable')->create()
        ->contacts()->create(['type' => 'email', 'value' => 'other@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'primary_contact' => [
                'filterType' => 'multi',
                'filterModels' => [
                    null,
                    ['filterType' => 'text', 'type' => 'contains', 'filter' => 'needle'],
                ],
            ],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($needle->id);
});

it('applies a multi SET sub-model on primary_contact (checklist selection)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $selected = User::factory()->create();
    PersonalData::factory()->individual()->for($selected, 'personable')->create()
        ->contacts()->create(['type' => 'email', 'value' => 'selected@example.com', 'is_primary' => true]);
    $unselected = User::factory()->create();
    PersonalData::factory()->individual()->for($unselected, 'personable')->create()
        ->contacts()->create(['type' => 'email', 'value' => 'unselected@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'primary_contact' => [
                'filterType' => 'multi',
                'filterModels' => [
                    ['filterType' => 'set', 'values' => ['selected@example.com']],
                    null,
                ],
            ],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($selected->id);
});

it('applies BOTH multi sub-models on primary_contact in AND', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $match = User::factory()->create();
    PersonalData::factory()->individual()->for($match, 'personable')->create()
        ->contacts()->create(['type' => 'email', 'value' => 'match@example.com', 'is_primary' => true]);
    // Same set value but fails the condition (no "match" substring elsewhere needed since value IS match@).
    $onlyInSet = User::factory()->create();
    PersonalData::factory()->individual()->for($onlyInSet, 'personable')->create()
        ->contacts()->create(['type' => 'email', 'value' => 'other@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'primary_contact' => [
                'filterType' => 'multi',
                'filterModels' => [
                    ['filterType' => 'set', 'values' => ['match@example.com', 'other@example.com']],
                    ['filterType' => 'text', 'type' => 'contains', 'filter' => 'match'],
                ],
            ],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('applies a multi CONDITION sub-model on roles users_count (inRange) — regression', function () {
    $actor = actorWithRoleAbilities(['viewAny']);
    $three = Role::create(['name' => 'three']);
    $one = Role::create(['name' => 'one']);
    User::factory()->count(3)->create()->each->assignRole($three);
    User::factory()->create()->assignRole($one);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/rows', rowsPayload([
        'filterModel' => [
            'users_count' => [
                'filterType' => 'multi',
                'filterModels' => [
                    null,
                    ['filterType' => 'number', 'type' => 'inRange', 'filter' => 2, 'filterTo' => 3],
                ],
            ],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.name'))->toBe('three');
});

it('applies a multi SET sub-model on roles users_count (checklist of counts)', function () {
    $actor = actorWithRoleAbilities(['viewAny']);
    $two = Role::create(['name' => 'two']);
    $one = Role::create(['name' => 'one']);
    $zero = Role::create(['name' => 'zero']);
    User::factory()->count(2)->create()->each->assignRole($two);
    User::factory()->create()->assignRole($one);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/rows', rowsPayload([
        'filterModel' => [
            'users_count' => [
                'filterType' => 'multi',
                'filterModels' => [
                    ['filterType' => 'set', 'values' => ['0', '2']],
                    null,
                ],
            ],
        ],
    ]))->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['two', 'zero']);
});

it('applies BOTH multi sub-models on roles users_count in AND', function () {
    $actor = actorWithRoleAbilities(['viewAny']);
    $two = Role::create(['name' => 'two']);
    $three = Role::create(['name' => 'three']);
    User::factory()->count(2)->create()->each->assignRole($two);
    User::factory()->count(3)->create()->each->assignRole($three);
    Sanctum::actingAs($actor);

    // Set picks {two,three}; condition (>=3) keeps only 'three'.
    $response = $this->postJson('/api/tables/roles/rows', rowsPayload([
        'filterModel' => [
            'users_count' => [
                'filterType' => 'multi',
                'filterModels' => [
                    ['filterType' => 'set', 'values' => ['2', '3']],
                    ['filterType' => 'number', 'type' => 'greaterThanOrEqual', 'filter' => 3],
                ],
            ],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.name'))->toBe('three');
});
