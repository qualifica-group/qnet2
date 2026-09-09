<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The closing/validating transition gate (spec 0102, D-3) on the Gestione
 * Richieste panel's two write paths: the PATCH .../request-management/
 * {quote} FormRequest (mirrored by ValidatesQuoteWorkflowStatus::
 * validateQuoteWorkflowStatusRequiresOfferLine(), AC-044/045) and the inline
 * grid cell edit (PATCH .../tables/request-management/rows/{row}), which
 * BYPASSES that FormRequest entirely and reaches QuoteWorkflowStatusWriter
 * only through RequestManagementService::updateWork() — the actual
 * enforcing gate on every channel (AC-017). AC-010..015/018 (the predicate
 * itself, group by group) are exercised once against the Offerte channel in
 * QuoteWorkflowStatusLineGateTest; this file covers what is specific to this
 * panel: the same-request line+status interaction (AC-016), the inline
 * bypass (AC-017) and the gate's precedence over `requires_note` (AC-019).
 */
uses(RefreshDatabase::class);

if (! function_exists('lineGateRequestActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management abilities, unprefixed
     */
    function lineGateRequestActor(array $abilities = ['viewAny', 'view', 'update', 'viewAll'], bool $canCreateNotes = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('lineGateRequestQuote')) {
    /**
     * A request with no offer lines and no matching active workflow: its
     * resolved set is deterministically the GLOBAL default one.
     *
     * The client card carries a codice fiscale since the direttiva utente
     * 2026-09-09: a positive close now also demands a fiscal identity
     * (RequestWorkflowStatusWriter), and this file must keep exercising the
     * LINE gate — with an empty card the closing cases would be refused one
     * step earlier, on `client_identity`.
     */
    function lineGateRequestQuote(User $operator): Quote
    {
        $registry = Registry::factory()->create();
        $registry->personalData()->create([
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => 'RSSMRA80A01H501U',
        ]);

        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('lineGateRequestProduct')) {
    /**
     * A product whose category carries an EFFECTIVE business function, so
     * OpportunityProductLineCoverage::ensure() auto-adds coverage instead of
     * 422-ing on a category with none (mirrors ContractLifecycleTest's own
     * contractLifecycleRevenueProduct()).
     */
    function lineGateRequestProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

if (! function_exists('lineGateRequestStatus')) {
    function lineGateRequestStatus(WorkflowStatusGroup $group, bool $requiresNote = false): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::factory()->global()->create([
            'group' => $group,
            'requires_note' => $requiresNote,
            'sort_order' => 99,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-044/045 — the panel FormRequest's own mirror
// ---------------------------------------------------------------------------

it('AC-044: the panel FormRequest rejects a closing destination on a zero-line offer -> 422 on offer_lines', function () {
    $actor = lineGateRequestActor();
    $quote = lineGateRequestQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedWon = lineGateRequestStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('AC-045: the panel FormRequest raises no error on an open/pending destination with zero lines', function () {
    $actor = lineGateRequestActor();
    $quote = lineGateRequestQuote($actor);
    $pending = lineGateRequestStatus(WorkflowStatusGroup::Pending);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $pending->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($pending->id);
});

// ---------------------------------------------------------------------------
// AC-016 — the same-request line write feeds the SAME request's status gate
// ---------------------------------------------------------------------------

it('AC-016: an offer_lines row submitted alongside a closing status in the SAME request satisfies the gate', function () {
    $actor = lineGateRequestActor();
    $quote = lineGateRequestQuote($actor);
    $product = lineGateRequestProduct();
    $closedWon = lineGateRequestStatus(WorkflowStatusGroup::ClosedWon);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
        ],
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    $fresh = $quote->fresh();

    expect($fresh->quote_workflow_status_id)->toBe($closedWon->id)
        ->and($fresh->offerLines()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-017 — the inline grid edit bypasses the FormRequest, not the writer
// ---------------------------------------------------------------------------

it('AC-017: the inline cell edit into a validated status on a zero-line offer -> 422, status unchanged', function () {
    $actor = lineGateRequestActor();
    $quote = lineGateRequestQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $validated = lineGateRequestStatus(WorkflowStatusGroup::Validated);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $validated->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

// ---------------------------------------------------------------------------
// AC-019 — the line gate precedes the requires_note note creation
// ---------------------------------------------------------------------------

it('AC-019: on a zero-line offer the line gate wins over requires_note, no note is created', function () {
    $actor = lineGateRequestActor(canCreateNotes: false);
    $quote = lineGateRequestQuote($actor);
    $originalStatusId = $quote->quote_workflow_status_id;
    $closedWon = lineGateRequestStatus(WorkflowStatusGroup::ClosedWon, requiresNote: true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
        'note' => 'Attempted note without permission.',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId)
        ->and(Note::query()->count())->toBe(0);
});

it('AC-019: the same gate wins over requires_note even for an actor WHO CAN create notes', function () {
    $actor = lineGateRequestActor();
    $quote = lineGateRequestQuote($actor);
    $closedWon = lineGateRequestStatus(WorkflowStatusGroup::ClosedWon, requiresNote: true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => $closedWon->id,
        'note' => 'Cliente confermato.',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines');

    expect(Note::query()->count())->toBe(0);
});
