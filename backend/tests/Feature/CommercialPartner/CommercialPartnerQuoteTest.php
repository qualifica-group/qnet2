<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0417 AC-004 / AC-014: the quote's "Partner commerciale" is a snapshot of
 * the opportunity's (omitted key = copied, sent value wins, later opportunity
 * changes do not reach the quote) and the registry transfer copies it.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function partnerQuoteActor(array $abilities): User
{
    $user = User::factory()->create();

    foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'viewCosts', 'viewActivity', 'transferRegistry'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $user->givePermissionTo(array_map(static fn (string $ability): string => "quotes.{$ability}", [...$abilities, 'view', 'viewAll', 'viewCosts', 'viewActivity']));

    return $user;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function partnerQuotePayload(Opportunity $opportunity, array $extra = []): array
{
    return [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => quoteHttpRevenueProduct()->id, 'quantity' => 1, 'unit_price' => 10]],
        ...$extra,
    ];
}

it('AC-004: POST without the key copies the partner from the opportunity', function () {
    $partner = Referent::factory()->create(['name' => 'Paola Partner']);
    $opportunity = Opportunity::factory()->create(['commercial_partner_id' => $partner->id]);
    Sanctum::actingAs(partnerQuoteActor(['create']));

    $this->postJson('/api/quotes', partnerQuotePayload($opportunity))
        ->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', $partner->id)
        ->assertJsonPath('data.commercial_partner.name', 'Paola Partner');
});

it('AC-004: POST with a submitted partner wins over the opportunity snapshot', function () {
    $inherited = Referent::factory()->create();
    $explicit = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_partner_id' => $inherited->id]);
    Sanctum::actingAs(partnerQuoteActor(['create']));

    $this->postJson('/api/quotes', partnerQuotePayload($opportunity, ['commercial_partner_id' => $explicit->id]))
        ->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', $explicit->id);
});

it('AC-004: POST with an explicit null wins over the opportunity snapshot', function () {
    $opportunity = Opportunity::factory()->create(['commercial_partner_id' => Referent::factory()->create()->id]);
    Sanctum::actingAs(partnerQuoteActor(['create']));

    $this->postJson('/api/quotes', partnerQuotePayload($opportunity, ['commercial_partner_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', null)
        ->assertJsonPath('data.commercial_partner', null);
});

it('AC-004: POST 422 on an unknown partner id', function () {
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(partnerQuoteActor(['create']));

    $this->postJson('/api/quotes', partnerQuotePayload($opportunity, ['commercial_partner_id' => 999999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');
});

it('AC-004: a later change on the opportunity does not reach the quote, and PATCH edits the quote only', function () {
    $original = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_partner_id' => $original->id]);
    Sanctum::actingAs(partnerQuoteActor(['create', 'update']));

    $quoteId = $this->postJson('/api/quotes', partnerQuotePayload($opportunity))->assertCreated()->json('data.id');

    $opportunity->update(['commercial_partner_id' => Referent::factory()->create()->id]);
    expect(Quote::findOrFail($quoteId)->commercial_partner_id)->toBe($original->id);

    $other = Referent::factory()->create();
    $this->patchJson("/api/quotes/{$quoteId}", ['commercial_partner_id' => $other->id])
        ->assertOk()
        ->assertJsonPath('data.commercial_partner.id', $other->id);

    expect($opportunity->fresh()->commercial_partner_id)->not->toBe($other->id);
});

it('AC-014: the registry transfer copies the partner onto the new opportunity', function () {
    $partner = Referent::factory()->create();
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'multiple_open_opportunities' => false,
        'single_quote_per_opportunity' => false,
    ]);
    $origin = Opportunity::factory()->create(['commercial_id' => null, 'reporter_id' => null, 'commercial_partner_id' => $partner->id]);
    OpportunityProductLine::factory()->create(['opportunity_id' => $origin->id, 'product_category_id' => $category->id]);
    $quote = Quote::factory()->create([
        'opportunity_id' => $origin->id,
        'quote_workflow_status_id' => QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Open])->id,
    ]);
    $destination = Registry::factory()->create();
    Sanctum::actingAs(partnerQuoteActor(['update', 'transferRegistry']));

    $this->postJson("/api/quotes/{$quote->id}/transfer-registry", ['registry_id' => $destination->id])->assertOk();

    $target = $quote->fresh()->opportunity;

    expect($target->id)->not->toBe($origin->id)
        ->and($target->commercial_partner_id)->toBe($partner->id);
});
