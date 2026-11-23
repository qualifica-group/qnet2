<?php

use App\Enums\WorkflowStatusSystemKey;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteSystemStatus;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * System status tabs of the Opportunita' page (spec 0409): the hidden
 * `quote_system_status` column (the statuses of the offers, `open` without
 * offers), its set filter, and `meta.aggregates` counting opportunities.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('opportunities.viewAny', 'web'), Permission::findOrCreate('opportunities.viewAll', 'web'));
    Sanctum::actingAs($actor);
});

/** @param  array<string, mixed>  $state */
function opportunityTabsResponse(array $state = []): array
{
    return test()->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 100, ...$state])->assertOk()->json();
}

/** @return array<string, mixed> */
function opportunitySystemStatusTab(string $name, array $state = []): array
{
    return ['filterModel' => ['quote_system_status' => ['filterType' => 'set', 'values' => [$name]]], ...$state];
}

/**
 * @param  array<int, string>  $systemStatusNames  one offer per name
 */
function opportunityWithOffersOn(array $systemStatusNames, string $name = 'Plain'): Opportunity
{
    $opportunity = Opportunity::factory()->create(['name' => $name]);

    foreach ($systemStatusNames as $statusName) {
        $systemStatus = QuoteSystemStatus::query()->firstOrCreate(['name' => $statusName], QuoteSystemStatus::factory()->raw(['name' => $statusName]));
        $workingStatus = QuoteWorkflowStatus::factory()->create(['quote_system_status_id' => $systemStatus->id]);
        Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $workingStatus->id]);
    }

    return $opportunity;
}

function openSystemStatusName(): string
{
    return (string) QuoteSystemStatus::query()->where('system_key', WorkflowStatusSystemKey::Open->value)->value('name');
}

it('the cell lists the distinct system statuses of the offers, and the open one without offers', function () {
    $withOffers = opportunityWithOffersOn(['Tab in corso', 'Tab in corso', 'Tab chiuso']);
    $quoteLess = opportunityWithOffersOn([]);

    $rows = collect(opportunityTabsResponse()['items'])->keyBy('id');
    $cell = $rows[$withOffers->id]['quote_system_status'];
    $empty = $rows[$quoteLess->id]['quote_system_status'];

    expect($cell['source'])->toBe('quotes')
        ->and($cell['distinct_count'])->toBe(2)
        ->and(collect($cell['entries'])->pluck('count', 'name')->all())->toEqual(['Tab in corso' => 2, 'Tab chiuso' => 1])
        ->and($empty['source'])->toBe('default')
        ->and(collect($empty['entries'])->pluck('name')->all())->toBe([openSystemStatusName()]);
});

it('AC-002: the tab filter keeps the opportunities with an offer in the status, and the quote-less ones under open', function () {
    $running = opportunityWithOffersOn(['Tab in corso', 'Tab chiuso']);
    $closed = opportunityWithOffersOn(['Tab chiuso']);
    $quoteLess = opportunityWithOffersOn([]);

    $ids = static fn (array $response): array => collect($response['items'])->pluck('id')->sort()->values()->all();

    expect($ids(opportunityTabsResponse(opportunitySystemStatusTab('Tab chiuso'))))->toBe([$running->id, $closed->id])
        ->and($ids(opportunityTabsResponse(opportunitySystemStatusTab('Tab in corso'))))->toBe([$running->id])
        ->and($ids(opportunityTabsResponse(opportunitySystemStatusTab(openSystemStatusName()))))->toBe([$quoteLess->id]);
});

it('AC-003: counts distinct opportunities per status, quote-less ones under open, whatever tab is active', function () {
    opportunityWithOffersOn(['Tab in corso', 'Tab in corso', 'Tab chiuso']);
    opportunityWithOffersOn(['Tab chiuso']);
    opportunityWithOffersOn([]);
    opportunityWithOffersOn([]);

    $expected = ['Tab in corso' => 1, 'Tab chiuso' => 2, openSystemStatusName() => 2];

    expect(opportunityTabsResponse()['meta']['aggregates'])->toEqual($expected)
        ->and(opportunityTabsResponse(opportunitySystemStatusTab('Tab chiuso'))['meta']['aggregates'])->toEqual($expected);
});

it('AC-003: each count equals the rows its tab returns, under the same search', function () {
    opportunityWithOffersOn(['Tab in corso'], 'Alpha one');
    opportunityWithOffersOn(['Tab chiuso'], 'Beta one');
    opportunityWithOffersOn([], 'Alpha two');

    $counts = opportunityTabsResponse(['search' => 'Alpha'])['meta']['aggregates'];

    expect($counts)->toEqual(['Tab in corso' => 1, openSystemStatusName() => 1]);

    foreach ($counts as $status => $count) {
        expect(opportunityTabsResponse(opportunitySystemStatusTab($status, ['search' => 'Alpha']))['items'])->toHaveCount($count);
    }
});

it('AC-004: counts only the opportunities the actor can see', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('opportunities.viewAny', 'web'));
    Sanctum::actingAs($actor);

    opportunityWithOffersOn(['Tab in corso']);
    opportunityWithOffersOn([]);

    $response = opportunityTabsResponse();

    expect(array_sum($response['meta']['aggregates'] ?? []))->toBe(count($response['items']));
});

it('the column values list the system statuses in use, open included for quote-less rows', function () {
    opportunityWithOffersOn(['Tab chiuso']);
    opportunityWithOffersOn([]);

    $values = test()->postJson('/api/tables/opportunities/values', ['columnId' => 'quote_system_status'])->assertOk()->json('data.values');

    expect($values)->toContain('Tab chiuso', openSystemStatusName());
});
