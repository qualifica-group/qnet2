<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteStatus;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `summary` block of GET /api/quotes/{quote} (spec 0065, data_contract):
 * revenue/cost side by side with a DERIVED `gross` (net + vat, D-9 — never
 * persisted) and the net margin, over real HTTP requests (AC-040/042/043).
 * The persisted-aggregate arithmetic itself is covered directly by
 * QuoteTotalsTest; this file only verifies the HTTP response shape.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteSummaryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteSummaryUserWith(array $abilities): User
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

if (! function_exists('quoteSummaryNewStatus')) {
    function quoteSummaryNewStatus(): QuoteStatus
    {
        return QuoteStatus::where('system_key', 'new')->sole();
    }
}

if (! function_exists('quoteSummaryRevenueProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (D-7), so a REVENUE line never trips the 422 coverage guard.
     */
    function quoteSummaryRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

it('AC-040: summary exposes revenue/cost {net,vat,gross} and margin.net over HTTP', function () {
    quoteSummaryNewStatus();
    $opportunity = Opportunity::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $revenueProduct = quoteSummaryRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = quoteSummaryUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Offerta riepilogo',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $revenueProduct->id, 'quantity' => 3, 'unit_price' => 10, 'vat_rate_id' => $vatRate->id],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 10, 'vat_rate_id' => $vatRate->id],
        ],
    ])->assertCreated();

    $response = $this->getJson('/api/quotes/'.$created->json('data.id'))->assertOk();

    expect($response->json('data.summary'))->toBe([
        'revenue' => ['net' => '30.00', 'vat' => '6.60', 'gross' => '36.60'],
        'cost' => ['net' => '10.00', 'vat' => '2.20', 'gross' => '12.20'],
        'margin' => ['net' => '20.00'],
        'commissions' => ['commercial' => '0.00', 'reporter' => '0.00', 'supervisor' => '0.00', 'supplier' => '0.00'],
    ]);
});

it('AC-042: a quote without lines exposes every summary value at 0.00 over HTTP', function () {
    quoteSummaryNewStatus();
    $opportunity = Opportunity::factory()->create();
    $actor = quoteSummaryUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', ['title' => 'Vuoto', 'opportunity_id' => $opportunity->id])
        ->assertCreated();

    $response = $this->getJson('/api/quotes/'.$created->json('data.id'))->assertOk();

    expect($response->json('data.summary'))->toBe([
        'revenue' => ['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'],
        'cost' => ['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'],
        'margin' => ['net' => '0.00'],
        'commissions' => ['commercial' => '0.00', 'reporter' => '0.00', 'supervisor' => '0.00', 'supplier' => '0.00'],
    ]);
});

it('aggregates the commission totals without inheriting the sort_order ordering of Quote::lines()', function () {
    quoteSummaryNewStatus();
    $opportunity = Opportunity::factory()->create();
    $revenueProduct = quoteSummaryRevenueProduct();
    $actor = quoteSummaryUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Riepilogo provvigioni',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $revenueProduct->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertCreated();

    DB::enableQueryLog();
    $this->getJson('/api/quotes/'.$created->json('data.id'))->assertOk();
    $aggregates = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_contains($sql, 'group by'));
    DB::disableQueryLog();

    // MySQL only_full_group_by rejects "order by sort_order" next to a
    // GROUP BY that neither groups nor aggregates it; SQLite tolerates it,
    // so the guarantee is asserted on the emitted SQL, not on the result.
    expect($aggregates)->not->toBeEmpty()
        ->and($aggregates->filter(fn (string $sql): bool => str_contains($sql, 'order by')))->toBeEmpty();
});

it('AC-043: cost exceeding revenue yields a negative margin.net over HTTP, never clamped', function () {
    quoteSummaryNewStatus();
    $opportunity = Opportunity::factory()->create();
    $revenueProduct = quoteSummaryRevenueProduct();
    $costProduct = Product::factory()->create();
    $actor = quoteSummaryUserWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quotes', [
        'title' => 'Margine negativo',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $revenueProduct->id, 'quantity' => 1, 'unit_price' => 10],
        ],
        'cost_lines' => [
            ['product_id' => $costProduct->id, 'quantity' => 1, 'unit_price' => 50],
        ],
    ])->assertCreated();

    $response = $this->getJson('/api/quotes/'.$created->json('data.id'))->assertOk();

    expect($response->json('data.summary.margin.net'))->toBe('-40.00');
});
