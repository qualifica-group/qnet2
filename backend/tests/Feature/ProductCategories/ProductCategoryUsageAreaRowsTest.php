<?php

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0389 rev.2 (AC-008..AC-013): a record whose categories are ALL
 * reserved to the other area is not part of the module — rows, totals,
 * record link, statistics and category filters. A mixed or unclassified
 * record stays.
 */
uses(RefreshDatabase::class);

/**
 * Four opportunities, each with one offer: reserved to requests, reserved to
 * opportunities, mixed (requests + opportunities) and unclassified.
 *
 * @return array<string, Quote>
 */
function usageAreaRowScenario(): array
{
    $requests = usageAreaCategory('requests', 'Formazione');
    $opportunities = usageAreaCategory('opportunities', 'Consulenza');

    return [
        'requests' => Quote::factory()->for(usageAreaOpportunity($requests))->create(),
        'opportunities' => Quote::factory()->for(usageAreaOpportunity($opportunities))->create(),
        'mixed' => Quote::factory()->for(usageAreaOpportunity($requests, $opportunities))->create(),
        'none' => Quote::factory()->for(usageAreaOpportunity())->create(),
    ];
}

/** @return array<int, int> */
function usageAreaRowIds(mixed $response): array
{
    return collect($response->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

/** @param  array<int, Quote>  $quotes  @return array<int, int> */
function usageAreaSortedIds(array $quotes, string $key = 'id'): array
{
    return collect($quotes)->map(fn (Quote $quote): int => $key === 'id' ? $quote->id : $quote->opportunity_id)->sort()->values()->all();
}

function usageAreaRequestViewer(): User
{
    return usageAreaActor(['request-management.viewAny', 'request-management.view', 'request-management.viewAll']);
}

it('AC-008: Gestione Richieste hides the offers whose categories are all reserved to opportunities', function () {
    $quotes = usageAreaRowScenario();
    Sanctum::actingAs(usageAreaRequestViewer());

    expect(usageAreaRowIds($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])))
        ->toBe(usageAreaSortedIds([$quotes['requests'], $quotes['mixed'], $quotes['none']]));
});

it('AC-009: a hidden request is not opened by its link either', function () {
    $quotes = usageAreaRowScenario();
    Sanctum::actingAs(usageAreaRequestViewer());

    $this->getJson("/api/request-management/{$quotes['opportunities']->id}")->assertForbidden();
    $this->getJson("/api/request-management/{$quotes['mixed']->id}")->assertOk();
});

it('AC-010: Opportunita\' and Offerte hide the records whose categories are all reserved to requests', function () {
    $quotes = usageAreaRowScenario();
    Sanctum::actingAs(usageAreaActor(['opportunities.viewAny', 'opportunities.viewAll', 'quotes.viewAny', 'quotes.viewAll']));
    $visible = [$quotes['opportunities'], $quotes['mixed'], $quotes['none']];

    expect(usageAreaRowIds($this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])))
        ->toBe(usageAreaSortedIds($visible, 'opportunity_id'))
        ->and(usageAreaRowIds($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])))
        ->toBe(usageAreaSortedIds($visible));
});

it('AC-011: with no restricted category every record is listed as before', function () {
    $category = usageAreaCategory('all');
    $quote = Quote::factory()->for(usageAreaOpportunity($category))->create();
    Sanctum::actingAs(usageAreaRequestViewer());

    expect(usageAreaRowIds($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])))
        ->toBe([$quote->id]);
});

it('AC-012: the request statistics never list a branch reserved to opportunities', function () {
    $requests = usageAreaCategory('requests', 'Formazione');
    $opportunities = usageAreaCategory('opportunities', 'Consulenza');
    $requests->update(['is_reportable' => true]);
    $opportunities->update(['is_reportable' => true]);
    Quote::factory()->for(usageAreaOpportunity($requests, $opportunities))->create();
    Sanctum::actingAs(usageAreaActor([requestReportPermission('statistics'), 'request-management.viewAny', 'request-management.viewAll']));

    expect(collect($this->getJson('/api/request-management/report/categories')->assertOk()->json('data.categories'))->pluck('label')->all())
        ->toBe(['Formazione']);
});

it('AC-013: the category filters of Opportunita\' and Offerte list only their categories; Contratti all', function () {
    usageAreaCategory('requests', 'Formazione');
    usageAreaCategory('opportunities', 'Consulenza');
    usageAreaCategory('all', 'Comune');
    Sanctum::actingAs(usageAreaActor(['opportunities.viewAny', 'quotes.viewAny', 'contracts.viewAny']));

    $source = fn (string $grid, string $filter) => collect($this->getJson("/api/tables/{$grid}/columns")->assertOk()->json('data.advancedFilters'))
        ->firstWhere('name', $filter)['source'];

    expect($source('opportunities', 'product_category')['params']['usage_area'])->toBe('opportunities')
        ->and($source('opportunities', 'parent_category')['params']['usage_area'])->toBe('opportunities')
        ->and($source('quotes', 'product_category')['params']['usage_area'])->toBe('opportunities')
        ->and($source('quotes', 'parent_category')['params']['usage_area'])->toBe('opportunities')
        ->and($source('contracts', 'parent_category')['params'])->not->toHaveKey('usage_area');

    $names = fn (string $url) => collect($this->getJson($url)->assertOk()->json('items'))->pluck('label')->all();

    expect($names('/api/product-categories/for-select?usage_area=opportunities&include_inactive=1'))->toBe(['Comune', 'Consulenza'])
        ->and($names('/api/product-category-roots/for-select?usage_area=requests&current_only=1'))->toBe(['Comune', 'Formazione']);
});
