<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Status tabs of the Contratti page (spec 0397): `meta.aggregates` of the
 * `contracts` grid counts the filtered set per contract status name.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('contracts.viewAny', 'web'), Permission::findOrCreate('contracts.viewAll', 'web'));
    Sanctum::actingAs($actor);
});

/** @param  array<string, mixed>  $state */
function contractTabsResponse(array $state = []): array
{
    return test()->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 100, ...$state])->assertOk()->json();
}

function contractOnStatus(string $statusName, string $title = 'Plain'): Contract
{
    $status = ContractStatus::query()->firstOrCreate(['name' => $statusName], ContractStatus::factory()->raw(['name' => $statusName]));
    $quote = Quote::factory()->create(['title' => $title]);

    return Contract::factory()->create(['quote_id' => $quote->id, 'contract_status_id' => $status->id]);
}

it('counts every status by name, and the active tab does not change the figures', function () {
    contractOnStatus('Tab da validare');
    contractOnStatus('Tab da validare');
    contractOnStatus('Tab programmato');
    contractOnStatus('Tab disdetto');

    $expected = ['Tab da validare' => 2, 'Tab programmato' => 1, 'Tab disdetto' => 1];
    $tab = ['filterModel' => ['contract_status' => ['filterType' => 'set', 'values' => ['Tab programmato']]]];

    expect(contractTabsResponse()['meta']['aggregates'])->toEqual($expected)
        ->and(contractTabsResponse($tab)['meta']['aggregates'])->toEqual($expected)
        ->and(contractTabsResponse($tab)['items'])->toHaveCount(1);
});

it('each count equals the rows its tab returns, under the same search', function () {
    contractOnStatus('Tab da validare', 'Alpha one');
    contractOnStatus('Tab da validare', 'Beta one');
    contractOnStatus('Tab disdetto', 'Alpha two');

    $counts = contractTabsResponse(['search' => 'Alpha'])['meta']['aggregates'];

    expect($counts)->toEqual(['Tab da validare' => 1, 'Tab disdetto' => 1]);

    foreach ($counts as $status => $count) {
        $tab = ['search' => 'Alpha', 'filterModel' => ['contract_status' => ['filterType' => 'set', 'values' => [$status]]]];

        expect(contractTabsResponse($tab)['items'])->toHaveCount($count);
    }
});

it('counts only the contracts the actor can see', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('contracts.viewAny', 'web'));
    Sanctum::actingAs($actor);

    contractOnStatus('Tab da validare');

    $response = contractTabsResponse();

    expect(array_sum($response['meta']['aggregates'] ?? []))->toBe(count($response['items']));
});
