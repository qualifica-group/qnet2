<?php

use App\Models\User;
use App\Models\UserTablePreference;
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
/** @return array<string, mixed> */
function columnsConfig(object $test): array
{
    return $test->getJson('/api/tables/users/columns')->json('data.columns');
}

it('exposes order and width on every column in the default config', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    foreach (columnsConfig($this) as $column) {
        expect($column)->toHaveKeys(['id', 'visible', 'width', 'order'])
            ->and($column['order'])->toBeInt();
        // Columns default to no explicit width; the avatar column is the one
        // exception (a narrow fixed default declared in the table definition).
        if ($column['id'] === 'avatar_url') {
            expect($column['width'])->toBe(56);
        } else {
            expect($column['width'])->toBeNull();
        }
    }

    // Default order follows declaration order.
    $ids = collect(columnsConfig($this))->pluck('id')->all();
    expect($ids)->toBe([
        'id', 'avatar_url', 'name', 'email', 'roles', 'locale', 'is_active', 'created_at',
        'user_type', 'primary_address', 'country', 'region', 'province', 'city', 'primary_contact',
        // spec 0015 — the 9 employment-derived columns.
        'business_function', 'company', 'operational_site', 'relationship_type',
        'qualification_type', 'is_manager', 'reports_to', 'hired_at', 'terminated_at',
    ]);
});

it('flags the config as customized only when a saved layout exists', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    // No preferences yet → not customized.
    expect($this->getJson('/api/tables/users/columns')->json('data.customized'))
        ->toBeFalse();

    $this->postJson('/api/tables/users/preferences', [
        'columns' => [['id' => 'name', 'width' => 400]],
    ])->assertOk()->assertJsonPath('data.customized', true);

    // Persisted → still customized on a fresh read.
    expect($this->getJson('/api/tables/users/columns')->json('data.customized'))
        ->toBeTrue();

    // Reset → back to not customized.
    $this->deleteJson('/api/tables/users/preferences')->assertNoContent();
    expect($this->getJson('/api/tables/users/columns')->json('data.customized'))
        ->toBeFalse();
});

it('requires authentication to save or reset preferences', function () {
    $this->postJson('/api/tables/users/preferences', ['columns' => [['id' => 'name']]])
        ->assertUnauthorized();

    $this->deleteJson('/api/tables/users/preferences')->assertUnauthorized();
});

it('returns 404 when saving preferences for an unregistered domain', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/nonexistent-domain/preferences', ['columns' => [['id' => 'name']]])
        ->assertNotFound()
        ->assertJson(['success' => false]);
});

it('returns 403 when saving preferences without users.viewAny', function () {
    Sanctum::actingAs(userWithUserAbilities([])); // no abilities

    $this->postJson('/api/tables/users/preferences', ['columns' => [['id' => 'name', 'width' => 200]]])
        ->assertForbidden();
});

it('persists only the deviations from the default (sparse delta)', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    // Default order is id=1, avatar_url=2, name=3, email=4, roles=5, locale=6,
    // is_active=7, created_at=8. Only orders that deviate from the default are persisted.
    $this->postJson('/api/tables/users/preferences', [
        'columns' => [
            ['id' => 'email', 'visible' => false, 'order' => 1],
            ['id' => 'name', 'width' => 400, 'order' => 3], // order 3 == default → not stored
            ['id' => 'id', 'order' => 2],
            ['id' => 'roles', 'order' => 5],
            ['id' => 'locale', 'order' => 6],
            ['id' => 'created_at', 'order' => 8],
        ],
    ])->assertOk();

    $stored = UserTablePreference::query()
        ->where('user_id', $user->id)->where('domain', 'users')->firstOrFail();

    // name keeps ONLY width (its order equals the default, so order is not stored).
    expect($stored->preferences)->toBe([
        'email' => ['visible' => false, 'order' => 1],
        'name' => ['width' => 400],
        'id' => ['order' => 2],
    ]);
});

it('merges saved preferences into the columns config and re-orders columns', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $this->postJson('/api/tables/users/preferences', [
        'columns' => [
            ['id' => 'email', 'visible' => false, 'order' => 1],
            ['id' => 'name', 'width' => 400, 'order' => 2],
            ['id' => 'id', 'order' => 3],
            ['id' => 'roles', 'order' => 4],
            ['id' => 'locale', 'order' => 5],
            ['id' => 'created_at', 'order' => 6],
        ],
    ])->assertOk();

    $columns = columnsConfig($this);
    $byId = collect($columns)->keyBy('id');

    // Columns come back ordered by the effective order: email first now.
    expect(collect($columns)->pluck('id')->first())->toBe('email')
        ->and($byId['email']['visible'])->toBeFalse()
        ->and($byId['email']['order'])->toBe(1)
        ->and($byId['name']['width'])->toBe(400)
        // Structural property is untouched by preferences.
        ->and($byId['name']['sortable'])->toBeTrue();
});

it('rejects a preference for an unknown column with 422', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/preferences', ['columns' => [['id' => 'ghost', 'width' => 200]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('columns.0.id');
});

it('rejects an out-of-bounds width with 422', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/preferences', ['columns' => [['id' => 'name', 'width' => 5]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('columns.0.width');

    $this->postJson('/api/tables/users/preferences', ['columns' => [['id' => 'name', 'width' => 99999]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('columns.0.width');
});

it('ignores a stored preference for a column no longer defined in PHP', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    // Simulate a column that was renamed/removed in the definition after the user
    // had saved a preference for it.
    UserTablePreference::query()->create([
        'user_id' => $user->id,
        'domain' => 'users',
        'preferences' => [
            'ghost' => ['visible' => false, 'order' => 1],
            'name' => ['width' => 300],
        ],
    ]);

    $byId = collect(columnsConfig($this))->keyBy('id');

    // No error; ghost is absent; the still-valid override is applied.
    expect($byId->has('ghost'))->toBeFalse()
        ->and($byId['name']['width'])->toBe(300);
});

it('resets preferences to the PHP default and removes the row', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    UserTablePreference::query()->create([
        'user_id' => $user->id,
        'domain' => 'users',
        'preferences' => ['name' => ['width' => 400]],
    ]);

    $this->deleteJson('/api/tables/users/preferences')->assertNoContent();

    $this->assertDatabaseMissing('user_table_preferences', [
        'user_id' => $user->id,
        'domain' => 'users',
    ]);

    $byId = collect(columnsConfig($this))->keyBy('id');
    expect($byId['name']['width'])->toBeNull(); // back to default
});

it('keeps each user preferences isolated from other users', function () {
    $userA = userWithUserAbilities(['viewAny']);
    $userB = userWithUserAbilities(['viewAny']);

    UserTablePreference::query()->create([
        'user_id' => $userA->id,
        'domain' => 'users',
        'preferences' => ['name' => ['width' => 400]],
    ]);

    // User B sees the default, unaffected by user A's saved layout.
    Sanctum::actingAs($userB);
    $byId = collect(columnsConfig($this))->keyBy('id');
    expect($byId['name']['width'])->toBeNull();
});
