<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** Spec 0370: last credential login on `users.last_login_at`. */
if (! function_exists('lastLoginGridActor')) {
    function lastLoginGridActor(): User
    {
        Permission::findOrCreate('users.viewAny');
        Permission::findOrCreate('users.view');
        $actor = User::factory()->create();
        $actor->givePermissionTo(['users.viewAny', 'users.view']);

        return $actor;
    }
}

it('AC-001: a successful login records last_login_at without touching updated_at or the activity log', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $updatedAt = $user->updated_at;
    $activities = Activity::count();

    $this->travel(5)->minutes();
    $this->freezeTime();

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

    $user->refresh();
    expect($user->last_login_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($user->updated_at->toDateTimeString())->toBe($updatedAt->toDateTimeString())
        ->and(Activity::count())->toBe($activities);
});

it('AC-002: a failed login leaves last_login_at untouched', function () {
    $wrongPassword = User::factory()->create(['password' => bcrypt('password')]);
    $inactive = User::factory()->inactive()->create(['password' => bcrypt('password')]);

    $this->postJson('/api/auth/login', ['email' => $wrongPassword->email, 'password' => 'wrong'])->assertStatus(422);
    $this->postJson('/api/auth/login', ['email' => $inactive->email, 'password' => 'password'])->assertStatus(422);

    expect($wrongPassword->fresh()->last_login_at)->toBeNull()
        ->and($inactive->fresh()->last_login_at)->toBeNull();
});

it('AC-003: impersonation does not record a login for the target', function () {
    Permission::findOrCreate('users.impersonate');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.impersonate');
    $target = User::factory()->create();

    $this->withToken($actor->createToken('api')->plainTextToken)
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk();

    expect($target->fresh()->last_login_at)->toBeNull();
});

it('AC-003: client-login does not record a QNet login', function () {
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create(['password' => bcrypt('password')]);

    callWithToken('POST', '/api/auth/client-login', $key, ['email' => $user->email, 'password' => 'password'])
        ->assertOk();

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('AC-004: the user detail exposes last_login_at', function () {
    $actor = lastLoginGridActor();
    $user = User::factory()->create();
    $user->forceFill(['last_login_at' => '2026-11-01 09:30:00'])->save();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.last_login_at', $user->fresh()->last_login_at->toJSON());
});

it('AC-004: the users grid exposes a sortable last_login_at column', function () {
    $actor = lastLoginGridActor();
    $older = User::factory()->create(['name' => 'Older Login']);
    $older->forceFill(['last_login_at' => '2026-10-01 08:00:00'])->save();
    $newer = User::factory()->create(['name' => 'Newer Login']);
    $newer->forceFill(['last_login_at' => '2026-11-01 08:00:00'])->save();
    Sanctum::actingAs($actor);

    $column = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'last_login_at');

    expect($column['type'])->toBe('datetime')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterType'])->toBe('date');

    $names = collect($this->postJson('/api/tables/users/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'sortModel' => [['colId' => 'last_login_at', 'sort' => 'desc']],
    ])->assertOk()->json('items'))->pluck('name');

    expect($names->take(2)->all())->toBe(['Newer Login', 'Older Login']);
});
