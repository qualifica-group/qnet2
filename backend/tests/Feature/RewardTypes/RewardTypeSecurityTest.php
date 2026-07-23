<?php

use App\Models\RewardType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-types.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-007 — every write/read ability 403s without the matching permission
// ---------------------------------------------------------------------------

it('GET show: 403 without reward-types.view, no data leaked (AC-007)', function () {
    $actor = rewardTypeUserWith([]);
    $target = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/reward-types/{$target->id}")->assertForbidden();
});

it('POST store: 403 without reward-types.create — a basic-rule-VALID payload still 403s, not 201 (AC-007 precedence)', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/reward-types', ['name' => 'Nope', 'color' => 'blue'])->assertForbidden();

    expect(RewardType::count())->toBe(0);
});

it('PATCH update: 403 without reward-types.update — a basic-rule-VALID payload still 403s, no change persisted (AC-007 precedence)', function () {
    $actor = rewardTypeUserWith([]);
    $target = RewardType::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('reward_types', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without reward-types.delete, record still exists (AC-007)', function () {
    $actor = rewardTypeUserWith([]);
    $target = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/reward-types/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('reward_types', ['id' => $target->id]);
});

it('GET for-select: 403 without reward-types.viewAny (AC-007)', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/reward-types/for-select')->assertForbidden();
});

it('GET tables columns: 403 without reward-types.viewAny (AC-007)', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/reward-types/columns')->assertForbidden();
});

it('POST export: 403 without reward-types.export (AC-007)', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/reward-types', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-007 — the 403 has precedence over a field-level 422
// ---------------------------------------------------------------------------
//
// NOTE on scope: StoreRewardTypeRequest/UpdateRewardTypeRequest::authorize()
// is hard-coded `true` (base authz stays in the controller), so basic
// FormRequest rule failures (`required`, `unique`, ...) run and 422
// UNCONDITIONALLY, before the controller's authorize() ever executes — a
// request with a rule-invalid payload (e.g. missing `name`) 422s regardless
// of permission. The "403 takes precedence over 422" clause of AC-007 is
// therefore about the FIELD-PERMISSION 422 (EnforcesFieldPermissions), which
// explicitly defers to the base-ability check
// (`if (! $actor->can("{resource}.{ability}")) return;`) — not about basic
// validation. The two tests above already cover that: a basic-rule-VALID
// payload from a no-permission actor still 403s, never reaching a 422.

// ---------------------------------------------------------------------------
// AC-013b — mandatory fields bypass the DB field-permission matrix
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = rewardTypeUserWith([]);
    $target = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['color' => 'blue'])->assertForbidden();
});

/**
 * AC-013b (spec amended 2026-07-23): `color` is declared `mandatory: true`
 * in RewardTypesAuthorization::fields() (D-5). AbstractResourceAuthorization
 * ::fieldPermissions() is `final` (spec 0008) and unconditionally bypasses
 * the DB role_field_permissions matrix for ANY mandatory field ("the DB
 * matrix may never narrow them, so they bypass the intersect and keep the
 * full ceiling") — the exact mechanism OpportunityStatusSecurityTest
 * documents for `name`. Because D-5 makes `color` mandatory too, a
 * restrictive DB row on `color` is bypassed the same way: the write below
 * returns 200, by design — not a security hole, the server-side twin of the
 * locked, disabled checkboxes the Role matrix shows for a mandatory field.
 */
it('update: a restrictive DB row on `color` is ignored (mandatory bypass), write succeeds (AC-013b)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("reward-types.{$ability}");
    }

    $role = Role::create(['name' => 'reward-type-color-locked']);
    $role->givePermissionTo(['reward-types.view', 'reward-types.update']);
    $role->fieldPermissions()->create([
        'resource' => 'reward-types',
        'field' => 'color',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = RewardType::factory()->create(['name' => 'Original', 'color' => 'slate']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['color' => 'green'])
        ->assertOk()
        ->assertJsonPath('data.color', 'green');

    $this->assertDatabaseHas('reward_types', ['id' => $target->id, 'color' => 'green']);
});

/**
 * Mirror of the above on `name` (also `mandatory: true`): the same bypass
 * applies to BOTH mandatory fields of this resource, so neither reads as a
 * one-off/accidental pass tomorrow.
 */
it('update: a restrictive DB row on `name` is ignored (mandatory bypass), write succeeds (AC-013b)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("reward-types.{$ability}");
    }

    $role = Role::create(['name' => 'reward-type-name-locked']);
    $role->givePermissionTo(['reward-types.view', 'reward-types.update']);
    $role->fieldPermissions()->create([
        'resource' => 'reward-types',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = RewardType::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/reward-types/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('reward_types', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// AC-008 — permissions:sync creates the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 reward-types.* permissions (AC-008)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "reward-types.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-015 — navigation node gated by reward-types.view
// ---------------------------------------------------------------------------

it('navigation: the reward-types node only shows with reward-types.view (AC-015)', function () {
    Permission::findOrCreate('reward-types.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->not->toContain('reward-types');

    $withView = User::factory()->create();
    $withView->givePermissionTo('reward-types.view');
    Sanctum::actingAs($withView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->toContain('reward-types');
});
