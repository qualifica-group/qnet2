<?php

use App\Migrations\Sources\ProductsSource;
use App\Migrations\Support\LegacyFreeLineProduct;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Spec 0305: a legacy offer line without a service carries `free_product`
// {key, name} and is sold as the [OLD] product of its title. Fixtures in
// tests/Helpers/QuoteMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    Opportunity::factory()->create(['old_id' => 7]);
    $this->uncategorized = ProductCategory::factory()->create([
        'name' => ProductsSource::UNCATEGORIZED_CATEGORY,
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $this->vatRate = VatRate::factory()->create(['old_id' => 6]);
});

function freeLine(int $id, int $key, string $name, float $unitPrice = 1000): array
{
    return ['id' => $id, 'product_id' => null, 'free_product' => ['key' => $key, 'name' => $name], 'description' => null,
        'quantity' => 1, 'unit_price' => $unitPrice, 'vat_rate_id' => 6, 'commissions' => []];
}

it('AC-001 sells a line without a service as a new [OLD] product under No categoria, with a warning', function () {
    $name = '[OLD] ISO _ Prezzo Approssimativo Ente da Voi Scelto* _ UNI ISO 11381:2017 _ 1° Anno';

    $run = runLegacyQuotesImport([legacyQuoteRecord(['lines' => [freeLine(74624, 74624, $name)]])]);

    $line = Quote::query()->where('old_id', 42)->sole()->offerLines()->sole();
    $product = Product::query()->where('old_source', LegacyFreeLineProduct::OLD_SOURCE)->where('old_id', 74624)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($line->product_id)->toBe($product->id)
        ->and($line->old_id)->toBe(74624)
        ->and($product->name)->toBe($name)
        ->and($product->category_id)->toBe($this->uncategorized->id)
        ->and($this->uncategorized->fresh()->is_active)->toBeFalse()
        ->and((float) $product->price)->toBe(1000.0)
        ->and($product->vat_rate_id)->toBe($this->vatRate->id)
        ->and(legacyQuoteWarnings($run))->toContain("Line 74624: product '{$name}' created from the line title.");
});

it('AC-002 creates one product per key, shared by every line and quote of the run', function () {
    runLegacyQuotesImport([
        legacyQuoteRecord(['id' => 42, 'lines' => [freeLine(500, 500, '[OLD] Orientamento Specialistico')]]),
        legacyQuoteRecord(['id' => 43, 'lines' => [freeLine(501, 500, '[OLD] Orientamento Specialistico'), freeLine(502, 500, '[OLD] Orientamento Specialistico')]]),
    ]);

    $product = Product::query()->where('old_source', LegacyFreeLineProduct::OLD_SOURCE)->sole();
    $productIds = Quote::query()->whereIn('old_id', [42, 43])->get()->flatMap(fn (Quote $quote) => $quote->offerLines()->pluck('product_id'));

    expect($productIds->all())->toBe([$product->id, $product->id, $product->id]);
});

it('AC-002 reuses the product an earlier run created for the key, without a warning', function () {
    $product = Product::factory()->saleOnly()->create(['category_id' => $this->uncategorized->id, 'old_source' => LegacyFreeLineProduct::OLD_SOURCE, 'old_id' => 500]);

    $run = runLegacyQuotesImport([legacyQuoteRecord(['lines' => [freeLine(502, 500, '[OLD] Orientamento Specialistico')]])]);

    expect(Quote::query()->where('old_id', 42)->sole()->offerLines()->sole()->product_id)->toBe($product->id)
        ->and(Product::query()->where('old_source', LegacyFreeLineProduct::OLD_SOURCE)->count())->toBe(1)
        ->and(legacyQuoteWarnings($run))->toBe([]);
});

it('AC-003 imports a discount line without a service with its negative price', function () {
    runLegacyQuotesImport([legacyQuoteRecord(['lines' => [freeLine(600, 600, '[OLD] Sconto a Lei riservato', -300)]])]);

    $line = Quote::query()->where('old_id', 42)->sole()->offerLines()->sole();

    expect((float) $line->unit_price)->toBe(-300.0)
        ->and($line->product->name)->toBe('[OLD] Sconto a Lei riservato');
});

it('AC-005 truncates a product name longer than the column', function () {
    runLegacyQuotesImport([legacyQuoteRecord(['lines' => [freeLine(700, 700, '[OLD] '.str_repeat('a', 214))]])]);

    expect(mb_strlen(Product::query()->where('old_source', LegacyFreeLineProduct::OLD_SOURCE)->sole()->name))->toBe(191);
});

it('fails the quote when free_product has no key or no name', function (array $freeProduct) {
    $run = runLegacyQuotesImport([legacyQuoteRecord(['lines' => [[...freeLine(800, 800, 'x'), 'free_product' => $freeProduct]]])]);

    expect($run->failed_rows)->toBe(1)
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])
        ->toBe('Failed to import the record: Line 800: product without a key or a name; the quote is not imported.')
        ->and(Product::query()->where('old_source', LegacyFreeLineProduct::OLD_SOURCE)->exists())->toBeFalse();
})->with([
    'no key' => [['name' => '[OLD] Titolo']],
    'blank name' => [['key' => 800, 'name' => '  ']],
]);
