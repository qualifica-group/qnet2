<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The positive-close fiscal gate (direttiva utente 2026-09-09) on Gestione
 * Richieste' two write channels: the work panel's
 * PATCH /api/request-management/{quote} and the grid's inline cell edit
 * (PATCH /api/tables/request-management/rows/{row}), which bypasses that
 * FormRequest entirely. Both reach RequestManagementService::updateWork() and
 * therefore RequestWorkflowStatusWriter::assertClientFiscalIdentity(), the one
 * enforcing gate.
 *
 * The rule: a transition into the `closed_won` group is refused while the
 * client's card carries NEITHER a codice fiscale NOR a partita IVA. Either of
 * the two satisfies it (decisione utente 2026-09-09) — a private client has no
 * VAT number and a company's own `tax_code` IS its eleven-digit code.
 *
 * The values are read as the request WILL leave them: a card filled in the
 * very payload that closes the request satisfies the gate.
 *
 * Decisione utente 2026-09-09 (rev-2): on the PANEL channel the same rule is
 * an INVARIANT of the record — a request that already SITS in `closed_won`
 * without a fiscal identity cannot be saved at all
 * (UpdateRequestRequest::validateClientFiscalIdentity()), which is what
 * surfaces the requests closed before the rule existed. The grid's other
 * cells stay editable on those very records.
 */
uses(RefreshDatabase::class);

if (! function_exists('fiscalGateActor')) {
    function fiscalGateActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();
        $user->givePermissionTo([
            'request-management.viewAny',
            'request-management.view',
            'request-management.update',
            'request-management.viewAll',
            'notes.create',
        ]);

        return $user;
    }
}

if (! function_exists('fiscalGateQuote')) {
    /**
     * A request whose client card carries the given fiscal identifiers (both
     * blank by default), and whose Offerta already holds a REVENUE line — so
     * the spec 0102 line gate is satisfied and only THIS gate can refuse the
     * closing transition.
     */
    function fiscalGateQuote(User $operator, ?string $taxCode = null, ?string $vatNumber = null): Quote
    {
        $registry = Registry::factory()->create();
        $registry->personalData()->create([
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => $taxCode,
            'vat_number' => $vatNumber,
        ]);

        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
        QuoteLine::factory()->for($quote)->create(['product_id' => fiscalGateProduct()->id]);

        return $quote;
    }
}

if (! function_exists('fiscalGateProduct')) {
    /** A product whose category carries an EFFECTIVE business function (coverage auto-adds). */
    function fiscalGateProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

if (! function_exists('fiscalGateStatus')) {
    function fiscalGateStatus(WorkflowStatusGroup $group): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::factory()->global()->create([
            'group' => $group,
            'requires_note' => false,
            'sort_order' => 99,
        ]);
    }
}

// ---------------------------------------------------------------------------
// The work panel channel
// ---------------------------------------------------------------------------

it('the panel refuses a positive close while the client carries neither tax code nor VAT number', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_identity');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('the panel allows a positive close on a client carrying the tax code alone', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor, taxCode: 'RSSMRA80A01H501U');
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

it('the panel allows a positive close on a client carrying the VAT number alone', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor, vatNumber: '01234567897');
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

it('a client_identity submitted in the SAME payload satisfies the gate', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => 'RSSMRA80A01H501U',
        ],
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    $card = $quote->fresh()->opportunity->registry->personalData;

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id)
        ->and($card->tax_code)->toBe('RSSMRA80A01H501U');
});

it('the gate guards the positive outcome only: a negative close needs no fiscal identity', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedLost = fiscalGateStatus(WorkflowStatusGroup::ClosedLost);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedLost->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedLost->id);
});

// Rev-2: the TRANSITION gate still ignores a resend of the current status
// (spec 0083 AC-026) — shown on the grid channel, the one the panel's own
// INVARIANT does not reach (see the last section).
it('resending the status a request already holds is not a transition and never trips the gate', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    $quote->forceFill(['quote_workflow_status_id' => $closedWon->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $closedWon->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

// ---------------------------------------------------------------------------
// The inline grid channel (no FormRequest at all)
// ---------------------------------------------------------------------------

it('the inline status cell refuses a positive close on a client with no fiscal identity', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $closedWon->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_identity');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('the new vat_number cell fills the card from the grid, unblocking the close', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'vat_number',
        'value' => 'IT 0123456789 7',
    ])->assertOk();

    // Canonicalized by the column's `vat_number` format: the IT prefix and the
    // separators are dropped before the value is persisted.
    expect($quote->opportunity->registry->personalData->fresh()->vat_number)->toBe('01234567897');

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $closedWon->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

it('the vat_number cell rejects an invalid partita IVA, the card untouched', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor, vatNumber: '01234567897');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'vat_number',
        'value' => '01234567890',
    ])->assertStatus(422);

    expect($quote->opportunity->registry->personalData->fresh()->vat_number)->toBe('01234567897');
});

// ---------------------------------------------------------------------------
// The INVARIANT half: a request ALREADY closed positive (decisione utente
// 2026-09-09 rev-2)
// ---------------------------------------------------------------------------

it('the panel refuses ANY save on a request already closed positive with no fiscal identity', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    $quote->forceFill(['quote_workflow_status_id' => $closedWon->id])->save();
    Sanctum::actingAs($actor);

    // An edit that has nothing to do with the status or the client.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'next_callback_at' => '2026-10-01T09:00:00Z',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_identity');

    expect($quote->fresh()->next_callback_at)->toBeNull();
});

it('the same save goes through once the client card carries an identifier', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor, taxCode: 'RSSMRA80A01H501U');
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    $quote->forceFill(['quote_workflow_status_id' => $closedWon->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'next_callback_at' => '2026-10-01T09:00:00Z',
    ])->assertOk();

    expect($quote->fresh()->next_callback_at)->not->toBeNull();
});

it('the invariant is satisfied by the card filled in the SAME save', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    $quote->forceFill(['quote_workflow_status_id' => $closedWon->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => 'RSSMRA80A01H501U',
        ],
    ])->assertOk();

    expect($quote->opportunity->registry->personalData->fresh()->tax_code)->toBe('RSSMRA80A01H501U');
});

it('the invariant does NOT reach the grid: another cell stays editable on the same record', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    $closedWon = fiscalGateStatus(WorkflowStatusGroup::ClosedWon);
    $quote->forceFill(['quote_workflow_status_id' => $closedWon->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'next_callback_at',
        'value' => '2026-10-01T09:00',
    ])->assertOk();

    expect($quote->fresh()->next_callback_at)->not->toBeNull();
});

it('a request in a NON positive status is saved from the panel with no fiscal identity', function () {
    $actor = fiscalGateActor();
    $quote = fiscalGateQuote($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'next_callback_at' => '2026-10-01T09:00:00Z',
    ])->assertOk();

    expect($quote->fresh()->next_callback_at)->not->toBeNull();
});
