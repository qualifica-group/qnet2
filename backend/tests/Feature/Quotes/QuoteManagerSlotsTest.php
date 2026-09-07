<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `manager_slots`/`promote_managers_to_opportunity` write path on
 * POST/PATCH /api/quotes (spec 0087, T-06): AC-001..006, exercised through
 * real HTTP requests against StoreQuoteRequest/UpdateQuoteRequest/
 * QuoteService/QuoteManagerWriter. AC-007/008 (D-7 bidirectional
 * propagation) live in OpportunityManagerSyncPropagationTest — this file
 * only covers the Offerta-side write.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteManagerSlotsActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteManagerSlotsActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteManagerSlotsOfferLine')) {
    /**
     * Spec 0102: POST /api/quotes now requires at least one offer_lines row.
     * A category with an EFFECTIVE business function so the auto-add
     * coverage path never trips the 422 guard (OpportunityProductLineCoverage).
     *
     * @return array<int, array<string, int>>
     */
    function quoteManagerSlotsOfferLine(): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        return [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]];
    }
}

it('AC-001: create without manager_slots inherits the opportunity\'s GA at the same positions', function () {
    $opportunity = Opportunity::factory()->create();
    $ga1 = User::factory()->create();
    $ga3 = User::factory()->create();
    $opportunity->managers()->sync([$ga1->id => ['position' => 1], $ga3->id => ['position' => 3]]);
    Sanctum::actingAs(quoteManagerSlotsActor(['create']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => quoteManagerSlotsOfferLine(),
    ])->assertCreated();

    expect($response->json('data.managers'))->toBe([
        ['id' => $ga1->id, 'name' => $ga1->name, 'position' => 1],
        ['id' => $ga3->id, 'name' => $ga3->name, 'position' => 3],
    ]);
});

it('AC-002: create WITH manager_slots wins over the opportunity; [] leaves the offer without GA', function () {
    $opportunity = Opportunity::factory()->create();
    $inherited = User::factory()->create();
    $opportunity->managers()->sync([$inherited->id => ['position' => 1]]);
    $submitted = User::factory()->create();
    $opportunity->managers()->attach($submitted->id, ['position' => 2]);
    Sanctum::actingAs(quoteManagerSlotsActor(['create']));

    $withSlots = $this->postJson('/api/quotes', [
        'title' => 'Offerta con GA espliciti',
        'opportunity_id' => $opportunity->id,
        'manager_slots' => [null, $submitted->id],
        'offer_lines' => quoteManagerSlotsOfferLine(),
    ])->assertCreated();

    expect($withSlots->json('data.managers'))->toBe([
        ['id' => $submitted->id, 'name' => $submitted->name, 'position' => 2],
    ]);

    $withEmpty = $this->postJson('/api/quotes', [
        'title' => 'Offerta senza GA',
        'opportunity_id' => $opportunity->id,
        'manager_slots' => [],
        'offer_lines' => quoteManagerSlotsOfferLine(),
    ])->assertCreated();

    expect($withEmpty->json('data.managers'))->toBe([]);
});

it('AC-003: PATCH without manager_slots leaves the GA untouched; an array is a full-replace', function () {
    $opportunity = Opportunity::factory()->create();
    $ga = User::factory()->create();
    $opportunity->managers()->sync([$ga->id => ['position' => 1]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->managers()->sync([$ga->id => ['position' => 1]]);
    $actor = quoteManagerSlotsActor(['update', 'view']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Titolo aggiornato'])->assertOk();
    expect($quote->managers()->get()->pluck('id')->all())->toBe([$ga->id]);

    $replacement = User::factory()->create();
    $opportunity->managers()->attach($replacement->id, ['position' => 2]);

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => [null, $replacement->id]])->assertOk();
    expect($quote->managers()->get()->pluck('id')->all())->toBe([$replacement->id]);

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => []])->assertOk();
    expect($quote->managers()->get()->count())->toBe(0);
});

it('AC-004: a manager_slots user who is not a GA of the opportunity -> 422 naming them, without the promote flag', function () {
    $opportunity = Opportunity::factory()->create();
    $stranger = User::factory()->create(['name' => 'Persona Estranea']);
    Sanctum::actingAs(quoteManagerSlotsActor(['create']));

    $response = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'manager_slots' => [$stranger->id],
        'offer_lines' => quoteManagerSlotsOfferLine(),
    ])->assertStatus(422)->assertJsonValidationErrors('manager_slots');

    expect($response->json('errors.manager_slots.0'))->toContain('Persona Estranea');
});

it('AC-005: promote_managers_to_opportunity=true appends the user to the opportunity\'s first free slot', function () {
    $opportunity = Opportunity::factory()->create();
    $existing = User::factory()->create();
    $opportunity->managers()->sync([$existing->id => ['position' => 1]]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs(quoteManagerSlotsActor(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'manager_slots' => [$newcomer->id],
        'promote_managers_to_opportunity' => true,
        'offer_lines' => quoteManagerSlotsOfferLine(),
    ])->assertCreated();

    $opportunityManagers = $opportunity->managers()->get();
    expect($opportunityManagers->pluck('id')->all())->toContain($existing->id, $newcomer->id)
        ->and($opportunityManagers->firstWhere('id', $existing->id)->pivot->position)->toBe(1)
        ->and($opportunityManagers->firstWhere('id', $newcomer->id)->pivot->position)->toBe(2);
});

it('AC-006: quotes.operator_id mirrors the OPERATOR slot after create and after update', function () {
    $opportunity = Opportunity::factory()->create();
    $operator = User::factory()->create();
    $opportunity->managers()->attach($operator->id, ['position' => ManagerPositions::OPERATOR]);
    Sanctum::actingAs(quoteManagerSlotsActor(['create', 'update']));

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'manager_slots' => [null, $operator->id],
        'offer_lines' => quoteManagerSlotsOfferLine(),
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    expect($created->json('data.operator_id'))->toBe($operator->id)
        ->and(Quote::findOrFail($quoteId)->operator_id)->toBe($operator->id);

    $newOperator = User::factory()->create();
    $opportunity->managers()->attach($newOperator->id, ['position' => 3]);

    $updated = $this->patchJson("/api/quotes/{$quoteId}", [
        'manager_slots' => [null, $newOperator->id],
    ])->assertOk();

    expect($updated->json('data.operator_id'))->toBe($newOperator->id)
        ->and(Quote::findOrFail($quoteId)->operator_id)->toBe($newOperator->id);
});

it('AC-006: clearing the OPERATOR slot on update nulls quotes.operator_id', function () {
    $opportunity = Opportunity::factory()->create();
    $operator = User::factory()->create();
    $opportunity->managers()->sync([$operator->id => ['position' => ManagerPositions::OPERATOR]]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->managers()->sync([$operator->id => ['position' => ManagerPositions::OPERATOR]]);
    $quote->forceFill(['operator_id' => $operator->id])->save();
    Sanctum::actingAs(quoteManagerSlotsActor(['update', 'view']));

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => []])->assertOk();

    expect($quote->fresh()->operator_id)->toBeNull();
});
