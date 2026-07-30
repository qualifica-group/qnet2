<?php

use App\Models\RewardStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-007 — every write/read ability 403s without the matching permission
// ---------------------------------------------------------------------------

it('GET show: 403 without reward-statuses.view, no data leaked (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/reward-statuses/{$target->id}")->assertForbidden();
});

it('POST store: 403 without reward-statuses.create — a basic-rule-VALID payload still 403s, not 201 (AC-007 precedence)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $countBefore = RewardStatus::count();

    $this->postJson('/api/reward-statuses', ['name' => 'Nope', 'color' => 'blue'])->assertForbidden();

    expect(RewardStatus::count())->toBe($countBefore);
});

it('PATCH update: 403 without reward-statuses.update — a basic-rule-VALID payload still 403s, no change persisted (AC-007 precedence)', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without reward-statuses.delete, record still exists (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id]);
});

it('GET for-select: 403 without reward-statuses.viewAny (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-statuses/for-select')->assertForbidden();
});

it('GET tables columns: 403 without reward-statuses.viewAny (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/reward-statuses/columns')->assertForbidden();
});

it('POST export: 403 without reward-statuses.export (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/reward-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();
});

it('POST reorder: 403 without reward-statuses.update (AC-007)', function () {
    $actor = rewardStatusUserWith([]);
    $custom = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-statuses/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-013b — mandatory fields bypass the DB field-permission matrix
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = rewardStatusUserWith([]);
    $target = RewardStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['color' => 'blue'])->assertForbidden();
});

it('update: a restrictive DB row on `color` is ignored (mandatory bypass), write succeeds (AC-013b)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("reward-statuses.{$ability}");
    }

    $role = Role::create(['name' => 'reward-status-color-locked']);
    $role->givePermissionTo(['reward-statuses.view', 'reward-statuses.update']);
    $role->fieldPermissions()->create([
        'resource' => 'reward-statuses',
        'field' => 'color',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = RewardStatus::factory()->create(['name' => 'Original', 'color' => 'slate']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['color' => 'green'])
        ->assertOk()
        ->assertJsonPath('data.color', 'green');

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id, 'color' => 'green']);
});

it('update: a restrictive DB row on `name` is ignored (mandatory bypass), write succeeds (AC-013b)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("reward-statuses.{$ability}");
    }

    $role = Role::create(['name' => 'reward-status-name-locked']);
    $role->givePermissionTo(['reward-statuses.view', 'reward-statuses.update']);
    $role->fieldPermissions()->create([
        'resource' => 'reward-statuses',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = RewardStatus::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-statuses/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('reward_statuses', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// AC-008 — permissions:sync creates the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 reward-statuses.* permissions (AC-008)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "reward-statuses.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-016 — navigation node gated by reward-statuses.view
// ---------------------------------------------------------------------------

it('navigation: the reward-statuses node only shows with reward-statuses.view (AC-016)', function () {
    Permission::findOrCreate('reward-statuses.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('reward-statuses');

    $withView = User::factory()->create();
    $withView->givePermissionTo('reward-statuses.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('reward-statuses');
});
