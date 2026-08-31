<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Lifecycle gating of the contract domain actions (user directive
 * 2026-08-31), as exposed by `permissions.actions` on GET
 * /api/contracts/{contract} and by the grid's row actions:
 *
 * - not validated yet → validate + terminate
 * - validated         → schedule + terminate (never validate again)
 * - disdetto          → none of the three, only reactivate
 *
 * The actor below holds EVERY ability, so what changes between the cases is
 * the contract's state alone (App\Services\Contracts\ContractActionAvailability).
 */
uses(RefreshDatabase::class);

if (! function_exists('contractAvailabilityActor')) {
    function contractAvailabilityActor(): User
    {
        $abilities = ['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'schedule', 'changeStatus', 'reactivate'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(static fn (string $ability): string => "contracts.{$ability}", $abilities));

        return $user;
    }
}

it('offers validate and terminate, never schedule, on a contract that is not validated yet', function () {
    $contract = Contract::factory()->create();
    Sanctum::actingAs(contractAvailabilityActor());

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.validate', true)
        ->assertJsonPath('permissions.actions.terminate', true)
        ->assertJsonPath('permissions.actions.schedule', false);
});

it('offers schedule and terminate, never validate, once the contract is validated', function () {
    $contract = Contract::factory()->create([
        'validated_at' => now()->subDay(),
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    Sanctum::actingAs(contractAvailabilityActor());

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.validate', false)
        ->assertJsonPath('permissions.actions.schedule', true)
        ->assertJsonPath('permissions.actions.terminate', true);
});

it('treats a contract sitting on a closed_won status as validated even without a validated_at stamp', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    Sanctum::actingAs(contractAvailabilityActor());

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.validate', false)
        ->assertJsonPath('permissions.actions.schedule', true);
});

it('offers only "Riattiva contratto" once the contract is disdetto', function () {
    $contract = Contract::factory()->create([
        'validated_at' => now()->subMonth(),
        'terminated_at' => now()->subDay(),
        'termination_reason' => 'Recesso del cliente',
        'contract_status_id' => ContractStatus::where('system_key', 'terminated')->sole()->id,
    ]);
    Sanctum::actingAs(contractAvailabilityActor());

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.validate', false)
        ->assertJsonPath('permissions.actions.schedule', false)
        ->assertJsonPath('permissions.actions.terminate', false)
        ->assertJsonPath('permissions.actions.reactivate', true);
});

it('never offers validate on a suspended contract (the endpoint would always 422)', function () {
    $contract = Contract::factory()->create([
        'suspended_at' => now(),
        'contract_status_id' => ContractStatus::where('system_key', 'suspended')->sole()->id,
    ]);
    Sanctum::actingAs(contractAvailabilityActor());

    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.validate', false)
        ->assertJsonPath('permissions.actions.reactivate', true);
});

it('drops the lifecycle-refused keys from the grid row actions too', function () {
    $terminated = Contract::factory()->create([
        'terminated_at' => now()->subDay(),
        'termination_reason' => 'Recesso del cliente',
        'contract_status_id' => ContractStatus::where('system_key', 'terminated')->sole()->id,
    ]);
    Sanctum::actingAs(contractAvailabilityActor());

    $response = $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();

    $row = collect($response->json('items'))->firstWhere('id', $terminated->id);

    expect($row)->not->toBeNull()
        ->and($row['actions'])->not->toContain('validate')
        ->and($row['actions'])->not->toContain('schedule')
        ->and($row['actions'])->not->toContain('terminate')
        ->and($row['actions'])->not->toContain('edit')
        ->and($row['actions'])->toContain('view')
        ->and($row['actions'])->toContain('reactivate');
});
