<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\QualificaSampleRequestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The Gestione Richieste half of the sample dataset: requests created through
// the module's own write path, each one an Opportunity plus its Offerta.
uses(RefreshDatabase::class);

/** The seeder's own default batch size (QualificaSampleRequestSeeder::DEFAULT_REQUESTS). */
const SAMPLE_REQUESTS = 8;

$seedOffer = function (): void {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->create(['category_id' => $category->getKey()]);
};

it('skips itself when there is no Anagrafica to hang a request on', function () use ($seedOffer): void {
    $seedOffer();
    User::factory()->create();

    test()->seed(QualificaSampleRequestSeeder::class);

    expect(Quote::query()->count())->toBe(0);
});

it('skips itself when no category pairs a business function with a product', function (): void {
    // `product_lines` is mandatory (spec 0057, D-3): with no offer to fill it
    // the request is one the create form itself would refuse.
    ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Registry::factory()->create();
    User::factory()->create();

    test()->seed(QualificaSampleRequestSeeder::class);

    expect(Quote::query()->count())->toBe(0);
});

it('skips itself when there is no actor to create on behalf of', function () use ($seedOffer): void {
    $seedOffer();
    Registry::factory()->create();

    test()->seed(QualificaSampleRequestSeeder::class);

    expect(Quote::query()->count())->toBe(0);
});

it('creates one Offerta and its Opportunity per free Anagrafica', function () use ($seedOffer): void {
    $seedOffer();
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(SAMPLE_REQUESTS)->create();

    test()->seed(QualificaSampleRequestSeeder::class);

    $quotes = Quote::query()->with('opportunity.productLines')->get();

    expect($quotes)->toHaveCount(SAMPLE_REQUESTS)
        ->and(Opportunity::query()->count())->toBe(SAMPLE_REQUESTS)
        // Spec 0086, D-5: the Offerta is born through QuoteService, so the
        // generated code and the bootstrapped status come from the real path.
        ->and($quotes->filter(fn (Quote $quote): bool => $quote->code === null))->toBeEmpty()
        ->and($quotes->filter(fn (Quote $quote): bool => $quote->quote_workflow_status_id === null))->toBeEmpty()
        ->and($quotes->filter(fn (Quote $quote): bool => $quote->opportunity->productLines->isEmpty()))->toBeEmpty();
});

it('prices every Offerta with one offer row, on a product the request itself classifies', function () use ($seedOffer): void {
    $seedOffer();
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(SAMPLE_REQUESTS)->create();

    test()->seed(QualificaSampleRequestSeeder::class);

    $quotes = Quote::query()->with(['offerLines.product', 'opportunity.productLines'])->get();

    expect($quotes)->toHaveCount(SAMPLE_REQUESTS)
        // Exactly one row: a `single`-managed classification refuses a second
        // (spec 0077), and RequestCreationService rejects rather than trims.
        ->and($quotes->filter(fn (Quote $quote): bool => $quote->offerLines->count() !== 1))->toBeEmpty()
        // The row's category is one the Opportunity already covers, so the
        // service never has to append a product line of its own.
        ->and($quotes->filter(fn (Quote $quote): bool => ! $quote->opportunity->productLines
            ->pluck('product_category_id')
            ->contains($quote->offerLines->first()->product->category_id)))->toBeEmpty()
        // Priced from the product (spec 0065, D-6): the aggregates the grid
        // shows come out of the real calculator, not a zero row.
        ->and($quotes->filter(fn (Quote $quote): bool => (float) $quote->offerLines->first()->quantity <= 0))->toBeEmpty();
});

it('gives every request an Operatore and a Sede, the two columns visibility is scoped on', function () use ($seedOffer): void {
    $seedOffer();
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(SAMPLE_REQUESTS)->create();

    test()->seed(QualificaSampleRequestSeeder::class);

    $quotes = Quote::query()->get();

    // Spec 0105: tier 2 is `quotes.operator_id`, tier 3 the Sede — a request
    // missing either is invisible to everyone without `viewAll` (D-3).
    expect($quotes->filter(fn (Quote $quote): bool => $quote->operator_id === null))->toBeEmpty()
        ->and($quotes->filter(fn (Quote $quote): bool => $quote->operational_site_id === null))->toBeEmpty()
        // Rotated over the roster, not all parked on the creating actor.
        ->and($quotes->pluck('operator_id')->unique())->toHaveCount(3);
});

it('appends a second batch on re-run, on the anagrafiche still free', function () use ($seedOffer): void {
    // User directive 2026-09-08: the seeder ACCUMULATES. The pool of free
    // anagrafiche is what caps a run, never a guard.
    $seedOffer();
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(SAMPLE_REQUESTS * 2)->create();

    test()->seed(QualificaSampleRequestSeeder::class);
    test()->seed(QualificaSampleRequestSeeder::class);

    expect(Quote::query()->count())->toBe(SAMPLE_REQUESTS * 2);
});

it('sizes its batch from the run() argument, so one run can be made bigger', function () use ($seedOffer): void {
    // `--requests` of qualifica:seed-sample lands here.
    $seedOffer();
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(SAMPLE_REQUESTS)->create();

    app(QualificaSampleRequestSeeder::class)->run(requests: 3);

    expect(Quote::query()->count())->toBe(3);
});

it('seeds nothing more once every anagrafica carries an open deal', function () use ($seedOffer): void {
    $seedOffer();
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(SAMPLE_REQUESTS)->create();

    test()->seed(QualificaSampleRequestSeeder::class);
    test()->seed(QualificaSampleRequestSeeder::class);

    expect(Quote::query()->count())->toBe(SAMPLE_REQUESTS);
});
