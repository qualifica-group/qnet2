<?php

use App\Enums\QuoteLineType;
use App\Models\BusinessFunction;
use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Direttiva utente 2026-11-09 — in Gestione Richieste OGNI colonna del
 * catalogo e' ordinabile e filtrabile. Copre le quattro che non lo erano:
 * `product_categories` e `offer_lines` (ordinamento per il primo nome
 * correlato in ordine alfabetico), `pending_change_requests` (ordinamento e
 * filtro `number` sul conteggio) e `created_at` (filtro `date`).
 */
uses(RefreshDatabase::class);

if (! function_exists('allColumnsSortFilterActor')) {
    function allColumnsSortFilterActor(): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);

        return $user;
    }
}

if (! function_exists('allColumnsSortFilterQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function allColumnsSortFilterQuote(array $attributes = []): Quote
    {
        return Quote::factory()->for(Opportunity::factory())->create($attributes);
    }
}

if (! function_exists('allColumnsSortFilterCategory')) {
    function allColumnsSortFilterCategory(Quote $quote, string $name): void
    {
        $category = ProductCategory::factory()->create([
            'name' => $name,
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        OpportunityProductLine::factory()->create([
            'opportunity_id' => $quote->opportunity_id,
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);
    }
}

if (! function_exists('allColumnsSortFilterLine')) {
    function allColumnsSortFilterLine(Quote $quote, string $productName, QuoteLineType $type): void
    {
        QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'product_id' => Product::factory()->create(['name' => $productName])->id,
            'line_type' => $type,
        ]);
    }
}

if (! function_exists('allColumnsSortFilterPendingRequest')) {
    function allColumnsSortFilterPendingRequest(Quote $quote, string $field): FieldChangeRequest
    {
        return FieldChangeRequest::factory()->create([
            'subject_type' => Relation::getMorphAlias(Quote::class),
            'subject_id' => $quote->id,
            'field' => $field,
        ]);
    }
}

if (! function_exists('allColumnsSortFilterRowIds')) {
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    function allColumnsSortFilterRowIds(array $payload): array
    {
        return collect(test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 25,
            ...$payload,
        ])->assertOk()->json('items'))->pluck('id')->all();
    }
}

it('declares every catalogue column sortable and filterable', function (): void {
    Sanctum::actingAs(allColumnsSortFilterActor());

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    // `id` is the engine's own cross-domain default (InjectsDefaultIdColumn:
    // hidden, sortable, deliberately not filterable), not a catalogue column.
    foreach ($columns->except('id') as $column) {
        expect($column['sortable'])->toBeTrue("{$column['id']} is not sortable")
            ->and($column['filterable'])->toBeTrue("{$column['id']} is not filterable");
    }

    expect($columns['product_categories']['filterType'])->toBe('set')
        ->and($columns['offer_lines']['filterType'])->toBe('set')
        ->and($columns['pending_change_requests']['filterType'])->toBe('number')
        ->and($columns['pending_change_requests']['hasFilterValues'])->toBeFalse()
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('sorts by the alphabetically first product category', function (string $direction, array $expected): void {
    $onlyZeta = allColumnsSortFilterQuote();
    allColumnsSortFilterCategory($onlyZeta, 'Zeta');
    $alfaAndZeta = allColumnsSortFilterQuote();
    allColumnsSortFilterCategory($alfaAndZeta, 'Zeta');
    allColumnsSortFilterCategory($alfaAndZeta, 'Alfa');
    Sanctum::actingAs(allColumnsSortFilterActor());

    $ids = allColumnsSortFilterRowIds(['sortModel' => [['colId' => 'product_categories', 'sort' => $direction]]]);

    $byKey = ['onlyZeta' => $onlyZeta->id, 'alfaAndZeta' => $alfaAndZeta->id];
    expect($ids)->toBe(array_map(static fn (string $key): int => $byKey[$key], $expected));
})->with([
    'asc' => ['asc', ['alfaAndZeta', 'onlyZeta']],
    'desc' => ['desc', ['onlyZeta', 'alfaAndZeta']],
]);

it('sorts by the alphabetically first revenue-line product, ignoring cost lines', function (): void {
    $zinco = allColumnsSortFilterQuote();
    allColumnsSortFilterLine($zinco, 'Zinco', QuoteLineType::Revenue);
    // A COST line's product never counts for this column (spec 0086 AC-007):
    // were it counted, this quote would sort first.
    allColumnsSortFilterLine($zinco, 'Aaa costo', QuoteLineType::Cost);
    $mare = allColumnsSortFilterQuote();
    allColumnsSortFilterLine($mare, 'Mare', QuoteLineType::Revenue);
    Sanctum::actingAs(allColumnsSortFilterActor());

    expect(allColumnsSortFilterRowIds(['sortModel' => [['colId' => 'offer_lines', 'sort' => 'asc']]]))
        ->toBe([$mare->id, $zinco->id])
        ->and(allColumnsSortFilterRowIds(['sortModel' => [['colId' => 'offer_lines', 'sort' => 'desc']]]))
        ->toBe([$zinco->id, $mare->id]);
});

it('sorts and filters by the number of pending change requests', function (): void {
    $two = allColumnsSortFilterQuote();
    allColumnsSortFilterPendingRequest($two, 'source_id');
    allColumnsSortFilterPendingRequest($two, 'operational_site_id');
    // An already handled request is not pending: it never counts.
    FieldChangeRequest::factory()->approved()->create([
        'subject_type' => Relation::getMorphAlias(Quote::class),
        'subject_id' => $two->id,
    ]);
    $none = allColumnsSortFilterQuote();
    $one = allColumnsSortFilterQuote();
    allColumnsSortFilterPendingRequest($one, 'source_id');
    Sanctum::actingAs(allColumnsSortFilterActor());

    expect(allColumnsSortFilterRowIds(['sortModel' => [['colId' => 'pending_change_requests', 'sort' => 'desc']]]))
        ->toBe([$two->id, $one->id, $none->id])
        ->and(allColumnsSortFilterRowIds(['sortModel' => [['colId' => 'pending_change_requests', 'sort' => 'asc']]]))
        ->toBe([$none->id, $one->id, $two->id])
        ->and(allColumnsSortFilterRowIds([
            'filterModel' => ['pending_change_requests' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 0]],
            'sortModel' => [['colId' => 'pending_change_requests', 'sort' => 'desc']],
        ]))->toBe([$two->id, $one->id])
        ->and(allColumnsSortFilterRowIds([
            'filterModel' => ['pending_change_requests' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 0]],
        ]))->toBe([$none->id])
        ->and(allColumnsSortFilterRowIds([
            'filterModel' => ['pending_change_requests' => ['filterType' => 'number', 'type' => 'inRange', 'filter' => 1, 'filterTo' => 1]],
        ]))->toBe([$one->id])
        ->and(allColumnsSortFilterRowIds([
            'filterModel' => ['pending_change_requests' => [
                'filterType' => 'number',
                'operator' => 'OR',
                'conditions' => [
                    ['filterType' => 'number', 'type' => 'equals', 'filter' => 0],
                    ['filterType' => 'number', 'type' => 'equals', 'filter' => 2],
                ],
            ]],
            'sortModel' => [['colId' => 'pending_change_requests', 'sort' => 'asc']],
        ]))->toBe([$none->id, $two->id]);
});

it('filters by the loading date', function (): void {
    $january = allColumnsSortFilterQuote(['created_at' => '2026-01-10 09:00:00']);
    $march = allColumnsSortFilterQuote(['created_at' => '2026-03-05 09:00:00']);
    Sanctum::actingAs(allColumnsSortFilterActor());

    expect(allColumnsSortFilterRowIds([
        'filterModel' => ['created_at' => ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-01-01 00:00:00', 'dateTo' => '2026-01-31 23:59:59']],
    ]))->toBe([$january->id])
        ->and(allColumnsSortFilterRowIds([
            'filterModel' => ['created_at' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-02-01 00:00:00']],
        ]))->toBe([$march->id]);
});
