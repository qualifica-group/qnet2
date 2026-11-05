<?php

use App\Enums\FinancialAccountType;
use App\Enums\TreasuryMovementDirection;
use App\Enums\WriteOffReason;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\TreasuryMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0362 — treasury overview ("Quadro finanziario"): AC-001..AC-009.

const OVERVIEW_TODAY = '2026-11-15';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(OVERVIEW_TODAY.' 10:00:00'));
});

function overviewActor(bool $view = true): User
{
    Permission::findOrCreate('treasury-movements.view');
    $user = User::factory()->create();

    return $view ? $user->givePermissionTo('treasury-movements.view') : $user;
}

function overviewDay(int $offset): string
{
    return CarbonImmutable::parse(OVERVIEW_TODAY)->addDays($offset)->toDateString();
}

/** An open receivable installment of a document of the company, due at today + offset. */
function openReceivable(Company $company, string $amount, int $offset, string $type = 'invoice'): InvoiceInstallment
{
    return InvoiceInstallment::factory()->create([
        'invoice_id' => Invoice::factory()->create(['company_id' => $company->id, 'type' => $type])->id,
        'amount' => $amount, 'collected_amount' => null, 'collected_at' => null, 'due_date' => overviewDay($offset),
    ]);
}

function openPayable(Company $company, string $amount, int $offset, string $type = 'invoice'): PurchaseInvoiceInstallment
{
    return PurchaseInvoiceInstallment::factory()->create([
        'purchase_invoice_id' => PurchaseInvoice::factory()->create(['company_id' => $company->id, 'document_type' => $type])->id,
        'amount' => $amount, 'paid_amount' => null, 'paid_at' => null, 'due_date' => overviewDay($offset),
    ]);
}

it('AC-001/AC-002: 401, 403 without view, 422 bad company', function () {
    $this->getJson('/api/treasury-overview')->assertUnauthorized();

    Sanctum::actingAs(overviewActor(false));
    $this->getJson('/api/treasury-overview')->assertForbidden();

    Sanctum::actingAs(overviewActor());
    $this->getJson('/api/treasury-overview?company_id=999999')->assertStatus(422);
    $this->getJson('/api/treasury-overview?company_id=abc')->assertStatus(422);
});

it('AC-003: liquidity by company, by type and total equal the account balances', function () {
    Sanctum::actingAs(overviewActor());
    [$a, $b] = Company::factory()->count(2)->create();
    $bank = FinancialAccount::factory()->create(['company_id' => $a->id, 'name' => 'Banca', 'type' => FinancialAccountType::BankAccount, 'opening_balance' => '1000.00']);
    FinancialAccount::factory()->create(['company_id' => $a->id, 'name' => 'Cassa', 'type' => FinancialAccountType::Cash, 'opening_balance' => '50.25']);
    FinancialAccount::factory()->create(['company_id' => $b->id, 'name' => 'Carta', 'type' => FinancialAccountType::Card, 'opening_balance' => '-20.00']);
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $bank->id, 'amount' => '100.00', 'movement_date' => '2026-03-10']);

    $scoped = $this->getJson("/api/treasury-overview?company_id={$a->id}")->assertOk()->json('data');
    expect($scoped['today'])->toBe(OVERVIEW_TODAY)
        ->and($scoped['company_id'])->toBe($a->id)
        ->and(array_column($scoped['liquidity']['accounts'], 'name'))->toBe(['Banca', 'Cassa'])
        ->and($scoped['liquidity']['accounts'][0]['balance'])->toBe('900.00')
        ->and($scoped['liquidity']['by_type'])->toBe(['bank_account' => '900.00', 'card' => '0.00', 'cash' => '50.25'])
        ->and($scoped['liquidity']['total'])->toBe('950.25');

    $all = $this->getJson('/api/treasury-overview')->json('data');
    expect($all['company_id'])->toBeNull()
        ->and($all['liquidity']['accounts'])->toHaveCount(3)
        ->and($all['liquidity']['by_type']['card'])->toBe('-20.00')
        ->and($all['liquidity']['total'])->toBe('930.25');
});

it('AC-004/AC-005/AC-006: open receivables and payables by overdue bucket, net position', function () {
    Sanctum::actingAs(overviewActor());
    [$a, $b] = Company::factory()->count(2)->create();
    FinancialAccount::factory()->create(['company_id' => $a->id, 'opening_balance' => '500.00']);
    openReceivable($a, '100.00', 10);
    openReceivable($a, '10.00', -5, 'proforma');
    openReceivable($a, '20.00', -45);
    openReceivable($a, '30.00', -75);
    openReceivable($a, '40.00', -120);
    openReceivable($b, '999.00', -5);
    $writtenOff = openReceivable($a, '7.00', -10);
    $writtenOff->update(['collected_amount' => '0.00', 'write_off_amount' => '7.00', 'write_off_reason' => WriteOffReason::BadDebt]);
    openPayable($a, '60.00', 5);
    openPayable($a, '15.00', -20, 'proforma');
    openPayable($a, '25.00', -95);

    $data = $this->getJson("/api/treasury-overview?company_id={$a->id}")->json('data');
    expect($data['receivable'])->toBe([
        'open_total' => '200.00',
        'not_due_total' => '100.00',
        'overdue_total' => '100.00',
        'overdue' => ['overdue_1_30' => '10.00', 'overdue_31_60' => '20.00', 'overdue_61_90' => '30.00', 'overdue_over_90' => '40.00'],
    ])->and($data['payable'])->toBe([
        'open_total' => '100.00',
        'not_due_total' => '60.00',
        'overdue_total' => '40.00',
        'overdue' => ['overdue_1_30' => '15.00', 'overdue_31_60' => '0.00', 'overdue_61_90' => '0.00', 'overdue_over_90' => '25.00'],
    ])->and($data['net_position'])->toBe('600.00');

    expect($this->getJson('/api/treasury-overview')->json('data.receivable.open_total'))->toBe('1199.00');
});

it('AC-007: forecast windows from today to today+N, overdue excluded', function () {
    Sanctum::actingAs(overviewActor());
    $company = Company::factory()->create();
    FinancialAccount::factory()->create(['company_id' => $company->id, 'opening_balance' => '1000.00']);
    openReceivable($company, '1.00', 0);
    openReceivable($company, '2.00', 30);
    openReceivable($company, '4.00', 31);
    openReceivable($company, '8.00', 90);
    openReceivable($company, '16.00', 91);
    openReceivable($company, '32.00', -1);
    openPayable($company, '100.00', 30);
    openPayable($company, '200.00', 60);
    openPayable($company, '400.00', -3);

    $forecast = $this->getJson('/api/treasury-overview')->json('data.forecast');
    expect($forecast)->toBe([
        ['days' => 30, 'until' => '2026-12-15', 'receivable' => '3.00', 'payable' => '100.00', 'expected_balance' => '903.00'],
        ['days' => 60, 'until' => '2027-01-14', 'receivable' => '7.00', 'payable' => '300.00', 'expected_balance' => '707.00'],
        ['days' => 90, 'until' => '2027-02-13', 'receivable' => '15.00', 'payable' => '300.00', 'expected_balance' => '715.00'],
    ]);
});

it('AC-008: to verify, unassigned facts and the adjustments of the month', function () {
    Sanctum::actingAs(overviewActor());
    [$a, $b] = Company::factory()->count(2)->create();
    InvoiceInstallment::factory()->create([
        'invoice_id' => Invoice::factory()->create(['company_id' => $a->id])->id,
        'amount' => '300.00', 'collected_amount' => '300.00', 'collected_at' => '2026-11-02',
    ]);
    PurchaseInvoiceInstallment::factory()->create([
        'purchase_invoice_id' => PurchaseInvoice::factory()->create(['company_id' => $b->id])->id,
        'amount' => '80.00', 'paid_amount' => '80.00', 'paid_at' => '2026-11-03',
    ]);
    $accountA = FinancialAccount::factory()->create(['company_id' => $a->id]);
    $accountB = FinancialAccount::factory()->create(['company_id' => $b->id]);
    TreasuryMovement::factory()->adjustment(TreasuryMovementDirection::In)->create(['financial_account_id' => $accountA->id, 'amount' => '12.50', 'movement_date' => '2026-11-01']);
    TreasuryMovement::factory()->adjustment(TreasuryMovementDirection::Out)->create(['financial_account_id' => $accountB->id, 'amount' => '3.00', 'movement_date' => '2026-11-30']);
    TreasuryMovement::factory()->adjustment(TreasuryMovementDirection::In)->create(['financial_account_id' => $accountA->id, 'amount' => '99.00', 'movement_date' => '2026-10-31']);
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $accountA->id, 'amount' => '5.00', 'movement_date' => '2026-11-05']);

    $all = $this->getJson('/api/treasury-overview')->json('data.to_verify');
    expect($all)->toBe([
        'unassigned' => ['count' => 2, 'collections' => '300.00', 'payments' => '80.00'],
        'adjustments' => ['month' => '2026-11', 'count' => 2, 'inflow' => '12.50', 'outflow' => '3.00'],
    ]);

    $scoped = $this->getJson("/api/treasury-overview?company_id={$a->id}")->json('data.to_verify');
    expect($scoped)->toBe([
        'unassigned' => ['count' => 1, 'collections' => '300.00', 'payments' => '0.00'],
        'adjustments' => ['month' => '2026-11', 'count' => 1, 'inflow' => '12.50', 'outflow' => '0.00'],
    ]);
});

it('AC-009: the menu shows the overview first in Finance only with treasury-movements.view', function () {
    Permission::findOrCreate('treasury-movements.view');
    $viewer = User::factory()->create();
    Permission::findOrCreate('ledger.view');
    $viewer->givePermissionTo('ledger.view');
    Sanctum::actingAs($viewer);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('treasury-overview');

    Sanctum::actingAs(overviewActor());
    $finance = navigationNode($this->getJson('/api/navigation')->json('data'), 'accounting-finance')['children'];
    expect($finance[0])->toMatchArray([
        'key' => 'treasury-overview', 'label' => 'navigation.treasuryOverview', 'icon' => 'wallet', 'route' => '/treasury-overview',
    ]);
});
