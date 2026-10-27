<?php

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Linee di prodotto" on the `quotes` grid (user directive 2026-10-15): the
 * REVENUE-line products of the offer, read-only, same projection as the
 * Gestione Richieste `offer_lines` column (App\Tables\Shared\OfferLinesColumn).
 */
uses(RefreshDatabase::class);

function quoteActor(): User
{
    Permission::findOrCreate('quotes.viewAny');
    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.viewAny', Permission::findOrCreate('quotes.viewAll', 'web')]);

    return $user;
}

/**
 * A row of the grid whose offer carries one REVENUE line per given product name.
 *
 * @return array{id: int, quote: Quote}
 */
function quoteRow(string ...$productNames): array
{
    $quote = Quote::factory()->create();

    foreach ($productNames as $name) {
        QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'product_id' => Product::factory()->create(['name' => $name])->id,
            'line_type' => QuoteLineType::Revenue,
        ]);
    }

    return ['id' => $quote->id, 'quote' => $quote];
}

/**
 * @param  array<string, mixed>  $filterModel
 * @return array<int, int>
 */
function quoteFilteredIds(mixed $test, array $filterModel): array
{
    return collect($test->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [], 'filterModel' => $filterModel,
    ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('declares offer_lines read-only, set-filterable and not sortable', function () {
    Sanctum::actingAs(quoteActor());

    $column = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'offer_lines');

    expect($column['label'])->toBe('quotes.columns.offerLines')
        ->and($column['type'])->toBe('text')
        ->and($column['visible'])->toBeTrue()
        ->and($column['sortable'])->toBeFalse()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        ->and($column['editable'] ?? false)->toBeFalse()
        ->and($column)->not->toHaveKey('editor');
});

it('projects the distinct REVENUE products only, never a COST line product', function () {
    $row = quoteRow('Fibra 1000', 'ADSL 20');
    $fibra = Product::query()->where('name', 'Fibra 1000')->firstOrFail();
    QuoteLine::factory()->create(['quote_id' => $row['quote']->id, 'product_id' => $fibra->id, 'line_type' => QuoteLineType::Revenue]);
    QuoteLine::factory()->create([
        'quote_id' => $row['quote']->id,
        'product_id' => Product::factory()->create(['name' => 'Costo interno'])->id,
        'line_type' => QuoteLineType::Cost,
    ]);
    Sanctum::actingAs(quoteActor());

    $items = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    $cell = collect($items)->firstWhere('id', $row['id'])['offer_lines'];

    expect(collect($cell)->pluck('name')->sort()->values()->all())->toBe(['ADSL 20', 'Fibra 1000'])
        ->and($cell[0])->toHaveKeys(['id', 'name', 'category_id', 'category_name'])
        ->and($cell[0]['category_name'])->toBe(Product::query()->findOrFail($cell[0]['id'])->category?->name);
});

it('returns an empty array for a row without revenue lines', function () {
    $empty = quoteRow();
    Sanctum::actingAs(quoteActor());

    $items = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    expect(collect($items)->firstWhere('id', $empty['id'])['offer_lines'])->toBe([]);
});

it('filters by product name and by the blank entry', function () {
    $matching = quoteRow('Fibra 1000');
    $other = quoteRow('ADSL 20');
    $blank = quoteRow();
    Sanctum::actingAs(quoteActor());

    expect(quoteFilteredIds($this, ['offer_lines' => ['filterType' => 'set', 'values' => ['Fibra 1000']]]))
        ->toBe([$matching['id']])
        ->and(quoteFilteredIds($this, ['offer_lines' => ['filterType' => 'set', 'values' => [null]]]))
        ->toBe([$blank['id']])
        ->and(quoteFilteredIds($this, ['offer_lines' => ['filterType' => 'set', 'values' => ['ADSL 20', null]]]))
        ->toBe(collect([$other['id'], $blank['id']])->sort()->values()->all());
});

it('enumerates the distinct product names with the blank entry first', function () {
    quoteRow('Fibra 1000');
    quoteRow('ADSL 20');
    quoteRow();
    Sanctum::actingAs(quoteActor());

    $values = $this->postJson('/api/tables/quotes/values', ['columnId' => 'offer_lines'])->assertOk()->json('data.values');

    expect($values)->toBe([null, 'ADSL 20', 'Fibra 1000']);
});
