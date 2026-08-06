<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `quotes` CRUD HTTP surface (spec 0065, MT-05): the domain layer
 * (QuoteService/DTOs) is already covered directly by QuoteServiceTest/
 * QuoteTotalsTest — this file exercises the SAME acceptance criteria through
 * real HTTP requests (POST/GET/PATCH), which did not exist before MT-05.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteHttpUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteHttpUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteHttpNewStatus')) {
    function quoteHttpNewStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('quoteHttpRevenueProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (D-7), so a REVENUE line never trips the 422 coverage guard
     * (QuoteCoverageTest owns AC-050/051 directly).
     */
    function quoteHttpRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/quotes (AC-020/021/023/024)
// ---------------------------------------------------------------------------

it('AC-020: POST without the 3 commercial roles inherits them from the opportunity', function () {
    quoteHttpNewStatus();
    $commercial = Referent::factory()->create();
    $reporter = Referent::factory()->create();
    $supervisor = User::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);
    // Directive 2026-08-06: only a Gestore Account of the opportunity is an
    // inheritable Supervisore (a non-GA one prefills nothing — covered in
    // QuoteSupervisorManagerTest).
    $opportunity->managers()->attach($supervisor->id, ['position' => 1]);
    $actor = quoteHttpUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.commercial_id', $commercial->id)
        ->assertJsonPath('data.reporter_id', $reporter->id)
        ->assertJsonPath('data.supervisor_id', $supervisor->id)
        ->assertJsonPath('data.commercial.name', $commercial->name);
});

it('AC-021: an explicitly submitted commercial_id wins over the opportunity snapshot', function () {
    quoteHttpNewStatus();
    $inherited = Referent::factory()->create();
    $explicit = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $inherited->id]);
    $actor = quoteHttpUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'commercial_id' => $explicit->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.commercial_id', $explicit->id);
});

it('AC-023: POST without quote_workflow_status_id assigns the system open row', function () {
    $newStatus = quoteHttpNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteHttpUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', ['title' => 'Offerta', 'opportunity_id' => $opportunity->id])
        ->assertCreated()
        ->assertJsonPath('data.quote_workflow_status_id', $newStatus->id)
        ->assertJsonPath('data.quote_workflow_status.name', $newStatus->name);
});

it('AC-024: an opportunity accepts multiple quotes, all readable', function () {
    quoteHttpNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteHttpUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $ids = collect(['Uno', 'Due', 'Tre'])->map(function (string $title) use ($opportunity) {
        return $this->postJson('/api/quotes', ['title' => $title, 'opportunity_id' => $opportunity->id])
            ->assertCreated()
            ->json('data.id');
    });

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(3);

    foreach ($ids as $id) {
        $this->getJson("/api/quotes/{$id}")->assertOk();
    }
});

// ---------------------------------------------------------------------------
// update — PATCH /api/quotes/{quote} (AC-025)
// ---------------------------------------------------------------------------

it('AC-025: PATCH with opportunity_id present is rejected (immutable)', function () {
    quoteHttpNewStatus();
    $quote = Quote::factory()->create();
    $otherOpportunity = Opportunity::factory()->create();
    $actor = quoteHttpUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['opportunity_id' => $otherOpportunity->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('opportunity_id');

    expect($quote->fresh()->opportunity_id)->toBe($quote->opportunity_id);
});

// ---------------------------------------------------------------------------
// line validation via HTTP (AC-033/034/035)
// ---------------------------------------------------------------------------

it('AC-033: a POST with net_amount on a line is rejected (server-computed)', function () {
    $opportunity = Opportunity::factory()->create();
    $product = quoteHttpRevenueProduct();
    $actor = quoteHttpUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'net_amount' => 10],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('offer_lines.0.net_amount');

    expect(Quote::count())->toBe(0);
});

it('AC-034: quantity <= 0 and a negative unit_price are rejected via POST', function () {
    $opportunity = Opportunity::factory()->create();
    $product = quoteHttpRevenueProduct();
    $actor = quoteHttpUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 0, 'unit_price' => 10],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.quantity');

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => -1],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines.0.unit_price');

    expect(Quote::count())->toBe(0);
});

it('AC-035: 201 rows in offer_lines is rejected via POST (max 200)', function () {
    $opportunity = Opportunity::factory()->create();
    $product = quoteHttpRevenueProduct();
    $actor = quoteHttpUserWith(['create']);
    Sanctum::actingAs($actor);

    $rows = array_fill(0, 201, ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]);

    $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => $rows,
    ])->assertStatus(422)->assertJsonValidationErrors('offer_lines');

    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// full-replace via PATCH (AC-036/037)
// ---------------------------------------------------------------------------

it('AC-036/037: PATCH full-replaces one tab, the other stays untouched', function () {
    quoteHttpNewStatus();
    $opportunity = Opportunity::factory()->create();
    $productA = quoteHttpRevenueProduct();
    $productB = quoteHttpRevenueProduct();
    $productC = Product::factory()->create();
    $actor = quoteHttpUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $productA->id, 'quantity' => 1, 'unit_price' => 10],
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 20],
        ],
        'cost_lines' => [
            ['product_id' => $productC->id, 'quantity' => 1, 'unit_price' => 5],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');

    $this->patchJson("/api/quotes/{$quoteId}", [
        'offer_lines' => [
            ['product_id' => $productB->id, 'quantity' => 1, 'unit_price' => 20],
        ],
    ])->assertOk();

    $response = $this->getJson("/api/quotes/{$quoteId}")->assertOk();

    expect($response->json('data.offer_lines'))->toHaveCount(1)
        ->and($response->json('data.offer_lines.0.product_id'))->toBe($productB->id)
        ->and($response->json('data.cost_lines'))->toHaveCount(1)
        ->and($response->json('data.cost_lines.0.product_id'))->toBe($productC->id);
});

// ---------------------------------------------------------------------------
// live product, frozen amounts (AC-055)
// ---------------------------------------------------------------------------

it('AC-055: GET shows the live product name, but the frozen line amounts', function () {
    quoteHttpNewStatus();
    $opportunity = Opportunity::factory()->create();
    $product = quoteHttpRevenueProduct();
    $product->update(['name' => 'Original name']);
    $actor = quoteHttpUserWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 15],
        ],
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $originalNetAmount = $created->json('data.offer_lines.0.net_amount');

    $product->update(['name' => 'Renamed product']);

    $response = $this->getJson("/api/quotes/{$quoteId}")->assertOk();

    expect($response->json('data.offer_lines.0.product.name'))->toBe('Renamed product')
        ->and($response->json('data.offer_lines.0.net_amount'))->toBe($originalNetAmount);
});

// ---------------------------------------------------------------------------
// delete happy path (contratto: 204 No Content, come opportunity-statuses)
// ---------------------------------------------------------------------------

it('delete: 204 No Content and the quote with its lines is gone', function () {
    quoteHttpNewStatus();
    $opportunity = Opportunity::factory()->create();
    $product = quoteHttpRevenueProduct();
    $actor = quoteHttpUserWith(['create', 'delete', 'view']);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta da eliminare',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/api/quotes/{$quoteId}")->assertNoContent();

    $this->assertDatabaseMissing('quotes', ['id' => $quoteId]);
    $this->assertDatabaseMissing('quote_lines', ['quote_id' => $quoteId]);
    $this->assertDatabaseHas('products', ['id' => $product->id]);
});
