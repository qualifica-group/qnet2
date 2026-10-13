<?php

use App\Models\AgentFiscalProfile;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\EnasarcoParameter;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function settlementUrl(CommissionSettlement $settlement, string $suffix = ''): string
{
    return "/api/commission-settlements/{$settlement->id}{$suffix}";
}

it('AC-007: modifying the profile or the parameters after the issue leaves the issued settlement untouched', function () {
    $company = Company::factory()->create();
    $parameter = EnasarcoParameter::factory()->create(['year' => 2026]);
    $agent = settlementAgent(['enasarco_enabled' => true, 'enasarco_mandate_type' => 'mono']);
    settlementMovement($agent, $company, '1000.00');
    $draft = draftSettlementFor($agent, $company);
    $this->postJson(settlementUrl($draft, '/issue'))->assertOk();
    $issued = $draft->fresh();

    AgentFiscalProfile::query()->update(['withholding_rate' => '10.00', 'vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id]);
    $parameter->update(['total_rate' => '30.00']);

    $after = $issued->fresh();
    expect($after->withholding_amount)->toBe($issued->withholding_amount)
        ->and($after->enasarco_agent_amount)->toBe('85.00')
        ->and($after->withholding_rate)->toBe('23.00')
        ->and($after->vat_rate)->toBeNull();
});

it('AC-007: a draft recalculation uses the current profile and parameters', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '1000.00');
    $draft = draftSettlementFor($agent, $company);
    expect($draft->withholding_amount)->toBe('115.00')->and($draft->vat_amount)->toBe('0.00');

    AgentFiscalProfile::query()->update(['withholding_rate' => '20.00', 'vat_rate_id' => VatRate::factory()->create(['rate' => '10.00'])->id]);

    $this->postJson(settlementUrl($draft, '/recalculate'))->assertOk()
        ->assertJsonPath('data.withholding_amount', '100.00')
        ->assertJsonPath('data.vat_rate', '10.00')
        ->assertJsonPath('data.vat_amount', '100.00')
        ->assertJsonPath('data.net_payable', '1000.00');
});

it('AC-008: recalculate attaches the movements that became open (a reversal) and refreshes the totals', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '1000.00');
    $draft = draftSettlementFor($agent, $company);
    $reversal = settlementMovement($agent, $company, '-200.00', '2026-09-28', ['type' => 'REVERSAL']);
    $later = settlementMovement($agent, $company, '90.00', '2026-10-05');

    $this->postJson(settlementUrl($draft, '/recalculate'))->assertOk()
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.taxable_amount', '800.00')
        ->assertJsonCount(2, 'data.movements');

    expect($reversal->fresh()->commission_settlement_id)->toBe($draft->id)
        ->and($later->fresh()->commission_settlement_id)->toBeNull();
});

it('AC-008: recalculate on an issued or cancelled settlement is a 409', function (string $state) {
    Sanctum::actingAs(settlementAdmin());
    $settlement = settlementRow(settlementAgent(), Company::factory()->create(), $state);

    $this->postJson(settlementUrl($settlement, '/recalculate'))->assertStatus(409)->assertJsonPath('success', false);
})->with(['issued', 'cancelled']);

it('AC-008: recalculate answers 422 with the reason when the profile no longer exists', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);
    AgentFiscalProfile::query()->update(['valid_until' => '2026-01-31']);

    $this->postJson(settlementUrl($draft, '/recalculate'))->assertUnprocessable()
        ->assertJsonPath('errors.reason.0', 'missing_profile');
});

it('AC-009: the issue numbers per (company, year of period_to) from 1, with independent sequences', function () {
    [$companyA, $companyB] = [Company::factory()->create(), Company::factory()->create()];
    $agent = settlementAgent(['valid_from' => '2025-01-01']);
    $issue = function (Company $company, string $periodTo) use ($agent) {
        settlementMovement($agent, $company, '100.00', $periodTo);
        $draft = draftSettlementFor($agent, $company, ['period_from' => '2025-01-01', 'period_to' => $periodTo]);
        test()->postJson(settlementUrl($draft, '/issue'))->assertOk();

        return $draft->fresh();
    };

    $first = $issue($companyA, '2026-03-31');
    $second = $issue($companyA, '2026-04-30');
    $otherCompany = $issue($companyB, '2026-04-30');
    $otherYear = $issue($companyA, '2025-12-31');

    expect([$first->code(), $second->code(), $otherCompany->code(), $otherYear->code()])->toBe(['1/2026', '2/2026', '1/2026', '1/2025'])
        ->and($first->status->value)->toBe('ISSUED')
        ->and($first->issued_at)->not->toBeNull()
        ->and($first->issued_by)->not->toBeNull();
});

it('AC-009: the issue response carries the code and the issued state', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);

    $this->postJson(settlementUrl($draft, '/issue'))->assertOk()
        ->assertJsonPath('data.status', 'ISSUED')
        ->assertJsonPath('data.code', '1/2026')
        ->assertJsonPath('data.abilities.cancel', true)
        ->assertJsonPath('data.abilities.issue', false);
});

it('AC-009: a number is not reused after a cancellation', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);
    $this->postJson(settlementUrl($draft, '/issue'))->assertOk();
    $this->postJson(settlementUrl($draft, '/cancel'), ['reason' => 'Wrong period'])->assertOk();

    $next = draftSettlementFor($agent, $company);
    $this->postJson(settlementUrl($next, '/issue'))->assertOk()->assertJsonPath('data.code', '2/2026');
});

it('AC-009: a draft whose taxable amount is not positive after the recalculation cannot be issued', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);
    settlementMovement($agent, $company, '-150.00', '2026-09-29', ['type' => 'REVERSAL']);

    $this->postJson(settlementUrl($draft, '/issue'))->assertStatus(409);

    expect($draft->fresh()->status->value)->toBe('DRAFT')->and($draft->fresh()->number)->toBeNull();
});

it('AC-009: after the issue PATCH, recalculate, delete and a second issue are 409', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);
    $this->postJson(settlementUrl($draft, '/issue'))->assertOk();

    $this->patchJson(settlementUrl($draft), ['notes' => 'x'])->assertStatus(409);
    $this->postJson(settlementUrl($draft, '/recalculate'))->assertStatus(409);
    $this->deleteJson(settlementUrl($draft))->assertStatus(409);
    $this->postJson(settlementUrl($draft, '/issue'))->assertStatus(409);
});

it('AC-010: cancel needs an issued settlement and a reason, and releases the movements for the next run', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    $movement = settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);

    $this->postJson(settlementUrl($draft, '/cancel'), ['reason' => 'Too early'])->assertStatus(409);

    $this->postJson(settlementUrl($draft, '/issue'))->assertOk();
    $this->postJson(settlementUrl($draft, '/cancel'), [])->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->postJson(settlementUrl($draft, '/cancel'), ['reason' => 'Wrong amounts'])->assertOk()
        ->assertJsonPath('data.status', 'CANCELLED')
        ->assertJsonPath('data.cancellation_reason', 'Wrong amounts')
        ->assertJsonPath('data.code', '1/2026')
        ->assertJsonCount(0, 'data.movements');

    expect($movement->fresh()->commission_settlement_id)->toBeNull()
        ->and($draft->fresh()->cancelled_by)->not->toBeNull();

    $this->postJson('/api/commission-settlement-runs', settlementRunBody())->assertCreated()->assertJsonPath('data.run.settlements_count', 1);
});

it('AC-011: deleting a draft is a 204 that reopens its movements, adjustments included', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    $movement = settlementMovement($agent, $company, '100.00');
    $adjustment = CommissionAccrual::factory()->adjustment()->create(['recipient_id' => $agent->id, 'company_id' => $company->id, 'amount' => '5.00', 'accrued_on' => '2026-09-10']);
    $draft = draftSettlementFor($agent, $company);
    expect($adjustment->fresh()->commission_settlement_id)->toBe($draft->id);

    $this->deleteJson(settlementUrl($draft))->assertNoContent();

    expect(CommissionSettlement::query()->find($draft->id))->toBeNull()
        ->and($movement->fresh()->commission_settlement_id)->toBeNull()
        ->and($adjustment->fresh()->commission_settlement_id)->toBeNull();
});

it('AC-011: deleting an issued settlement is a 409', function () {
    Sanctum::actingAs(settlementAdmin());
    $settlement = settlementRow(settlementAgent(), Company::factory()->create(), 'issued');

    $this->deleteJson(settlementUrl($settlement))->assertStatus(409);
    expect(CommissionSettlement::query()->find($settlement->id))->not->toBeNull();
});

it('shows the detail with movements, totals, snapshot and the abilities of the state', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);

    $this->getJson(settlementUrl($draft))->assertOk()
        ->assertJsonPath('data.id', $draft->id)
        ->assertJsonPath('data.code', null)
        ->assertJsonPath('data.referent.id', $agent->id)
        ->assertJsonPath('data.company.name', $company->denomination)
        ->assertJsonPath('data.taxable_amount', '100.00')
        ->assertJsonPath('data.movements.0.type', 'ACCRUAL')
        ->assertJsonPath('data.movements.0.amount', '100.00')
        ->assertJsonPath('data.movements.0.can_edit', false)
        ->assertJsonPath('data.abilities', ['recalculate' => true, 'issue' => true, 'cancel' => false, 'delete' => true, 'adjust' => true, 'update' => true])
        ->assertJsonStructure(['permissions' => ['resource', 'fields', 'actions'], 'data' => ['movements' => [['id', 'type', 'recipient_role', 'accrued_on', 'amount', 'base_amount', 'notes', 'invoice', 'quote', 'product', 'can_edit']]]]);

    $this->getJson('/api/commission-settlements/999999')->assertNotFound();
});

it('AC-017: hidden amounts are absent from the detail, and the movements follow the commission-accruals visibility', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);

    $user = commissionSettlementUserWith(['view']);
    $role = Role::create(['name' => 'no-amounts', 'guard_name' => 'web']);
    $user->assignRole($role);
    foreach ([['commission-settlements', 'net_payable'], ['commission-settlements', 'taxable_amount'], ['commission-accruals', 'amount']] as [$resource, $field]) {
        RoleFieldPermission::query()->create(['role_id' => $role->id, 'resource' => $resource, 'field' => $field, 'visible' => false, 'editable' => false, 'required' => false]);
    }
    Sanctum::actingAs($user);

    $data = $this->getJson(settlementUrl($draft))->assertOk()->json('data');

    expect($data)->not->toHaveKeys(['net_payable', 'taxable_amount', 'accruals_amount', 'adjustments_amount'])
        ->and($data)->toHaveKeys(['vat_amount', 'withholding_amount'])
        ->and($data['movements'][0])->not->toHaveKey('amount')->toHaveKey('base_amount');
});

it('AC-017: the notes are editable in a draft only', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);

    $this->getJson(settlementUrl($draft))->assertJsonPath('permissions.fields.notes.editable', true);
    $this->patchJson(settlementUrl($draft), ['notes' => 'Agreed with the agent'])->assertOk()->assertJsonPath('data.notes', 'Agreed with the agent');

    $this->postJson(settlementUrl($draft, '/issue'))->assertOk()->assertJsonPath('permissions.fields.notes.editable', false);
    $this->patchJson(settlementUrl($draft), ['notes' => 'Changed'])->assertStatus(409);
    expect($draft->fresh()->notes)->toBe('Agreed with the agent');
});

it('L-1: the ENASARCO base is hidden whenever the taxable amount or an ENASARCO share is hidden', function (string $hiddenField) {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    $draft = draftSettlementFor($agent, $company);

    $user = commissionSettlementUserWith(['view']);
    $role = Role::create(['name' => "hide-{$hiddenField}", 'guard_name' => 'web']);
    $user->assignRole($role);
    RoleFieldPermission::query()->create(['role_id' => $role->id, 'resource' => 'commission-settlements', 'field' => $hiddenField, 'visible' => false, 'editable' => false, 'required' => false]);
    Sanctum::actingAs($user);

    expect($this->getJson(settlementUrl($draft))->assertOk()->json('data'))->not->toHaveKey('enasarco_base_amount');
})->with(['taxable_amount', 'enasarco_agent_amount', 'enasarco_principal_amount']);
