<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function quoteCommissionActor(array $abilities): User
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

it('initializes only recipient-backed revenue commissions and summarizes authoritative amounts', function () {
    QuoteStatus::where('system_key', 'new')->sole();
    $commercial = Referent::factory()->create();
    $supplier = Registry::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
    ]);
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    foreach ([CommissionRecipientRole::Commercial, CommissionRecipientRole::Reporter, CommissionRecipientRole::Supplier] as $role) {
        CommissionConfiguration::factory()->create([
            'recipient_role' => $role,
            'application_scope' => CommissionApplicationScope::Product,
            'product_id' => $product->id,
            'product_category_id' => null,
            'value' => $role === CommissionRecipientRole::Supplier ? 10 : 5,
            'valid_from' => '2026-01-01',
        ]);
    }

    Sanctum::actingAs(quoteCommissionActor(['create', 'view', 'update']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Commission quote',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]],
        'cost_lines' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]],
    ])->assertCreated();

    $commissions = collect($response->json('data.offer_lines.0.commissions'))->keyBy('recipient_role');
    expect($commissions)->toHaveCount(2)
        ->and($commissions->has('REPORTER'))->toBeFalse()
        ->and($commissions['COMMERCIAL']['calculated_amount'])->toBe('10.00')
        ->and($commissions['SUPPLIER']['calculated_amount'])->toBe('20.00')
        ->and($response->json('data.cost_lines.0.commissions'))->toBe([])
        ->and($response->json('data.summary.commissions'))->toBe([
            'commercial' => '10.00',
            'reporter' => '0.00',
            'supervisor' => '0.00',
            'supplier' => '20.00',
        ]);
});

it('recalculates manual overrides and rejects calculated amount input', function () {
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);
    $quote = Quote::factory()->create([
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $commercial->id,
    ]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 100,
        'net_amount' => 200,
        'total_amount' => 200,
    ]);

    Sanctum::actingAs(quoteCommissionActor(['update']));

    $payload = [
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'commissions' => [[
                'recipient_role' => 'COMMERCIAL',
                'recipient_type' => 'referent',
                'recipient_id' => $commercial->id,
                'commission_type' => 'PERCENTAGE',
                'value' => 10,
                'origin' => 'MANUAL_OVERRIDE',
            ]],
        ]],
    ];

    $this->patchJson("/api/quotes/{$quote->id}", array_replace_recursive($payload, [
        'offer_lines' => [[
            ...$payload['offer_lines'][0],
            'commissions' => [[...$payload['offer_lines'][0]['commissions'][0], 'calculated_amount' => 1]],
        ]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('offer_lines.0.commissions.0.calculated_amount');

    $this->patchJson("/api/quotes/{$quote->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.offer_lines.0.commissions.0.calculated_amount', '20.00')
        ->assertJsonPath('data.offer_lines.0.commissions.0.origin', 'MANUAL_OVERRIDE')
        ->assertJsonPath('data.summary.commissions.commercial', '20.00');
});

it('allows unchanged locked commission fields but rejects attempts to modify them', function () {
    Permission::findOrCreate('quotes.view');
    Permission::findOrCreate('quotes.update');
    $role = Role::create(['name' => 'commission-value-locked']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create([
        'resource' => 'quotes',
        'field' => 'commission_value',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $actor = User::factory()->create();
    $actor->assignRole($role);

    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['commercial_id' => $commercial->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);
    $commission = $line->commissions()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'recipient_type' => 'referent',
        'recipient_id' => $commercial->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'calculated_amount' => 5,
        'origin' => 'MANUAL_OVERRIDE',
    ]);
    Sanctum::actingAs($actor);

    $linePayload = [
        'id' => $line->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 100,
        'commissions' => [[
            'id' => $commission->id,
            'recipient_role' => 'COMMERCIAL',
            'recipient_type' => 'referent',
            'recipient_id' => $commercial->id,
            'commission_type' => 'PERCENTAGE',
            'value' => 5,
            'origin' => 'MANUAL_OVERRIDE',
        ]],
    ];

    $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [$linePayload]])
        ->assertOk()
        ->assertJsonPath('data.offer_lines.0.commissions.0.calculated_amount', '10.00');

    $linePayload['commissions'][0]['value'] = 6;
    $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [$linePayload]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('offer_lines.0.commissions.0.value');
});

it('repeats automatic resolution when the submitted commission collection is stale', function () {
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['commercial_id' => $commercial->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'value' => 5,
        'valid_from' => '2026-01-01',
    ]);

    Sanctum::actingAs(quoteCommissionActor(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [[
            'id' => $line->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'commissions' => [],
        ]],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.offer_lines.0.commissions')
        ->assertJsonPath('data.offer_lines.0.commissions.0.recipient_id', $commercial->id)
        ->assertJsonPath('data.offer_lines.0.commissions.0.calculated_amount', '5.00');
});

it('initializes a role when its recipient is assigned later and removes its automatic commission when absent', function () {
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['commercial_id' => null]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'valid_from' => '2026-01-01',
    ]);
    Sanctum::actingAs(quoteCommissionActor(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['commercial_id' => $commercial->id])
        ->assertOk()
        ->assertJsonCount(1, 'data.offer_lines.0.commissions');

    $this->patchJson("/api/quotes/{$quote->id}", ['commercial_id' => null])
        ->assertOk()
        ->assertJsonCount(0, 'data.offer_lines.0.commissions')
        ->assertJsonPath('data.summary.commissions.commercial', '0.00');

    expect($line->commissions()->count())->toBe(0);
});

it('moves an automatic commission to the current quote recipient without refreshing its rule snapshot', function () {
    $originalCommercial = Referent::factory()->create();
    $replacementCommercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['commercial_id' => $originalCommercial->id]);
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'net_amount' => 100,
        'total_amount' => 100,
    ]);
    $configuration = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'value' => 5,
        'valid_from' => '2026-01-01',
    ]);
    $commission = $line->commissions()->create([
        'commission_configuration_id' => $configuration->id,
        'recipient_role' => CommissionRecipientRole::Commercial,
        'recipient_type' => 'referent',
        'recipient_id' => $originalCommercial->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'calculated_amount' => 5,
        'origin' => 'PRODUCT',
    ]);

    // A later edit to the global rule must not change this persisted Quote.
    $configuration->update(['value' => 25]);

    Sanctum::actingAs(quoteCommissionActor(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", [
        'commercial_id' => $replacementCommercial->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.offer_lines.0.commissions.0.recipient_id', $replacementCommercial->id)
        ->assertJsonPath('data.offer_lines.0.commissions.0.value', '5.0000')
        ->assertJsonPath('data.offer_lines.0.commissions.0.calculated_amount', '5.00');

    expect($commission->fresh())
        ->recipient_id->toBe($replacementCommercial->id)
        ->value->toBe('5.0000');
});

it('rejects duplicate, foreign and wrong-line-type ids during quote-line reconciliation', function () {
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    $otherQuote = Quote::factory()->create();
    $revenueLine = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    $foreignLine = QuoteLine::factory()->create(['quote_id' => $otherQuote->id, 'product_id' => $product->id]);
    $costLine = QuoteLine::factory()->cost()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    Sanctum::actingAs(quoteCommissionActor(['update']));
    $row = fn (int $id): array => [
        'id' => $id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 10,
        'commissions' => [],
    ];

    $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [$row($revenueLine->id), $row($revenueLine->id)],
    ])->assertUnprocessable()->assertJsonValidationErrors('offer_lines');

    $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [$row($foreignLine->id)],
    ])->assertUnprocessable()->assertJsonValidationErrors('offer_lines');

    $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [$row($costLine->id)],
    ])->assertUnprocessable()->assertJsonValidationErrors('offer_lines');

    expect($revenueLine->fresh())->not->toBeNull()
        ->and($foreignLine->fresh())->not->toBeNull()
        ->and($costLine->fresh())->not->toBeNull();
});

it('updates retained quote lines in place while deleting omissions and creating new ids', function () {
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create();
    $retained = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    $omitted = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    Sanctum::actingAs(quoteCommissionActor(['update']));

    $response = $this->patchJson("/api/quotes/{$quote->id}", [
        'offer_lines' => [
            [
                'id' => $retained->id,
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_price' => 10,
                'commissions' => [],
            ],
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 20,
                'commissions' => [],
            ],
        ],
    ])->assertOk();

    $ids = collect($response->json('data.offer_lines'))->pluck('id')->all();
    expect($ids)->toContain($retained->id)
        ->not->toContain($omitted->id)
        ->and($retained->fresh()->quantity)->toBe('3.00')
        ->and($omitted->fresh())->toBeNull()
        ->and(QuoteLine::where('quote_id', $quote->id)->where('line_type', 'REVENUE')->count())->toBe(2);
});

it('initializes defaults in quote-create context and denies missing quote abilities', function () {
    $commercial = Referent::factory()->create();
    $product = Product::factory()->create();
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'product_category_id' => null,
        'value' => 7,
        'valid_from' => '2026-01-01',
    ]);

    Sanctum::actingAs(quoteCommissionActor(['create']));
    $this->postJson('/api/quotes/commission-defaults', [
        'product_id' => $product->id,
        'line_net_amount' => 100,
        'commercial_id' => $commercial->id,
        'reference_date' => '2026-07-29',
    ])->assertOk()
        ->assertJsonPath('data.0.recipient_role', 'COMMERCIAL')
        ->assertJsonPath('data.0.calculated_amount', '7.00');

    $quote = Quote::factory()->create();
    Sanctum::actingAs(quoteCommissionActor([]));
    $this->postJson('/api/quotes/commission-defaults', [
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'line_net_amount' => 100,
    ])->assertForbidden();
});
