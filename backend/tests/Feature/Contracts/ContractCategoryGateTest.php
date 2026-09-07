<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0091 — the `generates_contract` gate on the Quote -> Contract
 * automation. The INHERITANCE of the flag is covered by
 * tests/Feature/ProductCategories/ProductCategoryGeneratesContractTest.php;
 * this suite only exercises what the flag DOES, on both channels that can
 * move an offer into a positively-closed working status (spec 0072 BR-1):
 * the quotes endpoints and the Gestione Richieste work panel.
 */
uses(RefreshDatabase::class);

if (! function_exists('contractGateActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractGateActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('contractGateOpportunityCovering')) {
    /**
     * An opportunity whose product lines cover one category per given flag
     * value — the coverage ContractEligibility reads (D-2). Also returns one
     * Product per covered category (spec 0102: POST /api/quotes now requires
     * at least one offer_lines row) — a product from an ALREADY-covered
     * category clears OpportunityProductLineCoverage::ensure() as a no-op,
     * regardless of the category's own business function.
     *
     * @param  array<int, bool>  $generatesContractFlags
     * @return array{opportunity: Opportunity, products: array<int, Product>}
     */
    function contractGateOpportunityCovering(array $generatesContractFlags): array
    {
        $opportunity = Opportunity::factory()->create();
        $products = [];

        foreach ($generatesContractFlags as $generatesContract) {
            $category = ProductCategory::factory()->create(['generates_contract' => $generatesContract]);

            OpportunityProductLine::factory()->create([
                'opportunity_id' => $opportunity->id,
                'product_category_id' => $category->id,
            ]);

            $products[] = Product::factory()->create(['category_id' => $category->id]);
        }

        return ['opportunity' => $opportunity, 'products' => $products];
    }
}

if (! function_exists('contractGateOfferLine')) {
    /**
     * @return array<string, int>
     */
    function contractGateOfferLine(Product $product): array
    {
        return ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10];
    }
}

if (! function_exists('contractGateClosedWonStatus')) {
    function contractGateClosedWonStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole();
    }
}

// ---------------------------------------------------------------------------
// AC-008/009/010 — the gate itself, on the quotes channel
// ---------------------------------------------------------------------------

it('AC-008: a branch that generates contracts still opens one on the positive close', function () {
    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([true]);
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    Sanctum::actingAs(contractGateActor(['create', 'update']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $openStatus->id,
        'offer_lines' => [contractGateOfferLine($products[0])],
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => contractGateClosedWonStatus()->id])->assertOk();

    $contract = Contract::where('quote_id', $quoteId)->first();

    expect($contract)->not->toBeNull()
        ->and($contract->contract_status_id)->toBe(ContractStatus::where('is_default', true)->sole()->id);
});

it('AC-009: a branch that does NOT generate contracts opens none — the offer still closes positively', function () {
    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([false]);
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $closedWon = contractGateClosedWonStatus();
    Sanctum::actingAs(contractGateActor(['create', 'update']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $openStatus->id,
        'offer_lines' => [contractGateOfferLine($products[0])],
    ])->assertCreated()->json('data.id');

    // D-6: no 422 — closing positively stays legitimate, it just yields no contract.
    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $closedWon->id])->assertOk();

    expect(Quote::find($quoteId)->quote_workflow_status_id)->toBe($closedWon->id)
        ->and(Contract::where('quote_id', $quoteId)->exists())->toBeFalse();
});

it('AC-009: a quote created directly in closed_won on a non-contract branch opens none either', function () {
    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([false]);
    Sanctum::actingAs(contractGateActor(['create']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => contractGateClosedWonStatus()->id,
        'offer_lines' => [contractGateOfferLine($products[0])],
    ])->assertCreated()->json('data.id');

    expect(Contract::where('quote_id', $quoteId)->exists())->toBeFalse();
});

it('AC-010: mixed coverage — a single non-contract category is enough to withhold the contract', function () {
    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([true, false]);
    Sanctum::actingAs(contractGateActor(['create']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => contractGateClosedWonStatus()->id,
        'offer_lines' => [contractGateOfferLine($products[0]), contractGateOfferLine($products[1])],
    ])->assertCreated()->json('data.id');

    expect(Contract::where('quote_id', $quoteId)->exists())->toBeFalse();
});

it('an opportunity with no product lines at all is not gated: the contract is created', function () {
    $opportunity = Opportunity::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs(contractGateActor(['create']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => contractGateClosedWonStatus()->id,
        'offer_lines' => [contractGateOfferLine($product)],
    ])->assertCreated()->json('data.id');

    expect(Contract::where('quote_id', $quoteId)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-011 — no retroactivity (D-4, INV-4)
// ---------------------------------------------------------------------------

it('AC-011: a contract already opened survives the flag being turned off, and still suspends', function () {
    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([true]);
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $closedWon = contractGateClosedWonStatus();
    Sanctum::actingAs(contractGateActor(['create', 'update']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $closedWon->id,
        'offer_lines' => [contractGateOfferLine($products[0])],
    ])->assertCreated()->json('data.id');

    expect(Contract::where('quote_id', $quoteId)->exists())->toBeTrue();

    // The catalogue is reconfigured AFTER the deal was closed.
    ProductCategory::query()->update(['generates_contract' => false]);

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $openStatus->id])->assertOk();

    $contract = Contract::where('quote_id', $quoteId)->first();

    expect($contract)->not->toBeNull()
        ->and($contract->isSuspended())->toBeTrue();
});

it('INV-3: the idempotence guard still wins — a re-entry into closed_won on a now-gated branch leaves the contract alone', function () {
    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([true]);
    $openStatus = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open]);
    $closedWon = contractGateClosedWonStatus();
    Sanctum::actingAs(contractGateActor(['create', 'update']));

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $closedWon->id,
        'offer_lines' => [contractGateOfferLine($products[0])],
    ])->assertCreated()->json('data.id');

    $contractId = Contract::where('quote_id', $quoteId)->sole()->id;

    ProductCategory::query()->update(['generates_contract' => false]);

    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $openStatus->id])->assertOk();
    $this->patchJson("/api/quotes/{$quoteId}", ['quote_workflow_status_id' => $closedWon->id])->assertOk();

    expect(Contract::where('quote_id', $quoteId)->pluck('id')->all())->toBe([$contractId]);
});

// ---------------------------------------------------------------------------
// AC-012 — the Gestione Richieste channel
// ---------------------------------------------------------------------------

it('AC-012: the gate holds on the request-management work panel too', function () {
    foreach (['view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.viewAll']);

    ['opportunity' => $opportunity, 'products' => $products] = contractGateOpportunityCovering([false]);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    // Spec 0102: closing an offer positively requires at least one REVENUE
    // line (the D-3 gate lives in QuoteWorkflowStatusWriter::apply(), shared
    // by every channel) — this test's own subject is the contract flag, not
    // that gate, so it just satisfies it.
    QuoteLine::factory()->for($quote)->create(['product_id' => $products[0]->id]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'quote_workflow_status_id' => contractGateClosedWonStatus()->id,
    ])->assertOk();

    expect(Contract::where('quote_id', $quote->id)->exists())->toBeFalse();
});
