<?php

use App\Models\BusinessFunction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0310: POST /api/users/bulk-activation, per-user outcome through the
 * single-user write path.
 */
const BULK_ACTIVATION_URL = '/api/users/bulk-activation';

function bulkActivationActor(): User
{
    return userWithUserAbilities(['viewAny', 'view', 'update']);
}

function inactiveMember(): User
{
    $user = User::factory()->create(['is_active' => false]);
    $user->businessFunctions()->attach(BusinessFunction::factory()->create()->id);

    return $user;
}

it('0310 AC-001: deactivates every selected user', function () {
    Sanctum::actingAs(bulkActivationActor());
    $targets = User::factory()->count(3)->create();
    $ids = $targets->pluck('id')->all();

    $this->postJson(BULK_ACTIVATION_URL, ['is_active' => false, 'ids' => $ids])
        ->assertOk()
        ->assertJsonPath('data.updated', $ids)
        ->assertJsonPath('data.failed', []);

    expect(User::query()->whereKey($ids)->where('is_active', true)->exists())->toBeFalse();
});

it('0310 AC-002/AC-003: activates the members and reports the user without a business function', function () {
    Sanctum::actingAs(bulkActivationActor());
    $member = inactiveMember();
    $orphan = User::factory()->create(['is_active' => false]);

    $this->postJson(BULK_ACTIVATION_URL, ['is_active' => true, 'ids' => [$member->id, $orphan->id]])
        ->assertOk()
        ->assertJsonPath('data.updated', [$member->id])
        ->assertJsonPath('data.failed.0.id', $orphan->id)
        ->assertJsonPath('data.failed.0.name', $orphan->name)
        ->assertJsonPath('data.failed.0.message', __('users.employment.business_function_required'));

    expect($member->fresh()->is_active)->toBeTrue()
        ->and($orphan->fresh()->is_active)->toBeFalse();
});

it('0310 AC-004: a user whose termination date has passed stays inactive and is reported', function () {
    Sanctum::actingAs(bulkActivationActor());
    $terminated = User::factory()->withEmployment(fn ($factory) => $factory->state(['terminated_at' => '2024-01-01']))
        ->create(['is_active' => false]);
    $terminated->businessFunctions()->attach(BusinessFunction::factory()->create()->id);

    $this->postJson(BULK_ACTIVATION_URL, ['is_active' => true, 'ids' => [$terminated->id]])
        ->assertOk()
        ->assertJsonPath('data.updated', [])
        ->assertJsonPath('data.failed.0.message', __('users.bulk_activation.terminated'));

    expect($terminated->fresh()->is_active)->toBeFalse();
});

it('0310 AC-005/AC-006: never deactivates the actor and reports an unknown id, the others still change', function () {
    $actor = bulkActivationActor();
    Sanctum::actingAs($actor);
    $target = User::factory()->create();
    $missingId = User::query()->max('id') + 100;

    $this->postJson(BULK_ACTIVATION_URL, ['is_active' => false, 'ids' => [$actor->id, $missingId, $target->id]])
        ->assertOk()
        ->assertJsonPath('data.updated', [$target->id])
        ->assertJsonPath('data.failed.0.message', __('users.bulk_activation.self'))
        ->assertJsonPath('data.failed.1.id', $missingId)
        ->assertJsonPath('data.failed.1.message', __('users.bulk_activation.not_found'));

    expect($actor->fresh()->is_active)->toBeTrue()
        ->and($target->fresh()->is_active)->toBeFalse();
});

it('0310 AC-007: an actor without users.update gets 403 and nothing changes', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny', 'view']));
    $target = User::factory()->create();

    $this->postJson(BULK_ACTIVATION_URL, ['is_active' => false, 'ids' => [$target->id]])->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

it('0310 AC-008: a read-only is_active field leaves the user unchanged and reported', function () {
    foreach (['users.viewAny', 'users.view', 'users.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $role = Role::findOrCreate('bulk-'.Str::random(8), 'web');
    $role->givePermissionTo(['users.viewAny', 'users.view', 'users.update']);
    $role->fieldPermissions()->create([
        'resource' => 'users', 'field' => 'is_active', 'visible' => true, 'editable' => false, 'required' => false,
    ]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);
    $target = User::factory()->create();

    $this->postJson(BULK_ACTIVATION_URL, ['is_active' => false, 'ids' => [$target->id]])
        ->assertOk()
        ->assertJsonPath('data.updated', [])
        ->assertJsonPath('data.failed.0.message', __('users.bulk_activation.forbidden'));

    expect($target->fresh()->is_active)->toBeTrue();
});

it('0310: validates the payload', function (array $payload, string $error) {
    Sanctum::actingAs(bulkActivationActor());

    $this->postJson(BULK_ACTIVATION_URL, $payload)->assertStatus(422)->assertJsonValidationErrors([$error]);
})->with([
    'missing state' => [['ids' => [1]], 'is_active'],
    'empty ids' => [['is_active' => true, 'ids' => []], 'ids'],
    'too many ids' => [['is_active' => true, 'ids' => range(1, 201)], 'ids'],
    'non-integer id' => [['is_active' => true, 'ids' => ['x']], 'ids.0'],
]);
