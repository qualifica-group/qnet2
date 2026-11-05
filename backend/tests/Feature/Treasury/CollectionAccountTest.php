<?php

use App\Models\FinancialAccount;
use App\Models\InvoiceInstallment;
use App\Models\MigrationRun;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// Spec 0355, AC-017 / AC-018 - the account chosen when an installment is collected.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = Registry::factory()->create();
    $this->bank = FinancialAccount::factory()->create(['name' => 'Banca A']);
    $this->invoice = installmentInvoice($this->customer, null, null, ['document_date' => '2026-09-01', 'net_amount' => 900, 'vat_amount' => 0, 'total_amount' => 900]);
    $this->first = installmentOf($this->invoice, ['due_date' => '2026-10-31', 'amount' => '300.00']);
    $this->second = installmentOf($this->invoice, ['due_date' => '2026-11-30', 'amount' => '300.00']);
    $this->third = installmentOf($this->invoice, ['due_date' => '2026-12-31', 'amount' => '300.00']);
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view'], ['invoices.collect', 'management-control.view']));
});

function collectionUrl(InvoiceInstallment $installment): string
{
    return "/api/invoice-installments/{$installment->id}/collection";
}

it('AC-017 a single collection saves the account on the installment and exposes it', function () {
    $this->putJson(collectionUrl($this->first), ['collected_amount' => '300.00', 'collected_at' => '2026-11-02', 'financial_account_id' => $this->bank->id])->assertOk();

    expect($this->first->fresh()->financial_account_id)->toBe($this->bank->id);

    $this->getJson("/api/invoice-installments/{$this->first->id}")->assertOk()
        ->assertJsonPath('data.financial_account', ['id' => $this->bank->id, 'name' => 'Banca A']);
});

it('AC-017 without the field the account stays null and the resource shows null', function () {
    $this->putJson(collectionUrl($this->first), ['collected_amount' => '300.00', 'collected_at' => '2026-11-02'])->assertOk();

    expect($this->first->fresh()->financial_account_id)->toBeNull();
    $this->getJson("/api/invoice-installments/{$this->first->id}")->assertJsonPath('data.financial_account', null);
});

it('AC-017 an unknown account is a 422 and nothing is collected', function () {
    $this->putJson(collectionUrl($this->first), ['collected_amount' => '300.00', 'collected_at' => '2026-11-02', 'financial_account_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors(['financial_account_id']);

    expect($this->first->fresh()->collected_amount)->toBeNull();
});

it('AC-017 a partial collection puts the account only on the reduced installment', function (string $mode) {
    $this->putJson(collectionUrl($this->first), [
        'collected_amount' => '100.00', 'collected_at' => '2026-11-02', 'financial_account_id' => $this->bank->id,
        'residual_mode' => $mode, 'residual_due_date' => '2026-11-15',
    ])->assertOk();

    $accounts = $this->invoice->installments()->orderBy('sequence')->pluck('financial_account_id')->all();

    expect($accounts[0])->toBe($this->bank->id)
        ->and(array_filter(array_slice($accounts, 1)))->toBe([]);
})->with(['spread', 'new_installment']);

it('AC-017 clearing the collection resets the account and the restored plan does not bring it back', function () {
    $this->putJson(collectionUrl($this->first), [
        'collected_amount' => '100.00', 'collected_at' => '2026-11-02', 'financial_account_id' => $this->bank->id, 'residual_mode' => 'spread',
    ])->assertOk();

    $this->deleteJson(collectionUrl($this->first))->assertOk();

    expect($this->invoice->installments()->pluck('financial_account_id')->filter()->all())->toBe([])
        ->and($this->first->fresh()->collected_amount)->toBeNull()
        ->and($this->first->fresh()->amount)->toBe('300.00');
});

it('AC-017 a bulk collection applies one account to every collected installment', function () {
    $this->postJson('/api/invoice-installments/collections', [
        'collected_at' => '2026-11-02', 'financial_account_id' => $this->bank->id,
        'items' => [
            ['installment_id' => $this->first->id, 'collected_amount' => '300.00'],
            ['installment_id' => $this->second->id, 'collected_amount' => '120.00'],
        ],
    ])->assertOk();

    $collected = $this->invoice->installments()->whereNotNull('collected_at')->get();

    expect($collected)->toHaveCount(2)
        ->and($collected->pluck('financial_account_id')->unique()->all())->toBe([$this->bank->id])
        ->and($this->invoice->installments()->whereNull('collected_at')->pluck('financial_account_id')->filter()->all())->toBe([]);
});

it('AC-017 a bulk collection with an unknown account is a 422', function () {
    $this->postJson('/api/invoice-installments/collections', [
        'collected_at' => '2026-11-02', 'financial_account_id' => 999999,
        'items' => [['installment_id' => $this->first->id, 'collected_amount' => '300.00']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['financial_account_id']);
});

it('AC-017 the management control attributes the collection to the installment account, else to the invoice one', function () {
    // REQUIREMENT CHANGED (spec 0355): the collection no longer follows only the invoice account.
    $invoiceAccount = FinancialAccount::factory()->create(['name' => 'Cassa fattura']);
    $this->invoice->update(['financial_account_id' => $invoiceAccount->id]);
    $month = mcMonth(0);

    $this->putJson(collectionUrl($this->first), ['collected_amount' => '300.00', 'collected_at' => "{$month}-02", 'financial_account_id' => $this->bank->id])->assertOk();
    $this->putJson(collectionUrl($this->second), ['collected_amount' => '50.00', 'collected_at' => "{$month}-03", 'residual_mode' => 'spread'])->assertOk();

    $byAccount = mcGet('account-flows', ['date_from' => "{$month}-01", 'date_to' => "{$month}-28"])->assertOk()->json('data.collections.by_account');
    $totals = collect($byAccount)->mapWithKeys(fn (array $row): array => [$row['account']['id'] => $row['amount']])->all();

    expect($totals[$this->bank->id])->toEqual(300)
        ->and($totals[$invoiceAccount->id])->toEqual(50);
});

it('AC-018 an incremental legacy import keeps the account chosen in qnet2 on a collected installment', function () {
    seedMigrationsConfig();
    $this->invoice->forceFill(['old_source' => 'proformas', 'old_id' => 11030])->save();
    $this->putJson(collectionUrl($this->first), ['collected_amount' => '300.00', 'collected_at' => '2026-11-02', 'financial_account_id' => $this->bank->id])->assertOk();

    // The legacy now reports 400.00 collected: 300.00 are already there, 100.00 land on the next open installment
    Http::fake([fakeMigrationsBaseUrl().'/collections*' => Http::response([
        'items' => [[
            'id' => 3625, 'kind' => 'registration', 'collected_at' => '2026-11-04', 'amount' => 100, 'description' => 'saldo',
            'customer_id' => 12874, 'warnings' => [], 'created_at' => '2026-11-04 18:10:26', 'updated_at' => '2026-11-04 18:10:26',
            'movements' => [['proforma_id' => 11030, 'amount' => 100, 'document_collected_total' => 400]],
        ]],
        'pagination' => ['total' => 1],
    ])]);
    auth()->shouldUse('web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'collections']);
    runMigrationJobFor($run);

    expect($run->fresh()->created_rows)->toBe(1)
        ->and($this->first->fresh()->financial_account_id)->toBe($this->bank->id)
        ->and($this->second->fresh()->collected_amount)->toBe('100.00')
        ->and($this->second->fresh()->financial_account_id)->toBeNull();
});
