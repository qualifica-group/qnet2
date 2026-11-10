<?php

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Task 427126: the quick-search bar of the opportunities, quotes and contracts
 * grids finds a row by its client's (anagrafica) name, like the work orders grid.
 */
uses(RefreshDatabase::class);

function clientSearchIds(string $domain, string $search): array
{
    foreach (['viewAny', 'viewAll'] as $ability) {
        Permission::findOrCreate("{$domain}.{$ability}");
    }

    Sanctum::actingAs(User::factory()->create()->givePermissionTo(["{$domain}.viewAny", "{$domain}.viewAll"]));

    return collect(test()->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25, 'search' => $search])
        ->assertOk()->json('items'))->pluck('id')->all();
}

it('finds an opportunity by its client name', function () {
    $match = Opportunity::factory()->create();
    $match->registry->update(['name' => 'ABC - ACQUA BENE COMUNE NAPOLI']);
    Opportunity::factory()->create();

    expect(clientSearchIds('opportunities', 'napoli'))->toBe([$match->id]);
});

it('finds a quote by its client name', function () {
    $match = Quote::factory()->create();
    $match->opportunity->registry->update(['name' => 'ABC - ACQUA BENE COMUNE NAPOLI']);
    Quote::factory()->create();

    expect(clientSearchIds('quotes', 'napoli'))->toBe([$match->id]);
});

it('finds a contract by its client name', function () {
    $match = Contract::factory()->create();
    $match->quote->opportunity->registry->update(['name' => 'ABC - ACQUA BENE COMUNE NAPOLI']);
    Contract::factory()->create();

    expect(clientSearchIds('contracts', 'napoli'))->toBe([$match->id]);
});
