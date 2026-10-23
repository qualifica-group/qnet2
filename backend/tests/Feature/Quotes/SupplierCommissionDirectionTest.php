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
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Supplier commission direction (spec 0202, REQUIREMENT CHANGED by spec 0306):
 * the typology keeps only the on/off switch, the direction comes from the
 * Supplier rule (or from a manual commission) and is frozen on the REVENUE
 * line; the D-8 margin of spec 0202 is unchanged.
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

/** A sellable product with a supplier, of a typology with the Supplier commission switch on/off. */
function supplierDirectionProduct(bool $enabled = true, ?ProductTypology $typology = null): Product
{
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $typology ??= $enabled ? ProductTypology::factory()->supplierCommission()->create() : ProductTypology::factory()->create();

    return Product::factory()->create([
        'category_id' => $category->id,
        'supplier_id' => Registry::factory()->create()->id,
        'product_typology_id' => $typology->id,
    ]);
}

function supplierDirectionRule(Product $product, CommissionRecipientRole $role, CommissionType $type, float $value, SupplierCommissionDirection $direction = SupplierCommissionDirection::Paid): CommissionConfiguration
{
    return CommissionConfiguration::factory()->create([
        'recipient_role' => $role,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'commission_type' => $type,
        'supplier_commission_direction' => $role === CommissionRecipientRole::Supplier ? $direction : null,
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
// AC-006 — snapshot written on creation and on product change
// ---------------------------------------------------------------------------

it('AC-006: a new REVENUE line freezes the direction of the Supplier rule, PAID without a rule, null with the switch off', function () {
    $opportunity = supplierDirectionOpportunity();
    $received = supplierDirectionProduct();
    $paid = supplierDirectionProduct();
    $noRule = supplierDirectionProduct();
    $disabled = supplierDirectionProduct(false);
    supplierDirectionRule($received, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    supplierDirectionRule($paid, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Paid);
    supplierDirectionRule($disabled, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    $cost = Product::factory()->create(['product_typology_id' => ProductTypology::factory()->supplierCommission()->create()->id]);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $response = $this->postJson('/api/quotes', [
        'title' => 'Snapshot',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $received->id, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $paid->id, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $noRule->id, 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $disabled->id, 'quantity' => 1, 'unit_price' => 100],
        ],
        'cost_lines' => [['product_id' => $cost->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated();

    $lines = collect($response->json('data.offer_lines'));
    expect($lines->pluck('supplier_commission_direction')->all())->toBe(['RECEIVED', 'PAID', 'PAID', null])
        ->and($lines->map(fn (array $line): array => collect($line['commissions'])->pluck('recipient_role')->all())->all())
        ->toBe([['SUPPLIER'], ['SUPPLIER'], [], []])
        ->and($response->json('data.cost_lines.0.supplier_commission_direction'))->toBeNull();
});

it('AC-006: changing the typology later does not touch an existing line, changing its product rewrites the snapshot', function () {
    $opportunity = supplierDirectionOpportunity();
    $typology = ProductTypology::factory()->supplierCommission()->create();
    $product = supplierDirectionProduct(true, $typology);
    $other = supplierDirectionProduct();
    supplierDirectionRule($other, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $created = $this->postJson('/api/quotes', [
        'title' => 'Freeze',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->assertCreated();
    $quoteId = $created->json('data.id');
    $lineId = $created->json('data.offer_lines.0.id');
    expect($created->json('data.offer_lines.0.supplier_commission_direction'))->toBe('PAID');

    $typology->update(['supplier_commission_enabled' => false]);

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $lineId, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'PAID');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $lineId, 'product_id' => $other->id, 'quantity' => 2, 'unit_price' => 100]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'RECEIVED');
});

it('AC-006: the legacy line duplicator carries the snapshot onto the copy', function () {
    $line = QuoteLine::factory()->create([
        'product_id' => supplierDirectionProduct()->id,
        'supplier_commission_direction' => SupplierCommissionDirection::Received,
    ]);

    $copy = app(QuoteLineDuplicator::class)->duplicate($line->load('commissions', 'quote'));

    expect($copy->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Received);
});

// ---------------------------------------------------------------------------
// AC-007 — a saved snapshot follows the saved commission, not the rule
// ---------------------------------------------------------------------------

it('AC-007: a later rule change leaves the saved RECEIVED line alone until the commissions are regenerated', function () {
    $opportunity = supplierDirectionOpportunity();
    $product = supplierDirectionProduct();
    $rule = supplierDirectionRule($product, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $created = $this->postJson('/api/quotes', [
        'title' => 'Rule drift',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
    ])->assertCreated()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'RECEIVED');
    $quoteId = $created->json('data.id');
    $line = $created->json('data.offer_lines.0');
    $rule->update(['supplier_commission_direction' => SupplierCommissionDirection::Paid]);

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $line['id'], 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]],
    ])->assertOk()
        ->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'RECEIVED')
        ->assertJsonPath('data.offer_lines.0.commissions.0.calculated_amount', '100.00');

    $supplier = $line['commissions'][0];
    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [[
            'id' => $line['id'], 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000,
            'commissions' => [[
                'recipient_role' => 'SUPPLIER', 'recipient_type' => $supplier['recipient_type'], 'recipient_id' => $supplier['recipient_id'],
                'commission_type' => 'PERCENTAGE', 'value' => 10, 'origin' => 'PRODUCT',
            ]],
        ]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'PAID');
});

// ---------------------------------------------------------------------------
// AC-009 — commission-defaults
// ---------------------------------------------------------------------------

it('AC-009: commission-defaults returns the direction of the resolved Supplier rule, PAID without one, null with the switch off', function () {
    $received = supplierDirectionProduct();
    $noRule = supplierDirectionProduct();
    $disabled = supplierDirectionProduct(false);
    supplierDirectionRule($received, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    supplierDirectionRule($disabled, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $this->postJson('/api/quotes/commission-defaults', ['product_id' => $received->id, 'line_net_amount' => 100])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', 'RECEIVED')
        ->assertJsonPath('data.commissions.0.recipient_role', 'SUPPLIER')
        ->assertJsonPath('data.commissions.0.calculated_amount', '10.00');

    $this->postJson('/api/quotes/commission-defaults', ['product_id' => $noRule->id, 'line_net_amount' => 100])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', 'PAID')
        ->assertJsonPath('data.commissions', []);

    $this->postJson('/api/quotes/commission-defaults', ['product_id' => $disabled->id, 'line_net_amount' => 100])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_direction', null)
        ->assertJsonPath('data.commissions', []);
});

// ---------------------------------------------------------------------------
// Switch off — a null snapshot never creates a Supplier commission
// ---------------------------------------------------------------------------

it('AC-005: with the switch off the Supplier commission is not created even with an active rule, the others are', function () {
    $opportunity = supplierDirectionOpportunity();
    $product = supplierDirectionProduct(false);
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
// Margin D-8 (spec 0202), now driven by the rule direction
// ---------------------------------------------------------------------------

it('margin_net follows D-8 (RECEIVED = s - c - p), revenue unchanged', function () {
    $opportunity = supplierDirectionOpportunity();
    $received = supplierDirectionProduct();
    $paid = supplierDirectionProduct();
    $cost = Product::factory()->create();
    supplierDirectionRule($received, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    supplierDirectionRule($received, CommissionRecipientRole::Commercial, CommissionType::FixedAmount, 50);
    supplierDirectionRule($paid, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Paid);
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
// AC-008 — manual Supplier commission
// ---------------------------------------------------------------------------

function supplierManualCommission(Product $product, array $overrides = []): array
{
    return [
        'recipient_role' => 'SUPPLIER',
        'recipient_type' => 'registry',
        'recipient_id' => $product->supplier_id,
        'commission_type' => 'PERCENTAGE',
        'value' => 10,
        'origin' => 'MANUAL_OVERRIDE',
        ...$overrides,
    ];
}

it('AC-008: a manual Supplier commission carries its own direction, the snapshot and the margin follow it', function () {
    $opportunity = supplierDirectionOpportunity();
    $product = supplierDirectionProduct();
    Sanctum::actingAs(supplierDirectionQuoteActor());

    $response = $this->postJson('/api/quotes', [
        'title' => 'Manuale',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [[
            'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000,
            'commissions' => [supplierManualCommission($product, ['supplier_commission_direction' => 'RECEIVED'])],
        ]],
    ])->assertCreated();

    expect($response->json('data.offer_lines.0.supplier_commission_direction'))->toBe('RECEIVED')
        ->and($response->json('data.offer_lines.0.commissions.0.origin'))->toBe('MANUAL_OVERRIDE')
        ->and($response->json('data.summary.margin.net'))->toBe('100.00');
});

it('AC-008: a manual Supplier commission without or with an invalid direction is a 422, an automatic one ignores it', function () {
    $opportunity = supplierDirectionOpportunity();
    $product = supplierDirectionProduct();
    supplierDirectionRule($product, CommissionRecipientRole::Supplier, CommissionType::Percentage, 10, SupplierCommissionDirection::Received);
    Sanctum::actingAs(supplierDirectionQuoteActor());
    $payload = fn (array $commission): array => [
        'title' => 'Verso',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'commissions' => [$commission]]],
    ];

    $this->postJson('/api/quotes', $payload(supplierManualCommission($product)))
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.commissions.0.supplier_commission_direction');
    $this->postJson('/api/quotes', $payload(supplierManualCommission($product, ['supplier_commission_direction' => 'BOTH'])))
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.commissions.0.supplier_commission_direction');

    $this->postJson('/api/quotes', $payload(supplierManualCommission($product, ['origin' => 'PRODUCT', 'supplier_commission_direction' => 'PAID'])))
        ->assertCreated()->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'RECEIVED');
});

it('AC-008: without the commission_type permission the direction cannot change, resending it unchanged is fine', function () {
    Permission::findOrCreate('quotes.view');
    Permission::findOrCreate('quotes.update');
    $role = Role::create(['name' => 'commission-type-locked']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create(['resource' => 'quotes', 'field' => 'commission_type', 'visible' => true, 'editable' => false, 'required' => false]);
    $actor = User::factory()->create();
    $actor->assignRole($role);

    $product = supplierDirectionProduct();
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'net_amount' => 100, 'total_amount' => 100,
        'supplier_commission_direction' => SupplierCommissionDirection::Received,
    ]);
    $commission = $line->commissions()->create([
        'recipient_role' => CommissionRecipientRole::Supplier, 'recipient_type' => 'registry', 'recipient_id' => $product->supplier_id,
        'commission_type' => 'PERCENTAGE', 'value' => 10, 'calculated_amount' => 10, 'origin' => 'MANUAL_OVERRIDE',
    ]);
    Sanctum::actingAs($actor);
    $payload = fn (string $direction): array => ['offer_lines' => [[
        'id' => $line->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100,
        'commissions' => [supplierManualCommission($product, ['id' => $commission->id, 'supplier_commission_direction' => $direction])],
    ]]];

    $this->patchJson("/api/quotes/{$quote->id}", $payload('RECEIVED'))->assertOk()
        ->assertJsonPath('data.offer_lines.0.supplier_commission_direction', 'RECEIVED');
    $this->patchJson("/api/quotes/{$quote->id}", $payload('PAID'))
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.commissions.0.supplier_commission_direction');
});

// ---------------------------------------------------------------------------
// AC-004 — migrations
// ---------------------------------------------------------------------------

it('AC-004: the migrations backfill PAID on the Supplier rules only, touch no snapshot nor amount, and are reversible', function () {
    $ruleMigration = require database_path('migrations/2026_11_01_100000_add_supplier_commission_direction_to_commission_configurations_table.php');
    $typologyMigration = require database_path('migrations/2026_11_01_100100_drop_supplier_commission_direction_from_product_typologies_table.php');
    $enabled = ProductTypology::factory()->supplierCommission()->create();
    $disabled = ProductTypology::factory()->create();
    $product = Product::factory()->create();
    $supplierRule = CommissionConfiguration::factory()->create(['recipient_role' => CommissionRecipientRole::Supplier, 'product_id' => $product->id, 'supplier_commission_direction' => SupplierCommissionDirection::Received]);
    $commercialRule = CommissionConfiguration::factory()->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => $product->id]);
    $quote = Quote::factory()->create(['margin_net' => 123.45]);
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id, 'supplier_commission_direction' => SupplierCommissionDirection::Received]);
    $commission = QuoteLineCommission::factory()->create(['quote_line_id' => $line->id, 'calculated_amount' => 77.7]);

    $typologyMigration->down();
    $ruleMigration->down();

    expect(Schema::hasColumn('commission_configurations', 'supplier_commission_direction'))->toBeFalse()
        ->and(Schema::hasColumn('product_typologies', 'supplier_commission_direction'))->toBeTrue();

    $ruleMigration->up();
    $typologyMigration->up();

    expect(Schema::hasColumn('product_typologies', 'supplier_commission_direction'))->toBeFalse()
        ->and(DB::table('commission_configurations')->where('id', $supplierRule->id)->value('supplier_commission_direction'))->toBe('PAID')
        ->and(DB::table('commission_configurations')->where('id', $commercialRule->id)->value('supplier_commission_direction'))->toBeNull()
        ->and($line->fresh()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Received)
        ->and($quote->fresh()->margin_net)->toBe('123.45')
        ->and($commission->fresh()->calculated_amount)->toBe('77.70');

    $typologyMigration->down();

    expect(DB::table('product_typologies')->where('id', $enabled->id)->value('supplier_commission_direction'))->toBe('PAID')
        ->and(DB::table('product_typologies')->where('id', $disabled->id)->value('supplier_commission_direction'))->toBeNull();

    $typologyMigration->up();
});

it('AC-004: the full history is reversible', function () {
    // REQUIREMENT CHANGED (spec 0306): +2 migrations (rule direction column, typology direction drop).
    // REQUIREMENT CHANGED (spec 0307): +2 migrations (stock balances and movements).
    expect(Artisan::call('migrate:rollback', ['--step' => 106]))->toBe(0);
    expect(Schema::hasColumn('quote_lines', 'supplier_commission_direction'))->toBeFalse()
        ->and(Schema::hasColumn('product_typologies', 'supplier_commission_enabled'))->toBeFalse()
        ->and(Schema::hasColumn('commission_configurations', 'supplier_commission_direction'))->toBeFalse();
});
