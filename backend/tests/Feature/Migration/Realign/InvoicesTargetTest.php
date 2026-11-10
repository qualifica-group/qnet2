<?php

use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\InvoicesTarget;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyRealignInvoice(array $overrides = []): array
{
    return array_merge([
        'id' => 11030,
        'source' => 'proformas',
        'type' => 'invoice',
        'number' => 12804,
        'year_max_number' => 12804,
        'document_date' => '2026-10-21',
        'company_id' => 17,
        'customer_id' => 30232,
        'work_order_id' => null,
        'quote_id' => null,
        'payment_method_id' => 19,
        'financial_account_id' => null,
        'proforma_request_id' => null,
        'external_number' => 'FE-1',
        'external_date' => '2026-10-21',
        'notes' => null,
        'internal_note' => null,
        'deviation' => null,
        'creator_user_id' => 98,
        'lines' => [['product_id' => null, 'quote_line_id' => null, 'description' => 'Consulenza ISO', 'quantity' => 1, 'unit_price' => 830, 'vat_rate_id' => 6]],
        'installments' => [['due_date' => '2026-10-21', 'amount' => 1012.6, 'payment_method_code' => 'MP05', 'collected_amount' => null, 'collected_at' => null]],
        'warnings' => [],
        'created_at' => '2026-10-21 19:41:47',
        'updated_at' => '2026-10-22 08:00:00',
    ], $overrides);
}

beforeEach(function () {
    seedMigrationsConfig();
    $company = Company::factory()->create();
    $company->forceFill(['old_id' => 17])->save();
    Registry::factory()->create(['old_id' => 30232]);
    $paymentMethod = PaymentMethod::factory()->create();
    $paymentMethod->forceFill(['old_id' => 19])->save();
    VatRate::factory()->create(['old_id' => 6, 'rate' => 22]);
    User::factory()->create(['old_id' => 98]);

    Http::fake([fakeMigrationsBaseUrl().'/invoices*' => Http::response([
        'items' => [legacyRealignInvoice()],
        'pagination' => ['total' => 1],
    ])]);
    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'invoices']);
    runMigrationJobFor($run);
    expect($run->fresh()->created_rows)->toBe(1);
    Http::swap(new Factory);
});

/**
 * @param  array<string, mixed>  $legacy
 */
function realignInvoice(array $legacy, bool $apply = true): LegacyRealignReport
{
    fakeLegacyRealign('invoices', ["{$legacy['source']}:{$legacy['id']}"], [], [$legacy]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(InvoicesTarget::class), '2026-10-23', $apply, $report);
    Http::swap(new Factory);

    return $report;
}

it('AC-017 aligns notes, external number and date, schedule and collection', function () {
    $report = realignInvoice(legacyRealignInvoice([
        'notes' => 'New note',
        'internal_note' => 'Internal',
        'external_number' => 'FE-2',
        'external_date' => '2026-10-25',
        'updated_at' => '2026-10-25 09:00:00',
        'installments' => [['due_date' => '2026-11-30', 'amount' => 1012.6, 'payment_method_code' => 'MP05', 'collected_amount' => 1012.6, 'collected_at' => '2026-10-26']],
    ]));

    $invoice = Invoice::query()->where('old_source', 'proformas')->where('old_id', 11030)->firstOrFail();
    $installment = $invoice->installments()->firstOrFail();

    expect($report->counters['invoices'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and($invoice->notes)->toBe('New note')
        ->and($invoice->internal_note)->toBe('Internal')
        ->and($invoice->external_number)->toBe('FE-2')
        ->and($invoice->external_date->toDateString())->toBe('2026-10-25')
        ->and($invoice->updated_at->toDateTimeString())->toBe('2026-10-25 09:00:00')
        ->and($installment->due_date->toDateString())->toBe('2026-11-30')
        ->and((float) $installment->collected_amount)->toBe(1012.6);
});

it('AC-017 never writes lines or amounts and says when they differ', function () {
    $report = realignInvoice(legacyRealignInvoice([
        'lines' => [['product_id' => null, 'quote_line_id' => null, 'description' => 'Consulenza ISO', 'quantity' => 2, 'unit_price' => 830, 'vat_rate_id' => 6]],
    ]));

    $invoice = Invoice::query()->where('old_id', 11030)->firstOrFail();

    expect((float) $invoice->total_amount)->toBe(1012.6)
        ->and($invoice->lines()->count())->toBe(1)
        ->and(array_column($report->messages, 3))->toContain('Legacy lines or amounts differ (legacy total 2025.20, qnet-2 1012.60); not updated.');
});

it('keeps a reconciled installment and reports the difference', function () {
    $invoice = Invoice::query()->where('old_id', 11030)->firstOrFail();
    $invoice->installments()->update(['reconciled_at' => '2026-10-20 10:00:00']);

    $report = realignInvoice(legacyRealignInvoice([
        'installments' => [['due_date' => '2026-12-31', 'amount' => 1012.6, 'payment_method_code' => 'MP05', 'collected_amount' => null, 'collected_at' => null]],
    ]));

    expect($invoice->installments()->firstOrFail()->due_date->toDateString())->toBe('2026-10-21')
        ->and(array_column($report->messages, 3))->toContain('Installment 1: reconciled, compensated or written off on qnet-2; kept.');
});

it('does not touch a schedule of a different length', function () {
    $report = realignInvoice(legacyRealignInvoice(['installments' => [
        ['due_date' => '2026-11-30', 'amount' => 506.3, 'payment_method_code' => 'MP05', 'collected_amount' => null, 'collected_at' => null],
        ['due_date' => '2026-12-31', 'amount' => 506.3, 'payment_method_code' => 'MP05', 'collected_amount' => null, 'collected_at' => null],
    ]]));

    expect(Invoice::query()->where('old_id', 11030)->firstOrFail()->installments()->count())->toBe(1)
        ->and(array_column($report->messages, 3))->toContain('The legacy has 2 installments and qnet-2 has 1; the schedule is not updated.');
});

it('does not match another legacy table with the same id', function () {
    $legacy = legacyRealignInvoice(['source' => 'proforma_ente', 'notes' => 'Other table']);

    $report = realignInvoice($legacy);

    expect($report->counters['invoices'][LegacyRealignReport::NOT_ON_QNET2])->toBe(1)
        ->and(Invoice::query()->where('old_id', 11030)->value('notes'))->toBeNull();
});
