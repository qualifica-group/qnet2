<?php

use App\Models\InvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const REDISTRIBUTION_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete', 'collect'];

function clearCollection(int $installmentId): TestResponse
{
    return test()->deleteJson("/api/invoice-installments/{$installmentId}/collection");
}

it('AC-010: a partial collection with spread closes the installment and adds the residual to the later ones', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();

    $response = collectInstallment($invoice['installments'][0]['id'], '200', ['residual_mode' => 'spread'])->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:200.00', '2:500.00', '3:500.00'])
        ->and($response->json('data.installments.0.status'))->toBe('paid')
        ->and($response->json('data.installments.1.status'))->toBe('unpaid');
});

it('AC-011: a partial collection with new_installment inserts the residual after it and renumbers the others', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();

    $response = collectInstallment($invoice['installments'][0]['id'], '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-05-01'])->assertOk();
    $installments = $response->json('data.installments');

    expect(invoicePlan($installments))->toBe(['1:200.00', '2:200.00', '3:400.00', '4:400.00'])
        ->and(substr($installments[1]['due_date'], 0, 10))->toBe('2026-05-01')
        ->and($installments[1]['payment_method_code'])->toBe($invoice['installments'][0]['payment_method_code'])
        ->and(array_map(fn (string $date): string => substr($date, 0, 10), array_column($installments, 'due_date')))->toBe(['2026-04-14', '2026-05-01', '2026-05-14', '2026-06-13']);
});

it('AC-012: spread on the last installment is a 422 on residual_mode, new_installment creates the installment', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $lastId = $invoice['installments'][2]['id'];

    collectInstallment($lastId, '100', ['residual_mode' => 'spread'])
        ->assertUnprocessable()->assertJsonValidationErrors(['residual_mode' => 'There are no later open installments to spread the residual on.']);
    expect(InvoiceInstallment::query()->whereKey($lastId)->value('collected_amount'))->toBeNull();

    $response = collectInstallment($lastId, '100', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-07-01'])->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:100.00', '4:300.00']);
});

it('AC-013: a partial collection needs residual_mode, and new_installment needs a date not before the document date', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $id = $invoice['installments'][0]['id'];

    collectInstallment($id, '200')->assertUnprocessable()->assertJsonValidationErrors('residual_mode');
    collectInstallment($id, '200', ['residual_mode' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('residual_mode');
    collectInstallment($id, '200', ['residual_mode' => 'new_installment'])->assertUnprocessable()->assertJsonValidationErrors('residual_due_date');
    collectInstallment($id, '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-03-14'])->assertUnprocessable()->assertJsonValidationErrors('residual_due_date');

    expect(InvoiceInstallment::query()->whereKey($id)->value('collected_amount'))->toBeNull();
});

it('AC-014: a full collection redistributes nothing, stores no snapshot and ignores the residual fields', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $id = $invoice['installments'][0]['id'];

    $response = collectInstallment($id, '400', ['residual_mode' => 'new_installment'])->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and(InvoiceInstallment::query()->whereKey($id)->value('redistribution_snapshot'))->toBeNull();
});

it('AC-015: collecting an already collected installment is a 409 and the collection does not change', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $id = $invoice['installments'][0]['id'];
    collectInstallment($id, '400')->assertOk();

    collectInstallment($id, '100', ['collected_at' => '2026-05-05'])
        ->assertStatus(409)->assertJsonPath('message', 'This installment is already collected: clear the collection first.');

    $row = InvoiceInstallment::query()->findOrFail($id);
    expect($row->collected_amount)->toBe('400.00')->and($row->collected_at->toDateString())->toBe('2026-04-20');
});

it('AC-016: clearing a spread collection restores the previous plan', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $id = $invoice['installments'][0]['id'];
    collectInstallment($id, '200', ['residual_mode' => 'spread'])->assertOk();

    $response = clearCollection($id)->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and(array_column($response->json('data.installments'), 'status'))->toBe(['unpaid', 'unpaid', 'unpaid'])
        ->and(InvoiceInstallment::query()->whereKey($id)->value('redistribution_snapshot'))->toBeNull();
});

it('AC-017: clearing a new_installment collection deletes the residual installment and restores the sequences', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $id = $invoice['installments'][0]['id'];
    collectInstallment($id, '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-05-01'])->assertOk();
    expect(InvoiceInstallment::query()->count())->toBe(4);

    $response = clearCollection($id)->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and(array_column($response->json('data.installments'), 'id'))->toBe(array_column($invoice['installments'], 'id'))
        ->and(InvoiceInstallment::query()->count())->toBe(3);
});

it('AC-018: a touched installment collected afterwards blocks the clearing (409) until it is cleared first', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    [$first, $second] = [$invoice['installments'][0]['id'], $invoice['installments'][1]['id']];
    collectInstallment($first, '200', ['residual_mode' => 'spread'])->assertOk();
    collectInstallment($second, '500')->assertOk();

    clearCollection($first)->assertStatus(409)->assertJsonPath('message', 'Clear the later collections first.');
    expect(invoicePlan(InvoiceInstallment::query()->orderBy('sequence')->get()->map->only(['sequence', 'amount'])->all()))
        ->toBe(['1:200.00', '2:500.00', '3:500.00']);

    clearCollection($second)->assertOk();
    $response = clearCollection($first)->assertOk();

    expect(invoicePlan($response->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00']);
});

it('AC-018: a created installment collected afterwards blocks the clearing, a later shifted one does not', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice] = invoiceWithPlan();
    $first = $invoice['installments'][0]['id'];
    $response = collectInstallment($first, '200', ['residual_mode' => 'new_installment', 'residual_due_date' => '2026-05-01'])->assertOk();
    $created = $response->json('data.installments.1.id');
    collectInstallment($response->json('data.installments.2.id'), '400')->assertOk();

    collectInstallment($created, '200')->assertOk();
    clearCollection($first)->assertStatus(409);

    clearCollection($created)->assertOk();
    $restored = clearCollection($first)->assertOk();

    expect(invoicePlan($restored->json('data.installments')))->toBe(['1:400.00', '2:400.00', '3:400.00'])
        ->and($restored->json('data.installments.1.status'))->toBe('paid');
});

it('AC-019: after a rebalancing the snapshot is gone and clearing only removes the collection', function () {
    Sanctum::actingAs(invoiceUserWith(REDISTRIBUTION_ABILITIES));
    [$invoice, $payload] = invoiceWithPlan();
    $first = $invoice['installments'][0]['id'];
    collectInstallment($first, '200', ['residual_mode' => 'spread'])->assertOk();
    $this->putJson("/api/invoices/{$invoice['id']}", invoiceWithUnitPrice($payload, '1400.00'))->assertOk();
    expect(InvoiceInstallment::query()->whereNotNull('redistribution_snapshot')->count())->toBe(0);

    $response = clearCollection($first)->assertOk();

    // collected 200 on rate 1 (closed at 200), residual 1200 split on the open ones: 600 / 600
    expect(invoicePlan($response->json('data.installments')))->toBe(['1:200.00', '2:600.00', '3:600.00'])
        ->and($response->json('data.installments.0.status'))->toBe('unpaid')
        ->and($response->json('data.installments.0.collected_amount'))->toBeNull();
});

it('AC-020: without invoices.collect the collection PUT and DELETE are 403', function () {
    $admin = invoiceUserWith(REDISTRIBUTION_ABILITIES);
    $without = invoiceUserWith(['viewAny', 'view', 'create', 'update', 'delete']);
    Sanctum::actingAs($admin);
    [$invoice] = invoiceWithPlan();
    $id = $invoice['installments'][0]['id'];
    collectInstallment($id, '200', ['residual_mode' => 'spread'])->assertOk();

    Sanctum::actingAs($without);

    collectInstallment($invoice['installments'][1]['id'], '100')->assertForbidden();
    clearCollection($id)->assertForbidden();
    expect(InvoiceInstallment::query()->whereKey($id)->value('collected_amount'))->toBe('200.00');
});
