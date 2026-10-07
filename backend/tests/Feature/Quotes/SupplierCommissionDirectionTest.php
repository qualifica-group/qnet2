<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Migrations\Support\QuoteLineDuplicator;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Supplier commission direction by typology (spec 0202): the snapshot frozen
 * on the REVENUE line (AC-002/AC-003/AC-007), the D-6 gate (AC-004) and the
 * D-8 margin (AC-005).
 */
uses(RefreshDatabase::class);

function supplierDirectionQuoteActor(): User
{
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.view', 'quotes.create', 'quotes.update']);

    return $user;
}

/** A sellable product with a supplier, of a typology configured with $direction (null = switch off). */
function supplierDirectionProduct(?SupplierCommissionDirection $direction, ?ProductTypology $typology = null): Product
{
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $typology ??= $direction === null
        ? ProductTypology::factory()->create()
        : ProductTypology::factory()->supplierCommission($direction)->create();

    return Product::factory()->create([
        'category_id' => $category->id,
        'supplier_id' => Registry::factory()->create()->id,
        'product_typology_id' => $typology->id,
    ]);
}

function supplierDirectionRule(Product $product, CommissionRecipientRole $role, CommissionType $type, float $value): void
{
    CommissionConfiguration::factory()->create([
        'recipient_role' => $role,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'commission_type' => $type,
        'value' => $value,
        'valid_from' => '2026-01-01',
    ]);
}

function supplierDirectionOpportunity(): Opportunity
{
    QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

    return Opportunity::factory()->create(['commercial_id' => Referent::factory()->create()->id]);
}

// ---------------------------------------------------------------------------
// AC-003 / AC-007 — the snapshot is written on creation and on product change
// ---------------------------------------------------------------------------

it('AC-003/AC-007: a new REVENUE line freezes the typology direction, a COST line never has one', function () {
    $opportunity = supplierDirectionOpportunity();
    $received = supplierDirectionProduct(SupplierCommissionDirection::Received);
    $paid = supplierDirectionProduct(SupplierCommissionDirection::Paid);
    $disabled = supplierDirectionProduct(null);
    $cost = Product::factory()->create(['product_typology_id' => ProductTypology::factory()->supplierCommission()->create()->id]);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $response = $this->postJson('/api/quotes', [
        'title' => 'Snapshot',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $received->id, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $paid->id, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $disabled->id, 'quantity' => 1, 'unit_price' => 100],
        ],
        'cost_lines' => [['product_id' => $cost->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated();

    expect(collect($response->json('data.offer_lines'))->pluck('supplier_commission_direction')->all())->toBe(['RECEIVED', 'PAID', null])
        ->and($response->json('data.cost_lines.0.supplier_commission_direction'))->toBeNull();
});

it('AC-003: changing the typology later does not touch an existing line, changing its product rewrites the snapshot', function () {
    $opportunity = supplierDirectionOpportunity();
    $typology = ProductTypology::factory()->supplierCommission(SupplierCommissionDirection::Paid)->create();
    $product = supplierDirectionProduct(null, $typology);
    $other = supplierDirectionProduct(SupplierCommissionDirection::Received);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $created = $this->postJson('/api/quotes', [
        'title' => 'Freeze',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->assertCreated();
    $quoteId = $created->json('data.id');
    $lineId = $created->json('data.offer_lines.0.id');
    expect($created->json('data.offer_lines.0.supplier_commission_direction'))->toBe('PAID');

    $typology->update(['supplier_commission_enabled' => true, 'supplier_commission_direction' => SupplierCommissionDirection::Received]);

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $lineId, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'PAID');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $lineId, 'product_id' => $other->id, 'quantity' => 2, 'unit_price' => 100]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'RECEIVED');
});

it('AC-007: the legacy line duplicator carries the snapshot onto the copy', function () {
    $line = QuoteLine::factory()->create([
        'product_id' => supplierDirectionProduct(SupplierCommissionDirection::Received)->id,
        'supplier_commission_direction' => SupplierCommissionDirection::Received,
    ]);

    $copy = app(QuoteLineDuplicator::class)->duplicate($line->load('commissions', 'quote'));

    expect($copy->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Received);
});

it('AC-003: commission-defaults returns the direction of the requested product typology', function () {
    $received = supplierDirectionProduct(SupplierCommissionDirection::Received);
    $disabled = supplierDirectionProduct(null);
    supplierDirectionRule($received, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10);
    supplierDirectionRule($disabled, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $this->postJson('/api/quotes/commission-defaults', ['product_id' => $received->id, 'line_net_amount' => 100])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', 'RECEIVED')
        ->assertJsonPath('data.commissions.0.recipient_role', 'SUPPLIER')
        ->assertJsonPath('data.commissions.0.calculated_amount', '10.00');

    $this->postJson('/api/quotes/commission-defaults', ['product_id' => $disabled->id, 'line_net_amount' => 100])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', null)
        ->assertJsonPath('data.commissions', []);
});

// ---------------------------------------------------------------------------
// AC-004 — a null snapshot never creates a Supplier commission
// ---------------------------------------------------------------------------

it('AC-004: with the switch off the Supplier commission is not created even with an active rule, the others are', function () {
    $opportunity = supplierDirectionOpportunity();
    $product = supplierDirectionProduct(null);
    supplierDirectionRule($product, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10);
    supplierDirectionRule($product, CommissionRecipientRole::Commercial, CommissionType::FixedAmount, 50);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $response = $this->postJson('/api/quotes', [
        'title' => 'Disattivata',
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $opportunity->commercial_id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
    ])->assertCreated();

    $roles = collect($response->json('data.offer_lines.0.commissions'))->pluck('recipient_role')->all();
    expect($roles)->toBe(['COMMERCIAL'])
        ->and($response->json('data.summary.margin.net'))->toBe('950.00');
});

// ---------------------------------------------------------------------------
// AC-005 — D-8 margin
// ---------------------------------------------------------------------------

it('AC-005: margin_net follows D-8 (RECEIVED = s - c - p), revenue unchanged', function () {
    $opportunity = supplierDirectionOpportunity();
    $received = supplierDirectionProduct(SupplierCommissionDirection::Received);
    $paid = supplierDirectionProduct(SupplierCommissionDirection::Paid);
    $cost = Product::factory()->create();
    supplierDirectionRule($received, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10);
    supplierDirectionRule($received, CommissionRecipientRole::Commercial, CommissionType::FixedAmount, 50);
    supplierDirectionRule($paid, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $response = $this->postJson('/api/quotes', [
        'title' => 'Margine D-8',
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $opportunity->commercial_id,
        'offer_lines' => [
            ['product_id' => $received->id, 'quantity' => 1, 'unit_price' => 2000],
            ['product_id' => $paid->id, 'quantity' => 1, 'unit_price' => 1000],
        ],
        'cost_lines' => [
            ['product_id' => $cost->id, 'quantity' => 1, 'unit_price' => 100, 'offer_line_index' => 1],
            ['product_id' => $cost->id, 'quantity' => 1, 'unit_price' => 30],
        ],
    ])->assertCreated();

    $amounts = collect($response->json('data.offer_lines'))->map(
        fn (array $line): array => collect($line['commissions'])->pluck('calculated_amount', 'recipient_role')->all(),
    )->all();

    expect($amounts[0])->toBe(['COMMERCIAL' => '50.00', 'SUPPLIER' => '200.00'])
        ->and($amounts[1])->toBe(['SUPPLIER' => '90.00'])
        ->and($response->json('data.summary.margin.net'))->toBe('930.00')
        ->and($response->json('data.summary.revenue.net'))->toBe('3000.00');
});

// ---------------------------------------------------------------------------
// AC-002 — migration backfill
// ---------------------------------------------------------------------------

it('AC-002: the backfill maps institution to RECEIVED, the others to PAID, copies the snapshot and touches no amount', function () {
    $typologyMigration = require database_path('migrations/2026_10_07_110000_add_supplier_commission_to_product_typologies_table.php');
    $lineMigration = require database_path('migrations/2026_10_07_110100_add_supplier_commission_direction_to_quote_lines_table.php');
    $lineMigration->down();
    $typologyMigration->down();

    $institution = ProductTypology::where('code', 'institution')->sole();
    $other = ProductTypology::factory()->create();
    $quote = Quote::factory()->create(['margin_net' => 123.45]);
    $lines = [
        'institution' => QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => Product::factory()->create(['product_typology_id' => $institution->id])->id]),
        'other' => QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => Product::factory()->create(['product_typology_id' => $other->id])->id]),
        'cost' => QuoteLine::factory()->cost()->create(['quote_id' => $quote->id, 'product_id' => Product::factory()->create(['product_typology_id' => $other->id])->id]),
    ];
    $commission = QuoteLineCommission::factory()->create(['quote_line_id' => $lines['institution']->id, 'calculated_amount' => 77.7]);

    $typologyMigration->up();
    $lineMigration->up();

    expect(ProductTypology::find($institution->id))->supplier_commission_enabled->toBeTrue()->supplier_commission_direction->toBe(SupplierCommissionDirection::Received)
        ->and(ProductTypology::find($other->id))->supplier_commission_enabled->toBeTrue()->supplier_commission_direction->toBe(SupplierCommissionDirection::Paid)
        ->and($lines['institution']->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Received)
        ->and($lines['other']->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Paid)
        ->and($lines['cost']->fresh()->supplier_commission_direction)->toBeNull()
        ->and($quote->fresh()->margin_net)->toBe('123.45')
        ->and($commission->fresh()->calculated_amount)->toBe('77.70');
});

it('AC-002: the migrations are reversible', function () {
    // 3 steps: spec 0204's color migration is the latest one, on top of the two of spec 0202.
    expect(Artisan::call('migrate:rollback', ['--step' => 3]))->toBe(0);
    expect(Schema::hasColumn('quote_lines', 'supplier_commission_direction'))->toBeFalse()
        ->and(Schema::hasColumn('product_typologies', 'supplier_commission_enabled'))->toBeFalse();
});
