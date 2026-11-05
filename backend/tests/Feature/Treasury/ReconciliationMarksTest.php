<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\TreasuryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0363 — ticking of statement rows and the lock of ticked rows: AC-001 .. AC-006, AC-011, AC-012.

const RECON_PERIOD = 'date_from=2026-01-01&date_to=2026-12-31';

function reconStatement($account, string $query = ''): array
{
    return test()->getJson("/api/financial-accounts/{$account->id}/statement?".RECON_PERIOD.$query)->assertOk()->json('data');
}

it('AC-001: ticks a collection, a payment and a movement, idempotent', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile', 'treasury-movements.view']));
    $account = reconAccount();
    $collection = reconCollection($account, '200.00', '2026-01-10');
    $payment = reconPayment($account, '50.00', '2026-01-20');
    $movement = reconMovement($account, '300.00', '2026-01-15');
    $keys = ["collection:{$collection->id}", "payment:{$payment->id}", "movement:{$movement->id}"];

    reconMark($account, $keys)->assertOk()->assertJsonPath('data.updated', 3);

    expect(collect(reconStatement($account)['rows'])->pluck('reconciled')->all())->toBe([true, true, true]);
    reconMark($account, $keys)->assertOk()->assertJsonPath('data.updated', 0);
    reconMark($account, $keys, false)->assertOk()->assertJsonPath('data.updated', 3);
    expect(collect(reconStatement($account)['rows'])->pluck('reconciled')->unique()->all())->toBe([false]);
});

it('AC-002: a transfer is ticked per side, decided by the account', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile', 'treasury-movements.view']));
    $from = reconAccount();
    $to = reconAccount();
    $to->update(['company_id' => $from->company_id]);
    $transfer = TreasuryMovement::factory()->transfer($from, $to)->create(['amount' => '100.00', 'movement_date' => '2026-02-01']);

    reconMark($to, ["movement:{$transfer->id}"])->assertOk()->assertJsonPath('data.updated', 1);

    expect(reconStatement($to)['rows'][0]['reconciled'])->toBeTrue()
        ->and(reconStatement($from)['rows'][0]['reconciled'])->toBeFalse();
    $transfer->refresh();
    expect($transfer->counterpart_reconciled_at)->not->toBeNull()->and($transfer->reconciled_at)->toBeNull();
});

it('AC-003: a foreign key makes the whole block fail with 422', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile']));
    $account = reconAccount();
    $mine = reconMovement($account, '10.00', '2026-01-10');
    $foreign = reconMovement(reconAccount(), '10.00', '2026-01-10');
    $beforeOpening = reconMovement($account, '10.00', '2025-12-31');

    foreach ([["movement:{$mine->id}", "movement:{$foreign->id}"], ["movement:{$mine->id}", 'foo:1'], ["movement:{$mine->id}", 'movement:999999'], ["movement:{$mine->id}", "movement:{$beforeOpening->id}"]] as $keys) {
        reconMark($account, $keys)->assertUnprocessable();
    }

    expect(TreasuryMovement::query()->whereNotNull('reconciled_at')->count())->toBe(0);
});

it('AC-004: ticking a collection without own account freezes the effective account on the installment', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile', 'treasury-movements.view']));
    $account = reconAccount();
    $installment = reconCollection($account, '200.00', '2026-01-10', own: false);

    reconMark($account, ["collection:{$installment->id}"])->assertOk();

    expect($installment->fresh()->financial_account_id)->toBe($account->id);
    Invoice::query()->whereKey($installment->invoice_id)->update(['financial_account_id' => reconAccount()->id]);
    expect(reconStatement($account)['rows'])->toHaveCount(1);
});

it('AC-005: a ticked movement cannot be updated or deleted until unticked', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile', 'treasury-movements.view', 'treasury-movements.update', 'treasury-movements.delete']));
    $account = reconAccount();
    $movement = reconMovement($account, '10.00', '2026-01-10');
    reconMark($account, ["movement:{$movement->id}"])->assertOk();

    $this->putJson("/api/treasury-movements/{$movement->id}", ['description' => 'changed'])
        ->assertUnprocessable()->assertJsonPath('message', __('treasury_reconciliations.movement_locked'));
    $this->deleteJson("/api/treasury-movements/{$movement->id}")->assertUnprocessable();
    expect(TreasuryMovement::query()->find($movement->id)->description)->not->toBe('changed');
    $this->getJson("/api/treasury-movements/{$movement->id}")->assertOk()->assertJsonPath('data.reconciled', true);

    reconMark($account, ["movement:{$movement->id}"], false)->assertOk();
    $this->putJson("/api/treasury-movements/{$movement->id}", ['description' => 'changed'])->assertOk();
    $this->deleteJson("/api/treasury-movements/{$movement->id}")->assertSuccessful();
});

it('AC-006: a ticked collection or payment cannot be recorded over or cleared', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile', 'invoices.collect', 'purchase-invoices.pay']));
    $account = reconAccount();
    $collection = reconCollection($account, '200.00', '2026-01-10');
    $payment = reconPayment($account, '50.00', '2026-01-20');
    reconMark($account, ["collection:{$collection->id}", "payment:{$payment->id}"])->assertOk();

    $this->deleteJson("/api/invoice-installments/{$collection->id}/collection")->assertUnprocessable();
    $this->putJson("/api/invoice-installments/{$collection->id}/collection", ['collected_amount' => '100.00', 'collected_at' => '2026-02-01'])->assertUnprocessable();
    $this->deleteJson("/api/purchase-invoice-installments/{$payment->id}/payment")->assertUnprocessable();
    $this->putJson("/api/purchase-invoice-installments/{$payment->id}/payment", ['paid_amount' => '10.00', 'paid_at' => '2026-02-01'])->assertUnprocessable();

    expect(InvoiceInstallment::query()->find($collection->id)->collected_amount)->toBe('200.00')
        ->and(PurchaseInvoiceInstallment::query()->find($payment->id)->paid_amount)->toBe('50.00');

    reconMark($account, ["collection:{$collection->id}", "payment:{$payment->id}"], false)->assertOk();
    $this->deleteJson("/api/invoice-installments/{$collection->id}/collection")->assertOk();
    $this->deleteJson("/api/purchase-invoice-installments/{$payment->id}/payment")->assertOk();
});

it('AC-011: marks need the reconcile permission', function () {
    $account = reconAccount();
    $movement = reconMovement($account, '10.00', '2026-01-10');
    Sanctum::actingAs(reconActor(['treasury-movements.view']));

    reconMark($account, ["movement:{$movement->id}"])->assertForbidden();
    $this->getJson("/api/financial-accounts/{$account->id}/statement?".RECON_PERIOD)->assertOk();
});

it('AC-012: reconciled filter narrows rows only; balance, reconciled balance and count stay unfiltered', function () {
    Sanctum::actingAs(reconActor(['treasury-movements.reconcile', 'treasury-movements.view']));
    $account = reconAccount();
    $first = reconMovement($account, '200.00', '2026-01-10');
    reconMovement($account, '300.00', '2026-01-15');
    reconMark($account, ["movement:{$first->id}"])->assertOk();

    $data = reconStatement($account, '&reconciled=no');

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['balance'])->toBe('1500.00')
        ->and($data['reconciled_balance'])->toBe('1200.00')
        ->and($data['unreconciled_count'])->toBe(1)
        ->and(reconStatement($account, '&reconciled=yes')['rows'])->toHaveCount(1);
    $this->getJson("/api/financial-accounts/{$account->id}/statement?reconciled=maybe")->assertUnprocessable();
});
