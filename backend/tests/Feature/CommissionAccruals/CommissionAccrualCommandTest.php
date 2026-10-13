<?php

use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * An invoice whose two installments were collected in different months with
 * no ledger movement yet (the history the command rebuilds).
 */
function historicInvoice(string $date = '2026-01-10'): Invoice
{
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['610.00', '610.00'], $date);
    InvoiceInstallment::query()->where('invoice_id', $invoice->id)->where('sequence', 1)->update(['collected_amount' => '610.00', 'collected_at' => '2026-01-15']);
    InvoiceInstallment::query()->where('invoice_id', $invoice->id)->where('sequence', 2)->update(['collected_amount' => '610.00', 'collected_at' => '2026-02-20']);

    return $invoice;
}

it('AC-012: rebuilds the history with one accrual per collection date', function () {
    $invoice = historicInvoice();

    $this->artisan('commissions:sync-accruals')->expectsOutput('Invoices: 1, accruals: 2, reversals: 0, marked external: 0')->assertExitCode(0);

    expect(accrualRows($invoice))->toBe(['ACCRUAL:50.00:2026-01-15', 'ACCRUAL:50.00:2026-02-20'])
        ->and(CommissionAccrual::query()->orderBy('id')->pluck('invoice_installment_id')->all())
        ->toBe($invoice->installments()->orderBy('sequence')->pluck('id')->all());
});

it('AC-012: --settled-before flags the older movements only', function () {
    historicInvoice();

    $this->artisan('commissions:sync-accruals', ['--settled-before' => '2026-02-01'])
        ->expectsOutput('Invoices: 1, accruals: 2, reversals: 0, marked external: 1')->assertExitCode(0);

    expect(CommissionAccrual::query()->orderBy('accrued_on')->get()->map(fn ($row) => $row->settled_externally_on?->toDateString())->all())
        ->toBe(['2026-02-01', null]);
});

it('AC-012: --dry-run prints the counts and writes nothing', function () {
    historicInvoice();

    $this->artisan('commissions:sync-accruals', ['--dry-run' => true, '--settled-before' => '2026-02-01'])
        ->expectsOutput('Invoices: 1, accruals: 2, reversals: 0, marked external: 1')->assertExitCode(0);

    expect(CommissionAccrual::query()->count())->toBe(0);
});

it('AC-012: --invoice and --from limit the invoices', function () {
    $first = historicInvoice('2026-01-10');
    $second = historicInvoice('2026-03-10');

    $this->artisan('commissions:sync-accruals', ['--invoice' => [$second->id]])
        ->expectsOutput('Invoices: 1, accruals: 2, reversals: 0, marked external: 0')->assertExitCode(0);
    expect(accrualRows($first))->toBe([])->and(accrualRows($second))->toHaveCount(2);

    $this->artisan('commissions:sync-accruals', ['--from' => '2026-02-01'])
        ->expectsOutput('Invoices: 1, accruals: 0, reversals: 0, marked external: 0')->assertExitCode(0);
    expect(accrualRows($first))->toBe([]);
});

it('AC-012: an invalid date fails with exit 1 and writes nothing', function (string $option) {
    historicInvoice();

    $this->artisan('commissions:sync-accruals', [$option => '2026-13-45'])->assertExitCode(1);

    expect(CommissionAccrual::query()->count())->toBe(0);
})->with(['--from', '--settled-before']);

it('AC-012: the command is scheduled daily at 02:00 without --settled-before', function () {
    Artisan::all();
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command, 'commissions:sync-accruals'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->command)->not->toContain('settled-before');
});
