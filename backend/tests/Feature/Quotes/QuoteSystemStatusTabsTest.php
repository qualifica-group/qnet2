<?php

use App\Models\Quote;
use App\Models\QuoteSystemStatus;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * System status tabs of the Offerte page (spec 0409): `meta.aggregates` of the
 * `quotes` grid counts the filtered set per system status name.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(quoteTableUserWith(['viewAny']));
});

/** @param  array<string, mixed>  $state */
function quoteTabsResponse(array $state = []): array
{
    return test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 100, ...$state])->assertOk()->json();
}

function quoteOnSystemStatus(string $systemStatusName, string $title = 'Plain'): Quote
{
    $systemStatus = QuoteSystemStatus::query()->firstOrCreate(['name' => $systemStatusName], QuoteSystemStatus::factory()->raw(['name' => $systemStatusName]));
    $workingStatus = QuoteWorkflowStatus::factory()->create(['quote_system_status_id' => $systemStatus->id]);

    return Quote::factory()->create(['title' => $title, 'quote_workflow_status_id' => $workingStatus->id]);
}

/** @return array<string, mixed> */
function quoteSystemStatusTab(string $name, array $state = []): array
{
    return ['filterModel' => ['quote_system_status' => ['filterType' => 'set', 'values' => [$name]]], ...$state];
}

it('AC-001: counts every system status by name, and the active tab does not change the figures', function () {
    quoteOnSystemStatus('Tab in corso');
    quoteOnSystemStatus('Tab in corso');
    quoteOnSystemStatus('Tab chiuso');

    $expected = ['Tab in corso' => 2, 'Tab chiuso' => 1];

    expect(quoteTabsResponse()['meta']['aggregates'])->toEqual($expected)
        ->and(quoteTabsResponse(quoteSystemStatusTab('Tab chiuso'))['meta']['aggregates'])->toEqual($expected)
        ->and(quoteTabsResponse(quoteSystemStatusTab('Tab chiuso'))['items'])->toHaveCount(1);
});

it('AC-001: each count equals the rows its tab returns, under the same search', function () {
    quoteOnSystemStatus('Tab in corso', 'Alpha one');
    quoteOnSystemStatus('Tab in corso', 'Beta one');
    quoteOnSystemStatus('Tab chiuso', 'Alpha two');

    $counts = quoteTabsResponse(['search' => 'Alpha'])['meta']['aggregates'];

    expect($counts)->toEqual(['Tab in corso' => 1, 'Tab chiuso' => 1]);

    foreach ($counts as $status => $count) {
        expect(quoteTabsResponse(quoteSystemStatusTab($status, ['search' => 'Alpha']))['items'])->toHaveCount($count);
    }
});

it('AC-004: counts only the offers the actor can see', function () {
    Sanctum::actingAs(quoteScopedUserWith(['viewAny']));

    quoteOnSystemStatus('Tab in corso');

    $response = quoteTabsResponse();

    expect(array_sum($response['meta']['aggregates'] ?? []))->toBe(count($response['items']));
});
