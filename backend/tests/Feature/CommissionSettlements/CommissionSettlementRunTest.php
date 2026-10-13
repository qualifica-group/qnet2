<?php

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\Company;
use App\Models\EnasarcoParameter;
use App\Models\Referent;
use App\Models\VatRate;
use App\Services\Commissions\Settlements\OpenMovementSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const RUN_URL = '/api/commission-settlement-runs';

it('AC-003: creates a draft per (referent, company), attaches the movements up to period_to and returns created/skipped', function () {
    Sanctum::actingAs(settlementAdmin());
    [$companyA, $companyB] = [Company::factory()->create(), Company::factory()->create()];
    [$ann, $bob] = [settlementAgent(['vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id]), settlementAgent()];

    $early = settlementMovement($ann, $companyA, '300.00', '2026-07-15');
    $inPeriod = settlementMovement($ann, $companyA, '700.00', '2026-09-20');
    $later = settlementMovement($ann, $companyA, '50.00', '2026-10-02');
    $otherCompany = settlementMovement($ann, $companyB, '200.00');
    $bobMovement = settlementMovement($bob, $companyA, '400.00');

    $response = $this->postJson(RUN_URL, settlementRunBody())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.run.settlements_count', 3)
        ->assertJsonPath('data.run.period_to', '2026-09-30')
        ->assertJsonCount(3, 'data.created')
        ->assertJsonCount(0, 'data.skipped')
        ->assertJsonStructure(['data' => [
            'run' => ['id', 'period_from', 'period_to', 'filters', 'min_amount', 'settlements_count', 'created_at', 'created_by' => ['id', 'name']],
            'created' => [['settlement_id', 'referent' => ['id', 'name'], 'company' => ['id', 'name'], 'taxable_amount', 'net_payable']],
        ]]);

    $draft = CommissionSettlement::query()->where('referent_id', $ann->id)->where('company_id', $companyA->id)->sole();
    expect($draft->status->value)->toBe('DRAFT')
        ->and($draft->taxable_amount)->toBe('1000.00')
        ->and($draft->accruals_amount)->toBe('1000.00')
        ->and($draft->vat_amount)->toBe('220.00')
        ->and($draft->withholding_amount)->toBe('115.00')
        ->and($draft->net_payable)->toBe('1105.00')
        ->and($draft->commission_settlement_run_id)->toBe($response->json('data.run.id'));

    expect($early->fresh()->commission_settlement_id)->toBe($draft->id)
        ->and($inPeriod->fresh()->commission_settlement_id)->toBe($draft->id)
        ->and($later->fresh()->commission_settlement_id)->toBeNull()
        ->and($otherCompany->fresh()->commission_settlement_id)->not->toBeNull()->not->toBe($draft->id)
        ->and($bobMovement->fresh()->commission_settlement_id)->not->toBeNull();
});

it('AC-003: the run can be read back with created rebuilt from the settlements', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    settlementMovement(settlementAgent(), $company, '-5.00');

    $runId = $this->actingAs(settlementAdmin())->postJson(RUN_URL, settlementRunBody())->assertCreated()->json('data.run.id');

    $this->getJson(RUN_URL."/{$runId}")
        ->assertOk()
        ->assertJsonPath('data.run.id', $runId)
        ->assertJsonCount(1, 'data.created')
        ->assertJsonPath('data.created.0.referent.id', $agent->id)
        ->assertJsonCount(1, 'data.skipped')
        ->assertJsonPath('data.skipped.0.reason', 'non_positive')
        ->assertJsonPath('data.skipped.0.taxable_amount', '-5.00');

    $this->getJson(RUN_URL.'/999999')->assertNotFound();
});

it('AC-004: user recipients, externally settled and already attached movements never enter', function () {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');
    settlementMovement($agent, $company, '900.00', overrides: ['settled_externally_on' => '2026-01-01']);
    settlementMovement($agent, $company, '800.00', overrides: ['recipient_type' => 'user']);
    $attached = settlementMovement($agent, $company, '700.00', overrides: ['commission_settlement_id' => settlementRow($agent, $company, 'issued')->id]);

    $this->postJson(RUN_URL, settlementRunBody())->assertCreated()->assertJsonPath('data.run.settlements_count', 1);

    $draft = CommissionSettlement::query()->where('status', 'DRAFT')->sole();
    expect($draft->taxable_amount)->toBe('100.00')
        ->and($attached->fresh()->commission_settlement_id)->not->toBe($draft->id);
});

it('AC-004: the company, referent and role filters narrow the selection; adjustments follow referent and company only', function () {
    Sanctum::actingAs(settlementAdmin());
    [$companyA, $companyB] = [Company::factory()->create(), Company::factory()->create()];
    [$ann, $bob] = [settlementAgent(), settlementAgent()];
    settlementMovement($ann, $companyA, '100.00', overrides: ['recipient_role' => 'COMMERCIAL']);
    settlementMovement($ann, $companyA, '40.00', overrides: ['recipient_role' => 'REPORTER']);
    CommissionAccrual::factory()->adjustment()->create(['recipient_id' => $ann->id, 'company_id' => $companyA->id, 'amount' => '-10.00', 'accrued_on' => '2026-09-01']);
    settlementMovement($ann, $companyB, '500.00');
    settlementMovement($bob, $companyA, '600.00');

    $this->postJson(RUN_URL, settlementRunBody([
        'company_id' => $companyA->id,
        'referent_ids' => [$ann->id],
        'recipient_roles' => ['COMMERCIAL'],
    ]))->assertCreated()->assertJsonPath('data.run.settlements_count', 1)
        ->assertJsonPath('data.run.filters.company_id', $companyA->id);

    $draft = CommissionSettlement::query()->sole();
    expect($draft->accruals_amount)->toBe('100.00')
        ->and($draft->adjustments_amount)->toBe('-10.00')
        ->and($draft->taxable_amount)->toBe('90.00')
        ->and(CommissionAccrual::query()->whereNull('commission_settlement_id')->count())->toBe(3);
});

it('AC-005: skips every pair that cannot be settled, keeping the movements open', function () {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    EnasarcoParameter::factory()->create(['year' => 2025]);

    $negative = settlementAgent();
    $small = settlementAgent();
    $noProfile = Referent::factory()->create();
    $noParams = settlementAgent(['enasarco_enabled' => true, 'enasarco_mandate_type' => 'mono']);
    $withDraft = settlementAgent();
    settlementRow($withDraft, $company);

    foreach ([[$negative, '-20.00'], [$small, '4.00'], [$noProfile, '300.00'], [$noParams, '300.00'], [$withDraft, '300.00']] as [$referent, $amount]) {
        settlementMovement($referent, $company, $amount);
    }

    $response = $this->postJson(RUN_URL, settlementRunBody(['min_amount' => '5']))->assertCreated()
        ->assertJsonPath('data.run.settlements_count', 0)
        ->assertJsonPath('data.run.min_amount', '5.00')
        ->assertJsonCount(5, 'data.skipped');

    $reasons = collect($response->json('data.skipped'))->mapWithKeys(fn (array $row): array => [$row['referent']['id'] => $row['reason']]);
    expect($reasons[$negative->id])->toBe('non_positive')
        ->and($reasons[$small->id])->toBe('below_threshold')
        ->and($reasons[$noProfile->id])->toBe('missing_profile')
        ->and($reasons[$noParams->id])->toBe('missing_enasarco_parameters')
        ->and($reasons[$withDraft->id])->toBe('draft_exists');

    expect(CommissionAccrual::query()->whereNotNull('commission_settlement_id')->count())->toBe(0)
        ->and(CommissionSettlement::query()->count())->toBe(1)
        ->and(CommissionSettlementRun::query()->sole()->skipped)->toHaveCount(5);
});

it('AC-005: the threshold defaults to the config value', function () {
    config(['commissions.settlement_min_amount' => '50.00']);
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    settlementMovement(settlementAgent(), $company, '49.99');

    $this->postJson(RUN_URL, settlementRunBody())->assertCreated()
        ->assertJsonPath('data.run.min_amount', '50.00')
        ->assertJsonPath('data.skipped.0.reason', 'below_threshold');
});

it('AC-006: a negative balance carried from January is settled with February', function () {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '-80.00', '2026-01-20');

    $this->postJson(RUN_URL, settlementRunBody(['period_from' => '2026-01-01', 'period_to' => '2026-01-31']))->assertCreated()
        ->assertJsonPath('data.skipped.0.reason', 'non_positive');

    settlementMovement($agent, $company, '500.00', '2026-02-10');

    $this->postJson(RUN_URL, settlementRunBody(['period_from' => '2026-02-01', 'period_to' => '2026-02-28']))->assertCreated()
        ->assertJsonPath('data.run.settlements_count', 1);

    expect(CommissionSettlement::query()->sole()->taxable_amount)->toBe('420.00');
});

it('AC-002: the ENASARCO base is capped by the issued settlements of the same agent, company and year', function () {
    Sanctum::actingAs(settlementAdmin());
    [$company, $other] = [Company::factory()->create(), Company::factory()->create()];
    EnasarcoParameter::factory()->create(['year' => 2026, 'single_firm_ceiling' => '10000.00', 'multi_firm_ceiling' => '4000.00']);
    $agent = settlementAgent(['enasarco_enabled' => true, 'enasarco_mandate_type' => 'mono']);
    $issued = fn (Company $on, int $year, int $number, string $state = 'issued') => settlementRow($agent, $on, $state, ['year' => $year, 'number' => $number, 'enasarco_base_amount' => '9500.00']);
    $issued($company, 2026, 1);
    // None of these counts: other company, other year, cancelled
    $issued($other, 2026, 1);
    $issued($company, 2025, 9);
    $issued($company, 2026, 2, 'cancelled');

    settlementMovement($agent, $company, '1000.00');
    $this->postJson(RUN_URL, settlementRunBody())->assertCreated();

    $draft = CommissionSettlement::query()->where('status', 'DRAFT')->sole();
    expect($draft->enasarco_base_amount)->toBe('500.00')
        ->and($draft->enasarco_agent_amount)->toBe('42.50')
        ->and($draft->enasarco_principal_amount)->toBe('42.50');
});

it('AC-002: mono and pluri use their own ceiling, and a reached ceiling charges nothing', function (string $mandate, string $base) {
    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    EnasarcoParameter::factory()->create(['year' => 2026, 'single_firm_ceiling' => '600.00', 'multi_firm_ceiling' => '200.00']);
    $agent = settlementAgent(['enasarco_enabled' => true, 'enasarco_mandate_type' => $mandate]);
    settlementMovement($agent, $company, '1000.00');

    $this->postJson(RUN_URL, settlementRunBody())->assertCreated();

    expect(CommissionSettlement::query()->sole()->enasarco_base_amount)->toBe($base);
})->with([['mono', '600.00'], ['pluri', '200.00']]);

it('AC-014: the movement selection is a locking read and a second run on the same period skips with draft_exists', function () {
    $query = app(OpenMovementSelector::class)->lockedQuery(1, 1, '2026-09-30');
    expect($query->getQuery()->lock)->toBeTrue();

    Sanctum::actingAs(settlementAdmin());
    $company = Company::factory()->create();
    $agent = settlementAgent();
    settlementMovement($agent, $company, '100.00');

    $this->postJson(RUN_URL, settlementRunBody())->assertCreated()->assertJsonPath('data.run.settlements_count', 1);
    settlementMovement($agent, $company, '60.00', '2026-09-25');

    $this->postJson(RUN_URL, settlementRunBody())->assertCreated()
        ->assertJsonPath('data.run.settlements_count', 0)
        ->assertJsonPath('data.skipped.0.reason', 'draft_exists')
        ->assertJsonPath('data.skipped.0.taxable_amount', '60.00');

    expect(CommissionSettlement::query()->count())->toBe(1)
        ->and(CommissionAccrual::query()->whereNull('commission_settlement_id')->count())->toBe(1);
});

it('rejects an invalid run body with 422', function (array $override, string $field) {
    Sanctum::actingAs(settlementAdmin());

    $this->postJson(RUN_URL, settlementRunBody($override))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'period_to before period_from' => [['period_to' => '2026-08-31'], 'period_to'],
    'unknown company' => [['company_id' => 999999], 'company_id'],
    'unknown referent' => [['referent_ids' => [999999]], 'referent_ids.0'],
    'supplier role' => [['recipient_roles' => ['SUPPLIER']], 'recipient_roles.0'],
    'negative threshold' => [['min_amount' => '-1'], 'min_amount'],
]);
