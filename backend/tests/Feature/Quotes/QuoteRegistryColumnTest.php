<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `registry` column of the `quotes` grid (user request 2026-11-09): the
 * client reached through the Opportunity, read-only, set-filtered and sorted
 * by name.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteRegistryViewer')) {
    function quoteRegistryViewer(): User
    {
        Permission::findOrCreate('quotes.viewAny');
        Permission::findOrCreate('quotes.viewAll', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('quotes.viewAny', 'quotes.viewAll');

        return $user;
    }
}

if (! function_exists('quoteOfRegistry')) {
    function quoteOfRegistry(string $registryName): Quote
    {
        $registry = Registry::factory()->create(['name' => $registryName]);

        return Quote::factory()->for(Opportunity::factory()->create(['registry_id' => $registry->id]))->create();
    }
}

it('declares the column read-only, sortable and set-filterable, right after the opportunity', function () {
    Sanctum::actingAs(quoteRegistryViewer());

    $columns = collect($this->getJson('/api/tables/quotes/columns')->assertOk()->json('data.columns'));
    $column = $columns->firstWhere('id', 'registry');
    $ids = $columns->pluck('id')->values();

    expect($column['label'])->toBe('quotes.columns.registry')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        ->and($column['editable'] ?? false)->toBeFalse()
        ->and($ids->search('registry'))->toBe($ids->search('opportunity') + 1);
});

it('projects the client of the linked opportunity as an id and a name', function () {
    Sanctum::actingAs(quoteRegistryViewer());
    $quote = quoteOfRegistry('Acme Srl');

    $row = collect($this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('id', $quote->id);

    expect($row['registry'])->toBe(['id' => $quote->opportunity->registry_id, 'name' => 'Acme Srl']);
});

it('filters the offers by client name', function () {
    Sanctum::actingAs(quoteRegistryViewer());
    $wanted = quoteOfRegistry('Acme Srl');
    quoteOfRegistry('Beta Spa');

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Acme Srl']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$wanted->id]);
});

it('sorts the offers by client name in both directions', function (string $direction, array $expected) {
    Sanctum::actingAs(quoteRegistryViewer());
    quoteOfRegistry('Beta Spa');
    quoteOfRegistry('Acme Srl');

    $response = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'registry', 'sort' => $direction]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('registry.name')->all())->toBe($expected);
})->with([
    'ascending' => ['asc', ['Acme Srl', 'Beta Spa']],
    'descending' => ['desc', ['Beta Spa', 'Acme Srl']],
]);

it('offers distinct client names scoped to the search', function () {
    Sanctum::actingAs(quoteRegistryViewer());
    quoteOfRegistry('Acme Srl');
    quoteOfRegistry('Acme Srl');
    quoteOfRegistry('Beta Spa');

    $this->postJson('/api/tables/quotes/values', ['columnId' => 'registry'])
        ->assertOk()->assertJsonPath('data.values', ['Acme Srl', 'Beta Spa']);

    $this->postJson('/api/tables/quotes/values', ['columnId' => 'registry', 'search' => 'Bet'])
        ->assertOk()->assertJsonPath('data.values', ['Beta Spa']);
});

it('refuses the grid to a user without the quotes permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('refuses an inline edit of the client', function () {
    Permission::findOrCreate('quotes.update');
    $actor = quoteRegistryViewer();
    $actor->givePermissionTo('quotes.update');
    Sanctum::actingAs($actor);
    $quote = quoteOfRegistry('Acme Srl');
    $other = Registry::factory()->create();

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['columnId' => 'registry', 'value' => $other->id])
        ->assertUnprocessable();

    expect($quote->opportunity->fresh()->registry_id)->not->toBe($other->id);
});
