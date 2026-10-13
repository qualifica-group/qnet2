<?php

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const ADJUSTMENTS_URL = '/api/commission-adjustments';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adjustmentBody(int $referentId, int $companyId, array $overrides = []): array
{
    return ['referent_id' => $referentId, 'company_id' => $companyId, 'amount' => '-25.50', 'accrued_on' => '2026-09-15', 'notes' => 'Customer credit note', ...$overrides];
}

it('AC-012: a standalone adjustment is an open ADJUSTMENT movement that the next run takes', function () {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '200.00');

    $this->postJson(ADJUSTMENTS_URL, adjustmentBody($agent->id, $company->id))->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.type', 'ADJUSTMENT')
        ->assertJsonPath('data.amount', '-25.50')
        ->assertJsonPath('data.referent.id', $agent->id)
        ->assertJsonPath('data.company.name', $company->denomination)
        ->assertJsonPath('data.commission_settlement_id', null);

    $row = CommissionAccrual::query()->where('type', 'ADJUSTMENT')->sole();
    expect($row->recipient_type)->toBe('referent')->and($row->recipient_role)->toBeNull()->and($row->created_by)->not->toBeNull();

    $this->postJson('/api/commission-settlement-runs', settlementRunBody())->assertCreated();
    expect($row->fresh()->commission_settlement_id)->not->toBeNull()
        ->and(CommissionSettlement::query()->sole()->taxable_amount)->toBe('174.50');
});

it('AC-012: an adjustment created inside a draft is attached and the draft recalculated', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '200.00');
    $draft = draftSettlementFor($agent, $company);

    $this->postJson(ADJUSTMENTS_URL, adjustmentBody($agent->id, $company->id, ['amount' => '30.00', 'accrued_on' => '2026-10-20', 'commission_settlement_id' => $draft->id]))
        ->assertCreated()->assertJsonPath('data.commission_settlement_id', $draft->id);

    $draft = $draft->fresh();
    expect($draft->adjustments_amount)->toBe('30.00')->and($draft->taxable_amount)->toBe('230.00');

    // A later recalculation keeps the attached adjustment although it is dated after period_to
    $this->postJson("/api/commission-settlements/{$draft->id}/recalculate")->assertOk()->assertJsonPath('data.taxable_amount', '230.00')
        ->assertJsonPath('data.movements.1.can_edit', true);
});

it('AC-012: a draft of another agent or company, or one that is not a draft, is a 422', function () {
    Sanctum::actingAs(settlementAdmin());
    [$company, $otherCompany] = [Company::factory()->create(), Company::factory()->create()];
    [$agent, $otherAgent] = [settlementAgent(), settlementAgent()];
    $draft = settlementRow($agent, $company);
    $issued = settlementRow($agent, $company, 'issued');

    foreach ([
        adjustmentBody($otherAgent->id, $company->id, ['commission_settlement_id' => $draft->id]),
        adjustmentBody($agent->id, $otherCompany->id, ['commission_settlement_id' => $draft->id]),
        adjustmentBody($agent->id, $company->id, ['commission_settlement_id' => $issued->id]),
    ] as $body) {
        $this->postJson(ADJUSTMENTS_URL, $body)->assertUnprocessable()->assertJsonValidationErrors('commission_settlement_id');
    }

    expect(CommissionAccrual::query()->count())->toBe(0);
});

it('AC-012: a zero amount, a missing note or a missing agent is a 422', function (array $override, string $field) {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();

    $this->postJson(ADJUSTMENTS_URL, [...adjustmentBody($agent->id, $company->id), ...$override])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'zero amount' => [['amount' => '0.00'], 'amount'],
    'zero amount without decimals' => [['amount' => 0], 'amount'],
    'too many decimals' => [['amount' => '1.234'], 'amount'],
    'missing notes' => [['notes' => ''], 'notes'],
    'notes too long' => [['notes' => str_repeat('x', 501)], 'notes'],
    'unknown agent' => [['referent_id' => 999999], 'referent_id'],
]);

it('AC-012: PUT and DELETE change an adjustment of a draft and recalculate it', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '200.00');
    $draft = draftSettlementFor($agent, $company);
    $id = $this->postJson(ADJUSTMENTS_URL, adjustmentBody($agent->id, $company->id, ['amount' => '30.00', 'commission_settlement_id' => $draft->id]))->json('data.id');

    $this->putJson(ADJUSTMENTS_URL."/{$id}", ['amount' => '-50.00', 'accrued_on' => '2026-09-16', 'notes' => 'Corrected'])->assertOk()
        ->assertJsonPath('data.amount', '-50.00')->assertJsonPath('data.notes', 'Corrected');
    expect($draft->fresh()->taxable_amount)->toBe('150.00');

    $this->deleteJson(ADJUSTMENTS_URL."/{$id}")->assertNoContent();
    expect($draft->fresh()->taxable_amount)->toBe('200.00')->and(CommissionAccrual::query()->find($id))->toBeNull();
});

it('AC-012: PUT and DELETE of an adjustment frozen in an issued settlement are a 409', function () {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();
    $issued = settlementRow($agent, $company, 'issued');
    $adjustment = CommissionAccrual::factory()->adjustment()->create(['recipient_id' => $agent->id, 'company_id' => $company->id, 'commission_settlement_id' => $issued->id, 'amount' => '10.00']);

    $this->putJson(ADJUSTMENTS_URL."/{$adjustment->id}", ['amount' => '99.00', 'accrued_on' => '2026-09-16', 'notes' => 'x'])->assertStatus(409);
    $this->deleteJson(ADJUSTMENTS_URL."/{$adjustment->id}")->assertStatus(409);

    expect($adjustment->fresh()->amount)->toBe('10.00');
});

it('AC-012: PUT and DELETE on a movement that is not an ADJUSTMENT are a 404', function () {
    Sanctum::actingAs(settlementAdmin());
    $movement = settlementMovement(settlementAgent(), Company::factory()->create(), '100.00');

    $this->putJson(ADJUSTMENTS_URL."/{$movement->id}", ['amount' => '1.00', 'accrued_on' => '2026-09-16', 'notes' => 'x'])->assertNotFound();
    $this->deleteJson(ADJUSTMENTS_URL."/{$movement->id}")->assertNotFound();
    $this->deleteJson(ADJUSTMENTS_URL.'/999999')->assertNotFound();

    expect($movement->fresh()->amount)->toBe('100.00');
});

it('M-1: dates must be Y-m-d strings (run and adjustment)', function () {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();

    $this->postJson(ADJUSTMENTS_URL, adjustmentBody($agent->id, $company->id, ['accrued_on' => '1 Jan 2030']))->assertUnprocessable()->assertJsonValidationErrors('accrued_on');
    $this->postJson('/api/commission-settlement-runs', settlementRunBody(['period_to' => '1 Jan 2030']))->assertUnprocessable()->assertJsonValidationErrors('period_to');
    $this->postJson('/api/commission-settlement-runs', settlementRunBody(['period_from' => '1 Jan 2020']))->assertUnprocessable()->assertJsonValidationErrors('period_from');
});

it('M-2: create, update and delete of an adjustment are audited with actor and old/new values', function () {
    $actor = settlementAdmin();
    Sanctum::actingAs($actor);
    $company = Company::factory()->create();
    $agent = settlementAgent();

    $id = $this->postJson(ADJUSTMENTS_URL, adjustmentBody($agent->id, $company->id))->assertCreated()->json('data.id');
    $this->putJson(ADJUSTMENTS_URL."/{$id}", ['amount' => '-40.00', 'accrued_on' => '2026-09-16', 'notes' => 'Corrected'])->assertOk();
    $this->deleteJson(ADJUSTMENTS_URL."/{$id}")->assertNoContent();

    $entries = Activity::query()->where('log_name', 'commission_accruals')->orderBy('id')->get();
    expect($entries->pluck('event')->all())->toBe(['commission-adjustment.created', 'commission-adjustment.updated', 'commission-adjustment.deleted'])
        ->and($entries->pluck('causer_id')->unique()->all())->toBe([$actor->id])
        ->and($entries[0]->subject_id)->toBe($id)
        ->and($entries[0]->properties['new']['amount'])->toBe('-25.50')
        ->and($entries[0]->properties['new']['referent_id'])->toBe($agent->id)
        ->and($entries[1]->properties['old']['amount'])->toBe('-25.50')
        ->and($entries[1]->properties['new']['amount'])->toBe('-40.00')
        ->and($entries[2]->subject_type)->toBe('referent')
        ->and($entries[2]->subject_id)->toBe($agent->id)
        ->and($entries[2]->properties['old']['notes'])->toBe('Corrected');
});

it('M-2: an adjustment inside a draft is audited on the settlement', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '200.00');
    $draft = draftSettlementFor($agent, $company);

    $this->postJson(ADJUSTMENTS_URL, adjustmentBody($agent->id, $company->id, ['amount' => '5.00', 'commission_settlement_id' => $draft->id]))->assertCreated();

    $entry = Activity::query()->where('event', 'commission-adjustment.created')->sole();
    expect($entry->subject_type)->toBe('commission_settlement')->and($entry->subject_id)->toBe($draft->id)
        ->and($entry->properties['new']['commission_settlement_id'])->toBe($draft->id);
});
