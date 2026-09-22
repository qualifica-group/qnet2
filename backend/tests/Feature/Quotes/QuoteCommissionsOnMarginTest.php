<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0145: a PERCENTAGE commission's base is the REVENUE line's margin
 * (net minus its own imputed costs, spec 0144), `margin_net` is net of every
 * REVENUE line's commissions, and the recalculation runs after any line-set
 * write regardless of channel (D-6). AC numbers below are this spec's own
 * (0145 acceptance_criteria), distinct from QuoteCommissionIntegrationTest's
 * and QuoteCostLineAllocation*Test's.
 */
uses(RefreshDatabase::class);

function marginTestActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("quotes.{$ability}");
    }

    return $user;
}

function marginNewStatus(): QuoteWorkflowStatus
{
    return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
}

/**
 * A product whose category resolves an EFFECTIVE business function (spec
 * 0065, D-7), so a REVENUE line never trips the coverage guard.
 */
function marginRevenueProduct(): Product
{
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);

    return Product::factory()->create(['category_id' => $category->id]);
}

/** A 10% PERCENTAGE commission rule for $product, recipient role Commercial. */
function marginPercentageRule(Product $product): CommissionConfiguration
{
    return CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'commission_type' => CommissionType::Percentage,
        'value' => 10,
        'valid_from' => '2026-01-01',
    ]);
}

// ---------------------------------------------------------------------------
// AC-001 — imputed cost reduces the base
// ---------------------------------------------------------------------------

it('AC-001: a 400 imputed cost on a 1000 revenue line yields a 60.00 commission at 10%', function () {
    marginNewStatus();
    $opportunity = Opportunity::factory()->create();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity->update(['commercial_id' => $commercial->id]);

    Sanctum::actingAs(marginTestActor(['create', 'view']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Margine provvigioni',
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $commercial->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 400, 'offer_line_index' => 0],
        ],
    ])->assertCreated();

    $commissions = collect($response->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($commissions['COMMERCIAL']['calculated_amount'])->toBe('60.00');
});

// ---------------------------------------------------------------------------
// AC-002 — a generic (unallocated) cost never reduces the base
// ---------------------------------------------------------------------------

it('AC-002: a generic (unallocated) 400 cost leaves the 1000 revenue line commission at 100.00', function () {
    marginNewStatus();
    $opportunity = Opportunity::factory()->create();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity->update(['commercial_id' => $commercial->id]);

    Sanctum::actingAs(marginTestActor(['create', 'view']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Margine provvigioni',
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $commercial->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
        'cost_lines' => [
            // No offer_line_index/offer_line_id: a generic cost (D-1).
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 400],
        ],
    ])->assertCreated();

    $commissions = collect($response->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($commissions['COMMERCIAL']['calculated_amount'])->toBe('100.00');
});

// ---------------------------------------------------------------------------
// AC-003 — a base clamped to zero zeroes PERCENTAGE, never FIXED_AMOUNT
// ---------------------------------------------------------------------------

it('AC-003: imputed costs exceeding revenue zero the PERCENTAGE commission but not a FIXED_AMOUNT override', function () {
    marginNewStatus();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    Sanctum::actingAs(marginTestActor(['create', 'update', 'view']));

    $created = $this->postJson('/api/quotes', [
        'title' => 'Margine negativo',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 1500, 'offer_line_index' => 0],
        ],
    ])->assertCreated();

    $commissions = collect($created->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($commissions['COMMERCIAL']['calculated_amount'])->toBe('0.00');

    $offerLineId = $created->json('data.offer_lines.0.id');
    $costLineId = $created->json('data.cost_lines.0.id');

    // A FIXED_AMOUNT manual override on the SAME over-cost line must stay
    // untouched by the clamp (AC-003, D-1: FIXED_AMOUNT never reads the base).
    $updated = $this->patchJson("/api/quotes/{$created->json('data.id')}", [
        'offer_lines' => [[
            'id' => $offerLineId,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'commissions' => [[
                'recipient_role' => 'COMMERCIAL',
                'recipient_type' => 'referent',
                'recipient_id' => $commercial->id,
                'commission_type' => 'FIXED_AMOUNT',
                'value' => 50,
                'origin' => 'MANUAL_OVERRIDE',
            ]],
        ]],
        'cost_lines' => [[
            'id' => $costLineId,
            'product_id' => $costProduct->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'offer_line_id' => $offerLineId,
        ]],
    ])->assertOk();

    $overrides = collect($updated->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($overrides['COMMERCIAL']['calculated_amount'])->toBe('50.00')
        ->and($overrides['COMMERCIAL']['origin'])->toBe('MANUAL_OVERRIDE');
});

// ---------------------------------------------------------------------------
// AC-004 — a cost_lines-only PATCH recalculates the REVENUE commission
// ---------------------------------------------------------------------------

it('AC-004: a cost_lines-only PATCH recalculates the commission and margin_net of the untouched REVENUE line', function () {
    marginNewStatus();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    Sanctum::actingAs(marginTestActor(['create', 'update', 'view']));

    $created = $this->postJson('/api/quotes', [
        'title' => 'Solo costi',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
    ])->assertCreated();

    $commissions = collect($created->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($commissions['COMMERCIAL']['calculated_amount'])->toBe('100.00')
        // revenue 1000, no cost yet, commission 10% of 1000 = 100 -> margin 900.
        ->and($created->json('data.summary.margin.net'))->toBe('900.00');

    $offerLineId = $created->json('data.offer_lines.0.id');
    $quoteId = $created->json('data.id');

    $updated = $this->patchJson("/api/quotes/{$quoteId}", [
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 400, 'offer_line_id' => $offerLineId],
        ],
    ])->assertOk();

    $recalculated = collect($updated->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($recalculated['COMMERCIAL']['calculated_amount'])->toBe('60.00')
        // revenue 1000 - cost 400 - commission 60 = 540.
        ->and($updated->json('data.summary.margin.net'))->toBe('540.00');
});

// ---------------------------------------------------------------------------
// AC-005 — margin_net coincides across detail, table and ContractResource
// ---------------------------------------------------------------------------

it('AC-005: margin_net is revenue_net - cost_net - total commissions, identical in detail, table and contract', function () {
    marginNewStatus();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    // Every permission this test needs is granted BEFORE the first
    // Sanctum::actingAs() call: that helper switches the app's default auth
    // guard (shouldUse('sanctum')), and creating permissions afterwards
    // makes spatie/laravel-permission guess a different guard for them than
    // the 'web' one an actor's own role/permission rows resolve against.
    $actor = marginTestActor(['create', 'view']);
    foreach (['viewAny'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $actor->givePermissionTo('quotes.viewAny');
    foreach (['viewAny', 'view'] as $ability) {
        Permission::findOrCreate("contracts.{$ability}");
    }
    $actor->givePermissionTo(['contracts.viewAny', 'contracts.view']);

    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Coerenza margine',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 400, 'offer_line_index' => 0],
        ],
    ])->assertCreated();

    // revenue 1000 - cost 400 - commission 60 (10% of 600) = 540.
    $quoteId = $created->json('data.id');
    expect($created->json('data.summary.margin.net'))->toBe('540.00');

    $quote = Quote::find($quoteId);
    expect($quote->margin_net)->toBe('540.00');

    $rows = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($rows->json('items'))->firstWhere('id', $quoteId);
    expect($row['margin_net'])->toBe('540.00');

    $contract = Contract::factory()->for($quote)->create();
    $this->getJson("/api/contracts/{$contract->id}")
        ->assertOk()
        ->assertJsonPath('data.quote.margin_net', '540.00');
});

// ---------------------------------------------------------------------------
// AC-006 — margin.net stays visible without the commissions permission
// ---------------------------------------------------------------------------

it('AC-006: a user without commission visibility still sees the net-of-commission margin.net', function () {
    marginNewStatus();
    $product = marginRevenueProduct();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    // Both actors' role/permission rows are set up BEFORE any Sanctum::
    // actingAs() call: switching the app's default auth guard mid-test
    // (Sanctum::actingAs()'s own shouldUse('sanctum')) would otherwise make
    // spatie/laravel-permission guess a DIFFERENT default guard for
    // Role::create()/assignRole() than the 'web' one `marginTestActor()`
    // already created its permissions under.
    $creator = marginTestActor(['create', 'view']);

    foreach (['view', 'update', 'viewActivity'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $role = Role::create(['name' => fake()->unique()->slug()]);
    $role->givePermissionTo(['quotes.view', 'quotes.update', 'quotes.viewActivity']);
    $role->fieldPermissions()->create([
        'resource' => 'quotes',
        'field' => 'commission_value',
        'visible' => false,
        'editable' => false,
        'required' => false,
    ]);
    $restrictedActor = User::factory()->create();
    $restrictedActor->assignRole($role);

    Sanctum::actingAs($creator);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Margine senza permesso',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    // revenue 1000, no cost, commission 10% of 1000 = 100 -> margin 900.
    expect($created->json('data.summary.margin.net'))->toBe('900.00');

    Sanctum::actingAs($restrictedActor);

    $this->getJson("/api/quotes/{$quoteId}")
        ->assertOk()
        ->assertJsonPath('data.summary.margin.net', '900.00')
        ->assertJsonMissingPath('data.summary.commissions');
});

// ---------------------------------------------------------------------------
// AC-007 — override and default share the base; a dropped association
// reverts the commission to the plain net
// ---------------------------------------------------------------------------

it('AC-007: dropping a revenue line\'s association reverts its commission to the plain net', function () {
    marginNewStatus();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    Sanctum::actingAs(marginTestActor(['create', 'update', 'view']));

    $created = $this->postJson('/api/quotes', [
        'title' => 'Rimozione associazione',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 400, 'offer_line_index' => 0],
        ],
    ])->assertCreated();

    $commissions = collect($created->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($commissions['COMMERCIAL']['calculated_amount'])->toBe('60.00');

    $quoteId = $created->json('data.id');
    $offerLineId = $created->json('data.offer_lines.0.id');
    $costLineId = $created->json('data.cost_lines.0.id');

    // Full-replace `offer_lines` WITHOUT the associated row: it is deleted,
    // its cost's `offer_line_id` reverts to NULL (nullOnDelete, spec 0144
    // D-3), and re-adding the SAME product as a brand-new row must see the
    // cost as generic again — its commission is back on the plain net.
    $updated = $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
    ])->assertOk();

    expect(QuoteLine::find($costLineId)?->offer_line_id)->toBeNull();

    $recreated = collect($updated->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($updated->json('data.offer_lines.0.id'))->not->toBe($offerLineId)
        ->and($recreated['COMMERCIAL']['calculated_amount'])->toBe('100.00');
});

// ---------------------------------------------------------------------------
// AC-008 — Gestione Richieste persists a REVENUE line whose commission
// reflects the imputed cost (D-1) even though `commissions` never travels
// on that channel.
// ---------------------------------------------------------------------------

it('AC-008: Gestione Richieste recalculates the REVENUE commission on the margin base after a cost association', function () {
    marginNewStatus();
    $product = marginRevenueProduct();
    $costProduct = Product::factory()->create();
    marginPercentageRule($product);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);
    $quote = Quote::factory()->for($opportunity)->create(['commercial_id' => $commercial->id]);

    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $operator = User::factory()->create();
    $opportunity->managers()->sync([$operator->id => ['position' => 2]]);
    $operator->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.create', 'request-management.viewAll']);
    // Both actors' permissions are resolved BEFORE either Sanctum::actingAs()
    // call: switching the app's default auth guard mid-test would otherwise
    // make spatie/laravel-permission guess a different guard for whichever
    // is created afterwards (see the AC-006 test above for the same pitfall).
    $offerteActor = marginTestActor(['update', 'view']);

    Sanctum::actingAs($operator);

    // Step 1: the panel creates the REVENUE line (no `commissions` key —
    // prohibited on this channel, ValidatesQuoteLineCommissions).
    $this->patchJson("/api/request-management/{$quote->id}", [
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
    ])->assertOk();

    $offerLineId = Quote::find($quote->id)->offerLines()->first()->id;
    // Read straight off the model (the operator carries no `quotes.view`):
    // this channel's own authorization is a concern of a different test.
    $noCostCommissions = QuoteLine::find($offerLineId)->commissions()->get()->keyBy(
        fn ($commission) => $commission->recipient_role->value
    );
    expect($noCostCommissions['COMMERCIAL']->calculated_amount)->toBe('100.00');

    // Step 2: a cost line is imputed to it from the Offerte channel (this
    // module owns no `cost_lines` endpoint) — the SAME Quote, another write
    // path, still converging on QuoteService::update() (D-6).
    Sanctum::actingAs($offerteActor);
    $this->patchJson("/api/quotes/{$quote->id}", [
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 400, 'offer_line_id' => $offerLineId],
        ],
    ])->assertOk();

    $withCost = collect(
        $this->getJson("/api/quotes/{$quote->id}")->json('data.offer_lines.0.commissions')
    )->keyBy('recipient_role');
    expect($withCost['COMMERCIAL']['calculated_amount'])->toBe('60.00');
});
