<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Tables\RequestManagement\RequestClientColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Direttiva utente 2026-11-09: "Ragione sociale" is a read-only column of
// Gestione Richieste and Gestione Iscritti, read from the client's card.

uses(RefreshDatabase::class);

/** @param  array<int, string>  $abilities */
function companyColumnActor(array $abilities, string $module = 'request-management'): User
{
    foreach ($abilities as $ability) {
        Permission::findOrCreate("{$module}.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("{$module}.{$ability}");
    }

    return $user;
}

function companyColumnRequest(?string $companyName): Quote
{
    $registry = Registry::factory()->create();
    $registry->personalData()->create(['type' => 'company', 'company_name' => $companyName]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

    return Quote::factory()->for($opportunity)->create();
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, int>
 */
function companyColumnRowIds(array $payload): array
{
    return collect(
        test()->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25, ...$payload])
            ->assertOk()
            ->json('items')
    )->pluck('id')->all();
}

it('shows the company name and the VAT number by default on both modules', function (string $module) {
    Sanctum::actingAs(companyColumnActor(['viewAny', 'viewAll'], $module));

    $visible = collect($this->getJson("/api/tables/{$module}/columns")->assertOk()->json('data.columns'))
        ->where('visible', true)
        ->pluck('id')
        ->all();

    expect($visible)->toContain('company_name')->toContain('vat_number');
})->with(['request-management', 'enrollee-management']);

it('projects the company name of the client card on the row', function () {
    Sanctum::actingAs(companyColumnActor(['viewAny', 'viewAll']));
    companyColumnRequest('Acme S.p.A.');

    $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonPath('items.0.company_name', 'Acme S.p.A.');
});

it('filters by the company name', function () {
    Sanctum::actingAs(companyColumnActor(['viewAny', 'viewAll']));
    $match = companyColumnRequest('Acme S.p.A.');
    companyColumnRequest('Globex S.r.l.');

    expect(companyColumnRowIds([
        'filterModel' => ['company_name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'acme']],
    ]))->toBe([$match->id]);
});

it('sorts by the company name', function () {
    Sanctum::actingAs(companyColumnActor(['viewAny', 'viewAll']));
    $zulu = companyColumnRequest('Zeta S.r.l.');
    $alpha = companyColumnRequest('Alfa S.r.l.');

    expect(companyColumnRowIds(['sortModel' => [['colId' => 'company_name', 'sort' => 'asc']]]))->toBe([$alpha->id, $zulu->id])
        ->and(companyColumnRowIds(['sortModel' => [['colId' => 'company_name', 'sort' => 'desc']]]))->toBe([$zulu->id, $alpha->id]);
});

it('lists the distinct company names, with the blank entry for a client without one', function () {
    Sanctum::actingAs(companyColumnActor(['viewAny', 'viewAll']));
    companyColumnRequest('Acme S.p.A.');
    $blank = companyColumnRequest(null);

    $values = $this->postJson('/api/tables/request-management/values', ['columnId' => 'company_name'])
        ->assertOk()
        ->json('data.values');

    expect($values)->toContain('Acme S.p.A.')
        ->and($values)->toContain(null)
        ->and(companyColumnRowIds([
            'filterModel' => ['company_name' => ['filterType' => 'set', 'values' => [null]]],
        ]))->toBe([$blank->id]);
});

it('keeps the company name read-only in the grid', function () {
    $actor = companyColumnActor(['viewAny', 'viewAll', 'update']);
    Sanctum::actingAs($actor);
    $quote = companyColumnRequest('Acme S.p.A.');

    $column = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['company_name'];

    expect($column['editable'])->toBeFalse();

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'company_name',
        'value' => 'Altra S.r.l.',
    ])->assertStatus(422);

    expect($quote->opportunity->registry->personalData->fresh()->company_name)->toBe('Acme S.p.A.');
});

// MATCH on MySQL/MariaDB needs exactly the columns of a FULLTEXT index: a column
// added to the search list without its own index breaks every quick-search,
// and SQLite would never notice.
it('searches only the columns of the FULLTEXT index, so never the company name', function () {
    Sanctum::actingAs(companyColumnActor(['viewAny', 'viewAll']));
    companyColumnRequest('Acme S.p.A.');

    expect(array_values(RequestClientColumns::SEARCHABLE_CARD_COLUMNS))
        ->toBe(['first_name', 'last_name', 'tax_code', 'vat_number'])
        ->and(companyColumnRowIds(['search' => 'acme']))->toBe([]);
});
