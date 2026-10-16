<?php

use App\Enums\InvoiceType;
use App\Enums\PurchaseInvoiceType;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0271, AC-008 / AC-009 — collections and payments by financial account and by month.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

/** The second day of the month `$months` after the current one ("Y-m-d"): inside the month whatever today is. */
function flowDay(int $months): string
{
    return mcMonth($months).'-02';
}

/**
 * An active installment of `$amount` collected on `$date` (open when null), on a document with the given attributes.
 *
 * @param  array<string, mixed>  $invoice
 */
function flowCollection(string $amount, ?string $date, array $invoice = []): InvoiceInstallment
{
    $document = Invoice::factory()->create([
        'type' => InvoiceType::Invoice, 'net_amount' => $amount, 'vat_amount' => '0.00', 'total_amount' => $amount, ...$invoice,
    ]);

    return InvoiceInstallment::factory()->create([
        'invoice_id' => $document->id, 'sequence' => 1, 'amount' => $amount,
        'collected_amount' => $date === null ? null : $amount, 'collected_at' => $date,
    ]);
}

/**
 * A payable installment of `$amount` paid on `$date` (open when null).
 *
 * @param  array<string, mixed>  $document  header attributes
 */
function flowPayment(string $amount, ?string $date, array $document = [], ?int $installmentAccountId = null): PurchaseInvoiceInstallment
{
    $header = PurchaseInvoice::factory()->create([
        'document_type' => PurchaseInvoiceType::Invoice, 'net_amount' => $amount, 'vat_amount' => '0.00', 'total_amount' => $amount, ...$document,
    ]);

    return PurchaseInvoiceInstallment::factory()->create([
        'purchase_invoice_id' => $header->id, 'sequence' => 1, 'amount' => $amount,
        'paid_amount' => $date === null ? null : $amount, 'paid_at' => $date, 'financial_account_id' => $installmentAccountId,
    ]);
}

it('AC-008: only the installments settled in the period count, attributed to their account with the header as fallback', function () {
    $bank = mcAccount('Banca A');
    $card = mcAccount('Carta B');
    flowCollection('100.00', flowDay(-2), ['financial_account_id' => $bank->id]);
    flowCollection('50.00', flowDay(-1), ['financial_account_id' => $bank->id]);
    flowCollection('30.00', flowDay(-1), ['financial_account_id' => $card->id]);
    flowCollection('20.00', flowDay(0));
    flowCollection('999.00', flowDay(-5), ['financial_account_id' => $bank->id]);
    flowCollection('888.00', null, ['financial_account_id' => $bank->id]);

    flowPayment('40.00', flowDay(-2), installmentAccountId: $card->id);
    flowPayment('25.00', flowDay(-1), ['financial_account_id' => $bank->id]);
    flowPayment('10.00', flowDay(0));
    flowPayment('500.00', flowDay(-4), installmentAccountId: $card->id);
    flowPayment('77.00', null, installmentAccountId: $card->id);

    $data = mcGet('account-flows', ['date_from' => flowDay(-2), 'date_to' => mcMonth(0).'-28'])->assertOk()->json('data');

    expect($data['collections']['total'])->toEqual(200)
        ->and($data['collections']['installments'])->toBe(4)
        ->and(array_column($data['collections']['by_account'], 'amount'))->toEqual([150, 30, 20])
        ->and(array_column($data['collections']['by_account'], 'installments'))->toBe([2, 1, 1])
        ->and($data['collections']['by_account'][0]['account'])->toBe(['id' => $bank->id, 'name' => 'Banca A', 'type' => 'bank_account'])
        ->and($data['collections']['by_account'][2]['account'])->toBeNull()
        ->and($data['payments']['total'])->toEqual(75)
        ->and($data['payments']['installments'])->toBe(3)
        ->and(array_column($data['payments']['by_account'], 'amount'))->toEqual([40, 25, 10])
        ->and($data['payments']['by_account'][0]['account']['id'])->toBe($card->id)
        ->and($data['payments']['by_account'][1]['account']['id'])->toBe($bank->id)
        ->and($data['payments']['by_account'][2]['account'])->toBeNull()
        ->and($data['by_month'])->toEqual([
            ['month' => mcMonth(-2), 'collected' => 100, 'paid' => 40, 'net' => 60],
            ['month' => mcMonth(-1), 'collected' => 80, 'paid' => 25, 'net' => 55],
            ['month' => mcMonth(0), 'collected' => 20, 'paid' => 10, 'net' => 10],
        ]);
});

it('AC-008: every month of the period is listed and the account type travels with the account', function () {
    $cash = FinancialAccount::factory()->cash()->create(['name' => 'Cassa']);
    flowCollection('10.00', flowDay(-3), ['financial_account_id' => $cash->id]);

    $data = mcGet('account-flows', ['date_from' => flowDay(-3), 'date_to' => flowDay(0)])->assertOk()->json('data');

    expect(array_column($data['by_month'], 'month'))->toBe([mcMonth(-3), mcMonth(-2), mcMonth(-1), mcMonth(0)])
        ->and(array_column($data['by_month'], 'collected'))->toEqual([10, 0, 0, 0])
        ->and($data['collections']['by_account'][0]['account']['type'])->toBe('cash')
        ->and($data['payments'])->toEqual(['total' => 0, 'installments' => 0, 'by_account' => []]);
});

it('AC-008: without dates the months run from the first to the last one with data', function () {
    flowCollection('10.00', flowDay(-3));
    flowPayment('4.00', flowDay(-1));

    $data = mcGet('account-flows')->assertOk()->json('data');

    expect($data['applied']['date_from'])->toBeNull()
        ->and(array_column($data['by_month'], 'month'))->toBe([mcMonth(-3), mcMonth(-2), mcMonth(-1)])
        ->and(mcGet('account-flows', ['date_from' => '2020-01-01', 'date_to' => '2020-01-31'])->json('data.by_month'))->toHaveCount(1);
});

it('AC-008: the company and site filters restrict collections via the offer and payments via the header', function () {
    $alfa = Company::factory()->create();
    $beta = Company::factory()->create();
    $site = CompanySite::factory()->create(['company_id' => $alfa->id]);
    $quote = Quote::factory()->create(['company_id' => $alfa->id, 'company_site_id' => $site->id]);
    flowCollection('10.00', flowDay(0), ['company_id' => $alfa->id, 'quote_id' => $quote->id]);
    flowCollection('20.00', flowDay(0), ['company_id' => $alfa->id]);
    flowCollection('40.00', flowDay(0), ['company_id' => $beta->id]);
    flowPayment('1.00', flowDay(0), ['company_id' => $alfa->id, 'company_site_id' => $site->id]);
    flowPayment('2.00', flowDay(0), ['company_id' => $beta->id]);
    $period = ['date_from' => flowDay(0), 'date_to' => flowDay(0)];

    $byCompany = mcGet('account-flows', [...$period, 'company_id' => [$alfa->id]])->assertOk()->json('data');
    $bySite = mcGet('account-flows', [...$period, 'company_site_id' => [$site->id]])->assertOk()->json('data');

    expect($byCompany['collections']['total'])->toEqual(30)->and($byCompany['payments']['total'])->toEqual(1)
        ->and($bySite['collections']['total'])->toEqual(10)->and($bySite['payments']['total'])->toEqual(1);
});

it('AC-008: invalid parameters answer 422, also an interval over 24 months', function (array $params) {
    mcGet('account-flows', $params)->assertUnprocessable();
})->with([
    'bad date' => [['date_from' => '2026-13-45']],
    'date_to before date_from' => [['date_from' => '2026-03-10', 'date_to' => '2026-03-09']],
    'over 24 months' => [['date_from' => '2024-01-01', 'date_to' => '2026-03-01']],
    'unknown company' => [['company_id' => [999999]]],
    'unknown company site' => [['company_site_id' => [999999]]],
    'unknown operational site' => [['operational_site_id' => [999999]]],
]);

it('AC-008: exactly 24 months are accepted', function () {
    mcGet('account-flows', ['date_from' => '2024-03-01', 'date_to' => '2026-02-28'])->assertOk()
        ->assertJsonCount(24, 'data.by_month');
});

it('AC-009: the number of queries does not depend on the installments', function () {
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            flowCollection('10.00', flowDay(-($i % 3)), ['financial_account_id' => mcAccount('Conto '.$i.'-'.uniqid())->id]);
            flowPayment('5.00', flowDay(-($i % 3)), installmentAccountId: mcAccount('Conto P'.$i.'-'.uniqid())->id);
        }
    };
    $params = ['date_from' => flowDay(-3), 'date_to' => flowDay(0)];

    $seed(5);
    $few = mcQueryCount('account-flows', $params);
    $seed(20);

    expect(mcQueryCount('account-flows', $params))->toBe($few);
});
