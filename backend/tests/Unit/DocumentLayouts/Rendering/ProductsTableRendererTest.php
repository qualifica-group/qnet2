<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

require_once __DIR__.'/RenderingTestSupport.php';

// spec 0070 — ProductsTableRenderer (AC-240..246): the "cuore della feature".

// ---------------------------------------------------------------------------
// AC-240 — header + one row per QuoteLine, in sort_order order
// ---------------------------------------------------------------------------

it('renders 1 header row + 3 line rows in sort_order order (AC-240)', function () {
    $quote = dlrFullQuote();
    dlrAddLine($quote, 2, lineOverrides: ['product_id' => Product::factory()->create(['name' => 'Terzo'])->id]);
    dlrAddLine($quote, 0, lineOverrides: ['product_id' => Product::factory()->create(['name' => 'Primo'])->id]);
    dlrAddLine($quote, 1, lineOverrides: ['product_id' => Product::factory()->create(['name' => 'Secondo'])->id]);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock([dlrProductColumn(['name'], 'Nome', 100)]),
    ]]]);

    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));

    expect($rows)->toHaveCount(4)
        ->and($rows[0]['is_header'])->toBeTrue();

    expect(dlrRowText($rows[1]))->toBe(['Primo'])
        ->and(dlrRowText($rows[2]))->toBe(['Secondo'])
        ->and(dlrRowText($rows[3]))->toBe(['Terzo']);
});

// ---------------------------------------------------------------------------
// AC-241 — the 9 allow-listed columns, vat_rate empty when null
// ---------------------------------------------------------------------------

it('renders every one of the 9 allow-listed columns with the expected formatted value (AC-241)', function () {
    $quote = dlrFullQuote();
    $product = Product::factory()->create(['name' => 'Prodotto X', 'description' => 'Descrizione X']);
    $vatRate = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22.00]);
    QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'vat_rate_id' => $vatRate->id,
        'quantity' => 2.5,
        'unit_price' => 100.00,
        'net_amount' => 250.00,
        'vat_amount' => 55.00,
        'total_amount' => 305.00,
        'sort_order' => 0,
    ]);

    $keys = ['code', 'name', 'description', 'quantity', 'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'total_amount'];
    $columns = array_map(fn (string $key): array => dlrProductColumn([$key], $key, 11), $keys);

    $config = dlrDocConfig(['body' => ['blocks' => [dlrProductsTableBlock($columns)]]]);
    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));

    expect(dlrRowText($rows[1]))->toBe([
        $product->code, 'Prodotto X', 'Descrizione X', '2,5', '100,00', 'IVA 22%', '250,00', '55,00', '305,00',
    ]);
});

it('renders an empty string for vat_rate when the line has no VAT rate, without error (AC-241)', function () {
    $quote = dlrFullQuote();
    QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'vat_rate_id' => null,
        'sort_order' => 0,
    ]);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock([dlrProductColumn(['vat_rate'], 'IVA', 100)]),
    ]]]);

    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));

    expect(dlrRowText($rows[1]))->toBe(['']);
});

// ---------------------------------------------------------------------------
// AC-242 — multi-line column: two paragraphs in the same cell (0069 D-11)
// ---------------------------------------------------------------------------

it('a 2-line column produces TWO paragraphs in the same cell, the first joining code-name with the separator (AC-242)', function () {
    $quote = dlrFullQuote();
    $product = Product::factory()->create(['name' => 'Sedia', 'description' => 'Sedia da ufficio']);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id, 'sort_order' => 0]);

    $column = [
        'lines' => [
            ['keys' => ['code', 'name'], 'separator' => '-', 'bold' => false, 'italic' => false, 'size' => null],
            ['keys' => ['description'], 'separator' => '', 'bold' => false, 'italic' => false, 'size' => null],
        ],
        'label' => 'Descrizione',
        'width_pct' => 100,
        'align' => 'left',
    ];

    $config = dlrDocConfig(['body' => ['blocks' => [dlrProductsTableBlock([$column])]]]);
    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));

    $cell = $rows[1]['cells'][0];
    expect($cell['paragraphs'])->toHaveCount(2)
        ->and($cell['paragraphs'][0])->toBe("{$product->code}-Sedia")
        ->and($cell['paragraphs'][1])->toBe('Sedia da ufficio');
});

// ---------------------------------------------------------------------------
// AC-243 — no lines -> one row, empty_text spanning every column via gridSpan
// ---------------------------------------------------------------------------

it('renders a single row with empty_text spanning all columns (gridSpan) when there are no lines (AC-243)', function () {
    $quote = dlrFullQuote();

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock([
            dlrProductColumn(['name'], 'Nome', 40),
            dlrProductColumn(['quantity'], 'Quantita', 30),
            dlrProductColumn(['unit_price'], 'Prezzo', 30),
        ], emptyText: 'Nessun prodotto inserito'),
    ]]]);

    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));

    expect($rows)->toHaveCount(2);
    $emptyRow = $rows[1];
    expect($emptyRow['cells'])->toHaveCount(1)
        ->and($emptyRow['cells'][0]['grid_span'])->toBe(3)
        ->and($emptyRow['cells'][0]['paragraphs'][0])->toBe('Nessun prodotto inserito');
});

// ---------------------------------------------------------------------------
// AC-244 — totals rows: label penultimate cell, value last cell, both bold
// ---------------------------------------------------------------------------

it('renders one row per totals.rows[] with label/value in the last two cells, bold on both (AC-244)', function () {
    $quote = dlrFullQuote(['revenue_net' => 1250.00, 'revenue_vat' => 275.00]);
    dlrAddLine($quote, 0);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock(
            [dlrProductColumn(['name'], 'Nome', 60), dlrProductColumn(['quantity'], 'Qta', 40)],
            totals: ['show' => true, 'rows' => [
                ['label' => 'Totale imponibile', 'variable' => '{totals.revenue_net}', 'bold' => true],
            ]],
        ),
    ]]]);

    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));
    $totalsRow = $rows[array_key_last($rows)];

    expect($totalsRow['cells'])->toHaveCount(2)
        ->and(dlrRowText($totalsRow))->toBe(['Totale imponibile', '1.250,00'])
        ->and($totalsRow['cells'][0]['bold'])->toBeTrue()
        ->and($totalsRow['cells'][1]['bold'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-245 — source: cost_lines renders cost rows, not offer rows
// ---------------------------------------------------------------------------

it('source: cost_lines renders the cost lines, not the offer lines (AC-245)', function () {
    $quote = dlrFullQuote();
    dlrAddLine($quote, 0, 'offer', ['product_id' => Product::factory()->create(['name' => 'Offerta'])->id]);
    dlrAddLine($quote, 0, 'cost', ['product_id' => Product::factory()->create(['name' => 'Costo'])->id]);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock([dlrProductColumn(['name'], 'Nome', 100)], source: 'cost_lines'),
    ]]]);

    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['costLines']))));

    expect($rows)->toHaveCount(2)
        ->and(dlrRowText($rows[1]))->toBe(['Costo']);
});

// ---------------------------------------------------------------------------
// AC-246 — header row repeats on every page (tblHeader)
// ---------------------------------------------------------------------------

it('marks the header row as tblHeader so it repeats on every page (AC-246)', function () {
    $quote = dlrFullQuote();
    dlrAddLine($quote, 0);

    $config = dlrDocConfig(['body' => ['blocks' => [
        dlrProductsTableBlock([dlrProductColumn(['name'], 'Nome', 100)]),
    ]]]);

    $rows = dlrExtractTable(dlrOpenZip(dlrRender($config, $quote->fresh(['offerLines']))));

    expect($rows[0]['is_header'])->toBeTrue()
        ->and($rows[1]['is_header'])->toBeFalse();
});
