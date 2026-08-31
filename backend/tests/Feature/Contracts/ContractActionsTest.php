<?php

use App\Enums\ContractStatusGroup;
use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * The 4 contract domain actions (spec 0072, BR-2/3/4): validate, schedule,
 * terminate, reactivate. AC-006/007/009-017.
 */
uses(RefreshDatabase::class);

if (! function_exists('contractActionsUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractActionsUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'schedule', 'changeStatus', 'reactivate'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006/007 — reactivate (BR-2/D-3)
// ---------------------------------------------------------------------------

it('AC-006: reactivating a suspended contract whose quote is back in closed_won restores the prior status', function () {
    $contract = Contract::factory()->create();
    $closedWonStatus = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    $contract->quote()->update(['quote_workflow_status_id' => $closedWonStatus->id]);

    $previousStatus = ContractStatus::where('name', 'Programmato')->sole();
    $contract->forceFill([
        'status_before_suspension_id' => $previousStatus->id,
        'contract_status_id' => ContractStatus::where('system_key', 'suspended')->sole()->id,
        'suspended_at' => now(),
    ])->save();

    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate")
        ->assertOk()
        ->assertJsonPath('data.contract_status_id', $previousStatus->id)
        ->assertJsonPath('data.is_suspended', false)
        ->assertJsonPath('data.status_before_suspension', null);

    $contract->refresh();
    expect($contract->suspended_at)->toBeNull()
        ->and($contract->status_before_suspension_id)->toBeNull();

    expect(Activity::where('subject_id', $contract->id)->where('event', 'contract.reactivated')->exists())->toBeTrue();
});

it('AC-007: reactivating a suspended contract whose quote is NOT closed_won is 422, nothing changes', function () {
    $contract = Contract::factory()->create();
    $openStatus = QuoteWorkflowStatus::factory()->create(['group' => WorkflowStatusGroup::Open]);
    $contract->quote()->update(['quote_workflow_status_id' => $openStatus->id]);

    $previousStatus = ContractStatus::where('name', 'Programmato')->sole();
    $suspendedStatusId = ContractStatus::where('system_key', 'suspended')->sole()->id;
    $contract->forceFill([
        'status_before_suspension_id' => $previousStatus->id,
        'contract_status_id' => $suspendedStatusId,
        'suspended_at' => now(),
    ])->save();

    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate")->assertStatus(422);

    $contract->refresh();
    expect($contract->contract_status_id)->toBe($suspendedStatusId)
        ->and($contract->suspended_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Reactivating a DISDETTO contract (user directive 2026-08-31)
// ---------------------------------------------------------------------------

if (! function_exists('terminatedContractForReactivation')) {
    function terminatedContractForReactivation(): Contract
    {
        $contract = Contract::factory()->create([
            'validated_at' => now()->subMonths(2),
            'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
        ]);

        $contract->forceFill([
            'terminated_at' => now()->subDay(),
            'termination_reason' => 'Recesso del cliente',
            'terminated_by' => User::factory()->create()->id,
            'contract_status_id' => ContractStatus::where('system_key', 'terminated')->sole()->id,
        ])->save();

        return $contract;
    }
}

it('reactivating a disdetto contract clears the whole termination stamp and lands on the chosen status', function () {
    $contract = terminatedContractForReactivation();
    $destination = ContractStatus::where('name', 'Programmato')->sole();
    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate", ['contract_status_id' => $destination->id])
        ->assertOk()
        ->assertJsonPath('data.contract_status_id', $destination->id)
        ->assertJsonPath('data.terminated_at', null)
        ->assertJsonPath('data.termination_reason', null)
        ->assertJsonPath('data.terminated_by', null);

    $contract->refresh();
    expect($contract->terminated_at)->toBeNull()
        ->and($contract->termination_reason)->toBeNull()
        ->and($contract->terminated_by)->toBeNull()
        ->and($contract->contract_status_id)->toBe($destination->id)
        // The validation stamp survives: only the disdetta is undone.
        ->and($contract->validated_at)->not->toBeNull();

    $activity = Activity::where('subject_id', $contract->id)->where('event', 'contract.reactivated')->first();
    expect($activity->properties['reactivated_from'])->toBe('terminated');
});

it('reactivating a disdetto contract without a contract_status_id is 422', function () {
    $contract = terminatedContractForReactivation();
    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate")
        ->assertStatus(422)
        ->assertJsonValidationErrors('contract_status_id');

    expect($contract->fresh()->terminated_at)->not->toBeNull();
});

it('reactivating a disdetto contract onto anything but an open/pending status is 422', function () {
    $contract = terminatedContractForReactivation();
    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    foreach (['cancelled', 'validated'] as $systemKey) {
        $this->postJson("/api/contracts/{$contract->id}/reactivate", [
            'contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('contract_status_id');
    }

    expect($contract->fresh()->terminated_at)->not->toBeNull();
});

it('reactivating a disdetto contract does NOT require the quote to be closed_won (user decision)', function () {
    $contract = terminatedContractForReactivation();
    $openStatus = QuoteWorkflowStatus::factory()->create(['group' => WorkflowStatusGroup::Open]);
    $contract->quote()->update(['quote_workflow_status_id' => $openStatus->id]);
    $destination = ContractStatus::where('name', 'Da programmare')->sole();
    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate", ['contract_status_id' => $destination->id])
        ->assertOk()
        ->assertJsonPath('data.contract_status_id', $destination->id);
});

it('reactivating a contract that is neither suspended nor disdetto is 422', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith(['reactivate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate", [
        'contract_status_id' => ContractStatus::where('name', 'Programmato')->sole()->id,
    ])->assertStatus(422);
});

it('reactivate is 403 without contracts.reactivate', function () {
    $contract = Contract::factory()->create(['suspended_at' => now()]);
    $actor = contractActionsUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/reactivate")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-009/010/011 — validate (BR-3)
// ---------------------------------------------------------------------------

it('AC-009: validating an unvalidated contract stamps validated_at/validated_by and logs the activity', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")
        ->assertOk()
        ->assertJsonPath('data.validated_by.id', $actor->id);

    $contract->refresh();
    expect($contract->validated_at->toDateString())->toBe(now()->toDateString())
        ->and($contract->validated_by)->toBe($actor->id);

    $activity = Activity::where('subject_id', $contract->id)->where('event', 'contract.validated')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->properties['validated_by'])->toBe($actor->id)
        ->and($activity->properties['contract_status_id'])->toBe($contract->contract_status_id);
});

it('AC-010: validating a contract already sitting on a closed_won status is 422, nothing changes', function () {
    // The refusal is driven by the GROUP of the current status (directive
    // 2026-08-31 rev.2), not by the `validated_at` stamp: a contract that
    // was validated, disdetto and then riattivato is back on an open status
    // and must be validatable again (see the sibling test below).
    $validator = User::factory()->create();
    $contract = Contract::factory()->create([
        'validated_at' => now()->subDay(),
        'validated_by' => $validator->id,
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")->assertStatus(422);

    $contract->refresh();
    expect($contract->validated_at->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($contract->validated_by)->toBe($validator->id);
});

it('re-validates a contract that carries an old validated_at but is back on an open status', function () {
    $contract = Contract::factory()->create([
        'validated_at' => now()->subMonths(3),
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ]);
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")
        ->assertOk()
        ->assertJsonPath('data.contract_status.group', 'closed_won');

    expect($contract->fresh()->validated_at->toDateString())->toBe(now()->toDateString());
});

it('validating a contract closed on the negative side is 422 (reactivate it first)', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'terminated')->sole()->id,
    ]);
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")->assertStatus(422);
});

it('AC-011: validating without contracts.validate is 403, nothing written', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")->assertForbidden();

    expect($contract->fresh()->validated_at)->toBeNull();
});

it('validating a suspended contract is 422', function () {
    $contract = Contract::factory()->create(['suspended_at' => now()]);
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Destination status of "Valida" (user directive 2026-08-31)
// ---------------------------------------------------------------------------

it('validating without a contract_status_id defaults to the system validated row', function () {
    $contract = Contract::factory()->create();
    $validatedStatus = ContractStatus::where('system_key', 'validated')->sole();
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate")
        ->assertOk()
        ->assertJsonPath('data.contract_status_id', $validatedStatus->id)
        ->assertJsonPath('data.contract_status.group', 'closed_won');

    expect($contract->fresh()->contract_status_id)->toBe($validatedStatus->id);
});

it('validating with a non closed_won contract_status_id is 422, nothing persisted', function () {
    $contract = Contract::factory()->create();
    $wrongGroupStatus = ContractStatus::factory()->create(['is_active' => true, 'group' => ContractStatusGroup::Pending]);
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate", [
        'contract_status_id' => $wrongGroupStatus->id,
    ])->assertStatus(422)->assertJsonValidationErrors('contract_status_id');

    expect($contract->fresh()->validated_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-012/013 — schedule
// ---------------------------------------------------------------------------

it('AC-012: scheduling a contract persists the dates and status, and logs the activity', function () {
    $contract = Contract::factory()->create();
    $newStatus = ContractStatus::factory()->create(['is_active' => true]);
    $actor = contractActionsUserWith(['schedule']);
    Sanctum::actingAs($actor);

    $expiryDate = now()->addYear()->toDateString();
    $renewalDate = now()->addMonths(11)->toDateString();

    $this->postJson("/api/contracts/{$contract->id}/schedule", [
        'expiry_date' => $expiryDate,
        'renewal_date' => $renewalDate,
        'contract_status_id' => $newStatus->id,
    ])->assertOk();

    $contract->refresh();
    expect($contract->contract_status_id)->toBe($newStatus->id)
        ->and($contract->expiry_date->toDateString())->toBe($expiryDate)
        ->and($contract->renewal_date->toDateString())->toBe($renewalDate);

    expect(Activity::where('subject_id', $contract->id)->where('event', 'contract.scheduled')->exists())->toBeTrue();
});

it('AC-013: scheduling with renewal_date after expiry_date is 422, nothing persisted', function () {
    $contract = Contract::factory()->create();
    $status = ContractStatus::factory()->create(['is_active' => true]);
    $actor = contractActionsUserWith(['schedule']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/schedule", [
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'renewal_date' => now()->addYear()->toDateString(),
        'contract_status_id' => $status->id,
    ])->assertStatus(422)->assertJsonValidationErrors('renewal_date');

    expect($contract->fresh()->expiry_date)->toBeNull();
});

it('scheduling a suspended contract is 422', function () {
    $contract = Contract::factory()->create(['suspended_at' => now()]);
    $status = ContractStatus::factory()->create(['is_active' => true]);
    $actor = contractActionsUserWith(['schedule']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/schedule", [
        'expiry_date' => now()->addYear()->toDateString(),
        'contract_status_id' => $status->id,
    ])->assertStatus(422);
});

it('schedule is 403 without contracts.schedule', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/schedule", [
        'expiry_date' => now()->addYear()->toDateString(),
        'contract_status_id' => ContractStatus::factory()->create(['is_active' => true])->id,
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-014/015/016 — terminate (BR-4)
// ---------------------------------------------------------------------------

it('AC-014: terminating without a contract_status_id defaults to the system terminated row', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith(['terminate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Cliente insoddisfatto',
    ])->assertOk();

    $terminatedStatus = ContractStatus::where('system_key', 'terminated')->sole();
    $contract->refresh();

    expect($contract->contract_status_id)->toBe($terminatedStatus->id)
        ->and($contract->terminated_at->toDateString())->toBe(now()->toDateString())
        ->and($contract->termination_reason)->toBe('Cliente insoddisfatto')
        ->and($contract->terminated_by)->toBe($actor->id);

    expect(Activity::where('subject_id', $contract->id)->where('event', 'contract.terminated')->exists())->toBeTrue();
});

it('AC-015: terminating with a non closed_lost contract_status_id is 422', function () {
    $contract = Contract::factory()->create();
    $wrongGroupStatus = ContractStatus::factory()->create(['is_active' => true, 'group' => ContractStatusGroup::Pending]);
    $actor = contractActionsUserWith(['terminate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Motivo',
        'contract_status_id' => $wrongGroupStatus->id,
    ])->assertStatus(422)->assertJsonValidationErrors('contract_status_id');
});

it('terminating an already-terminated contract is 422', function () {
    $contract = Contract::factory()->create(['terminated_at' => now()->subDay(), 'termination_reason' => 'Prima']);
    $actor = contractActionsUserWith(['terminate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Seconda',
    ])->assertStatus(422);

    expect($contract->fresh()->termination_reason)->toBe('Prima');
});

it('AC-016: a terminated contract still exposes its historical dates on GET', function () {
    $contract = Contract::factory()->create([
        'validated_at' => now()->subMonths(2),
        'renewal_date' => now()->subMonth(),
        'expiry_date' => now(),
    ]);
    $actor = contractActionsUserWith(['terminate', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Motivo',
    ])->assertOk();

    $response = $this->getJson("/api/contracts/{$contract->id}")->assertOk();

    expect($response->json('data.validated_at'))->not->toBeNull()
        ->and($response->json('data.accepted_at'))->not->toBeNull()
        ->and($response->json('data.renewal_date'))->not->toBeNull();
});

it('terminate is 403 without contracts.terminate', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->toDateString(),
        'termination_reason' => 'Motivo',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-017 — never a future date (validate/terminate; see handoff note re:
// schedule, whose own endpoint doc does NOT declare this constraint)
// ---------------------------------------------------------------------------

it('AC-017: validating with a future validated_at is 422 on the date field', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith(['validate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/validate", ['validated_at' => now()->addDay()->toDateString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('validated_at');
});

it('AC-017: terminating with a future terminated_at is 422 on the date field', function () {
    $contract = Contract::factory()->create();
    $actor = contractActionsUserWith(['terminate']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/terminate", [
        'terminated_at' => now()->addDay()->toDateString(),
        'termination_reason' => 'Motivo',
    ])->assertStatus(422)->assertJsonValidationErrors('terminated_at');
});
