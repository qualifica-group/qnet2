<?php

use App\Enums\QuoteLineType;
use App\Models\Concerns\LogsModelActivity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring OpportunityProductLineTest.
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the quote_lines table with the expected columns', function () {
    expect(Schema::hasTable('quote_lines'))->toBeTrue();
    expect(Schema::hasColumns('quote_lines', [
        'id', 'quote_id', 'line_type', 'product_id', 'quantity', 'unit_price',
        'vat_rate_id', 'net_amount', 'vat_amount', 'total_amount', 'sort_order',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

// ---------------------------------------------------------------------------
// #[Fillable] / casts
// ---------------------------------------------------------------------------

it('mass-assigns every declared #[Fillable] column, including the frozen amounts (D-10)', function () {
    $quote = Quote::factory()->create();
    $product = Product::factory()->create();
    $vatRate = VatRate::factory()->create();

    $line = QuoteLine::create([
        'quote_id' => $quote->id,
        'line_type' => QuoteLineType::Revenue->value,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 10.01,
        'vat_rate_id' => $vatRate->id,
        'net_amount' => 30.03,
        'vat_amount' => 6.61,
        'total_amount' => 36.64,
        'sort_order' => 1,
    ]);

    expect($line->exists)->toBeTrue()
        ->and($line->quote_id)->toBe($quote->id)
        ->and($line->line_type)->toBe(QuoteLineType::Revenue)
        ->and($line->product_id)->toBe($product->id)
        ->and($line->vat_rate_id)->toBe($vatRate->id)
        ->and($line->net_amount)->toBe('30.03')
        ->and($line->vat_amount)->toBe('6.61')
        ->and($line->total_amount)->toBe('36.64')
        ->and($line->sort_order)->toBe(1);
});

it('casts line_type to QuoteLineType and the decimal columns to decimal:2 (AC-030)', function () {
    $line = QuoteLine::factory()->create([
        'quantity' => 3,
        'unit_price' => 10,
        'net_amount' => 30,
        'vat_amount' => 6.6,
        'total_amount' => 36.6,
    ]);
    $line->refresh();

    expect($line->line_type)->toBe(QuoteLineType::Revenue)
        ->and($line->quantity)->toBe('3.00')
        ->and($line->unit_price)->toBe('10.00')
        ->and($line->net_amount)->toBe('30.00')
        ->and($line->vat_amount)->toBe('6.60')
        ->and($line->total_amount)->toBe('36.60')
        ->and($line->sort_order)->toBeInt();
});

it('the cost() factory state sets line_type to Cost', function () {
    $line = QuoteLine::factory()->cost()->create();

    expect($line->line_type)->toBe(QuoteLineType::Cost);
});

// ---------------------------------------------------------------------------
// relations
// ---------------------------------------------------------------------------

it('every relation is a BelongsTo to the expected model', function () {
    $line = new QuoteLine;

    expect($line->quote())->toBeInstanceOf(BelongsTo::class)
        ->and($line->quote()->getRelated())->toBeInstanceOf(Quote::class)
        ->and($line->product())->toBeInstanceOf(BelongsTo::class)
        ->and($line->product()->getRelated())->toBeInstanceOf(Product::class)
        ->and($line->vatRate())->toBeInstanceOf(BelongsTo::class)
        ->and($line->vatRate()->getRelated())->toBeInstanceOf(VatRate::class);
});

// ---------------------------------------------------------------------------
// schema-level constraints (D-11 cascade vs restrict)
// ---------------------------------------------------------------------------

it('quote_id cascades on delete, product_id/vat_rate_id restrict on delete', function () {
    $line = QuoteLine::factory()->create();

    expect(fn () => DB::table('products')->where('id', $line->product_id)->delete())
        ->toThrow(QueryException::class);

    $quoteId = $line->quote_id;
    Quote::destroy($quoteId);

    expect(DB::table('quote_lines')->where('id', $line->id)->exists())->toBeFalse();
});

it('does not log its own model activity (child collection of Quote, mirrors OpportunityProductLine)', function () {
    expect(class_uses(QuoteLine::class))->not->toHaveKey(LogsModelActivity::class);
});
