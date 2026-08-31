<?php

use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/contract-statuses/reorder (spec 0072, AC-026/AC-027)
|--------------------------------------------------------------------------
|
| The migrations seed 3 custom rows ("Da programmare"/"Programmato"/"In
| scadenza") alongside the 5 system rows, so `ordered_ids` must include
| exactly those 3 (plus any extra custom row a test creates). The HEAD is
| two rows long ("Da validare" 0, "Validato" 10, directive 2026-08-31), so
| the first custom always lands on 20.
*/

if (! function_exists('contractStatusReorderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractStatusReorderUserWith(array $abilities): User
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

it('reorder: a valid permutation resequences the customs, the head stays Da validare/Validato, tail stays Sospeso/Annullato/Disdetto (AC-026)', function () {
    $actor = contractStatusReorderUserWith(['update']);
    $daProgrammare = ContractStatus::where('name', 'Da programmare')->firstOrFail();
    $programmato = ContractStatus::where('name', 'Programmato')->firstOrFail();
    $inScadenza = ContractStatus::where('name', 'In scadenza')->firstOrFail();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/contract-statuses/reorder', [
        'ordered_ids' => [$inScadenza->id, $daProgrammare->id, $programmato->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$inScadenza->id]['sort_order'])->toBe(20)
        ->and($rows[$daProgrammare->id]['sort_order'])->toBe(30)
        ->and($rows[$programmato->id]['sort_order'])->toBe(40);

    $newRow = $rows->firstWhere('system_key', 'new');
    $validatedRow = $rows->firstWhere('system_key', 'validated');
    $suspendedRow = $rows->firstWhere('system_key', 'suspended');
    $cancelledRow = $rows->firstWhere('system_key', 'cancelled');
    $terminatedRow = $rows->firstWhere('system_key', 'terminated');

    expect($newRow['sort_order'])->toBe(0)
        ->and($validatedRow['sort_order'])->toBe(10)
        ->and($suspendedRow['sort_order'])->toBe(50)
        ->and($cancelledRow['sort_order'])->toBe(60)
        ->and($terminatedRow['sort_order'])->toBe(70);
});

it('reorder: 422 when ordered_ids includes a system status id, no sort_order changes (AC-027)', function () {
    $actor = contractStatusReorderUserWith(['update']);
    $newStatus = ContractStatus::where('system_key', 'new')->firstOrFail();
    $daProgrammare = ContractStatus::where('name', 'Da programmare')->firstOrFail();
    $programmato = ContractStatus::where('name', 'Programmato')->firstOrFail();
    $inScadenza = ContractStatus::where('name', 'In scadenza')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', [
        'ordered_ids' => [$newStatus->id, $daProgrammare->id, $programmato->id, $inScadenza->id],
    ])->assertStatus(422);

    $this->assertDatabaseHas('contract_statuses', ['id' => $newStatus->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('contract_statuses', ['id' => $daProgrammare->id, 'sort_order' => 20]);
});

it('reorder: 422 when ordered_ids is missing a custom id, no sort_order changes (AC-027)', function () {
    $actor = contractStatusReorderUserWith(['update']);
    $daProgrammare = ContractStatus::where('name', 'Da programmare')->firstOrFail();
    $programmato = ContractStatus::where('name', 'Programmato')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', ['ordered_ids' => [$daProgrammare->id, $programmato->id]])
        ->assertStatus(422);

    $this->assertDatabaseHas('contract_statuses', ['id' => $daProgrammare->id, 'sort_order' => 20]);
});

it('reorder: 422 when ordered_ids contains a duplicate (AC-027)', function () {
    $actor = contractStatusReorderUserWith(['update']);
    $daProgrammare = ContractStatus::where('name', 'Da programmare')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', ['ordered_ids' => [$daProgrammare->id, $daProgrammare->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');
});

it('reorder: 422 when ordered_ids includes a non-existent id (AC-027)', function () {
    $actor = contractStatusReorderUserWith(['update']);
    $daProgrammare = ContractStatus::where('name', 'Da programmare')->firstOrFail();
    $programmato = ContractStatus::where('name', 'Programmato')->firstOrFail();
    $inScadenza = ContractStatus::where('name', 'In scadenza')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', [
        'ordered_ids' => [$daProgrammare->id, $programmato->id, $inScadenza->id, 999999],
    ])->assertStatus(422);
});

it('reorder: 403 without contract-statuses.update, order unchanged', function () {
    $actor = contractStatusReorderUserWith([]);
    $daProgrammare = ContractStatus::where('name', 'Da programmare')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contract-statuses/reorder', ['ordered_ids' => [$daProgrammare->id]])->assertForbidden();

    $this->assertDatabaseHas('contract_statuses', ['id' => $daProgrammare->id, 'sort_order' => 20]);
});
