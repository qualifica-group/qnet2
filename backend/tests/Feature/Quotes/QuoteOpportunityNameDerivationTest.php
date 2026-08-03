<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteStatus;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0077 (D-3/D-4, `derivazione_nome`): `opportunities.name` derives from
 * the REVENUE lines of every quote of the opportunity, recalculated by
 * QuoteService inside create()/update()/delete() (AC-030..AC-037).
 */
uses(RefreshDatabase::class);

if (! function_exists('nameDerivationQuoteService')) {
    function nameDerivationQuoteService(): QuoteService
    {
        return app(QuoteService::class);
    }
}

if (! function_exists('nameDerivationNewQuoteStatus')) {
    function nameDerivationNewQuoteStatus(): QuoteStatus
    {
        return QuoteStatus::where('system_key', 'new')->sole();
    }
}

if (! function_exists('nameDerivationRevenueProduct')) {
    /**
     * A product whose category resolves an EFFECTIVE business function, so a
     * REVENUE line never trips OpportunityProductLineCoverage's 422 guard.
     */
    function nameDerivationRevenueProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['name' => $name, 'category_id' => $category->id]);
    }
}

if (! function_exists('nameDerivationCreateQuoteData')) {
    /**
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    function nameDerivationCreateQuoteData(int $opportunityId, ?array $offerLines = null, ?array $costLines = null): CreateQuoteData
    {
        return new CreateQuoteData(
            code: null,
            title: 'Offerta di test',
            opportunityId: $opportunityId,
            quoteStatusId: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            offerLines: $offerLines,
            costLines: $costLines,
        );
    }
}

if (! function_exists('nameDerivationRevenueLine')) {
    function nameDerivationRevenueLine(Product $product): QuoteLineData
    {
        return new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null);
    }
}

if (! function_exists('nameDerivationOpportunityCreatePayload')) {
    /**
     * The mandatory POST /api/opportunities payload beyond `name` (which is
     * never accepted from the client, AC-037): a fresh Registry/status/
     * supervisor plus a valid one-row `product_lines` + `products_of_interest`.
     *
     * @return array<string, mixed>
     */
    function nameDerivationOpportunityCreatePayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'opportunity_status_id' => OpportunityStatus::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-030 — no offer at all -> the OPP_{id} fallback (unchanged behaviour)
// ---------------------------------------------------------------------------

it('AC-030: an opportunity created with no offer at all gets the OPP_{id} fallback', function () {
    Permission::findOrCreate('opportunities.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.create');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', nameDerivationOpportunityCreatePayload())
        ->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));

    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);
});

// ---------------------------------------------------------------------------
// AC-031 — exact concatenation, in line order
// ---------------------------------------------------------------------------

it('AC-031: creating an offer with 3 revenue lines derives "ISO 9001 + SOA + Attestati HACCP"', function () {
    nameDerivationNewQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $iso = nameDerivationRevenueProduct('ISO 9001');
    $soa = nameDerivationRevenueProduct('SOA');
    $haccp = nameDerivationRevenueProduct('Attestati HACCP');

    nameDerivationQuoteService()->create(nameDerivationCreateQuoteData($opportunity->id, [
        nameDerivationRevenueLine($iso),
        nameDerivationRevenueLine($soa),
        nameDerivationRevenueLine($haccp),
    ]));

    expect($opportunity->fresh()->name)->toBe('ISO 9001 + SOA + Attestati HACCP');
});

// ---------------------------------------------------------------------------
// AC-032 — dedup across quotes, first occurrence wins
// ---------------------------------------------------------------------------

it('AC-032: the same product on two different quotes appears only once', function () {
    nameDerivationNewQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $iso = nameDerivationRevenueProduct('ISO 9001');
    $service = nameDerivationQuoteService();

    $service->create(nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine($iso)]));
    $service->create(nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine($iso)]));

    $name = $opportunity->fresh()->name;

    expect(substr_count($name, 'ISO 9001'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-033 — COST lines never contribute
// ---------------------------------------------------------------------------

it('AC-033: a cost line\'s product never appears in the derived name', function () {
    nameDerivationNewQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $revenueProduct = nameDerivationRevenueProduct('ISO 9001');
    $costProduct = Product::factory()->create(['name' => 'Materiale di consumo']);

    nameDerivationQuoteService()->create(nameDerivationCreateQuoteData(
        $opportunity->id,
        offerLines: [nameDerivationRevenueLine($revenueProduct)],
        costLines: [new QuoteLineData(productId: $costProduct->id, quantity: 1.0, unitPrice: 3.0, vatRateId: null, sortOrder: null)],
    ));

    expect($opportunity->fresh()->name)
        ->toBe('ISO 9001')
        ->not->toContain('Materiale di consumo');
});

// ---------------------------------------------------------------------------
// AC-034 — deleting the only offer reverts to the fallback
// ---------------------------------------------------------------------------

it('AC-034: deleting the only offer reverts the name to OPP_{id}', function () {
    nameDerivationNewQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $product = nameDerivationRevenueProduct('ISO 9001');
    $service = nameDerivationQuoteService();

    $quote = $service->create(nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine($product)]));

    expect($opportunity->fresh()->name)->toBe('ISO 9001');

    $service->delete($quote);

    expect($opportunity->fresh()->name)->toBe('OPP_'.$opportunity->id);
});

// ---------------------------------------------------------------------------
// AC-035 — length capped at 191, no partial name, ends with " …"
// ---------------------------------------------------------------------------

it('AC-035: an over-length concatenation is capped at 191 chars, never splitting a name', function () {
    nameDerivationNewQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $first = nameDerivationRevenueProduct(str_repeat('A', 100));
    $second = nameDerivationRevenueProduct(str_repeat('B', 100));
    $third = nameDerivationRevenueProduct(str_repeat('C', 100));

    nameDerivationQuoteService()->create(nameDerivationCreateQuoteData($opportunity->id, [
        nameDerivationRevenueLine($first),
        nameDerivationRevenueLine($second),
        nameDerivationRevenueLine($third),
    ]));

    $name = $opportunity->fresh()->name;

    expect(mb_strlen($name))->toBeLessThanOrEqual(191)
        ->and($name)->toBe(str_repeat('A', 100).' …')
        ->and($name)->not->toContain('B')
        ->and($name)->not->toContain('C');
});

// ---------------------------------------------------------------------------
// AC-036 — identical behaviour for a Gestione Richieste record
// ---------------------------------------------------------------------------

it('AC-036: an opportunity created via Gestione Richieste derives its name the same way', function () {
    Permission::findOrCreate('request-management.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('request-management.create');
    Sanctum::actingAs($actor);

    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
        ],
        'source_id' => Source::factory()->create()->id,
    ])->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);

    nameDerivationNewQuoteStatus();
    $product = nameDerivationRevenueProduct('ISO 9001');
    nameDerivationQuoteService()->create(nameDerivationCreateQuoteData($opportunity->id, [nameDerivationRevenueLine($product)]));

    expect($opportunity->fresh()->name)->toBe('ISO 9001');
});

// ---------------------------------------------------------------------------
// AC-037 — `name` is never a client input, even with `Quote` in scope
// ---------------------------------------------------------------------------

it('AC-037: a submitted `name` on POST /api/opportunities is ignored, derivation stays the only source', function () {
    Permission::findOrCreate('opportunities.create');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.create');
    Sanctum::actingAs($actor);

    $payload = array_merge(['name' => 'Client-supplied name'], nameDerivationOpportunityCreatePayload());

    $response = $this->postJson('/api/opportunities', $payload)->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));

    expect($opportunity->name)->toBe('OPP_'.$opportunity->id)
        ->not->toBe('Client-supplied name');
});
