<?php

use App\Models\CommissionAccrual;
use App\Models\CommissionSettlementRun;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Endpoint to call and the single ability that unlocks it: every other ability is granted in the 403 case.
dataset('settlement endpoints', [
    'launch' => ['create', 'POST', '/api/commission-settlement-runs', ['period_from' => '2026-09-01', 'period_to' => '2026-09-30']],
    'read a run' => ['viewAny', 'GET', '/api/commission-settlement-runs/{run}', []],
    'view' => ['view', 'GET', '/api/commission-settlements/{settlement}', []],
    'notes' => ['update', 'PATCH', '/api/commission-settlements/{settlement}', ['notes' => 'x']],
    'recalculate' => ['update', 'POST', '/api/commission-settlements/{settlement}/recalculate', []],
    'issue' => ['issue', 'POST', '/api/commission-settlements/{settlement}/issue', []],
    'cancel' => ['cancel', 'POST', '/api/commission-settlements/{settlement}/cancel', ['reason' => 'x']],
    'delete' => ['delete', 'DELETE', '/api/commission-settlements/{settlement}', []],
    'create adjustment' => ['adjust', 'POST', '/api/commission-adjustments', ['referent_id' => 1, 'company_id' => 1, 'amount' => '1.00', 'accrued_on' => '2026-09-01', 'notes' => 'x']],
    'update adjustment' => ['adjust', 'PUT', '/api/commission-adjustments/{adjustment}', ['amount' => '1.00', 'accrued_on' => '2026-09-01', 'notes' => 'x']],
    'delete adjustment' => ['adjust', 'DELETE', '/api/commission-adjustments/{adjustment}', []],
]);

it('AC-016: every endpoint answers 403 without its ability and passes the gate with it', function (string $ability, string $method, string $url, array $body) {
    $company = Company::factory()->create();
    $agent = settlementAgent();
    $settlement = settlementRow($agent, $company);
    $run = CommissionSettlementRun::factory()->create();
    $adjustment = CommissionAccrual::factory()->adjustment()->create(['recipient_id' => $agent->id, 'company_id' => $company->id]);
    $url = str_replace(['{settlement}', '{run}', '{adjustment}'], [$settlement->id, $run->id, $adjustment->id], $url);
    $body = array_merge($body, str_contains($url, 'commission-adjustments') && $method === 'POST' ? ['referent_id' => $agent->id, 'company_id' => $company->id] : []);

    $all = ['viewAny', 'view', 'create', 'update', 'delete', 'issue', 'cancel', 'adjust', 'export', 'viewActivity'];
    Sanctum::actingAs(commissionSettlementUserWith(array_values(array_diff($all, [$ability]))));
    $this->json($method, $url, $body)->assertForbidden();

    Sanctum::actingAs(commissionSettlementUserWith([$ability]));
    expect($this->json($method, $url, $body)->status())->not->toBe(403)->not->toBe(500);
})->with('settlement endpoints');

it('AC-016: an unauthenticated request is a 401', function () {
    $this->postJson('/api/commission-settlement-runs', [])->assertUnauthorized();
    $this->postJson('/api/commission-adjustments', [])->assertUnauthorized();
});
