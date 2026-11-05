<?php

use App\Enums\TreasuryMovementDirection;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use App\Models\TreasuryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0355 — account statement: AC-010 .. AC-014.

function stmtActor(array $abilities = ['view']): User
{
    $user = User::factory()->create();
    foreach (['view', 'export'] as $ability) {
        Permission::findOrCreate("treasury-movements.{$ability}");
    }
    foreach ($abilities as $ability) {
        $user->givePermissionTo("treasury-movements.{$ability}");
    }

    return $user;
}

function stmtCollection(FinancialAccount $account, string $amount, string $date, bool $onInstallment = true, ?Registry $registry = null): InvoiceInstallment
{
    $invoice = Invoice::factory()->create([
        'financial_account_id' => $onInstallment ? null : $account->id,
        'customer_registry_id' => ($registry ?? Registry::factory()->create())->id,
    ]);

    return InvoiceInstallment::factory()->create([
        'invoice_id' => $invoice->id, 'amount' => $amount, 'collected_amount' => $amount, 'collected_at' => $date,
        'financial_account_id' => $onInstallment ? $account->id : null,
    ]);
}

function stmtPayment(FinancialAccount $account, string $amount, string $date): PurchaseInvoiceInstallment
{
    $invoice = PurchaseInvoice::factory()->create(['financial_account_id' => $account->id]);

    return PurchaseInvoiceInstallment::factory()->create([
        'purchase_invoice_id' => $invoice->id, 'amount' => $amount, 'paid_amount' => $amount, 'paid_at' => $date,
    ]);
}

function stmtGet(FinancialAccount $account, string $query = '')
{
    return test()->getJson("/api/financial-accounts/{$account->id}/statement?{$query}");
}

/** Account with opening 1000.00 and the AC-010 scenario inside February 2026. */
function stmtScenario(): FinancialAccount
{
    $account = FinancialAccount::factory()->create(['opening_balance' => '1000.00']);
    $other = FinancialAccount::factory()->create(['company_id' => $account->company_id]);
    stmtCollection($account, '500.00', '2026-02-10');
    stmtPayment($account, '200.00', '2026-02-15');
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $account->id, 'amount' => '50.00', 'movement_date' => '2026-02-20']);
    TreasuryMovement::factory()->transfer($other, $account)->create(['amount' => '100.00', 'movement_date' => '2026-02-25']);

    return $account;
}

it('AC-010: rows in date order with progressive balance, totals and closing balance', function () {
    Sanctum::actingAs(stmtActor());
    $account = stmtScenario();

    $data = stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28')->assertOk()->json('data');

    expect($data['opening'])->toBe(['date' => '2026-01-01', 'balance' => '1000.00'])
        ->and($data['period_opening_balance'])->toBe('1000.00')
        ->and(array_column($data['rows'], 'source'))->toBe(['collection', 'payment', 'movement', 'movement'])
        ->and(array_column($data['rows'], 'balance'))->toBe(['1500.00', '1300.00', '1250.00', '1350.00'])
        ->and(array_column($data['rows'], 'inflow'))->toBe(['500.00', null, null, '100.00'])
        ->and(array_column($data['rows'], 'outflow'))->toBe([null, '200.00', '50.00', null])
        ->and($data['totals'])->toBe(['inflow' => '600.00', 'outflow' => '250.00'])
        ->and($data['closing_balance'])->toBe('1350.00')
        ->and($data['filtered'])->toBeFalse()
        ->and($data['rows'][0]['key'])->toStartWith('collection:');
});

it('AC-010: authorization 401, 403 without view, 404 unknown account', function () {
    $account = FinancialAccount::factory()->create();
    $this->getJson("/api/financial-accounts/{$account->id}/statement")->assertUnauthorized();

    Sanctum::actingAs(stmtActor([]));
    stmtGet($account)->assertForbidden();

    Sanctum::actingAs(stmtActor());
    $this->getJson('/api/financial-accounts/999999/statement')->assertNotFound();
});

it('AC-011: facts before the opening date are excluded from rows and balance', function () {
    Sanctum::actingAs(stmtActor());
    $account = FinancialAccount::factory()->create(['opening_balance' => '100.00', 'opening_balance_date' => '2026-03-01']);
    stmtCollection($account, '900.00', '2026-02-20');
    TreasuryMovement::factory()->expense()->create(['financial_account_id' => $account->id, 'amount' => '40.00', 'movement_date' => '2026-02-10']);
    stmtCollection($account, '10.00', '2026-03-05');

    $data = stmtGet($account, 'date_from=2026-01-01&date_to=2026-03-31')->assertOk()->json('data');

    expect($data['opening'])->toBe(['date' => '2026-03-01', 'balance' => '100.00'])
        ->and($data['rows'])->toHaveCount(1)
        ->and($data['closing_balance'])->toBe('110.00');
});

it('AC-012: period opening is the balance of the day before; filters keep the real balance', function () {
    Sanctum::actingAs(stmtActor());
    $account = stmtScenario();

    $data = stmtGet($account, 'date_from=2026-02-12&date_to=2026-02-28')->assertOk()->json('data');
    expect($data['period_opening_balance'])->toBe('1500.00')
        ->and($data['rows'][0]['balance'])->toBe('1300.00');

    $filtered = stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28&types[]=manual_expense')->assertOk()->json('data');
    expect($filtered['filtered'])->toBeTrue()
        ->and($filtered['rows'])->toHaveCount(1)
        ->and($filtered['rows'][0]['balance'])->toBe('1250.00')
        ->and($filtered['totals'])->toBe(['inflow' => '0.00', 'outflow' => '50.00'])
        ->and($filtered['closing_balance'])->toBe('1350.00');

    $registry = Registry::factory()->create();
    stmtCollection($account, '5.00', '2026-02-26', true, $registry);
    $byRegistry = stmtGet($account, "date_from=2026-02-01&date_to=2026-02-28&registry_id={$registry->id}")->json('data');
    expect($byRegistry['rows'])->toHaveCount(1)->and($byRegistry['filtered'])->toBeTrue();

    $search = stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28&search=zzzz-none')->json('data');
    expect($search['rows'])->toBe([])->and($search['closing_balance'])->toBe('1355.00');
});

it('AC-012: an adjustment out lowers the balance', function () {
    Sanctum::actingAs(stmtActor());
    $account = FinancialAccount::factory()->create(['opening_balance' => '10.00']);
    TreasuryMovement::factory()->adjustment(TreasuryMovementDirection::Out)->create(['financial_account_id' => $account->id, 'amount' => '4.50', 'movement_date' => '2026-02-01']);

    expect(stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28')->json('data.closing_balance'))->toBe('5.50');
});

it('AC-013: too many rows answer 422 and inverted dates answer 422', function () {
    Sanctum::actingAs(stmtActor());
    $account = FinancialAccount::factory()->create();
    TreasuryMovement::factory()->count(3)->create(['financial_account_id' => $account->id, 'movement_date' => '2026-02-10']);

    config(['treasury.statement_max_rows' => 2]);
    stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28')->assertStatus(422);

    config(['treasury.statement_max_rows' => 5000]);
    stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28')->assertOk();
    stmtGet($account, 'date_from=2026-02-28&date_to=2026-02-01')->assertStatus(422);
});

it('AC-014: the export is an xlsx with the same rows and progressive balance; 403 without export', function () {
    $account = stmtScenario();

    Sanctum::actingAs(stmtActor(['view']));
    $this->get("/api/financial-accounts/{$account->id}/statement/export?date_from=2026-02-01&date_to=2026-02-28")->assertForbidden();

    Sanctum::actingAs(stmtActor(['view', 'export']));
    $json = stmtGet($account, 'date_from=2026-02-01&date_to=2026-02-28')->json('data');
    $response = $this->get("/api/financial-accounts/{$account->id}/statement/export?date_from=2026-02-01&date_to=2026-02-28")->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');

    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $lines = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $lines[] = $row->toArray();
        }
    }
    $reader->close();

    // header + period opening + 4 rows; last column is the progressive balance
    expect($lines)->toHaveCount(6)
        ->and(array_map(fn (array $line): string => (string) end($line), array_slice($lines, 1)))
        ->toBe(['1000.00', ...array_column($json['rows'], 'balance')]);
});
