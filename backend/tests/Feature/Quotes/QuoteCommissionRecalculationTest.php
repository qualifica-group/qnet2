<?php

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\SupplierCommissionDirection;
use App\Enums\WorkflowStatusGroup;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0308, quote channel: GET commission-recalculation and
 * POST recalculate-commissions (D-3, D-4, D-5).
 */
uses(RefreshDatabase::class);

function recalcWriter(): User
{
    return recalcActor(['quotes.view', 'quotes.update']);
}

it('AC-001: KEEP_MANUAL moves a RECEIVED Supplier line to the rule that is now PAID and the margin follows the PAID formula', function () {
    $product = recalcProduct(supplierEnabled: true);
    $rule = recalcRule($product, CommissionRecipientRole::Supplier, 10, ['supplier_commission_direction' => SupplierCommissionDirection::Paid]);
    [$quote, $line] = recalcQuote($product, direction: SupplierCommissionDirection::Received);
    recalcCommission($line, CommissionRecipientRole::Supplier, 20, $rule);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $quote->id)
        ->assertJsonPath('permissions.actions.recalculate_commissions', true);

    $supplier = $line->commissions()->sole();
    expect($line->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Paid)
        ->and($supplier->commission_configuration_id)->toBe($rule->id)
        ->and((float) $supplier->value)->toBe(10.0)
        ->and($supplier->commission_type->value)->toBe('PERCENTAGE')
        ->and((float) $supplier->calculated_amount)->toBe(10.0)
        ->and((float) $quote->fresh()->margin_net)->toBe(90.0);
});

it('AC-002: KEEP_MANUAL keeps a manual Commerciale commission and only recomputes its amount', function () {
    $product = recalcProduct();
    recalcRule($product, CommissionRecipientRole::Commercial, 5);
    [$quote, $line] = recalcQuote($product);
    $recipient = Referent::factory()->create();
    $manual = recalcCommission($line, CommissionRecipientRole::Commercial, 10, null, [
        'recipient_id' => $recipient->id,
        'calculated_amount' => 999,
        'internal_note' => 'agreed',
    ]);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertOk();

    $manual->refresh();
    expect($manual->origin)->toBe(CommissionOrigin::ManualOverride)
        ->and($manual->recipient_id)->toBe($recipient->id)
        ->and((float) $manual->value)->toBe(10.0)
        ->and((float) $manual->calculated_amount)->toBe(10.0)
        ->and($manual->internal_note)->toBe('agreed');
});

it('AC-002: REGENERATE_ALL replaces a manual commission with the rule default, or deletes it when no rule applies', function () {
    $product = recalcProduct();
    $rule = recalcRule($product, CommissionRecipientRole::Commercial, 5);
    [$quote, $line] = recalcQuote($product, quoteAttributes: ['reporter_id' => Referent::factory()->create()->id]);
    $manual = recalcCommission($line, CommissionRecipientRole::Commercial, 10);
    $orphanManual = recalcCommission($line, CommissionRecipientRole::Reporter, 7, null, ['recipient_id' => $quote->reporter_id]);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'REGENERATE_ALL'])->assertOk();

    $commissions = $line->commissions()->get();
    expect($commissions)->toHaveCount(1)
        ->and($commissions->first()->commission_configuration_id)->toBe($rule->id)
        ->and($commissions->first()->origin)->not->toBe(CommissionOrigin::ManualOverride)
        ->and((float) $commissions->first()->value)->toBe(5.0)
        ->and($line->commissions()->whereKey($orphanManual->id)->exists())->toBeFalse();
});

it('AC-003: KEEP_MANUAL deletes a rule commission whose rule no longer applies and gives a rule commission to an empty role', function () {
    $product = recalcProduct();
    $expired = recalcRule($product, CommissionRecipientRole::Commercial, 5, ['valid_until' => now()->subDay()->toDateString()]);
    $reporterRule = recalcRule($product, CommissionRecipientRole::Reporter, 3);
    [$quote, $line] = recalcQuote($product, quoteAttributes: ['reporter_id' => Referent::factory()->create()->id]);
    recalcCommission($line, CommissionRecipientRole::Commercial, 5, $expired);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertOk();

    $commissions = $line->commissions()->get();
    expect($commissions)->toHaveCount(1)
        ->and($commissions->first()->recipient_role)->toBe(CommissionRecipientRole::Reporter)
        ->and($commissions->first()->commission_configuration_id)->toBe($reporterRule->id);
});

it('AC-004: switch off nulls the snapshot and deletes the Supplier commission, even a manual one', function () {
    $product = recalcProduct(supplierEnabled: true);
    [$quote, $line] = recalcQuote($product, direction: SupplierCommissionDirection::Received);
    recalcCommission($line, CommissionRecipientRole::Supplier, 10);
    $product->productTypology->update(['supplier_commission_enabled' => false]);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertOk();

    expect($line->fresh()->supplier_commission_direction)->toBeNull()
        ->and($line->commissions()->count())->toBe(0);
});

it('AC-004: switch on with a null snapshot becomes PAID, or the direction of the applied Supplier rule', function () {
    $paidProduct = recalcProduct(supplierEnabled: true);
    $receivedProduct = recalcProduct(supplierEnabled: true);
    recalcRule($receivedProduct, CommissionRecipientRole::Supplier, 10, ['supplier_commission_direction' => SupplierCommissionDirection::Received]);
    [$paidQuote, $paidLine] = recalcQuote($paidProduct);
    [$receivedQuote, $receivedLine] = recalcQuote($receivedProduct);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$paidQuote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertOk();
    $this->postJson("/api/quotes/{$receivedQuote->id}/recalculate-commissions", ['mode' => 'REGENERATE_ALL'])->assertOk();

    expect($paidLine->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Paid)
        ->and($receivedLine->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Received)
        ->and($receivedLine->commissions()->sole()->recipient_role)->toBe(CommissionRecipientRole::Supplier);
});

it('AC-005: a closed quote answers 422 with the closed message, nothing changes and the action is false', function (WorkflowStatusGroup $group) {
    $product = recalcProduct();
    $rule = recalcRule($product, CommissionRecipientRole::Commercial, 5);
    [$quote, $line] = recalcQuote($product, $group);
    $commission = recalcCommission($line, CommissionRecipientRole::Commercial, 2, $rule);
    Sanctum::actingAs(recalcWriter());

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'REGENERATE_ALL'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('quotes.commission_recalculation.closed'));

    expect((float) $commission->fresh()->value)->toBe(2.0);
    $this->getJson("/api/quotes/{$quote->id}")->assertOk()->assertJsonPath('permissions.actions.recalculate_commissions', false);
})->with([WorkflowStatusGroup::ClosedWon, WorkflowStatusGroup::ClosedLost]);

it('AC-006: no update on the quote, or a non-editable commissions field, is 403; a bad mode is 422', function () {
    [$quote] = recalcQuote(recalcProduct());
    $viewer = recalcActor(['quotes.view']);
    Permission::findOrCreate('quotes.view');
    Permission::findOrCreate('quotes.update');
    $role = Role::create(['name' => 'commissions-locked']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create(['resource' => 'quotes', 'field' => 'commissions', 'visible' => true, 'editable' => false, 'required' => false]);
    $locked = User::factory()->create();
    $locked->assignRole($role);

    Sanctum::actingAs($viewer);
    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertForbidden();

    Sanctum::actingAs($locked);
    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertForbidden();
    $this->getJson("/api/quotes/{$quote->id}")->assertJsonPath('permissions.actions.recalculate_commissions', false);

    Sanctum::actingAs(recalcWriter());
    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'NOPE'])->assertUnprocessable()->assertJsonValidationErrors('mode');
    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", [])->assertUnprocessable()->assertJsonValidationErrors('mode');
});

it('AC-007: the impact endpoint counts lines, commissions, manual commissions and the closed flag', function () {
    $product = recalcProduct();
    [$quote, $line] = recalcQuote($product);
    [, $secondLine] = [null, $line->replicate()->forceFill(['quote_id' => $quote->id])];
    $secondLine->save();
    recalcCommission($line, CommissionRecipientRole::Commercial, 5, recalcRule($product, CommissionRecipientRole::Commercial, 5));
    recalcCommission($secondLine, CommissionRecipientRole::Commercial, 8);
    [$closed] = recalcQuote($product, WorkflowStatusGroup::ClosedWon);

    Sanctum::actingAs(recalcActor(['quotes.view']));
    $this->getJson("/api/quotes/{$quote->id}/commission-recalculation")
        ->assertOk()
        ->assertJsonPath('data', ['lines_count' => 2, 'commissions_count' => 2, 'manual_commissions_count' => 1, 'closed' => false]);
    $this->getJson("/api/quotes/{$closed->id}/commission-recalculation")->assertOk()->assertJsonPath('data.closed', true);

    Sanctum::actingAs(recalcActor([]));
    $this->getJson("/api/quotes/{$quote->id}/commission-recalculation")->assertForbidden();
});
