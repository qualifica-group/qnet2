<?php

use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0360: title, service lines and the four data fields are editable from
 * the contract of a working or validated contract, written to the origin offer.
 */
uses(RefreshDatabase::class);

function inForceActor(array $abilities = ['view', 'update']): User
{
    return contractScopedUserWith([...$abilities, 'viewAll']);
}

it('AC-001: title is written on the offer as manual, null returns it to automatic', function () {
    ['contract' => $contract, 'quote' => $quote] = inForceContract('validated');
    Sanctum::actingAs(inForceActor());

    $this->patchJson("/api/contracts/{$contract->id}", ['title' => 'Custom title'])
        ->assertOk()
        ->assertJsonPath('data.quote.title', 'Custom title');
    expect($quote->fresh()->title_is_manual)->toBeTrue();

    $this->patchJson("/api/contracts/{$contract->id}", ['title' => null])->assertOk();
    expect($quote->fresh()->title_is_manual)->toBeFalse();
});

it('AC-002: a new row is added on an open or validated contract and totals are recalculated', function (string $statusKey) {
    ['contract' => $contract, 'quote' => $quote, 'lines' => $lines, 'product' => $product] = inForceContract($statusKey, 1);
    Sanctum::actingAs(inForceActor());

    $response = $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [
        inForceRow($lines[0]),
        ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50],
    ]])->assertOk();

    expect($quote->offerLines()->count())->toBe(2)
        ->and($response->json('data.offer_lines'))->toHaveCount(2);
    $this->assertEqualsWithDelta(350.0, (float) $quote->fresh()->revenue_net, 0.001);
})->with(['validated' => 'validated', 'new' => 'new']);

it('AC-003: an omitted free row is deleted and a changed one is saved', function () {
    ['contract' => $contract, 'lines' => [$keep, $drop]] = inForceContract('validated');
    Sanctum::actingAs(inForceActor());

    $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($keep, ['quantity' => 5, 'unit_price' => 10])]])->assertOk();

    $this->assertDatabaseMissing('quote_lines', ['id' => $drop->id]);
    expect((float) QuoteLine::find($keep->id)->quantity)->toBe(5.0)
        ->and((float) QuoteLine::find($keep->id)->unit_price)->toBe(10.0);
});

it('AC-004: a closed_lost contract refuses every editable field, nothing is persisted', function (string $statusKey, string $field) {
    ['contract' => $contract, 'quote' => $quote, 'lines' => [$line]] = inForceContract($statusKey, 1);
    $title = $quote->title;
    $payload = [
        'title' => ['title' => 'X'],
        'offer_lines' => ['offer_lines' => [inForceRow($line, ['quantity' => 9])]],
        'renewal_date' => ['renewal_date' => '2027-01-01'],
        'expiry_date' => ['expiry_date' => '2027-01-01'],
        'payment_notes' => ['payment_notes' => 'X'],
        'comments' => ['comments' => 'X'],
    ][$field];
    Sanctum::actingAs(inForceActor());

    $this->patchJson("/api/contracts/{$contract->id}", $payload)->assertUnprocessable();

    expect($quote->fresh()->title)->toBe($title)
        ->and((float) $line->fresh()->quantity)->toBe(2.0)
        ->and($contract->fresh()->comments)->toBeNull();

    $permissions = $this->getJson("/api/contracts/{$contract->id}")->json('permissions.fields');
    expect($permissions[$field]['editable'])->toBeFalse();
})->with(['terminated', 'cancelled'])->with(['title', 'offer_lines', 'renewal_date', 'expiry_date', 'payment_notes', 'comments']);

it('AC-005: the four data fields are saved on a validated contract', function () {
    ['contract' => $contract] = inForceContract('validated');
    Sanctum::actingAs(inForceActor());

    $this->patchJson("/api/contracts/{$contract->id}", [
        'renewal_date' => '2027-01-15', 'expiry_date' => '2027-06-30', 'payment_notes' => 'Notes', 'comments' => 'Comments',
    ])->assertOk()->assertJsonPath('data.payment_notes', 'Notes');

    $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'comments' => 'Comments', 'payment_notes' => 'Notes']);
});

it('AC-006/007: the contract channel blocks an assigned row too, with the work order code', function () {
    ['contract' => $contract, 'lines' => [$locked, $free]] = inForceContract('validated');
    assignLineToWorkOrder($locked, 'COM-0042');
    Sanctum::actingAs(inForceActor());

    $omit = $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($free)]])
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines');
    expect($omit->json('errors.offer_lines.0'))->toContain('COM-0042');

    $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($free), inForceRow($locked, ['quantity' => 9])]])
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.1');

    $this->assertDatabaseHas('quote_line_work_order', ['quote_line_id' => $locked->id]);

    $response = $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($free), inForceRow($locked)]])->assertOk();
    $byId = collect($response->json('data.offer_lines'))->keyBy('id');
    expect($byId[$locked->id]['work_orders'][0]['code'])->toBe('COM-0042')
        ->and($byId[$free->id]['work_orders'])->toBe([]);
});

it('AC-009: quotes.update without contracts.update is 403; a hidden contract is refused', function () {
    ['contract' => $contract] = inForceContract('validated');
    Permission::findOrCreate('quotes.update');
    $user = contractScopedUserWith(['view', 'viewAll']);
    $user->givePermissionTo('quotes.update');
    Sanctum::actingAs($user);

    $this->patchJson("/api/contracts/{$contract->id}", ['title' => 'X'])->assertForbidden();

    Sanctum::actingAs(contractScopedUserWith(['view', 'update']));
    expect($this->patchJson("/api/contracts/{$contract->id}", ['title' => 'X'])->status())->toBeIn([403, 404]);
});

it('AC-010: commissions are prohibited and absent ones keep the stored overrides', function () {
    ['contract' => $contract, 'lines' => [$line]] = inForceContract('validated', 1);
    Sanctum::actingAs(inForceActor());

    $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($line, ['commissions' => [['recipient_role' => 'commercial', 'type' => 'percentage', 'value' => 5]]])]])
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.commissions');

    $commission = QuoteLineCommission::factory()->create(['quote_line_id' => $line->id]);

    $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($line, ['quantity' => 4])]])->assertOk();

    $this->assertDatabaseHas('quote_line_commissions', ['id' => $commission->id]);
});

it('AC-011: editing the rows moves neither the offer status nor the contract status', function () {
    ['contract' => $contract, 'quote' => $quote, 'lines' => [$line]] = inForceContract('validated', 1);
    $quoteStatus = $quote->quote_workflow_status_id;
    $contractStatus = $contract->contract_status_id;
    Sanctum::actingAs(inForceActor());

    $this->patchJson("/api/contracts/{$contract->id}", ['offer_lines' => [inForceRow($line, ['quantity' => 7])]])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($quoteStatus)
        ->and($contract->fresh()->contract_status_id)->toBe($contractStatus)
        ->and(Contract::where('quote_id', $quote->id)->count())->toBe(1)
        ->and(Quote::find($quote->id))->not->toBeNull();
});
