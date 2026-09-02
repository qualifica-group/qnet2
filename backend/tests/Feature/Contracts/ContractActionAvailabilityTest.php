<?php

use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Lifecycle gating of the contract domain actions (user directive
 * 2026-08-31 rev.2), driven by the GROUP of the current status and exposed
 * by `permissions.actions` on GET /api/contracts/{contract} plus the grid's
 * row actions:
 *
 * - open | pending → edit, change_status, validate, terminate
 * - closed_won     → terminate, program, reactivate
 * - closed_lost    → reactivate
 *
 * The actor below holds EVERY ability, so what changes between the cases is
 * the contract's status alone (App\Services\Contracts\ContractActionAvailability).
 */
uses(RefreshDatabase::class);

if (! function_exists('contractAvailabilityActor')) {
    function contractAvailabilityActor(): User
    {
        $abilities = ['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(static fn (string $ability): string => "contracts.{$ability}", $abilities));

        return $user;
    }
}

if (! function_exists('contractOnSystemStatus')) {
    /**
     * @param  array<string, mixed>  $overrides
     */
    function contractOnSystemStatus(string $systemKey, array $overrides = []): Contract
    {
        return Contract::factory()->create([
            'contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id,
            ...$overrides,
        ]);
    }
}

/**
 * @return array<string, bool>
 */
function contractActionFlags(Contract $contract): array
{
    Sanctum::actingAs(contractAvailabilityActor());

    /** @var array<string, bool> $actions */
    $actions = test()->getJson("/api/contracts/{$contract->id}")->assertOk()->json('permissions.actions');

    return $actions;
}

it('offers edit, change_status, validate and terminate on an OPEN contract', function () {
    $flags = contractActionFlags(contractOnSystemStatus('new'));

    expect($flags['validate'])->toBeTrue()
        ->and($flags['terminate'])->toBeTrue()
        ->and($flags['change_status'])->toBeTrue()
        ->and($flags['program'])->toBeFalse()
        ->and($flags['reactivate'])->toBeFalse();
});

it('behaves the same on a PENDING contract', function () {
    $pending = ContractStatus::where('name', 'Programmato')->sole();
    $flags = contractActionFlags(Contract::factory()->create(['contract_status_id' => $pending->id]));

    expect($pending->group)->toBe(ContractStatusGroup::Pending)
        ->and($flags['validate'])->toBeTrue()
        ->and($flags['terminate'])->toBeTrue()
        ->and($flags['change_status'])->toBeTrue()
        ->and($flags['program'])->toBeFalse()
        ->and($flags['reactivate'])->toBeFalse();
});

it('offers terminate, program and reactivate on a CLOSED_WON contract', function () {
    // Directive 2026-08-31 rev.3: a positively closed contract must be
    // reopenable too, so `reactivate` joins the two it already had.
    $flags = contractActionFlags(contractOnSystemStatus('validated', ['validated_at' => now()->subDay()]));

    expect($flags['terminate'])->toBeTrue()
        ->and($flags['program'])->toBeTrue()
        ->and($flags['reactivate'])->toBeTrue()
        ->and($flags['validate'])->toBeFalse()
        ->and($flags['change_status'])->toBeFalse();
});

it('offers only reactivate on a CLOSED_LOST contract', function () {
    $flags = contractActionFlags(contractOnSystemStatus('terminated', [
        'terminated_at' => now()->subDay(),
        'termination_reason' => 'Recesso del cliente',
    ]));

    expect($flags['reactivate'])->toBeTrue()
        ->and($flags['validate'])->toBeFalse()
        ->and($flags['program'])->toBeFalse()
        ->and($flags['terminate'])->toBeFalse()
        ->and($flags['change_status'])->toBeFalse();
});

it('keeps reactivate and drops validate on a SUSPENDED contract, which sits on a pending status', function () {
    $flags = contractActionFlags(contractOnSystemStatus('suspended', ['suspended_at' => now()]));

    expect($flags['reactivate'])->toBeTrue()
        ->and($flags['validate'])->toBeFalse()
        ->and($flags['terminate'])->toBeTrue()
        ->and($flags['change_status'])->toBeTrue();
});

it('drops the lifecycle-refused keys from the grid row actions too', function () {
    $terminated = contractOnSystemStatus('terminated', [
        'terminated_at' => now()->subDay(),
        'termination_reason' => 'Recesso del cliente',
    ]);
    Sanctum::actingAs(contractAvailabilityActor());

    $response = $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();

    $row = collect($response->json('items'))->firstWhere('id', $terminated->id);

    expect($row)->not->toBeNull()
        ->and($row['actions'])->not->toContain('validate')
        ->and($row['actions'])->not->toContain('program')
        ->and($row['actions'])->not->toContain('terminate')
        ->and($row['actions'])->not->toContain('change_status')
        ->and($row['actions'])->not->toContain('edit')
        ->and($row['actions'])->toContain('view')
        ->and($row['actions'])->toContain('reactivate');
});
