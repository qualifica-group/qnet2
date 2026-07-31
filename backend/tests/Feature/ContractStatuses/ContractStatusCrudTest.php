<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| CRUD — /api/contract-statuses (spec 0072)
|--------------------------------------------------------------------------
*/

if (! function_exists('contractStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
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
// create — POST /api/contract-statuses
// ---------------------------------------------------------------------------

it('create: 201 with the full data shape, system_key null, sort_order placed before the tail', function () {
    $actor = contractStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', [
        'name' => 'In revisione',
        'description' => 'Contratto in fase di revisione legale',
        'color' => 'blue',
        'group' => 'pending',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'In revisione')
        ->assertJsonPath('data.description', 'Contratto in fase di revisione legale')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.group', 'pending')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.system_key', null);

    $ordered = ContractStatus::query()->orderBy('sort_order')->pluck('system_key', 'name');

    expect($ordered->keys()->first())->toBe('Da validare')
        ->and($ordered->keys()->last())->toBe('Disdetto')
        ->and($ordered->keys()->get($ordered->keys()->count() - 2))->toBe('Annullato')
        ->and($ordered->keys()->get($ordered->keys()->count() - 3))->toBe('Sospeso');
});

it('create: 422 when name is missing', function () {
    $actor = contractStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', ['color' => 'blue', 'group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when color is missing', function () {
    $actor = contractStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', ['name' => 'Senza colore', 'group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

it('create: 422 when group is missing', function () {
    $actor = contractStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', ['name' => 'Senza gruppo', 'color' => 'blue'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('create: 422 when group is not one of open/pending/closed_won/closed_lost', function () {
    $actor = contractStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', ['name' => 'Gruppo invalido', 'color' => 'blue', 'group' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('create: 422 when name duplicates an existing status, no row created', function () {
    $actor = contractStatusUserWith(['create']);
    ContractStatus::factory()->create(['name' => 'Duplicata']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', ['name' => 'Duplicata', 'color' => 'blue', 'group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(ContractStatus::where('name', 'Duplicata')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// show — GET /api/contract-statuses/{contractStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = contractStatusUserWith(['view']);
    $target = ContractStatus::factory()->create(['name' => 'Attiva', 'color' => 'blue', 'sort_order' => 15]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/contract-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attiva')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 15);
});

it('show: 404 for a non-existent contract status', function () {
    $actor = contractStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contract-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/contract-statuses/{contractStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the contract status', function () {
    $actor = contractStatusUserWith(['update']);
    $target = ContractStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('contract_statuses', ['id' => $target->id, 'name' => 'After']);
});

it('update: 422 when name duplicates ANOTHER existing status', function () {
    $actor = contractStatusUserWith(['update']);
    ContractStatus::factory()->create(['name' => 'Taken']);
    $target = ContractStatus::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('update: 422 when color is submitted empty', function () {
    $actor = contractStatusUserWith(['update']);
    $target = ContractStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$target->id}", ['color' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('color');
});

// ---------------------------------------------------------------------------
// 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without contract-statuses.view', function () {
    $actor = contractStatusUserWith([]);
    $target = ContractStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/contract-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without contract-statuses.create, no row created', function () {
    $actor = contractStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', ['name' => 'Nope', 'color' => 'blue', 'group' => 'open'])->assertForbidden();

    // spec 0072 (D-2): the create migration seeds the 7 mandatory rows
    // unconditionally, so the post-403 baseline is 7, not 0.
    expect(ContractStatus::count())->toBe(7);
});

it('PATCH update: 403 without contract-statuses.update, no change persisted', function () {
    $actor = contractStatusUserWith([]);
    $target = ContractStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('contract_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without contract-statuses.delete, record still exists', function () {
    $actor = contractStatusUserWith([]);
    $target = ContractStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('contract_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/contract-statuses/{contractStatus} (AC-022, AC-023)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by a Contract, status AND contract still exist (AC-022)', function () {
    $actor = contractStatusUserWith(['delete']);
    $target = ContractStatus::factory()->create();
    $contract = Contract::factory()->create(['contract_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This contract status is used by a contract and cannot be deleted.');

    $this->assertDatabaseHas('contract_statuses', ['id' => $target->id]);
    $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
});

it('delete: 204 + removed when not referenced by anything (AC-023)', function () {
    $actor = contractStatusUserWith(['delete']);
    $target = ContractStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/contract-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('contract_statuses', ['id' => $target->id]);
});
