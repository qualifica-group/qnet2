<?php

use App\Enums\ContractStatusGroup;
use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-status rules for `contract-statuses` (spec 0072, D-2)
|--------------------------------------------------------------------------
|
| The 4 mandatory rows ("Da validare"/"Sospeso"/"Annullato"/"Disdetto") are
| seeded unconditionally by the create-table migration, so every test here
| reads them back rather than creating them (system_key is UNIQUE — a second
| 'new'/'suspended'/'cancelled'/'terminated' row would violate it).
*/

if (! function_exists('contractStatusSystemUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusSystemUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("contract-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contract-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-019 — PATCH name+color on a system row is allowed
// ---------------------------------------------------------------------------

it('update: 200 when a system row changes ONLY name/color (AC-019)', function () {
    $actor = contractStatusSystemUserWith(['update']);
    $suspended = ContractStatus::where('system_key', 'suspended')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$suspended->id}", ['name' => 'Sospeso (nuovo)', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Sospeso (nuovo)')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.system_key', 'suspended');

    $this->assertDatabaseHas('contract_statuses', ['id' => $suspended->id, 'name' => 'Sospeso (nuovo)', 'color' => 'teal']);
});

// ---------------------------------------------------------------------------
// AC-020 — any other field on a system row is rejected, nothing persists
// ---------------------------------------------------------------------------

it('update: 422 when a system row payload includes group, nothing persists (AC-020)', function () {
    $actor = contractStatusSystemUserWith(['update']);
    $newStatus = ContractStatus::where('system_key', 'new')->firstOrFail();
    $originalGroup = $newStatus->group->value;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$newStatus->id}", ['group' => 'closed_lost'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('contract_statuses', ['id' => $newStatus->id, 'group' => $originalGroup]);
});

it('update: 422 when a system row payload includes is_active, nothing persists (AC-020)', function () {
    $actor = contractStatusSystemUserWith(['update']);
    $terminated = ContractStatus::where('system_key', 'terminated')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$terminated->id}", ['is_active' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('contract_statuses', ['id' => $terminated->id, 'is_active' => true]);
});

it('update: 422 when a system row payload includes is_default, nothing persists (AC-020)', function () {
    $actor = contractStatusSystemUserWith(['update']);
    $cancelled = ContractStatus::where('system_key', 'cancelled')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$cancelled->id}", ['is_default' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('contract_statuses', ['id' => $cancelled->id, 'is_default' => false]);
});

it('update: 422 when a system row payload includes description, nothing persists (AC-020)', function () {
    $actor = contractStatusSystemUserWith(['update']);
    $newStatus = ContractStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$newStatus->id}", ['description' => 'Nuovo testo'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('contract_statuses', ['id' => $newStatus->id, 'description' => null]);
});

it('update: a custom row accepts group (AC-020)', function () {
    $actor = contractStatusSystemUserWith(['update']);
    $custom = ContractStatus::factory()->create(['group' => ContractStatusGroup::Pending]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$custom->id}", ['group' => 'closed_won'])
        ->assertOk()
        ->assertJsonPath('data.group', 'closed_won');
});

// ---------------------------------------------------------------------------
// AC-021 — delete guard on every system row, custom rows unaffected
// ---------------------------------------------------------------------------

it('delete: 422 on the system "new" row, message names it, row persists (AC-021)', function () {
    $actor = contractStatusSystemUserWith(['delete']);
    $newStatus = ContractStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$newStatus->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$newStatus->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('contract_statuses', ['id' => $newStatus->id]);
});

it('delete: 422 on the system "suspended" row (AC-021)', function () {
    $actor = contractStatusSystemUserWith(['delete']);
    $suspended = ContractStatus::where('system_key', 'suspended')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$suspended->id}")->assertStatus(422);

    $this->assertDatabaseHas('contract_statuses', ['id' => $suspended->id]);
});

it('delete: 422 on the system "cancelled" row (AC-021)', function () {
    $actor = contractStatusSystemUserWith(['delete']);
    $cancelled = ContractStatus::where('system_key', 'cancelled')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$cancelled->id}")->assertStatus(422);

    $this->assertDatabaseHas('contract_statuses', ['id' => $cancelled->id]);
});

it('delete: 422 on the system "terminated" row (AC-021)', function () {
    $actor = contractStatusSystemUserWith(['delete']);
    $terminated = ContractStatus::where('system_key', 'terminated')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$terminated->id}")->assertStatus(422);

    $this->assertDatabaseHas('contract_statuses', ['id' => $terminated->id]);
});

it('delete: a custom, unreferenced row still returns 204 (invariant, AC-021)', function () {
    $actor = contractStatusSystemUserWith(['delete']);
    $custom = ContractStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$custom->id}")->assertNoContent();
});
