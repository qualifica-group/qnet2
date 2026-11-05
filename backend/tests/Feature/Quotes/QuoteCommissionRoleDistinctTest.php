<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * `recipient_role` is unique per quote line (DB unique on quote_line_id +
 * recipient_role), not across the whole quote: every line may pay the same
 * commercial/reporter.
 */
function distinctRoleQuotePayload(): array
{
    $commercial = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);

    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['quotes.create', 'quotes.view', 'quotes.update']);
    $actor->givePermissionTo(Permission::findOrCreate('quotes.viewAll', 'web'));
    Sanctum::actingAs($actor);

    $commission = [
        'recipient_role' => 'COMMERCIAL',
        'recipient_type' => 'referent',
        'recipient_id' => $commercial->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 10,
        'origin' => 'MANUAL_OVERRIDE',
    ];
    $line = ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'commissions' => [$commission]];

    return [
        'payload' => ['title' => 'Distinct roles', 'opportunity_id' => $opportunity->id, 'commercial_id' => $commercial->id],
        'line' => $line,
        'commission' => $commission,
    ];
}

it('accepts the same commission role on different quote lines', function () {
    ['payload' => $payload, 'line' => $line] = distinctRoleQuotePayload();

    $this->postJson('/api/quotes', [...$payload, 'offer_lines' => [$line, $line, $line]])
        ->assertCreated()
        ->assertJsonPath('data.offer_lines.2.commissions.0.recipient_role', 'COMMERCIAL');
});

it('rejects the same commission role twice on one quote line', function () {
    ['payload' => $payload, 'line' => $line, 'commission' => $commission] = distinctRoleQuotePayload();
    $line['commissions'] = [$commission, $commission];

    $this->postJson('/api/quotes', [...$payload, 'offer_lines' => [$line, ['product_id' => $line['product_id'], 'quantity' => 1, 'unit_price' => 100]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'offer_lines.0.commissions.0.recipient_role',
            'offer_lines.0.commissions.1.recipient_role',
        ])
        ->assertJsonMissingValidationErrors('offer_lines.1.commissions.0.recipient_role');
});
