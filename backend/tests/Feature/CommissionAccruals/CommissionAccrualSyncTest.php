<?php

use App\Enums\CommissionAccrualType;
use App\Enums\CommissionRecipientRole as Role;
use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\User;
use App\Services\Commissions\Accruals\CommissionAccrualSynchronizer;
use App\Services\Invoices\InvoiceBulkCollector;
use App\Services\Invoices\InvoiceCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: collecting the first of two installments accrues half of the commission on the collection date', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['610.00', '610.00']);
    $installment = accrualInstallment($invoice);

    accrualCollect($installment, '610.00', '2026-04-20');

    $row = CommissionAccrual::query()->sole();
    expect($row->type)->toBe(CommissionAccrualType::Accrual)
        ->and((string) $row->amount)->toBe('50.00')
        ->and((string) $row->base_amount)->toBe('100.00')
        ->and($row->accrued_on->toDateString())->toBe('2026-04-20')
        ->and($row->invoice_installment_id)->toBe($installment->id)
        ->and($row->quote_line_id)->toBe($line->id);
});

it('AC-002: collecting every installment sums the bucket exactly to the calculated amount, remainders included', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '33.33');
    $invoice = accrualInvoice([[$line, '1000.00']], ['133.34', '133.33', '133.33']);

    foreach ([0, 1, 2] as $position) {
        $installment = accrualInstallment($invoice, $position);
        accrualCollect($installment, (string) $installment->amount, '2026-04-2'.$position);
    }

    expect(accrualSum($invoice))->toBe('33.33')
        ->and(CommissionAccrual::query()->count())->toBe(3);
});

it('AC-003: a partial collection accrues the collected ratio and the balance of the schedule completes the commission', function (string $mode) {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00', '600.00']);
    $extra = $mode === 'spread' ? ['residual_mode' => 'spread'] : ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-06-30'];

    accrualCollect(accrualInstallment($invoice), '300.00', '2026-04-20', $extra);
    expect(accrualSum($invoice))->toBe('25.00');

    $invoice->installments()->orderBy('sequence')->get()->filter(fn ($row) => $row->collected_at === null)
        ->each(fn ($row) => accrualCollect($row, (string) $row->amount, '2026-05-20'));

    expect(accrualSum($invoice))->toBe('100.00');
})->with(['spread', 'new_installment']);

it('AC-004: clearing a collection reverses the accrual today and leaves the original movement untouched', function (bool $partial) {
    $this->travelTo('2026-05-10');
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00', '600.00']);
    $installment = accrualInstallment($invoice);
    $extra = $partial ? ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-06-30'] : [];
    accrualCollect($installment, $partial ? '300.00' : '600.00', '2026-04-20', $extra);
    $original = CommissionAccrual::query()->sole();
    $amount = $partial ? '25.00' : '50.00';

    app(InvoiceCollectionService::class)->clear($installment);

    expect(accrualRows($invoice))->toBe(["ACCRUAL:{$amount}:2026-04-20", "REVERSAL:-{$amount}:2026-05-10"])
        ->and(accrualSum($invoice))->toBe('0.00')
        ->and($original->fresh()->only('amount', 'accrued_on', 'type', 'updated_at'))->toEqual($original->only('amount', 'accrued_on', 'type', 'updated_at'));
})->with([false, true]);

it('AC-005: synchronizing twice, and running the command twice, creates nothing more', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['610.00', '610.00']);
    accrualCollect(accrualInstallment($invoice), '610.00');

    $sync = app(CommissionAccrualSynchronizer::class);
    $first = $sync->syncInvoice($invoice);
    $second = $sync->syncInvoice($invoice);
    $this->artisan('commissions:sync-accruals')->assertSuccessful();
    $this->artisan('commissions:sync-accruals')->assertSuccessful();

    expect([$first->accruals, $first->reversals, $second->accruals, $second->reversals])->toBe([0, 0, 0, 0])
        ->and(CommissionAccrual::query()->count())->toBe(1);
});

it('AC-006: partial invoicing splits the commission by invoiced share and never exceeds it', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $first = accrualInvoice([[$line, '400.00']], ['400.00'], '2026-03-01');
    $second = accrualInvoice([[$line, '600.00']], ['600.00'], '2026-03-10');
    $third = accrualInvoice([[$line, '200.00']], ['200.00'], '2026-03-20');

    // the later invoice is collected first: its share does not depend on the collection order
    accrualCollect(accrualInstallment($second), '600.00');
    accrualCollect(accrualInstallment($first), '400.00');
    accrualCollect(accrualInstallment($third), '200.00');

    expect(accrualSum($first))->toBe('40.00')
        ->and(accrualSum($second))->toBe('60.00')
        ->and(accrualSum($third))->toBe('0.00')
        ->and(CommissionAccrual::query()->where('invoice_id', $third->id)->count())->toBe(0);
});

it('AC-007: only commercial, reporter and supervisor accrue, for user and referent recipients alike', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00', Role::Commercial);
    accrualCommission($line, '40.00', Role::Reporter, ['recipient_type' => 'user', 'recipient_id' => User::factory()->create()->id]);
    accrualCommission($line, '20.00', Role::Supervisor);
    accrualCommission($line, '10.00', Role::Supplier, ['recipient_type' => 'registry', 'recipient_id' => 1]);
    $invoice = accrualInvoice([[$line, '1000.00']], ['500.00']);

    accrualCollect(accrualInstallment($invoice), '500.00');

    $rows = CommissionAccrual::query()->orderBy('id')->get();
    expect($rows->pluck('recipient_role')->map->value->all())->toBe(['COMMERCIAL', 'REPORTER', 'SUPERVISOR'])
        ->and($rows->pluck('recipient_type')->all())->toBe(['referent', 'user', 'referent'])
        ->and(accrualSum($invoice))->toBe('160.00');
});

it('AC-008: late changes of the commission post only the difference, today, and never touch the old rows', function () {
    $this->travelTo('2026-05-10');
    $line = accrualQuoteLine();
    $commission = accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00', '600.00']);
    accrualCollect(accrualInstallment($invoice), '600.00', '2026-04-20');
    $original = CommissionAccrual::query()->sole();

    // amount raised: only the +10 difference
    $commission->update(['calculated_amount' => '120.00']);
    $this->artisan('commissions:sync-accruals')->assertSuccessful();
    expect(accrualRows($invoice))->toBe(['ACCRUAL:50.00:2026-04-20', 'ACCRUAL:10.00:2026-05-10']);

    // regenerated with a new id and the same values: nothing
    $commission->delete();
    $commission = accrualCommission($line, '120.00', Role::Commercial, ['recipient_type' => $original->recipient_type, 'recipient_id' => $original->recipient_id]);
    $this->artisan('commissions:sync-accruals')->assertSuccessful();
    expect(CommissionAccrual::query()->count())->toBe(2);

    // recipient changed: the old bucket is reversed, the new one accrues
    $commission->update(['recipient_id' => Referent::factory()->create()->id]);
    $this->artisan('commissions:sync-accruals')->assertSuccessful();
    expect(accrualRows($invoice))->toHaveCount(4)
        ->and(accrualSum($invoice))->toBe('60.00')
        ->and(CommissionAccrual::query()->where('type', 'REVERSAL')->sole()->amount)->toBe('-60.00')
        ->and($original->fresh()->amount)->toBe('50.00');
});

it('AC-008: removing the quote line reverses the whole bucket', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00']);
    accrualCollect(accrualInstallment($invoice), '600.00');

    $line->delete();
    $this->artisan('commissions:sync-accruals')->assertSuccessful();

    $reversal = CommissionAccrual::query()->orderByDesc('id')->first();
    expect(accrualSum($invoice))->toBe('0.00')
        ->and($reversal->type)->toBe(CommissionAccrualType::Reversal)
        ->and($reversal->quote_line_id)->toBeNull();
});

it('AC-009: a new invoice total with collections posts the delta; an update without collections posts nothing', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'create', 'update', 'collect']));
    [$collected, $payload] = invoiceWithPlan('1200.00');
    $quoteLine = QuoteLine::query()->findOrFail($payload['lines'][0]['quote_line_id']);
    $quoteLine->update(['net_amount' => '0.00']);
    accrualCommission($quoteLine, '100.00');
    collectInstallment($collected['installments'][0]['id'], '400')->assertOk();
    expect(accrualSum(Invoice::query()->findOrFail($collected['id'])))->toBe('33.33');

    $this->putJson("/api/invoices/{$collected['id']}", invoiceWithUnitPrice($payload, '1000.00'))->assertOk();
    expect(accrualSum(Invoice::query()->findOrFail($collected['id'])))->toBe('40.00');

    CommissionAccrual::query()->delete();
    [$open, $openPayload] = invoiceWithPlan('1200.00');
    accrualCommission(QuoteLine::query()->findOrFail($openPayload['lines'][0]['quote_line_id']), '100.00');
    $this->putJson("/api/invoices/{$open['id']}", invoiceWithUnitPrice($openPayload, '900.00'))->assertOk();

    expect(CommissionAccrual::query()->count())->toBe(0);
});

it('AC-010: a collected proforma accrues and becoming an invoice posts nothing', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'create', 'update', 'collect']));
    [$proforma, $payload] = invoiceWithPlan('1200.00', 2);
    $quoteLine = QuoteLine::query()->findOrFail($payload['lines'][0]['quote_line_id']);
    $quoteLine->update(['net_amount' => '1200.00']);
    accrualCommission($quoteLine, '100.00');
    collectInstallment($proforma['installments'][0]['id'], '600')->assertOk();
    $before = CommissionAccrual::query()->count();

    $this->patchJson("/api/invoices/{$proforma['id']}/details", ['external_number' => 'FT-9', 'external_date' => '2026-04-01'])->assertOk();

    expect($before)->toBe(1)
        ->and(CommissionAccrual::query()->count())->toBe(1)
        ->and(accrualSum(Invoice::query()->findOrFail($proforma['id'])))->toBe('50.00');
});

it('AC-011: a failing synchronizer rolls the collection back', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['600.00']);
    $installment = accrualInstallment($invoice);
    $this->mock(CommissionAccrualSynchronizer::class, fn ($mock) => $mock->shouldReceive('syncInvoice')->andThrow(new RuntimeException('boom')));

    expect(fn () => accrualCollect($installment, '600.00'))->toThrow(RuntimeException::class, 'boom');

    expect($installment->fresh()->collected_amount)->toBeNull()
        ->and(CommissionAccrual::query()->count())->toBe(0);
});

it('the bulk collector goes through the same hook', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $first = accrualInvoice([[$line, '500.00']], ['500.00'], '2026-03-01');
    $second = accrualInvoice([[$line, '500.00']], ['500.00'], '2026-03-02');
    $second->update(['customer_registry_id' => $first->customer_registry_id]);
    $actor = installmentUserWith([], ['invoices.collect']);

    app(InvoiceBulkCollector::class)->handle($actor, [
        ['installment_id' => accrualInstallment($first)->id, 'collected_amount' => '500.00'],
        ['installment_id' => accrualInstallment($second)->id, 'collected_amount' => '500.00'],
    ], '2026-04-21');

    expect(accrualSum($first))->toBe('50.00')->and(accrualSum($second))->toBe('50.00');
});

it('selects every invoice sharing the quote line in ascending id order before reading the state', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $first = accrualInvoice([[$line, '300.00']], ['300.00'], '2026-03-20');
    $second = accrualInvoice([[$line, '300.00']], ['300.00'], '2026-03-10');
    $third = accrualInvoice([[$line, '400.00']], ['400.00'], '2026-03-01');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query;
    });

    app(CommissionAccrualSynchronizer::class)->syncInvoice($third);

    // sqlite drops FOR UPDATE, so the lock order is asserted on the generated select (MySQL appends the lock to it)
    $locking = collect($queries)->first(fn ($query): bool => str_contains($query->sql, 'from "invoices"') && str_contains($query->sql, 'order by "id" asc'));
    expect($locking)->not->toBeNull()
        ->and($locking->bindings)->toBe([$first->id, $second->id, $third->id]);
});
