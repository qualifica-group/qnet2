<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\MigrationRun;
use App\Models\Registry;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// Spec 0361 - write-off of the residual when an invoice installment is collected.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->invoice = installmentInvoice(Registry::factory()->create(), null, null, ['document_date' => '2026-09-01', 'net_amount' => 2000, 'vat_amount' => 0, 'total_amount' => 2000]);
    $this->first = installmentOf($this->invoice, ['due_date' => '2026-10-31', 'amount' => '1000.00']);
    $this->second = installmentOf($this->invoice, ['due_date' => '2026-11-30', 'amount' => '1000.00']);
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view'], ['invoices.collect']));
});

function writeOffCollect(InvoiceInstallment $installment, array $payload): TestResponse
{
    return test()->putJson("/api/invoice-installments/{$installment->id}/collection", $payload + ['collected_at' => '2026-11-02']);
}

function writeOffPlan(Invoice $invoice): array
{
    return $invoice->installments()->orderBy('sequence')->get()->map(fn (InvoiceInstallment $row): string => "{$row->sequence}:{$row->amount}:{$row->collected_amount}")->all();
}

it('AC-001 closes the installment with a write-off and touches nothing else', function () {
    $response = writeOffCollect($this->first, [
        'collected_amount' => '999.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding', 'write_off_note' => 'bank fee',
    ])->assertOk();

    $row = $this->first->fresh();
    expect($row->amount)->toBe('1000.00')
        ->and($row->collected_amount)->toBe('999.80')
        ->and($row->write_off_amount)->toBe('0.20')
        ->and($row->write_off_reason->value)->toBe('rounding')
        ->and($row->write_off_note)->toBe('bank fee')
        ->and($row->residualAmount())->toBe('0.00')
        ->and($row->status()->value)->toBe('paid')
        ->and($row->redistribution_snapshot)->toBeNull()
        ->and($this->invoice->installments()->count())->toBe(2)
        ->and($this->second->fresh()->amount)->toBe('1000.00');

    $first = collect($response->json('data.installments'))->firstWhere('id', $this->first->id);
    expect($first['write_off'])->toBe(['amount' => '0.20', 'reason' => 'rounding', 'note' => 'bank fee'])
        ->and(collect($response->json('data.installments'))->firstWhere('id', $this->second->id)['write_off'])->toBeNull()
        ->and($response->json('data.residual_amount'))->toBe('1000.00');
});

it('AC-001 exposes the write-off on the single installment resource', function () {
    writeOffCollect($this->first, ['collected_amount' => '999.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding'])->assertOk();

    $this->getJson("/api/invoice-installments/{$this->first->id}")->assertOk()
        ->assertJsonPath('data.write_off', ['amount' => '0.20', 'reason' => 'rounding', 'note' => null])
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.residual_amount', '0.00');
});

it('AC-003 a zero collection with write-off closes a never collected installment', function () {
    writeOffCollect($this->first, ['collected_amount' => '0', 'residual_mode' => 'write_off', 'write_off_reason' => 'bad_debt'])->assertOk();

    $row = $this->first->fresh();
    expect($row->write_off_amount)->toBe('1000.00')
        ->and($row->collected_amount)->toBe('0.00')
        ->and($row->financial_account_id)->toBeNull()
        ->and($row->residualAmount())->toBe('0.00')
        ->and($row->status()->value)->toBe('paid')
        ->and($row->write_off_reason->value)->toBe('bad_debt');
});

it('AC-003 a zero collection without write-off is a 422', function (array $extra) {
    writeOffCollect($this->first, ['collected_amount' => '0', ...$extra])->assertUnprocessable()->assertJsonValidationErrors(['collected_amount']);

    expect($this->first->fresh()->collected_amount)->toBeNull();
})->with([
    'no mode' => [[]],
    'spread' => [['residual_mode' => 'spread']],
]);

it('AC-004 rejects malformed write-off requests and changes nothing', function (array $payload, string $field) {
    writeOffCollect($this->first, $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect($this->first->fresh()->collected_amount)->toBeNull()->and($this->first->fresh()->write_off_amount)->toBeNull();
})->with([
    'no reason' => [['collected_amount' => '999.80', 'residual_mode' => 'write_off'], 'write_off_reason'],
    'invalid reason' => [['collected_amount' => '999.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'gift'], 'write_off_reason'],
    'reason without write-off' => [['collected_amount' => '999.80', 'residual_mode' => 'spread', 'write_off_reason' => 'rounding'], 'write_off_reason'],
    'note without write-off' => [['collected_amount' => '999.80', 'residual_mode' => 'spread', 'write_off_note' => 'x'], 'write_off_note'],
    'note too long' => [['collected_amount' => '999.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding', 'write_off_note' => str_repeat('a', 256)], 'write_off_note'],
    'full collection' => [['collected_amount' => '1000.00', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding'], 'residual_mode'],
]);

it('AC-004 a write-off on an already collected installment is a 409', function () {
    writeOffCollect($this->first, ['collected_amount' => '1000.00'])->assertOk();

    writeOffCollect($this->first, ['collected_amount' => '999.00', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding'])->assertStatus(409);
});

it('AC-005 clearing the collection removes the write-off', function () {
    writeOffCollect($this->first, ['collected_amount' => '999.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding', 'write_off_note' => 'n'])->assertOk();

    $this->deleteJson("/api/invoice-installments/{$this->first->id}/collection")->assertOk();

    $row = $this->first->fresh();
    expect($row->collected_amount)->toBeNull()
        ->and($row->write_off_amount)->toBeNull()
        ->and($row->write_off_reason)->toBeNull()
        ->and($row->write_off_note)->toBeNull()
        ->and($row->residualAmount())->toBe('1000.00')
        ->and(writeOffPlan($this->invoice))->toBe(['1:1000.00:', '2:1000.00:']);
});

it('AC-006 an invoice with collected and written-off installments is paid', function () {
    writeOffCollect($this->first, ['collected_amount' => '1000.00'])->assertOk();
    $response = writeOffCollect($this->second, ['collected_amount' => '0', 'residual_mode' => 'write_off', 'write_off_reason' => 'bad_debt'])->assertOk();

    expect($response->json('data.payment_status'))->toBe('paid')
        ->and($response->json('data.residual_amount'))->toBe('0.00')
        ->and(app(InvoicePaymentStatusResolver::class)->resolve($this->invoice->installments()->get(), now())->value)->toBe('paid');
});

it('forbids a user without the collect permission', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view']));

    writeOffCollect($this->first, ['collected_amount' => '999.80', 'residual_mode' => 'write_off', 'write_off_reason' => 'rounding'])->assertForbidden();
});

it('AC-009 an incremental legacy import never overwrites an installment with a write-off', function () {
    seedMigrationsConfig();
    $this->invoice->forceFill(['old_source' => 'proformas', 'old_id' => 11030])->save();
    $this->second->delete();
    writeOffCollect($this->first, ['collected_amount' => '0', 'residual_mode' => 'write_off', 'write_off_reason' => 'bad_debt'])->assertOk();

    Http::fake([fakeMigrationsBaseUrl().'/collections*' => Http::response([
        'items' => [[
            'id' => 3625, 'kind' => 'registration', 'collected_at' => '2026-11-04', 'amount' => 500, 'description' => 'saldo',
            'customer_id' => 12874, 'warnings' => [], 'created_at' => '2026-11-04 18:10:26', 'updated_at' => '2026-11-04 18:10:26',
            'movements' => [['proforma_id' => 11030, 'amount' => 500, 'document_collected_total' => 500]],
        ]],
        'pagination' => ['total' => 1],
    ])]);
    auth()->shouldUse('web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'collections']);
    runMigrationJobFor($run);

    $row = $this->first->fresh();
    expect($run->fresh()->created_rows)->toBe(0)
        ->and($row->collected_amount)->toBe('0.00')
        ->and($row->write_off_amount)->toBe('1000.00')
        ->and(json_encode($run->fresh()->report))->toContain('write-off in qnet-2');
});
