<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0417 AC-006: the Partner commerciale is a grid column (right after the
// Commerciale), editable on registries/opportunities/quotes, filterable and
// sortable, read-only on contracts.

uses(RefreshDatabase::class);

function partnerGridActor(string $module, array $abilities): User
{
    $actor = User::factory()->create();

    foreach ($abilities as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("{$module}.{$ability}", 'web'));
    }

    return $actor;
}

dataset('partner grid modules', [
    'registries' => ['registries'],
    'opportunities' => ['opportunities'],
    'quotes' => ['quotes'],
    'contracts' => ['contracts'],
]);

it('declares the commercial_partner column right after commercial, with its own advanced filter', function (string $module) {
    Sanctum::actingAs(partnerGridActor($module, ['viewAny', 'viewAll', 'update']));

    $data = $this->getJson("/api/tables/{$module}/columns")->assertOk()->json('data');
    $ids = collect($data['columns'])->pluck('id')->values()->all();
    $column = collect($data['columns'])->firstWhere('id', 'commercial_partner');

    expect(array_search('commercial_partner', $ids, true))->toBe(array_search('commercial', $ids, true) + 1)
        ->and($column['label'])->toBe("{$module}.columns.commercial_partner")
        ->and($column['editable'] ?? false)->toBe($module !== 'contracts');

    if ($module === 'registries') {
        return;
    }

    $filter = collect($data['advancedFilters'])->firstWhere('name', 'commercial_partner');

    expect($filter)->toMatchArray([
        'label' => "{$module}.advancedFilters.commercial_partner",
        'type' => 'relation',
        'multiple' => true,
        'source' => ['resource' => 'referents'],
    ]);
})->with('partner grid modules');

it('registries: row carries the partner, the cell is editable, the set filter and the sort work', function () {
    Sanctum::actingAs(partnerGridActor('registries', ['viewAny', 'update']));
    $alpha = Referent::factory()->create(['name' => 'Alfa partner']);
    $zulu = Referent::factory()->create(['name' => 'Zulu partner']);
    $withAlpha = Registry::factory()->create(['commercial_partner_id' => $alpha->id]);
    $withZulu = Registry::factory()->create(['commercial_partner_id' => $zulu->id]);
    $without = Registry::factory()->create(['commercial_partner_id' => null]);

    $rows = $this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    expect(collect($rows)->firstWhere('id', $withAlpha->id)['commercial_partner'])->toBe(['id' => $alpha->id, 'name' => 'Alfa partner']);

    $sorted = $this->postJson('/api/tables/registries/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'commercial_partner', 'sort' => 'desc']],
    ])->assertOk()->json('items');
    expect(collect($sorted)->pluck('id')->first())->toBe($withZulu->id);

    $filtered = $this->postJson('/api/tables/registries/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['commercial_partner' => ['filterType' => 'set', 'values' => ['Alfa partner']]],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('id')->all())->toBe([$withAlpha->id]);

    $this->patchJson("/api/tables/registries/rows/{$without->id}", ['column' => 'commercial_partner', 'value' => $alpha->id])
        ->assertOk()->assertJsonPath('data.commercial_partner.id', $alpha->id);
    expect($without->fresh()->commercial_partner_id)->toBe($alpha->id);
});

it('opportunities: advanced filter, sort and cell edit work on the partner', function () {
    $actor = partnerGridActor('opportunities', ['viewAny', 'viewAll', 'update']);
    Sanctum::actingAs($actor);
    $alpha = Referent::factory()->create(['name' => 'Alfa partner']);
    $zulu = Referent::factory()->create(['name' => 'Zulu partner']);
    $withAlpha = Opportunity::factory()->create(['commercial_partner_id' => $alpha->id]);
    $withZulu = Opportunity::factory()->create(['commercial_partner_id' => $zulu->id]);
    $without = Opportunity::factory()->create(['commercial_partner_id' => null]);

    $filtered = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['commercial_partner' => [$alpha->id]],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('id')->all())->toBe([$withAlpha->id])
        ->and($filtered[0]['commercial_partner'])->toBe(['id' => $alpha->id, 'name' => 'Alfa partner']);

    $sorted = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'commercial_partner', 'sort' => 'desc']],
    ])->assertOk()->json('items');
    expect(collect($sorted)->pluck('id')->first())->toBe($withZulu->id);

    $this->patchJson("/api/tables/opportunities/rows/{$without->id}", ['column' => 'commercial_partner', 'value' => $zulu->id])
        ->assertOk()->assertJsonPath('data.commercial_partner.id', $zulu->id);
    expect($without->fresh()->commercial_partner_id)->toBe($zulu->id);
});

it('opportunities: changing the registry from the grid fills an EMPTY partner and keeps a filled one', function () {
    Sanctum::actingAs(partnerGridActor('opportunities', ['viewAny', 'viewAll', 'update']));
    $inherited = Referent::factory()->create();
    $kept = Referent::factory()->create();
    $registry = Registry::factory()->create(['commercial_partner_id' => $inherited->id]);
    $empty = Opportunity::factory()->create(['commercial_partner_id' => null]);
    $filled = Opportunity::factory()->create(['commercial_partner_id' => $kept->id]);

    $this->patchJson("/api/tables/opportunities/rows/{$empty->id}", ['column' => 'registry', 'value' => $registry->id])->assertOk();
    $this->patchJson("/api/tables/opportunities/rows/{$filled->id}", ['column' => 'registry', 'value' => $registry->id])->assertOk();

    expect($empty->fresh()->commercial_partner_id)->toBe($inherited->id)
        ->and($filled->fresh()->commercial_partner_id)->toBe($kept->id);
});

it('quotes: advanced filter, sort and cell edit work on the partner', function () {
    Sanctum::actingAs(partnerGridActor('quotes', ['viewAny', 'viewAll', 'update']));
    $alpha = Referent::factory()->create(['name' => 'Alfa partner']);
    $zulu = Referent::factory()->create(['name' => 'Zulu partner']);
    $withAlpha = Quote::factory()->create(['commercial_partner_id' => $alpha->id]);
    $withZulu = Quote::factory()->create(['commercial_partner_id' => $zulu->id]);
    $without = Quote::factory()->create(['commercial_partner_id' => null]);

    $filtered = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['commercial_partner' => [$alpha->id]],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('id')->all())->toBe([$withAlpha->id])
        ->and($filtered[0]['commercial_partner'])->toBe(['id' => $alpha->id, 'name' => 'Alfa partner']);

    $sorted = $this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'commercial_partner', 'sort' => 'desc']],
    ])->assertOk()->json('items');
    expect(collect($sorted)->pluck('id')->first())->toBe($withZulu->id);

    $this->patchJson("/api/tables/quotes/rows/{$without->id}", ['column' => 'commercial_partner', 'value' => $zulu->id])
        ->assertOk()->assertJsonPath('data.commercial_partner.id', $zulu->id);
    expect($without->fresh()->commercial_partner_id)->toBe($zulu->id);
});

it('contracts: the partner column and filter are read-only and follow the quote', function () {
    Sanctum::actingAs(partnerGridActor('contracts', ['viewAny', 'viewAll', 'update']));
    $alpha = Referent::factory()->create(['name' => 'Alfa partner']);
    $zulu = Referent::factory()->create(['name' => 'Zulu partner']);
    $withAlpha = Contract::factory()->create(['quote_id' => Quote::factory()->create(['commercial_partner_id' => $alpha->id])->id]);
    $withZulu = Contract::factory()->create(['quote_id' => Quote::factory()->create(['commercial_partner_id' => $zulu->id])->id]);
    Contract::factory()->create();

    $filtered = $this->postJson('/api/tables/contracts/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['commercial_partner' => [$alpha->id]],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('id')->all())->toBe([$withAlpha->id])
        ->and($filtered[0]['commercial_partner'])->toBe(['id' => $alpha->id, 'name' => 'Alfa partner']);

    $sorted = $this->postJson('/api/tables/contracts/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'commercial_partner', 'sort' => 'desc']],
    ])->assertOk()->json('items');
    expect(collect($sorted)->pluck('id')->first())->toBe($withZulu->id);

    $this->patchJson("/api/tables/contracts/rows/{$withAlpha->id}", ['column' => 'commercial_partner', 'value' => $zulu->id])
        ->assertClientError();
    expect($withAlpha->quote->fresh()->commercial_partner_id)->toBe($alpha->id);
});
