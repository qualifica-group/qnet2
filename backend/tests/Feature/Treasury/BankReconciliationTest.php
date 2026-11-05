<?php

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\BankReconciliation;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\TreasuryMovement;
use App\Models\TreasuryType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0363 — bank reconciliations, adjustment, overview count: AC-007 .. AC-011, AC-013.

const RECON_FULL = ['treasury-movements.reconcile', 'treasury-movements.view', 'treasury-movements.create'];

/** AC-007 scenario: opening 1000, +200 ticked 10/01, -50 ticked 20/01, +300 not ticked 15/01. */
function reconScenario(): FinancialAccount
{
    $account = reconAccount();
    $first = reconMovement($account, '200.00', '2026-01-10');
    $second = reconMovement($account, '50.00', '2026-01-20', income: false);
    reconMovement($account, '300.00', '2026-01-15');
    reconMark($account, ["movement:{$first->id}", "movement:{$second->id}"])->assertOk();

    return $account;
}

function reconPreview(FinancialAccount $account, string $date)
{
    return test()->getJson("/api/financial-accounts/{$account->id}/bank-reconciliations/preview?statement_date={$date}");
}

function reconStore(FinancialAccount $account, array $payload)
{
    return test()->postJson("/api/financial-accounts/{$account->id}/bank-reconciliations", $payload);
}

it('AC-007: preview gives the reconciled balance and the unreconciled count at a date', function () {
    Sanctum::actingAs(reconActor(RECON_FULL));
    $account = reconScenario();

    reconPreview($account, '2026-01-31')->assertOk()
        ->assertJsonPath('data.reconciled_balance', '1150.00')->assertJsonPath('data.unreconciled_count', 1);
    reconPreview($account, '2026-01-15')->assertOk()->assertJsonPath('data.reconciled_balance', '1200.00');
    reconPreview($account, '2025-12-31')->assertUnprocessable();
    $this->getJson("/api/financial-accounts/{$account->id}/bank-reconciliations/preview")->assertUnprocessable();
});

it('AC-008: creating the reconciliation with an adjustment closes the difference', function () {
    Sanctum::actingAs(reconActor(RECON_FULL));
    $account = reconScenario();
    $type = TreasuryType::factory()->create();

    $data = reconStore($account, ['statement_date' => '2026-01-31', 'bank_balance' => 1140, 'create_adjustment' => true, 'treasury_type_id' => $type->id, 'note' => 'January'])
        ->assertCreated()->json('data');

    expect($data['difference'])->toBe('-10.00')->and($data['reconciled_balance'])->toBe('1150.00')->and($data['bank_balance'])->toBe('1140.00')
        ->and($data['note'])->toBe('January');
    $adjustment = TreasuryMovement::query()->findOrFail($data['adjustment_movement_id']);
    expect($adjustment->type)->toBe(TreasuryMovementType::Adjustment)
        ->and($adjustment->direction)->toBe(TreasuryMovementDirection::Out)
        ->and((string) $adjustment->amount)->toBe('10.00')
        ->and($adjustment->movement_date->toDateString())->toBe('2026-01-31')
        ->and($adjustment->reconciled_at)->not->toBeNull()
        ->and($adjustment->description)->toBe(__('treasury_reconciliations.adjustment_description'));
    reconPreview($account, '2026-01-31')->assertJsonPath('data.reconciled_balance', '1140.00');
});

it('AC-009: duplicate date, missing sub-account and zero difference', function () {
    Sanctum::actingAs(reconActor(RECON_FULL));
    $account = reconScenario();
    $type = TreasuryType::factory()->create();

    reconStore($account, ['statement_date' => '2026-01-31', 'bank_balance' => 1150, 'create_adjustment' => true])
        ->assertCreated()->assertJsonPath('data.adjustment_movement_id', null)->assertJsonPath('data.difference', '0.00');
    reconStore($account, ['statement_date' => '2026-01-31', 'bank_balance' => 1150, 'create_adjustment' => false])->assertUnprocessable();
    reconStore($account, ['statement_date' => '2026-01-30', 'bank_balance' => 1000, 'create_adjustment' => true])->assertUnprocessable();
    reconStore($account, ['statement_date' => '2026-01-29', 'bank_balance' => 1000, 'create_adjustment' => true, 'treasury_type_id' => TreasuryType::factory()->inactive()->create()->id])->assertUnprocessable();
    reconStore($account, ['statement_date' => '2025-12-31', 'bank_balance' => 1000, 'create_adjustment' => false])->assertUnprocessable();

    expect(BankReconciliation::query()->count())->toBe(1)->and(TreasuryMovement::query()->where('type', 'adjustment')->count())->toBe(0);
    reconStore($account, ['statement_date' => '2026-01-28', 'bank_balance' => 1300.5, 'create_adjustment' => true, 'treasury_type_id' => $type->id])
        ->assertCreated()->assertJsonPath('data.difference', '150.50');
    expect(TreasuryMovement::query()->where('type', 'adjustment')->first()->direction)->toBe(TreasuryMovementDirection::In);
});

it('AC-010: deleting a reconciliation keeps its adjustment, still ticked', function () {
    Sanctum::actingAs(reconActor(RECON_FULL));
    $account = reconScenario();
    $created = reconStore($account, ['statement_date' => '2026-01-31', 'bank_balance' => 1140, 'create_adjustment' => true, 'treasury_type_id' => TreasuryType::factory()->create()->id])->json('data');
    $this->getJson("/api/financial-accounts/{$account->id}/bank-reconciliations")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.created_by.id', auth()->id());

    $this->deleteJson("/api/bank-reconciliations/{$created['id']}")->assertOk()->assertJsonPath('success', true);

    $this->getJson("/api/financial-accounts/{$account->id}/bank-reconciliations")->assertJsonCount(0, 'data');
    expect(TreasuryMovement::query()->find($created['adjustment_movement_id'])->reconciled_at)->not->toBeNull();
});

it('AC-011: only view reads the history; reconcile without create cannot create the adjustment', function () {
    Sanctum::actingAs(reconActor(RECON_FULL));
    $account = reconScenario();
    $reconciliation = BankReconciliation::query()->create(['financial_account_id' => $account->id, 'statement_date' => '2026-01-31', 'bank_balance' => '1150.00', 'reconciled_balance' => '1150.00', 'difference' => '0.00']);

    Sanctum::actingAs(reconActor(['treasury-movements.view']));
    $this->getJson("/api/financial-accounts/{$account->id}/bank-reconciliations")->assertOk();
    reconPreview($account, '2026-01-31')->assertForbidden();
    reconStore($account, ['statement_date' => '2026-02-01', 'bank_balance' => 1, 'create_adjustment' => false])->assertForbidden();
    $this->deleteJson("/api/bank-reconciliations/{$reconciliation->id}")->assertForbidden();

    Sanctum::actingAs(reconActor(['treasury-movements.reconcile']));
    reconStore($account, ['statement_date' => '2026-02-01', 'bank_balance' => 1, 'create_adjustment' => true, 'treasury_type_id' => TreasuryType::factory()->create()->id])->assertForbidden();
    reconStore($account, ['statement_date' => '2026-02-01', 'bank_balance' => 1, 'create_adjustment' => false])->assertCreated();
    $this->getJson("/api/financial-accounts/{$account->id}/bank-reconciliations")->assertForbidden();
});

it('AC-013: the overview counts unticked rows up to today per account, filtered by company', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00'));
    Sanctum::actingAs(reconActor(['treasury-movements.view']));
    $company = Company::factory()->create();
    $busy = FinancialAccount::factory()->create(['company_id' => $company->id, 'opening_balance' => '0.00']);
    $quiet = FinancialAccount::factory()->create(['company_id' => $company->id, 'opening_balance' => '0.00']);
    $other = reconAccount();
    reconMovement($busy, '10.00', '2026-03-01');
    reconMovement($busy, '10.00', '2026-03-02');
    reconMovement($busy, '10.00', '2026-12-01');
    $ticked = reconMovement($quiet, '10.00', '2026-03-01');
    $ticked->update(['reconciled_at' => now()]);
    reconMovement($other, '10.00', '2026-03-01');

    $scoped = $this->getJson("/api/treasury-overview?company_id={$company->id}")->assertOk()->json('data.to_verify.unreconciled');
    expect($scoped)->toBe(['count' => 2, 'accounts' => [['id' => $busy->id, 'name' => $busy->name, 'count' => 2]]]);

    $all = $this->getJson('/api/treasury-overview')->assertOk()->json('data.to_verify.unreconciled');
    expect($all['count'])->toBe(3)->and($all['accounts'][0]['id'])->toBe($busy->id);
});

it('exposes the reconciled column and its yes/no set filter on both installment grids', function () {
    $account = reconAccount();
    $ticked = reconCollection($account, '10.00', '2026-01-10');
    $open = reconCollection($account, '20.00', '2026-01-11');
    $paidTicked = reconPayment($account, '5.00', '2026-01-12');
    $paidOpen = reconPayment($account, '6.00', '2026-01-13');
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile']));
    reconMark($account, ["collection:{$ticked->id}", "payment:{$paidTicked->id}"])->assertOk();

    Sanctum::actingAs(installmentUserWith(['viewAny', 'view'], ['invoices.view']));
    $ids = fn (string $value): array => array_column($this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 50, 'filterModel' => ['reconciled' => ['filterType' => 'set', 'values' => [$value]]]])->assertOk()->json('items'), 'id');
    expect($ids('yes'))->toBe([$ticked->id])->and($ids('no'))->toBe([$open->id]);

    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view'], ['purchase-invoices.pay']));
    $purchaseIds = fn (string $value): array => array_column($this->postJson('/api/tables/purchase-invoice-installments/rows', ['startRow' => 0, 'endRow' => 50, 'filterModel' => ['reconciled' => ['filterType' => 'set', 'values' => [$value]]]])->assertOk()->json('items'), 'id');
    expect($purchaseIds('yes'))->toBe([$paidTicked->id])->and($purchaseIds('no'))->toBe([$paidOpen->id]);
});
