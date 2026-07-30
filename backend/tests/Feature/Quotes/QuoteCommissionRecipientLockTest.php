<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A commission recipient is never free input: each role may only be awarded to
 * the identity picked upstream (the quote's commercial/reporter/supervisor,
 * the line product's supplier), and a role with no upstream pick admits no
 * commission at all. The dialog locks the field client-side; these cover the
 * server-side half — the recipients endpoint the lock reads from, and the
 * write-side guard that makes it more than cosmetic.
 */
function recipientLockActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $actor = User::factory()->create();
    foreach ($abilities as $ability) {
        $actor->givePermissionTo("quotes.{$ability}");
    }

    return $actor;
}

function recipientLockProduct(?int $supplierId = null): Product
{
    return Product::factory()->create([
        'category_id' => ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ])->id,
        'supplier_id' => $supplierId,
    ]);
}

it('exposes the one admissible recipient per role, null where nothing was picked upstream', function () {
    $commercial = Referent::factory()->create();
    $supervisor = User::factory()->create();
    $supplier = Registry::factory()->create();
    $product = recipientLockProduct($supplier->id);

    Sanctum::actingAs(recipientLockActor(['create']));

    $response = $this->postJson('/api/quotes/commission-recipients', [
        'product_id' => $product->id,
        'commercial_id' => $commercial->id,
        'reporter_id' => null,
        'supervisor_id' => $supervisor->id,
    ])->assertOk();

    expect($response->json('data.COMMERCIAL'))
        ->toBe(['type' => 'referent', 'id' => $commercial->id, 'name' => $commercial->name])
        ->and($response->json('data.SUPERVISOR.id'))->toBe($supervisor->id)
        ->and($response->json('data.SUPPLIER'))
        ->toBe(['type' => 'registry', 'id' => $supplier->id, 'name' => $supplier->name])
        ->and($response->json('data.REPORTER'))->toBeNull();
});

it('resolves the recipients of an existing quote and lets the submitted roles win over the persisted ones', function () {
    $persisted = Referent::factory()->create();
    $replacement = Referent::factory()->create();
    $product = recipientLockProduct();
    $quote = Quote::factory()->create(['commercial_id' => $persisted->id]);

    Sanctum::actingAs(recipientLockActor(['update']));

    $this->postJson('/api/quotes/commission-recipients', [
        'quote_id' => $quote->id,
        'product_id' => $product->id,
    ])->assertOk()->assertJsonPath('data.COMMERCIAL.id', $persisted->id);

    $this->postJson('/api/quotes/commission-recipients', [
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'commercial_id' => $replacement->id,
    ])->assertOk()->assertJsonPath('data.COMMERCIAL.id', $replacement->id);
});

it('denies the recipients endpoint to an actor who cannot write the quote', function () {
    $product = recipientLockProduct();

    Sanctum::actingAs(recipientLockActor(['view']));

    $this->postJson('/api/quotes/commission-recipients', [
        'product_id' => $product->id,
    ])->assertForbidden();
});

it('rejects a commission paid to anyone other than the role holder picked upstream', function () {
    $commercial = Referent::factory()->create();
    $stranger = Referent::factory()->create();
    $product = recipientLockProduct();
    $opportunity = Opportunity::factory()->create();

    Sanctum::actingAs(recipientLockActor(['create', 'view']));

    $this->postJson('/api/quotes', [
        'title' => 'Locked recipient quote',
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $commercial->id,
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'commissions' => [[
                'recipient_role' => 'COMMERCIAL',
                'recipient_type' => 'referent',
                'recipient_id' => $stranger->id,
                'commission_type' => 'PERCENTAGE',
                'value' => 10,
                'origin' => 'MANUAL_OVERRIDE',
            ]],
        ]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['offer_lines.0.commissions.0.recipient_id']);

    expect(Quote::count())->toBe(0);
});

it('rejects a commission for a role with no recipient picked upstream', function () {
    $product = recipientLockProduct();
    $opportunity = Opportunity::factory()->create();

    Sanctum::actingAs(recipientLockActor(['create', 'view']));

    $this->postJson('/api/quotes', [
        'title' => 'Roleless quote',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'commissions' => [[
                'recipient_role' => 'SUPPLIER',
                'recipient_type' => 'registry',
                'recipient_id' => Registry::factory()->create()->id,
                'commission_type' => 'FIXED_AMOUNT',
                'value' => 10,
                'origin' => 'MANUAL_OVERRIDE',
            ]],
        ]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['offer_lines.0.commissions.0.recipient_role']);
});

it('validates an updated line against the roles submitted in the same request', function () {
    $persisted = Referent::factory()->create();
    $replacement = Referent::factory()->create();
    $product = recipientLockProduct();
    $quote = Quote::factory()->create(['commercial_id' => $persisted->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);

    Sanctum::actingAs(recipientLockActor(['update', 'view']));

    $payload = fn (int $recipientId): array => [
        'commercial_id' => $replacement->id,
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'commissions' => [[
                'recipient_role' => 'COMMERCIAL',
                'recipient_type' => 'referent',
                'recipient_id' => $recipientId,
                'commission_type' => 'PERCENTAGE',
                'value' => 10,
                'origin' => 'MANUAL_OVERRIDE',
            ]],
        ]],
    ];

    // The now-stale persisted commercial is no longer admissible...
    $this->patchJson("/api/quotes/{$quote->id}", $payload($persisted->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['offer_lines.0.commissions.0.recipient_id']);

    // ...while the one submitted alongside the line is.
    $this->patchJson("/api/quotes/{$quote->id}", $payload($replacement->id))->assertOk();

    expect($line->commissions()->sole()->recipient_id)->toBe($replacement->id);
});
