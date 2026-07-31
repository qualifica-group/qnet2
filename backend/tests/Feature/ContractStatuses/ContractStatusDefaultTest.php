<?php

use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| BR-5 — exclusive `is_default` on `contract_statuses` (spec 0072)
|--------------------------------------------------------------------------
|
| The create migration seeds "Da validare" (system, `new`) as the ONLY
| `is_default = true` row, so every test here starts from that baseline.
*/

if (! function_exists('contractStatusDefaultUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusDefaultUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
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
// AC-024 — exclusive default on create and on update
// ---------------------------------------------------------------------------

it('create: is_default=true on a new row unsets the previous default, exactly one remains (AC-024)', function () {
    $actor = contractStatusDefaultUserWith(['create']);
    $priorDefault = ContractStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/contract-statuses', [
        'name' => 'Nuovo predefinito',
        'color' => 'blue',
        'group' => 'open',
        'is_default' => true,
    ])->assertCreated();

    $newId = $response->json('data.id');

    $this->assertDatabaseHas('contract_statuses', ['id' => $newId, 'is_default' => true]);
    $this->assertDatabaseHas('contract_statuses', ['id' => $priorDefault->id, 'is_default' => false]);
    expect(ContractStatus::where('is_default', true)->count())->toBe(1);
});

it('update: is_default=true on a custom row unsets the previous default, exactly one remains (AC-024)', function () {
    $actor = contractStatusDefaultUserWith(['update']);
    $priorDefault = ContractStatus::where('system_key', 'new')->firstOrFail();
    $custom = ContractStatus::factory()->create(['is_default' => false, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$custom->id}", ['is_default' => true])
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    $this->assertDatabaseHas('contract_statuses', ['id' => $custom->id, 'is_default' => true]);
    $this->assertDatabaseHas('contract_statuses', ['id' => $priorDefault->id, 'is_default' => false]);
    expect(ContractStatus::where('is_default', true)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-025 — is_default=true with is_active=false is rejected
// ---------------------------------------------------------------------------

it('create: 422 when is_default=true and is_active=false, no row created (AC-025)', function () {
    $actor = contractStatusDefaultUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses', [
        'name' => 'Predefinito inattivo',
        'color' => 'blue',
        'group' => 'open',
        'is_default' => true,
        'is_active' => false,
    ])->assertStatus(422)->assertJsonValidationErrors('is_default');

    expect(ContractStatus::where('name', 'Predefinito inattivo')->exists())->toBeFalse();
});

it('update: 422 when is_default=true and is_active=false in the same PATCH (AC-025)', function () {
    $actor = contractStatusDefaultUserWith(['update']);
    $custom = ContractStatus::factory()->create(['is_default' => false, 'is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$custom->id}", ['is_default' => true, 'is_active' => false])
        ->assertStatus(422)->assertJsonValidationErrors('is_default');

    $this->assertDatabaseHas('contract_statuses', ['id' => $custom->id, 'is_default' => false, 'is_active' => true]);
});

it('update: 422 when promoting an already-inactive row to default (AC-025)', function () {
    $actor = contractStatusDefaultUserWith(['update']);
    $custom = ContractStatus::factory()->create(['is_default' => false, 'is_active' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$custom->id}", ['is_default' => true])
        ->assertStatus(422)->assertJsonValidationErrors('is_default');

    $this->assertDatabaseHas('contract_statuses', ['id' => $custom->id, 'is_default' => false]);
});

// ---------------------------------------------------------------------------
// BR-5d/e — a default row cannot be deactivated nor un-defaulted directly
// ---------------------------------------------------------------------------

it('update: 422 when directly deactivating the default row', function () {
    $actor = contractStatusDefaultUserWith(['update']);
    $default = ContractStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contract-statuses/{$default->id}", ['is_active' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');
});

it('update: 422 when directly unsetting is_default on a custom default row', function () {
    $actor = contractStatusDefaultUserWith(['update']);
    $default = ContractStatus::where('system_key', 'new')->firstOrFail();
    // Reassign the default to a custom row first, so the guard is exercised
    // on a row NOT protected by SystemStatusGuard.
    $custom = ContractStatus::factory()->create(['is_default' => false, 'is_active' => true]);
    Sanctum::actingAs($actor);
    $this->patchJson("/api/contract-statuses/{$custom->id}", ['is_default' => true])->assertOk();

    $this->patchJson("/api/contract-statuses/{$custom->id}", ['is_default' => false])
        ->assertStatus(422)->assertJsonValidationErrors('is_default');

    $this->assertDatabaseHas('contract_statuses', ['id' => $custom->id, 'is_default' => true]);
    $this->assertDatabaseHas('contract_statuses', ['id' => $default->id, 'is_default' => false]);
});
