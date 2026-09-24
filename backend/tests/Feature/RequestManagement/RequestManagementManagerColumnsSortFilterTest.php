<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Direttiva utente 2026-09-24 — in Gestione Richieste le colonne Operatore
 * (GA2, `quotes.operator_id`) e Tutor (GA1, posizione 1 del pivot
 * `quote_user`) sono ordinabili e filtrabili (filtro set sul nome utente,
 * voce "(Vuoti)" per lo slot vuoto). Supera AC-011 della spec 0086.
 */
uses(RefreshDatabase::class);

if (! function_exists('managerSortFilterActor')) {
    function managerSortFilterActor(): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);

        return $user;
    }
}

if (! function_exists('managerSortFilterQuote')) {
    /**
     * @param  array<int, int>  $slots  position => user id
     */
    function managerSortFilterQuote(array $slots = []): Quote
    {
        $quote = Quote::factory()->for(Opportunity::factory())->create([
            'operator_id' => $slots[ManagerPositions::OPERATOR] ?? null,
        ]);

        foreach ($slots as $position => $userId) {
            $quote->managers()->attach($userId, ['position' => $position]);
        }

        return $quote;
    }
}

if (! function_exists('managerSortFilterRowIds')) {
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    function managerSortFilterRowIds(array $payload): array
    {
        return collect(test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 25,
            ...$payload,
        ])->assertOk()->json('items'))->pluck('id')->all();
    }
}

it('declares both Gestore Account columns sortable and set-filterable', function (): void {
    Sanctum::actingAs(managerSortFilterActor());

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    foreach (['operator_ga2', 'manager_ga1'] as $id) {
        expect($columns[$id]['sortable'])->toBeTrue()
            ->and($columns[$id]['filterable'])->toBeTrue()
            ->and($columns[$id]['filterType'])->toBe('set');
    }
});

it('sorts by the Operatore name', function (string $direction, array $expected): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $zenoQuote = managerSortFilterQuote([ManagerPositions::OPERATOR => $zeno->id]);
    $annaQuote = managerSortFilterQuote([ManagerPositions::OPERATOR => $anna->id]);
    Sanctum::actingAs(managerSortFilterActor());

    $ids = managerSortFilterRowIds(['sortModel' => [['colId' => 'operator_ga2', 'sort' => $direction]]]);

    $byName = ['Anna' => $annaQuote->id, 'Zeno' => $zenoQuote->id];
    expect($ids)->toBe(array_map(static fn (string $name): int => $byName[$name], $expected));
})->with([
    'asc' => ['asc', ['Anna', 'Zeno']],
    'desc' => ['desc', ['Zeno', 'Anna']],
]);

it('sorts by the Tutor (GA1) name, ignoring the other slots', function (string $direction, array $expected): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $operator = User::factory()->create(['name' => 'Aaron']);
    $zenoQuote = managerSortFilterQuote([ManagerPositions::GA1 => $zeno->id, ManagerPositions::OPERATOR => $operator->id]);
    $annaQuote = managerSortFilterQuote([ManagerPositions::GA1 => $anna->id]);
    Sanctum::actingAs(managerSortFilterActor());

    $ids = managerSortFilterRowIds(['sortModel' => [['colId' => 'manager_ga1', 'sort' => $direction]]]);

    $byName = ['Anna' => $annaQuote->id, 'Zeno' => $zenoQuote->id];
    expect($ids)->toBe(array_map(static fn (string $name): int => $byName[$name], $expected));
})->with([
    'asc' => ['asc', ['Anna', 'Zeno']],
    'desc' => ['desc', ['Zeno', 'Anna']],
]);

it('filters by Operatore name and by the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $annaQuote = managerSortFilterQuote([ManagerPositions::OPERATOR => $anna->id]);
    managerSortFilterQuote([ManagerPositions::OPERATOR => $zeno->id]);
    $unassigned = managerSortFilterQuote();
    Sanctum::actingAs(managerSortFilterActor());

    expect(managerSortFilterRowIds(['filterModel' => ['operator_ga2' => ['filterType' => 'set', 'values' => ['Anna']]]]))
        ->toBe([$annaQuote->id])
        ->and(managerSortFilterRowIds(['filterModel' => ['operator_ga2' => ['filterType' => 'set', 'values' => [null]]]]))
        ->toBe([$unassigned->id]);
});

it('filters by Tutor name on the GA1 slot only, and by the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $annaAsTutor = managerSortFilterQuote([ManagerPositions::GA1 => $anna->id]);
    // Anna in the Operatore slot must NOT match a Tutor filter on her name.
    $annaAsOperator = managerSortFilterQuote([ManagerPositions::GA1 => $zeno->id, ManagerPositions::OPERATOR => $anna->id]);
    $noTutor = managerSortFilterQuote([ManagerPositions::OPERATOR => $zeno->id]);
    Sanctum::actingAs(managerSortFilterActor());

    expect(managerSortFilterRowIds(['filterModel' => ['manager_ga1' => ['filterType' => 'set', 'values' => ['Anna']]]]))
        ->toBe([$annaAsTutor->id])
        ->and(managerSortFilterRowIds(['filterModel' => ['manager_ga1' => ['filterType' => 'set', 'values' => [null]]]]))
        ->toBe([$noTutor->id])
        ->and(managerSortFilterRowIds(['filterModel' => ['manager_ga1' => ['filterType' => 'set', 'values' => ['Zeno', null]]]]))
        ->toEqualCanonicalizing([$annaAsOperator->id, $noTutor->id]);
});

it('lists the distinct Operatore and Tutor names, with the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    User::factory()->create(['name' => 'Nobody']);
    managerSortFilterQuote([ManagerPositions::OPERATOR => $zeno->id, ManagerPositions::GA1 => $anna->id]);
    managerSortFilterQuote();
    Sanctum::actingAs(managerSortFilterActor());

    $this->postJson('/api/tables/request-management/values', ['columnId' => 'operator_ga2'])
        ->assertOk()->assertJsonPath('data.values', [null, 'Zeno']);
    $this->postJson('/api/tables/request-management/values', ['columnId' => 'manager_ga1'])
        ->assertOk()->assertJsonPath('data.values', [null, 'Anna']);
    $this->postJson('/api/tables/request-management/values', ['columnId' => 'manager_ga1', 'search' => 'an'])
        ->assertOk()->assertJsonPath('data.values', ['Anna']);
});
