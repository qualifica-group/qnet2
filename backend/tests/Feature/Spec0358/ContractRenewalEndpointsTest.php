<?php

use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\ContractStatus;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0358 MT-04: POST/DELETE contracts/{contract}/renewals (AC-001..AC-007,
 * AC-021..AC-024).
 */
uses(RefreshDatabase::class);

function renewableContract(array $overrides = []): Contract
{
    return Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
        'renewal_date' => '2026-11-30',
        'expiry_date' => '2026-12-31',
        ...$overrides,
    ]);
}

function renewalActor(array $abilities = ['viewAny', 'view', 'program', 'viewAll']): User
{
    $actor = contractScopedUserWith($abilities);
    Sanctum::actingAs($actor);

    return $actor;
}

it('AC-001: renews the contract, writes the history row and returns the detail', function () {
    $contract = renewableContract();
    $actor = renewalActor();

    $response = $this->postJson("/api/contracts/{$contract->id}/renewals", [
        'expiry_date' => '2027-12-31', 'renewal_date' => '2027-11-30',
    ])->assertCreated()->assertJsonPath('success', true);

    expect($response->json('data.renewal_number'))->toBe(1)
        ->and($response->json('data.expiry_date'))->toStartWith('2027-12-31')
        ->and($response->json('data.renewals.0.number'))->toBe(1)
        ->and($response->json('data.renewals.0.starts_on'))->toBe('2027-01-01')
        ->and($response->json('data.renewals.0.previous_expiry_date'))->toBe('2026-12-31')
        ->and($response->json('data.renewals.0.previous_renewal_date'))->toBe('2026-11-30')
        ->and($response->json('data.renewals.0.renewed_by.id'))->toBe($actor->id)
        ->and($response->json('data.renewals.0.work_orders_count'))->toBe(0)
        ->and($response->json('data.renewals.0.can_undo'))->toBeTrue()
        ->and($response->json('data.initial_period_work_orders_count'))->toBe(0)
        ->and($response->json('permissions.actions.renew'))->toBeTrue();
});

it('AC-002: a second renewal is number 2 and the list stays ordered', function () {
    $contract = renewableContract();
    renewalActor();

    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->assertCreated();
    $response = $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2028-12-31'])->assertCreated();

    expect($response->json('data.renewal_number'))->toBe(2)
        ->and(collect($response->json('data.renewals'))->pluck('number')->all())->toBe([1, 2])
        ->and($response->json('data.renewals.0.can_undo'))->toBeFalse()
        ->and($response->json('data.renewals.1.can_undo'))->toBeTrue();
});

it('AC-003: a non programmable contract is 403 and nothing is written', function (string $systemKey, array $overrides) {
    $contract = renewableContract([
        'contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id,
        ...$overrides,
    ]);
    renewalActor();

    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->assertForbidden();

    expect(ContractRenewal::count())->toBe(0)
        ->and($contract->fresh()->expiry_date->toDateString())->toBe('2026-12-31');
})->with([
    'closed lost' => ['terminated', ['terminated_at' => '2026-01-01', 'termination_reason' => 'x']],
    'suspended' => ['suspended', ['suspended_at' => '2026-01-01', 'validated_at' => '2026-01-01']],
]);

it('AC-003: an open contract that was never validated is 403', function () {
    $open = Contract::factory()->create(['expiry_date' => '2026-12-31', 'validated_at' => null]);
    renewalActor();

    $this->postJson("/api/contracts/{$open->id}/renewals", ['expiry_date' => '2027-12-31'])->assertForbidden();
});

it('AC-004: 403 without contracts.program or out of perimeter, 401 when anonymous', function () {
    $contract = renewableContract();

    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->assertUnauthorized();

    renewalActor(['viewAny', 'view', 'viewAll']);
    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->assertForbidden();

    renewalActor(['viewAny', 'view', 'program']);
    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->assertForbidden();

    expect(ContractRenewal::count())->toBe(0);
});

it('AC-005: validates expiry_date and renewal_date', function (array $payload, string $field) {
    $contract = renewableContract();
    renewalActor();

    $this->postJson("/api/contracts/{$contract->id}/renewals", $payload)
        ->assertStatus(422)->assertJsonValidationErrors($field);

    expect(ContractRenewal::count())->toBe(0)
        ->and($contract->fresh()->expiry_date->toDateString())->toBe('2026-12-31');
})->with([
    'missing expiry' => [['renewal_date' => '2027-01-01'], 'expiry_date'],
    'expiry equal to current' => [['expiry_date' => '2026-12-31'], 'expiry_date'],
    'expiry before current' => [['expiry_date' => '2026-06-30'], 'expiry_date'],
    'renewal after expiry' => [['expiry_date' => '2027-12-31', 'renewal_date' => '2028-01-01'], 'renewal_date'],
]);

it('AC-006: an already expired contract can be renewed', function () {
    $contract = renewableContract(['renewal_date' => '2020-05-01', 'expiry_date' => '2020-06-01']);
    renewalActor();

    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2021-06-01'])
        ->assertCreated();
});

it('AC-007: without a current expiry the period starts today and previous_expiry_date is null', function () {
    $contract = renewableContract(['renewal_date' => null, 'expiry_date' => null]);
    renewalActor();

    $response = $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2030-01-01'])->assertCreated();

    expect($response->json('data.renewals.0.starts_on'))->toBe(now()->toDateString())
        ->and($response->json('data.renewals.0.previous_expiry_date'))->toBeNull()
        ->and($response->json('data.renewals.0.previous_renewal_date'))->toBeNull();
});

it('AC-021: undoing the latest renewal without work orders restores the dates', function () {
    $contract = renewableContract();
    renewalActor();
    $created = $this->postJson("/api/contracts/{$contract->id}/renewals", [
        'expiry_date' => '2027-12-31', 'renewal_date' => '2027-11-30',
    ])->assertCreated();
    $renewalId = $created->json('data.renewals.0.id');

    $response = $this->deleteJson("/api/contracts/{$contract->id}/renewals/{$renewalId}")->assertOk();

    expect($response->json('data.renewal_number'))->toBe(0)
        ->and($response->json('data.renewals'))->toBe([])
        ->and($response->json('data.expiry_date'))->toStartWith('2026-12-31')
        ->and($response->json('data.renewal_date'))->toStartWith('2026-11-30')
        ->and(ContractRenewal::count())->toBe(0);
});

it('AC-022: undo is 422 when the renewal is not the latest', function () {
    $contract = renewableContract();
    renewalActor();
    $first = $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->json('data.renewals.0.id');
    $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2028-12-31'])->assertCreated();

    $this->deleteJson("/api/contracts/{$contract->id}/renewals/{$first}")
        ->assertStatus(422)->assertJsonValidationErrors('renewal');

    expect(ContractRenewal::count())->toBe(2)
        ->and($contract->fresh()->expiry_date->toDateString())->toBe('2028-12-31');
});

it('AC-022: undo is 422 when the period already has work orders, and can_undo is false', function () {
    $contract = renewableContract();
    renewalActor();
    $renewalId = $this->postJson("/api/contracts/{$contract->id}/renewals", ['expiry_date' => '2027-12-31'])->json('data.renewals.0.id');
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod(WorkOrder::factory()->create(['quote_id' => $contract->quote_id]), $line, 1);

    $this->deleteJson("/api/contracts/{$contract->id}/renewals/{$renewalId}")
        ->assertStatus(422)->assertJsonValidationErrors('renewal');

    $detail = $this->getJson("/api/contracts/{$contract->id}")->assertOk();
    expect($detail->json('data.renewals.0.can_undo'))->toBeFalse()
        ->and($detail->json('data.renewals.0.work_orders_count'))->toBe(1)
        ->and(ContractRenewal::count())->toBe(1);
});

it('AC-023: a renewal of another contract is 404; no permission or non programmable is 403', function () {
    $contract = renewableContract();
    $other = renewableContract();
    $foreign = ContractRenewal::factory()->create(['contract_id' => $other->id, 'number' => 1]);
    renewalActor();

    $this->deleteJson("/api/contracts/{$contract->id}/renewals/{$foreign->id}")->assertNotFound();

    $closed = renewableContract(['contract_status_id' => ContractStatus::where('system_key', 'terminated')->sole()->id]);
    $own = ContractRenewal::factory()->create(['contract_id' => $closed->id, 'number' => 1]);
    $this->deleteJson("/api/contracts/{$closed->id}/renewals/{$own->id}")->assertForbidden();

    renewalActor(['viewAny', 'view', 'viewAll']);
    $this->deleteJson("/api/contracts/{$other->id}/renewals/{$foreign->id}")->assertForbidden();
});

it('AC-024: the detail and the grid expose renew with the same rule as program', function () {
    $programmable = renewableContract();
    $terminated = renewableContract(['contract_status_id' => ContractStatus::where('system_key', 'terminated')->sole()->id]);
    renewalActor();

    expect($this->getJson("/api/contracts/{$programmable->id}")->json('permissions.actions.renew'))->toBeTrue()
        ->and($this->getJson("/api/contracts/{$terminated->id}")->json('permissions.actions.renew'))->toBeFalse();

    $rows = collect($this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'));

    expect($rows->firstWhere('id', $programmable->id)['actions'])->toContain('renew')
        ->and($rows->firstWhere('id', $terminated->id)['actions'])->not->toContain('renew');

    renewalActor(['viewAny', 'view', 'viewAll']);
    expect($this->getJson("/api/contracts/{$programmable->id}")->json('permissions.actions.renew'))->toBeFalse();
});

it('AC-024: the grid action catalog declares renew right after program', function () {
    renewalActor();

    $actions = collect($this->getJson('/api/tables/contracts/columns')->assertOk()->json('data.actions'));

    expect($actions->pluck('key')->all())->toContain('renew')
        ->and($actions->pluck('key')->search('renew'))->toBe($actions->pluck('key')->search('program') + 1)
        ->and($actions->firstWhere('key', 'renew'))->toMatchArray([
            'label' => 'contracts.actions.renew', 'icon' => 'refresh-cw', 'type' => 'action',
            'confirm' => true,
        ]);
});
