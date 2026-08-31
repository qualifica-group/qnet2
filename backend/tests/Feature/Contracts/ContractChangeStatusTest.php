<?php

use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * POST /api/contracts/{contract}/change-status — "Modifica stato" (user
 * directive 2026-08-31 rev.2): moves a WORKING contract onto another
 * open/pending status. Both ends are constrained, so this action can never
 * open or close a contract.
 */
uses(RefreshDatabase::class);

if (! function_exists('changeStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function changeStatusUserWith(array $abilities): User
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

it('moves a working contract onto another open/pending status and logs the activity', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ]);
    $destination = ContractStatus::where('name', 'Programmato')->sole();
    Sanctum::actingAs(changeStatusUserWith(['changeStatus']));

    $this->postJson("/api/contracts/{$contract->id}/change-status", ['contract_status_id' => $destination->id])
        ->assertOk()
        ->assertJsonPath('data.contract_status_id', $destination->id);

    expect($contract->fresh()->contract_status_id)->toBe($destination->id)
        ->and(Activity::where('subject_id', $contract->id)->where('event', 'contract.status_changed')->exists())->toBeTrue();
});

it('refuses a destination outside the open/pending groups', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ]);
    Sanctum::actingAs(changeStatusUserWith(['changeStatus']));

    foreach (['validated', 'terminated'] as $systemKey) {
        $this->postJson("/api/contracts/{$contract->id}/change-status", [
            'contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('contract_status_id');
    }

    expect($contract->fresh()->contract_status_id)->toBe(ContractStatus::where('system_key', 'new')->sole()->id);
});

it('refuses an inactive destination status', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ]);
    $inactive = ContractStatus::factory()->group(ContractStatusGroup::Pending)->create(['is_active' => false]);
    Sanctum::actingAs(changeStatusUserWith(['changeStatus']));

    $this->postJson("/api/contracts/{$contract->id}/change-status", ['contract_status_id' => $inactive->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('contract_status_id');
});

it('refuses to change the status of an already closed contract', function () {
    $destination = ContractStatus::where('name', 'Programmato')->sole();
    Sanctum::actingAs(changeStatusUserWith(['changeStatus']));

    foreach (['validated', 'terminated'] as $systemKey) {
        $closed = Contract::factory()->create([
            'contract_status_id' => ContractStatus::where('system_key', $systemKey)->sole()->id,
        ]);

        $this->postJson("/api/contracts/{$closed->id}/change-status", ['contract_status_id' => $destination->id])
            ->assertStatus(422);
    }
});

it('is 403 without contracts.changeStatus', function () {
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'new')->sole()->id,
    ]);
    $destination = ContractStatus::where('name', 'Programmato')->sole();
    Sanctum::actingAs(changeStatusUserWith([]));

    $this->postJson("/api/contracts/{$contract->id}/change-status", ['contract_status_id' => $destination->id])
        ->assertForbidden();

    expect($contract->fresh()->contract_status_id)->not->toBe($destination->id);
});

it('narrows contract-statuses/for-select to the requested groups', function () {
    Sanctum::actingAs(changeStatusUserWith(['view']));

    $working = $this->getJson('/api/contract-statuses/for-select?status_groups[]=open&status_groups[]=pending')->assertOk();
    $won = $this->getJson('/api/contract-statuses/for-select?status_groups[]=closed_won')->assertOk();
    $lost = $this->getJson('/api/contract-statuses/for-select?status_groups[]=closed_lost')->assertOk();

    expect(collect($working->json('items'))->pluck('label')->all())
        ->toBe(['Da validare', 'Da programmare', 'Programmato', 'In scadenza', 'Sospeso'])
        ->and(collect($won->json('items'))->pluck('label')->all())->toBe(['Validato'])
        ->and(collect($lost->json('items'))->pluck('label')->all())->toBe(['Annullato', 'Disdetto']);
});

it('rejects an unknown status group', function () {
    Sanctum::actingAs(changeStatusUserWith(['view']));

    $this->getJson('/api/contract-statuses/for-select?status_groups[]=nope')
        ->assertStatus(422)
        ->assertJsonValidationErrors('status_groups.0');
});
