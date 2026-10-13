<?php

use App\Models\CommissionAccrual;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('--settled-before marks only the open invoice movements: adjustments and attached ones stay', function () {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    $open = settlementMovement($agent, $company, '100.00', '2026-01-10');
    $recent = settlementMovement($agent, $company, '100.00', '2026-03-10');
    $attached = settlementMovement($agent, $company, '100.00', '2026-01-11', ['commission_settlement_id' => settlementRow($agent, $company)->id]);
    $adjustment = CommissionAccrual::factory()->adjustment()->create(['recipient_id' => $agent->id, 'company_id' => $company->id, 'accrued_on' => '2026-01-12']);

    $this->artisan('commissions:sync-accruals', ['--settled-before' => '2026-02-01'])
        ->expectsOutput('Invoices: 0, accruals: 0, reversals: 0, marked external: 1')->assertExitCode(0);

    expect($open->fresh()->settled_externally_on?->toDateString())->toBe('2026-02-01')
        ->and($recent->fresh()->settled_externally_on)->toBeNull()
        ->and($attached->fresh()->settled_externally_on)->toBeNull()
        ->and($adjustment->fresh()->settled_externally_on)->toBeNull();
});
