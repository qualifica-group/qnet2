<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature coverage for POST /api/tables/{domain}/values (spec 0004): the
 * Excel-like distinct-values endpoint. AC-001..AC-006.
 */

/**
 * Standard users.* permissions + a user granted the requested subset. Mirror
 * of the helper in TableRowsTest/TableConfigTest (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
/**
 * A non super-admin actor granted exactly the given `roles.*` abilities.
 * Mirror of the helper in RolesTableRowsTest (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
/**
 * Create a user owning a PersonalData card, optionally with a primary address
 * (geo names create the reference rows) and a primary contact. Mirror of the
 * helper in TableRowsPersonalDataTest (guarded for redeclare safety).
 *
 * @param  array{type?: 'individual'|'company', line1?: string, city?: string, region?: string, country?: string, contact_value?: string}  $opts
 */
if (! function_exists('userWithPersonalCard')) {
    function userWithPersonalCard(array $opts = []): User
    {
        $user = User::factory()->create();
        $type = $opts['type'] ?? 'individual';
        $card = PersonalData::factory()->{$type}()->for($user, 'personable')->create();

        $geo = ['country_id' => null, 'state_id' => null, 'city_id' => null, 'province_id' => null];

        if (isset($opts['country'])) {
            $geo['country_id'] = Country::factory()->create(['name' => $opts['country']])->id;
        }
        if (isset($opts['region'])) {
            $country = $geo['country_id'] ?? Country::factory()->create()->id;
            $geo['country_id'] = $country;
            $geo['state_id'] = State::factory()->create(['name' => $opts['region'], 'country_id' => $country])->id;
        }
        if (isset($opts['city'])) {
            $state = State::find($geo['state_id']) ?? State::factory()->create();
            $geo['state_id'] = $state->id;
            $geo['country_id'] = $state->country_id;
            $geo['city_id'] = City::factory()->forState($state)->create(['name' => $opts['city']])->id;
        }

        if (isset($opts['line1']) || $geo['country_id'] !== null || isset($opts['city'])) {
            Address::factory()->primary()->for($card, 'addressable')->create(array_merge($geo, [
                'line1' => $opts['line1'] ?? 'Via Roma 1',
            ]));
        }

        if (isset($opts['contact_value'])) {
            Contact::factory()->primary()->for($card, 'contactable')->create([
                'type' => 'email',
                'value' => $opts['contact_value'],
            ]);
        }

        return $user;
    }
}

it('requires authentication on the values endpoint', function () {
    $this->postJson('/api/tables/users/values', ['columnId' => 'email'])->assertUnauthorized();
});

it('returns 404 on the values endpoint for an unregistered domain (before validation)', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $this->postJson('/api/tables/nonexistent-domain/values', ['columnId' => 'nope'])
        ->assertNotFound()
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message']);
});

it('returns 403 on values without users.viewAny (before any query)', function () {
    $user = userWithUserAbilities([]);
    Sanctum::actingAs($user);

    $this->postJson('/api/tables/users/values', ['columnId' => 'email'])->assertForbidden();
});

it('returns 422 when columnId is missing', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/values', [])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('returns 422 when columnId is not filterable (whitelist)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    // avatar_url is declared but NOT filterable.
    $this->postJson('/api/tables/users/values', ['columnId' => 'avatar_url'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('returns 422 when a filterModel key is not filterable (whitelist, never reaches the query)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/values', [
        'columnId' => 'email',
        'filterModel' => [
            'avatar_url' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('filterModel.avatar_url');
});

it('returns distinct values (ordered) for a real column, from ALL rows not just the current page', function () {
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->create(['email' => 'zeta@example.com']);
    User::factory()->create(['email' => 'alpha@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'email'])->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('data.values'))->toContain('alpha@example.com', 'zeta@example.com')
        ->and($response->json('data.hasMore'))->toBeBool();
});

it('scopes distinct values by filters active on OTHER columns, ignoring the target column\'s own filter', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Role::create(['name' => 'editor']);
    $editorUser = User::factory()->create(['email' => 'editor@example.com']);
    $editorUser->assignRole('editor');
    User::factory()->create(['email' => 'other@example.com']); // no role
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', [
        'columnId' => 'email',
        'filterModel' => [
            'roles' => ['filterType' => 'set', 'values' => ['editor']],
            // Own-column filter must be IGNORED (Excel behaviour, AC-004):
            // this would otherwise exclude the very row we expect to see.
            'email' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'nonexistent-substring'],
        ],
    ])->assertOk();

    expect($response->json('data.values'))->toContain('editor@example.com')
        ->and($response->json('data.values'))->not->toContain('other@example.com');
});

it('filters distinct values by a case-insensitive substring search, wildcards escaped', function () {
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->create(['email' => 'FOO@example.com']);
    User::factory()->create(['email' => 'bar@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', [
        'columnId' => 'email',
        'search' => 'foo',
    ])->assertOk();

    expect($response->json('data.values'))->toContain('FOO@example.com')
        ->and($response->json('data.values'))->not->toContain('bar@example.com');
});

it('caps distinct values to the requested limit and flags hasMore', function () {
    $actor = userWithUserAbilities(['viewAny']); // + 1 more distinct email
    foreach (range(1, 5) as $i) {
        User::factory()->create(['email' => "user{$i}@example.com"]);
    }
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', [
        'columnId' => 'email',
        'limit' => 3,
    ])->assertOk();

    expect($response->json('data.values'))->toHaveCount(3)
        ->and($response->json('data.hasMore'))->toBeTrue();
});

it('returns 422 when limit exceeds the cap (200)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/values', ['columnId' => 'email', 'limit' => 201])
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

it('resolves a DERIVED column (roles) via the definition, scoped to the actor\'s assignable roles', function () {
    Role::create(['name' => 'editor']);
    Role::create(['name' => 'super-admin']);
    $actor = userWithUserAbilities(['viewAny']); // not a super-admin
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'roles'])->assertOk();

    expect($response->json('data.values'))->toContain('editor')
        ->and($response->json('data.values'))->not->toContain('super-admin');
});

it('resolves a DERIVED column (permissions on the roles domain) scoped to the assignable catalogue', function () {
    Permission::findOrCreate('roles.viewAny');
    // Not a registered form-module resource: governed elsewhere, never offered
    // as an assignable role permission.
    Permission::findOrCreate('widgets.publish');
    $actor = actorWithRoleAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/values', ['columnId' => 'permissions'])->assertOk();

    expect($response->json('data.values'))->toContain('roles.viewAny')
        ->and($response->json('data.values'))->not->toContain('widgets.publish');
});

it('always offers both boolean states for a boolean column, even when the data holds only one', function () {
    $actor = userWithUserAbilities(['viewAny']);
    // Every user (actor included) is active by default: the data holds only "1",
    // yet the filter must still offer "0" so the user can filter by either state.
    User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'is_active'])->assertOk();

    expect($response->json('data.values'))->toEqualCanonicalizing(['1', '0'])
        ->and($response->json('data.hasMore'))->toBeFalse();
});

it('resolves a set/enum column (locale) via its static options — non-regression', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'locale'])->assertOk();

    expect($response->json('data.values'))->not->toBeEmpty();
});

it('resolves a geo derived column (city) via the definition — non-regression', function () {
    $actor = userWithUserAbilities(['viewAny']);
    userWithPersonalCard(['city' => 'Rome']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'city'])->assertOk();

    expect($response->json('data.values'))->toContain('Roma');
});

// AC-016/017/018 (spec 0004/0005 bugfix): AGGREGATE/COMPUTED columns with NO
// real DB column must never crash /values. `primary_address` is a deliberate
// CONDITIONS-ONLY UX decision (spec 0005 follow-up): a formatted address
// string has no clean single-column match, so it declares hasFilterValues=
// false and TableService short-circuits to an empty result BEFORE building
// any query — the frontend never calls /values for it, but the endpoint
// stays safe even if it did. `primary_contact`/`users_count` DO resolve REAL
// distinct values via a dedicated query (never the generic engine's `SELECT
// DISTINCT` fallback, which would hit an unknown column).
it('short-circuits to an empty result for primary_address (conditions-only, no Set Filter)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    userWithPersonalCard(['line1' => 'Via Roma 1', 'city' => 'Rome']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'primary_address'])->assertOk();

    expect($response->json('data.values'))->toBe([])
        ->and($response->json('data.hasMore'))->toBeFalse();
});

it('resolves REAL distinct values for the COMPUTED primary_contact column', function () {
    $actor = userWithUserAbilities(['viewAny']);
    userWithPersonalCard(['contact_value' => 'a@example.com']);
    userWithPersonalCard(['contact_value' => 'b@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'primary_contact'])->assertOk();

    expect($response->json('data.values'))->toContain('a@example.com', 'b@example.com');
});

it('search narrows primary_contact distinct values on value/label', function () {
    $actor = userWithUserAbilities(['viewAny']);
    userWithPersonalCard(['contact_value' => 'needle@example.com']);
    userWithPersonalCard(['contact_value' => 'other@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', [
        'columnId' => 'primary_contact',
        'search' => 'needle',
    ])->assertOk();

    expect($response->json('data.values'))->toBe(['needle@example.com']);
});

it('scopes primary_contact distinct values by filters active on OTHER columns', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Role::create(['name' => 'editor']);
    $editor = userWithPersonalCard(['contact_value' => 'editor@example.com']);
    $editor->assignRole('editor');
    userWithPersonalCard(['contact_value' => 'other@example.com']); // no role
    Sanctum::actingAs($actor);

    $contactResponse = $this->postJson('/api/tables/users/values', [
        'columnId' => 'primary_contact',
        'filterModel' => ['roles' => ['filterType' => 'set', 'values' => ['editor']]],
    ])->assertOk();

    expect($contactResponse->json('data.values'))->toContain('editor@example.com')
        ->and($contactResponse->json('data.values'))->not->toContain('other@example.com');
});

it('resolves REAL distinct values for the AGGREGATE roles users_count column, cap+hasMore honoured', function () {
    $actor = actorWithRoleAbilities(['viewAny']);
    $noUsers = Role::create(['name' => 'empty']);
    $oneUser = Role::create(['name' => 'one']);
    $twoUsers = Role::create(['name' => 'two']);
    User::factory()->create()->assignRole($oneUser);
    User::factory()->count(2)->create()->each->assignRole($twoUsers);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/values', ['columnId' => 'users_count'])->assertOk();

    expect($response->json('data.values'))->toContain('0', '1', '2')
        ->and($response->json('data.hasMore'))->toBeFalse();

    $capped = $this->postJson('/api/tables/roles/values', [
        'columnId' => 'users_count',
        'limit' => 2,
    ])->assertOk();

    expect($capped->json('data.values'))->toHaveCount(2)
        ->and($capped->json('data.hasMore'))->toBeTrue();
});
