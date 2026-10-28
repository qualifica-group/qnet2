<?php

declare(strict_types=1);

use App\Enums\CommissionRecipientRole;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0320, D-5/D-7: every offer/cost row names the supplier among the
 * product's own (ProductSupplierRule), checked only on new or changed rows;
 * the Supplier commission goes to the ROW's supplier.
 */
uses(RefreshDatabase::class);

function lineSupplierActor(): User
{
    foreach (['view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.view', 'quotes.create', 'quotes.update', Permission::findOrCreate('quotes.viewAll', 'web')]);

    return $user;
}

function lineSupplierOpportunity(): Opportunity
{
    QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

    return Opportunity::factory()->create(['commercial_id' => Referent::factory()->create()->id]);
}

/**
 * A product with a DEFAULT supplier and a second one.
 *
 * @return array{0: Product, 1: Registry, 2: Registry}
 */
function lineSupplierProduct(?ProductTypology $typology = null): array
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $first = Registry::factory()->supplier()->create();
    $second = Registry::factory()->supplier()->create();
    $product = productWithSupplier(['category_id' => $category->id, 'product_typology_id' => ($typology ?? ProductTypology::factory()->create())->id], $first);
    ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $second->id]);

    return [$product, $first, $second];
}

function lineSupplierlessProduct(): Product
{
    return Product::factory()->create([
        'category_id' => ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id])->id,
    ]);
}

/** @return array<string, mixed> */
function lineSupplierPayload(Opportunity $opportunity, array $offer, array $cost = []): array
{
    return [
        'title' => 'Supplier quote',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['quantity' => 1, 'unit_price' => 100, ...$offer]],
        ...($cost === [] ? [] : ['cost_lines' => [['quantity' => 1, 'unit_price' => 10, ...$cost]]]),
    ];
}

// ---------------------------------------------------------------------------
// AC-006 — Offerte store/update, REVENUE and COST
// ---------------------------------------------------------------------------

it('AC-006: a product with suppliers requires one of them, a product without suppliers admits none', function (string $field) {
    $opportunity = lineSupplierOpportunity();
    [$product, $first, $second] = lineSupplierProduct();
    $foreign = Registry::factory()->supplier()->create();
    $plain = lineSupplierlessProduct();
    Sanctum::actingAs(lineSupplierActor());
    $send = function (array $row) use ($field, $opportunity) {
        $payload = $field === 'offer_lines'
            ? lineSupplierPayload($opportunity, $row)
            : lineSupplierPayload($opportunity, ['product_id' => lineSupplierlessProduct()->id], $row);

        return $this->postJson('/api/quotes', $payload);
    };

    $required = $send(['product_id' => $product->id, 'supplier_id' => null])->assertUnprocessable();
    expect($required->json('errors')["{$field}.0.supplier_id"])->toBe([__('quotes.supplier_required')]);

    $send(['product_id' => $product->id])->assertUnprocessable()->assertJsonValidationErrors("{$field}.0.supplier_id");

    $foreignError = $send(['product_id' => $product->id, 'supplier_id' => $foreign->id])->assertUnprocessable();
    expect($foreignError->json('errors')["{$field}.0.supplier_id"])->toBe([__('quotes.supplier_not_of_product')]);

    $plainError = $send(['product_id' => $plain->id, 'supplier_id' => $first->id])->assertUnprocessable();
    expect($plainError->json('errors')["{$field}.0.supplier_id"])->toBe([__('quotes.supplier_not_of_product')]);

    $send(['product_id' => $plain->id, 'supplier_id' => null])->assertCreated();

    $created = $send(['product_id' => $product->id, 'supplier_id' => $second->id])->assertCreated();
    expect($created->json("data.{$field}.0.supplier_id"))->toBe($second->id)
        ->and($created->json("data.{$field}.0.supplier"))->toBe(['id' => $second->id, 'name' => $second->name]);
})->with(['REVENUE' => 'offer_lines', 'COST' => 'cost_lines']);

it('AC-006: updating a row to another supplier of the product passes, to a foreign one is refused', function () {
    $opportunity = lineSupplierOpportunity();
    [$product, $first, $second] = lineSupplierProduct();
    Sanctum::actingAs(lineSupplierActor());
    $created = $this->postJson('/api/quotes', lineSupplierPayload($opportunity, ['product_id' => $product->id, 'supplier_id' => $first->id]))->assertCreated();
    $quoteId = $created->json('data.id');
    $lineId = $created->json('data.offer_lines.0.id');
    $row = fn (?int $supplierId): array => ['offer_lines' => [['id' => $lineId, 'product_id' => $product->id, 'supplier_id' => $supplierId, 'quantity' => 1, 'unit_price' => 100]]];

    $this->patchJson("/api/quotes/{$quoteId}", $row($second->id))->assertOk()->assertJsonPath('data.offer_lines.0.supplier_id', $second->id);
    $this->patchJson("/api/quotes/{$quoteId}", $row(Registry::factory()->supplier()->create()->id))
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.supplier_id');
    $this->patchJson("/api/quotes/{$quoteId}", $row(null))
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.supplier_id');
    expect(QuoteLine::findOrFail($lineId)->supplier_id)->toBe($second->id);
});

// ---------------------------------------------------------------------------
// AC-007 — a saved row stays valid when the product loses that supplier
// ---------------------------------------------------------------------------

it('AC-007: a saved row keeps its supplier after the product drops it, and an omitted key keeps it too', function () {
    $opportunity = lineSupplierOpportunity();
    [$product, $first, $second] = lineSupplierProduct();
    Sanctum::actingAs(lineSupplierActor());
    $created = $this->postJson('/api/quotes', lineSupplierPayload($opportunity, ['product_id' => $product->id, 'supplier_id' => $second->id]))->assertCreated();
    $quoteId = $created->json('data.id');
    $lineId = $created->json('data.offer_lines.0.id');
    ProductSupplier::where('product_id', $product->id)->where('supplier_id', $second->id)->delete();

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $lineId, 'product_id' => $product->id, 'supplier_id' => $second->id, 'quantity' => 2, 'unit_price' => 100]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_id', $second->id);

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [['id' => $lineId, 'product_id' => $product->id, 'quantity' => 3, 'unit_price' => 100]],
    ])->assertOk()->assertJsonPath('data.offer_lines.0.supplier_id', $second->id);
});

// ---------------------------------------------------------------------------
// AC-006 — Gestione Richieste (panel PATCH and inline cell)
// ---------------------------------------------------------------------------

it('AC-006: Gestione Richieste applies the same rule on the panel and on the offer_lines cell', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.viewAll', 'request-management.viewAny']);
    [$product, $first, $second] = lineSupplierProduct();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    OpportunityProductLine::factory()->for($opportunity)->create([
        'business_function_id' => $product->category->business_function_id,
        'product_category_id' => $product->category_id,
    ]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    Sanctum::actingAs($actor);
    $row = ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10];

    $this->patchJson("/api/request-management/{$quote->id}", ['offer_lines' => [$row]])
        ->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.supplier_id');
    $this->patchJson("/api/request-management/{$quote->id}", ['offer_lines' => [[...$row, 'supplier_id' => $second->id]]])
        ->assertOk()->assertJsonPath('data.offer_lines.0.supplier.id', $second->id);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", ['column' => 'offer_lines', 'value' => [$row]])
        ->assertUnprocessable()->assertJsonValidationErrors('value.0.supplier_id');
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [[...$row, 'supplier_id' => Registry::factory()->supplier()->create()->id]],
    ])->assertUnprocessable()->assertJsonValidationErrors('value.0.supplier_id');
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [[...$row, 'supplier_id' => $first->id]],
    ])->assertOk();
    expect($quote->offerLines()->sole()->supplier_id)->toBe($first->id);

    // The saved pair resubmitted untouched is not re-checked, even once the product drops it.
    ProductSupplier::where('product_id', $product->id)->where('supplier_id', $first->id)->delete();
    $savedId = $quote->offerLines()->sole()->id;
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'offer_lines',
        'value' => [['id' => $savedId, ...$row, 'supplier_id' => $first->id]],
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-008 — commissions follow the row's supplier
// ---------------------------------------------------------------------------

it('AC-008: the Supplier commission goes to the ROW supplier with that supplier\'s rule, and a foreign recipient is refused', function () {
    $opportunity = lineSupplierOpportunity();
    [$product, $default, $other] = lineSupplierProduct(ProductTypology::factory()->supplierCommission()->create());
    CommissionConfiguration::factory()->forRecipient('registry', $default->id)->create(['recipient_role' => CommissionRecipientRole::Supplier, 'value' => 5]);
    CommissionConfiguration::factory()->forRecipient('registry', $other->id)->create(['recipient_role' => CommissionRecipientRole::Supplier, 'value' => 7]);
    Sanctum::actingAs(lineSupplierActor());

    $created = $this->postJson('/api/quotes', lineSupplierPayload($opportunity, ['product_id' => $product->id, 'supplier_id' => $other->id]))->assertCreated();
    $commission = collect($created->json('data.offer_lines.0.commissions'))->firstWhere('recipient_role', 'SUPPLIER');
    expect($commission['recipient_id'])->toBe($other->id)
        ->and((float) $commission['value'])->toBe(7.0);

    $this->postJson('/api/quotes/commission-defaults', ['product_id' => $product->id, 'supplier_id' => $other->id, 'line_net_amount' => 100])
        ->assertOk()->assertJsonPath('data.commissions.0.recipient_id', $other->id);
    $this->postJson('/api/quotes/commission-recipients', ['product_id' => $product->id, 'supplier_id' => $other->id])
        ->assertOk()->assertJsonPath('data.SUPPLIER.id', $other->id);
    $this->postJson('/api/quotes/commission-recipients', ['product_id' => $product->id])
        ->assertOk()->assertJsonPath('data.SUPPLIER', null);

    $this->postJson('/api/quotes', lineSupplierPayload($opportunity, [
        'product_id' => $product->id,
        'supplier_id' => $other->id,
        'commissions' => [[
            'recipient_role' => 'SUPPLIER', 'recipient_type' => 'registry', 'recipient_id' => $default->id,
            'commission_type' => 'PERCENTAGE', 'value' => 5, 'origin' => 'MANUAL_OVERRIDE', 'supplier_commission_direction' => 'PAID',
        ]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.commissions.0.recipient_id');
});

it('AC-008: changing the row supplier regenerates the Supplier commission for the new supplier', function () {
    $opportunity = lineSupplierOpportunity();
    [$product, $default, $other] = lineSupplierProduct(ProductTypology::factory()->supplierCommission()->create());
    CommissionConfiguration::factory()->forRecipient('registry', $default->id)->create(['recipient_role' => CommissionRecipientRole::Supplier, 'value' => 5]);
    CommissionConfiguration::factory()->forRecipient('registry', $other->id)->create(['recipient_role' => CommissionRecipientRole::Supplier, 'value' => 7]);
    Sanctum::actingAs(lineSupplierActor());
    $created = $this->postJson('/api/quotes', lineSupplierPayload($opportunity, ['product_id' => $product->id, 'supplier_id' => $default->id]))->assertCreated();

    $updated = $this->patchJson('/api/quotes/'.$created->json('data.id'), [
        'offer_lines' => [['id' => $created->json('data.offer_lines.0.id'), 'product_id' => $product->id, 'supplier_id' => $other->id, 'quantity' => 1, 'unit_price' => 100]],
    ])->assertOk();

    $commission = collect($updated->json('data.offer_lines.0.commissions'))->firstWhere('recipient_role', 'SUPPLIER');
    expect($commission['recipient_id'])->toBe($other->id)->and((float) $commission['value'])->toBe(7.0);
});

it('AC-008: the 0308 recalculation realigns the Supplier commission to the supplier of the SAVED row', function () {
    $product = recalcProduct(supplierEnabled: true);
    $other = Registry::factory()->supplier()->create();
    ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $other->id]);
    CommissionConfiguration::factory()->forRecipient('registry', $other->id)->create(['recipient_role' => CommissionRecipientRole::Supplier, 'value' => 7]);
    [$quote, $line] = recalcQuote($product);
    $line->update(['supplier_id' => $other->id]);
    Sanctum::actingAs(recalcActor(['quotes.view', 'quotes.update']));

    $this->postJson("/api/quotes/{$quote->id}/recalculate-commissions", ['mode' => 'KEEP_MANUAL'])->assertOk();

    $supplier = $line->commissions()->where('recipient_role', CommissionRecipientRole::Supplier)->sole();
    expect($supplier->recipient_id)->toBe($other->id)->and((float) $supplier->value)->toBe(7.0);
});
