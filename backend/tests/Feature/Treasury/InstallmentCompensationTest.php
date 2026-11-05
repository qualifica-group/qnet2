<?php

use App\DataObjects\ManagementControl\AccountFlowsFilters;
use App\Enums\PurchaseInvoiceType;
use App\Models\CommissionAccrual;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\InstallmentCompensation;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use App\Models\User;
use App\Services\ManagementControl\Accounts\CollectionAggregator;
use App\Services\ManagementControl\Accounts\PaymentAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// Spec 0364 - compensation of a receivable against a payable installment.

const COMP_PERMISSIONS = ['invoices.collect', 'purchase-invoices.pay', 'ledger.view', 'invoices.view', 'purchase-invoices.view', 'treasury-movements.view'];

beforeEach(function () {
    Carbon::setTestNow('2026-11-05 10:00:00');
    $this->company = Company::factory()->create();
    $this->registry = Registry::factory()->supplier()->create();
    Sanctum::actingAs(compActor(COMP_PERMISSIONS));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  array<int, string>  $permissions
 */
function compActor(array $permissions): User
{
    foreach ($permissions as $name) {
        Permission::findOrCreate($name, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::factory()->create()->givePermissionTo($permissions);
}

/**
 * One-installment invoice of the registry in the company (an invoice, not a proforma, unless overridden).
 *
 * @param  array<string, mixed>  $document
 * @param  array<string, mixed>  $installment
 */
function compReceivable(string $amount, array $document = [], array $installment = []): InvoiceInstallment
{
    $invoice = Invoice::factory()->asInvoice()->create([
        'customer_registry_id' => test()->registry->id, 'company_id' => test()->company->id,
        'document_date' => '2026-03-01', 'net_amount' => $amount, 'vat_amount' => '0.00', 'total_amount' => $amount, ...$document,
    ]);

    return InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'due_date' => '2026-12-01', 'amount' => $amount, ...$installment]);
}

/**
 * @param  array<string, mixed>  $document
 * @param  array<string, mixed>  $installment
 */
function compPayable(string $amount, array $document = [], array $installment = []): PurchaseInvoiceInstallment
{
    $invoice = PurchaseInvoice::factory()->create([
        'supplier_id' => test()->registry->id, 'company_id' => test()->company->id,
        'document_date' => '2026-03-01', 'net_amount' => $amount, 'vat_amount' => '0.00', 'total_amount' => $amount, ...$document,
    ]);

    return PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $invoice->id, 'sequence' => 1, 'due_date' => '2026-12-05', 'amount' => $amount, ...$installment]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function compose(InvoiceInstallment $receivable, PurchaseInvoiceInstallment $payable, array $extra = []): TestResponse
{
    return test()->postJson('/api/installment-compensations', [
        'invoice_installment_id' => $receivable->id,
        'purchase_invoice_installment_id' => $payable->id,
        'compensated_at' => '2026-11-04',
        ...$extra,
    ]);
}

it('AC-001: equal residuals close both installments with no account and amount 300', function () {
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');

    $data = compose($receivable, $payable, ['note' => 'netting'])->assertCreated()->json('data');

    $compensation = InstallmentCompensation::query()->sole();
    expect($data['amount'])->toBe('300.00')
        ->and($data['note'])->toBe('netting')
        ->and($data['invoice_installment']['id'])->toBe($receivable->id)
        ->and($data['can_delete'])->toBeTrue()
        ->and((string) $compensation->amount)->toBe('300.00');

    $receivable->refresh();
    $payable->refresh();
    expect((string) $receivable->collected_amount)->toBe('300.00')
        ->and($receivable->collected_at->toDateString())->toBe('2026-11-04')
        ->and($receivable->financial_account_id)->toBeNull()
        ->and($receivable->compensation_id)->toBe($compensation->id)
        ->and((string) $payable->paid_amount)->toBe('300.00')
        ->and($payable->financial_account_id)->toBeNull()
        ->and($payable->compensation_id)->toBe($compensation->id);
});

it('AC-002: a larger receivable needs the residual mode and a new installment takes the rest', function () {
    $receivable = compReceivable('500.00');
    $payable = compPayable('300.00');

    compose($receivable, $payable)->assertUnprocessable()->assertJsonValidationErrors('residual_mode');
    compose($receivable, $payable, ['residual_mode' => 'write_off'])->assertUnprocessable()->assertJsonValidationErrors('residual_mode');
    compose($receivable, $payable, ['residual_mode' => 'new_installment'])->assertUnprocessable()->assertJsonValidationErrors('residual_due_date');
    expect(InstallmentCompensation::query()->count())->toBe(0)
        ->and($receivable->fresh()->collected_amount)->toBeNull()
        ->and($payable->fresh()->paid_amount)->toBeNull();

    compose($receivable, $payable, ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-12-31'])->assertCreated()
        ->assertJsonPath('data.amount', '300.00');

    $plan = $receivable->invoice->installments()->orderBy('sequence')->get();
    expect($plan)->toHaveCount(2)
        ->and((string) $plan[0]->amount)->toBe('300.00')
        ->and((string) $plan[0]->collected_amount)->toBe('300.00')
        ->and((string) $plan[1]->amount)->toBe('200.00')
        ->and($plan[1]->due_date->toDateString())->toBe('2026-12-31')
        ->and($plan[1]->compensation_id)->toBeNull()
        ->and((string) $payable->fresh()->paid_amount)->toBe('300.00');
});

it('AC-002: a larger payable keeps the rest on the payable side, spread on the later installment', function () {
    $receivable = compReceivable('100.00');
    $payable = compPayable('400.00', installment: ['amount' => '250.00']);
    PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $payable->purchase_invoice_id, 'sequence' => 2, 'due_date' => '2027-01-05', 'amount' => '150.00']);

    compose($receivable, $payable, ['residual_mode' => 'spread'])->assertCreated()->assertJsonPath('data.amount', '100.00');

    $plan = $payable->purchaseInvoice->installments()->orderBy('sequence')->get();
    expect((string) $plan[0]->paid_amount)->toBe('100.00')
        ->and((string) $plan[1]->amount)->toBe('300.00')
        ->and((string) $receivable->fresh()->collected_amount)->toBe('100.00');
});

it('AC-002: spread without later open installments is a 422 and nothing is written', function () {
    $receivable = compReceivable('500.00');
    $payable = compPayable('300.00');

    compose($receivable, $payable, ['residual_mode' => 'spread'])->assertUnprocessable()->assertJsonValidationErrors('residual_mode');

    expect(InstallmentCompensation::query()->count())->toBe(0)
        ->and($receivable->fresh()->collected_amount)->toBeNull()
        ->and($payable->fresh()->paid_amount)->toBeNull();
});

it('AC-003: another registry, another company, a proforma or a collected installment is a 422 with no writes', function (string $case) {
    $receivable = compReceivable('300.00');
    $payable = match ($case) {
        'registry' => compPayable('300.00', ['supplier_id' => Registry::factory()->supplier()->create()->id]),
        'company' => compPayable('300.00', ['company_id' => Company::factory()->create()->id]),
        'purchase_proforma' => compPayable('300.00', ['document_type' => PurchaseInvoiceType::Proforma]),
        'sales_proforma' => ($receivable = compReceivable('300.00', ['type' => 'proforma'])) ? compPayable('300.00') : null,
        'collected' => ($receivable = compReceivable('300.00', installment: ['collected_amount' => '300.00', 'collected_at' => '2026-10-01'])) ? compPayable('300.00') : null,
        'paid' => compPayable('300.00', installment: ['paid_amount' => '300.00', 'paid_at' => '2026-10-01']),
    };

    compose($receivable, $payable)->assertUnprocessable();

    expect(InstallmentCompensation::query()->count())->toBe(0)
        ->and($payable->fresh()->compensation_id)->toBeNull();
})->with(['registry', 'company', 'purchase_proforma', 'sales_proforma', 'collected', 'paid']);

it('AC-003: a future date and a missing field are validation errors', function () {
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');

    compose($receivable, $payable, ['compensated_at' => '2026-11-06'])->assertUnprocessable()->assertJsonValidationErrors('compensated_at');
    compose($receivable, $payable, ['note' => str_repeat('x', 256)])->assertUnprocessable()->assertJsonValidationErrors('note');
    test()->postJson('/api/installment-compensations', [])->assertUnprocessable();
});

it('AC-004: compensated rows are in no statement, no unassigned list, no balance, no account flow', function () {
    $account = FinancialAccount::factory()->create(['company_id' => $this->company->id]);
    $receivable = compReceivable('300.00', ['financial_account_id' => $account->id]);
    $payable = compPayable('300.00', ['financial_account_id' => $account->id]);
    compose($receivable, $payable)->assertCreated();
    // a plain collection, to prove the same queries still read the other facts
    $plain = compReceivable('40.00', ['financial_account_id' => $account->id], ['collected_amount' => '40.00', 'collected_at' => '2026-11-03']);

    $rows = $this->getJson("/api/financial-accounts/{$account->id}/statement?date_from=2026-01-01&date_to=2026-12-31")->assertOk()->json('data.rows');
    expect(array_column($rows, 'description'))->toBe(["{$plain->invoice->number}/{$plain->invoice->year}"]);
    expect($this->getJson('/api/account-statements/unassigned')->json('data.count'))->toBe(0)
        ->and($this->getJson('/api/account-statements/balances?date=2026-12-31')->json('data.total'))->toBe('40.00');

    $filters = new AccountFlowsFilters('2026-11-01', '2026-11-30');
    $collected = collect(app(CollectionAggregator::class)->rows($filters))->sum(fn ($row) => (float) $row->total);
    $paid = collect(app(PaymentAggregator::class)->rows($filters))->sum(fn ($row) => (float) $row->total);
    expect($collected)->toEqual(40)->and($paid)->toEqual(0);
});

it('AC-005: commissions accrue on the compensated amount and are reversed by the cancellation', function () {
    $line = accrualQuoteLine();
    accrualCommission($line, '100.00');
    $invoice = accrualInvoice([[$line, '1000.00']], ['610.00', '610.00'], '2026-03-01');
    $invoice->update(['company_id' => $this->company->id, 'customer_registry_id' => $this->registry->id, 'type' => 'invoice']);
    $receivable = accrualInstallment($invoice);
    $payable = compPayable('610.00');

    $id = compose($receivable, $payable)->assertCreated()->json('data.id');

    expect(accrualSum($invoice))->toBe('50.00')
        ->and(CommissionAccrual::query()->where('invoice_id', $invoice->id)->count())->toBe(1);

    $this->deleteJson("/api/installment-compensations/{$id}")->assertOk();

    expect(accrualSum($invoice))->toBe('0.00');
});

it('AC-006: cancelling restores both plans and deletes the compensation', function () {
    $receivable = compReceivable('500.00');
    $payable = compPayable('300.00');
    $id = compose($receivable, $payable, ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-12-31'])->assertCreated()->json('data.id');

    $this->deleteJson("/api/installment-compensations/{$id}")->assertOk()->assertJsonPath('success', true);

    $plan = $receivable->invoice->installments()->orderBy('sequence')->get();
    expect($plan)->toHaveCount(1)
        ->and((string) $plan[0]->amount)->toBe('500.00')
        ->and($plan[0]->collected_amount)->toBeNull()
        ->and($plan[0]->compensation_id)->toBeNull()
        ->and($payable->fresh()->paid_amount)->toBeNull()
        ->and($payable->fresh()->compensation_id)->toBeNull()
        ->and(InstallmentCompensation::query()->count())->toBe(0);
});

it('AC-007: collection and payment endpoints refuse a compensated installment with a 422', function () {
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');
    compose($receivable, $payable)->assertCreated();

    $this->deleteJson("/api/invoice-installments/{$receivable->id}/collection")->assertUnprocessable()->assertJsonValidationErrors('compensation');
    $this->putJson("/api/invoice-installments/{$receivable->id}/collection", ['collected_amount' => '300.00', 'collected_at' => '2026-11-04'])->assertUnprocessable();
    $this->deleteJson("/api/purchase-invoice-installments/{$payable->id}/payment")->assertUnprocessable()->assertJsonValidationErrors('compensation');
    $this->putJson("/api/purchase-invoice-installments/{$payable->id}/payment", ['paid_amount' => '300.00', 'paid_at' => '2026-11-04'])->assertUnprocessable();

    expect((string) $receivable->fresh()->collected_amount)->toBe('300.00')
        ->and((string) $payable->fresh()->paid_amount)->toBe('300.00');
});

it('AC-008: register and cancel need both abilities, the reads need ledger.view, every 403 has a message', function () {
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');

    foreach ([['invoices.collect', 'ledger.view'], ['purchase-invoices.pay', 'ledger.view']] as $permissions) {
        Sanctum::actingAs(compActor($permissions));
        compose($receivable, $payable)->assertForbidden()->assertJsonPath('success', false)->assertJsonPath('message', fn (string $message): bool => $message !== '' && $message !== 'This action is unauthorized.');
    }

    Sanctum::actingAs(compActor(COMP_PERMISSIONS));
    $id = compose($receivable, $payable)->assertCreated()->json('data.id');

    Sanctum::actingAs(compActor(['invoices.collect']));
    $this->deleteJson("/api/installment-compensations/{$id}")->assertForbidden()->assertJsonPath('message', fn (string $message): bool => $message !== '');

    Sanctum::actingAs(compActor(['invoices.collect', 'purchase-invoices.pay']));
    $this->getJson("/api/registries/{$this->registry->id}/compensation-candidates?company_id={$this->company->id}")->assertForbidden()
        ->assertJsonPath('message', fn (string $message): bool => $message !== '');
    $this->getJson("/api/registries/{$this->registry->id}/compensations")->assertForbidden();
    expect(InstallmentCompensation::query()->count())->toBe(1);
});

it('AC-009: candidates are the open installments of invoices of the registry and company, by due date', function () {
    $late = compReceivable('100.00', installment: ['due_date' => '2026-12-20']);
    $early = compReceivable('200.00', installment: ['due_date' => '2026-12-01']);
    compReceivable('50.00', installment: ['collected_amount' => '50.00', 'collected_at' => '2026-10-01']);
    compReceivable('60.00', installment: ['write_off_amount' => '60.00', 'write_off_reason' => 'bad_debt']);
    compReceivable('70.00', ['type' => 'proforma']);
    compReceivable('80.00', ['company_id' => Company::factory()->create()->id]);
    compReceivable('90.00', ['customer_registry_id' => Registry::factory()->create()->id]);
    $payable = compPayable('300.00');
    compPayable('10.00', ['document_type' => PurchaseInvoiceType::Proforma]);
    compPayable('20.00', installment: ['paid_amount' => '20.00', 'paid_at' => '2026-10-01']);

    $data = $this->getJson("/api/registries/{$this->registry->id}/compensation-candidates?company_id={$this->company->id}")->assertOk()->json('data');

    expect(array_column($data['receivables'], 'id'))->toBe([$early->id, $late->id])
        ->and($data['receivables'][0])->toMatchArray(['residual' => '200.00', 'due_date' => '2026-12-01'])
        ->and($data['receivables'][0]['invoice']['number_label'])->toBe("{$early->invoice->number}/{$early->invoice->year}")
        ->and(array_column($data['payables'], 'id'))->toBe([$payable->id])
        ->and($data['payables'][0]['purchase_invoice']['number_label'])->toBe($payable->purchaseInvoice->document_number);

    $this->getJson("/api/registries/{$this->registry->id}/compensation-candidates")->assertUnprocessable()->assertJsonValidationErrors('company_id');
});

it('AC-009: the list of a registry is by date descending and can be filtered by company', function () {
    $first = compose(compReceivable('100.00'), compPayable('100.00'), ['compensated_at' => '2026-10-01'])->assertCreated()->json('data.id');
    $second = compose(compReceivable('100.00'), compPayable('100.00'), ['compensated_at' => '2026-11-01'])->assertCreated()->json('data.id');

    $data = $this->getJson("/api/registries/{$this->registry->id}/compensations")->assertOk()->json('data');
    expect(array_column($data, 'id'))->toBe([$second, $first])
        ->and($data[0]['company']['id'])->toBe($this->company->id)
        ->and($data[0]['purchase_invoice_installment']['purchase_invoice']['id'])->toBeInt()
        ->and($data[0]['created_by']['id'])->toBeInt();

    $other = Company::factory()->create();
    expect($this->getJson("/api/registries/{$this->registry->id}/compensations?company_id={$other->id}")->json('data'))->toBe([]);
});

it('AC-010: the ledger labels compensated installments, with a zero residual and the compensated total', function () {
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');
    compose($receivable, $payable)->assertCreated();

    $data = $this->getJson("/api/registries/{$this->registry->id}/ledger?date_from=2026-01-01&document_type=invoice")->assertOk()->json('data');

    $row = $data['customer']['documents'][0]['installments'][0];
    expect($row['compensation']['amount'])->toEqual(300)
        ->and($row['compensation']['counterpart_label'])->toBe($payable->purchaseInvoice->document_number)
        ->and($row['residual_amount'])->toEqual(0)
        ->and($data['customer']['summary']['compensated_total'])->toEqual(300)
        ->and($data['supplier']['summary']['compensated_total'])->toEqual(300)
        ->and($data['supplier']['documents'][0]['installments'][0]['compensation']['counterpart_label'])
        ->toBe("{$receivable->invoice->number}/{$receivable->invoice->year}");
});

it('exposes the compensation on the installment resources and the grid column', function () {
    Sanctum::actingAs(compActor([...COMP_PERMISSIONS, 'invoice-installments.view', 'invoice-installments.viewAny', 'purchase-invoice-installments.view', 'purchase-invoice-installments.viewAny']));
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');
    $id = compose($receivable, $payable)->assertCreated()->json('data.id');

    $this->getJson("/api/invoice-installments/{$receivable->id}")->assertOk()
        ->assertJsonPath('data.compensation.id', $id)->assertJsonPath('data.compensation.amount', '300.00')
        ->assertJsonPath('data.compensation.counterpart_label', $payable->purchaseInvoice->document_number);
    $this->getJson("/api/purchase-invoice-installments/{$payable->id}")->assertOk()
        ->assertJsonPath('data.compensation.id', $id);
    $this->getJson("/api/invoices/{$receivable->invoice_id}")->assertOk()->assertJsonPath('data.installments.0.compensation.id', $id);
});

it('the compensated column and its set filter work on both installment grids', function () {
    Sanctum::actingAs(compActor([...COMP_PERMISSIONS, 'invoice-installments.viewAny', 'purchase-invoice-installments.viewAny']));
    $receivable = compReceivable('300.00');
    $payable = compPayable('300.00');
    $plainReceivable = compReceivable('10.00');
    $plainPayable = compPayable('10.00');
    compose($receivable, $payable)->assertCreated();

    foreach ([['invoice-installments', $receivable, $plainReceivable], ['purchase-invoice-installments', $payable, $plainPayable]] as [$table, $compensated, $plain]) {
        $rows = fn (string $value): array => collect($this->postJson("/api/tables/{$table}/rows", [
            'startRow' => 0, 'endRow' => 50, 'filterModel' => ['compensated' => ['filterType' => 'set', 'values' => [$value]]],
        ])->assertOk()->json('items'))->pluck('id')->all();

        expect($rows('yes'))->toBe([$compensated->id])->and($rows('no'))->toBe([$plain->id]);
    }
});

it('AC-012: the compensate row action is offered only on open installments of invoices, to users with both abilities', function () {
    $open = compReceivable('300.00');
    $openPayable = compPayable('300.00');
    $proforma = compReceivable('20.00', ['type' => 'proforma']);
    $collected = compReceivable('30.00', installment: ['collected_amount' => '30.00', 'collected_at' => '2026-10-01']);
    $paid = compPayable('30.00', installment: ['paid_amount' => '30.00', 'paid_at' => '2026-10-01']);
    $purchaseProforma = compPayable('40.00', ['document_type' => PurchaseInvoiceType::Proforma]);
    $done = compReceivable('50.00');
    compose($done, compPayable('50.00'))->assertCreated();

    $actions = function (string $table, array $permissions): array {
        Sanctum::actingAs(compActor([...$permissions, "{$table}.viewAny"]));
        $items = $this->postJson("/api/tables/{$table}/rows", ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items');

        return collect($items)->mapWithKeys(fn (array $row): array => [$row['id'] => in_array('compensate', $row['actions'] ?? [], true)])->all();
    };

    $receivables = $actions('invoice-installments', COMP_PERMISSIONS);
    expect($receivables[$open->id])->toBeTrue()
        ->and($receivables[$proforma->id])->toBeFalse()
        ->and($receivables[$collected->id])->toBeFalse()
        ->and($receivables[$done->id])->toBeFalse();

    $payables = $actions('purchase-invoice-installments', COMP_PERMISSIONS);
    expect($payables[$openPayable->id])->toBeTrue()
        ->and($payables[$paid->id])->toBeFalse()
        ->and($payables[$purchaseProforma->id])->toBeFalse();

    expect($actions('invoice-installments', ['invoices.collect', 'invoices.view'])[$open->id])->toBeFalse()
        ->and($actions('purchase-invoice-installments', ['purchase-invoices.pay', 'purchase-invoices.view'])[$openPayable->id])->toBeFalse();
});
