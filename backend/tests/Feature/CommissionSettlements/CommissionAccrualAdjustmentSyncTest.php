<?php

use App\Models\CommissionAccrual;
use App\Models\Referent;
use App\Services\Commissions\Accruals\CommissionAccrualSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('AC-013: the synchronizer writes the invoice company on the movements it posts', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['500.00', '500.00']);

    accrualCollect(accrualInstallment($invoice, 0), '500.00');
    accrualCollect(accrualInstallment($invoice, 1), '500.00', '2026-04-21');

    $movements = CommissionAccrual::query()->where('invoice_id', $invoice->id)->get();
    expect($movements)->toHaveCount(2)
        ->and($movements->pluck('company_id')->unique()->all())->toBe([$invoice->company_id]);
});

it('AC-013: adjustments are never reversed by the synchronizer or the command', function () {
    $line = accrualQuoteLine();
    $commission = accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['1000.00']);
    accrualCollect(accrualInstallment($invoice), '1000.00');

    $adjustment = CommissionAccrual::factory()->adjustment()->create([
        'recipient_id' => $commission->recipient_id,
        'company_id' => $invoice->company_id,
        'amount' => '-12.00',
    ]);
    $before = CommissionAccrual::query()->count();

    app(CommissionAccrualSynchronizer::class)->syncInvoice($invoice->fresh());
    $this->artisan('commissions:sync-accruals')->assertExitCode(0);

    expect(CommissionAccrual::query()->count())->toBe($before)
        ->and(CommissionAccrual::query()->where('type', 'REVERSAL')->count())->toBe(0)
        ->and($adjustment->fresh()->amount)->toBe('-12.00');
});

it('AC-013: the ledger of an agent without invoice movements keeps its adjustment when the commission disappears', function () {
    $line = accrualQuoteLine();
    $commission = accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['1000.00']);
    accrualCollect(accrualInstallment($invoice), '1000.00');
    $adjustment = CommissionAccrual::factory()->adjustment()->create(['recipient_id' => Referent::factory()->create()->id, 'company_id' => $invoice->company_id]);
    $commission->delete();

    app(CommissionAccrualSynchronizer::class)->syncInvoice($invoice->fresh());

    expect(CommissionAccrual::query()->where('type', 'REVERSAL')->sole()->invoice_id)->toBe($invoice->id)
        ->and($adjustment->fresh()->type->value)->toBe('ADJUSTMENT');
});
