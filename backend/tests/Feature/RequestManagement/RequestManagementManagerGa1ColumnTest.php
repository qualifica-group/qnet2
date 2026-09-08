<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Direttiva utente 2026-09-07 — la Gestione Richieste espone una colonna GA1
 * accanto alla GA2 "Operatore": stessa editabilita' in-cella (una posizione
 * del pivot `quote_user`) e stessa rietichettatura per tab categoria, che ora
 * vale per ENTRAMBE le posizioni (spec 0080 esteso).
 */
uses(RefreshDatabase::class);

if (! function_exists('ga1ColumnActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function ga1ColumnActor(array $abilities = ['viewAny', 'update', 'viewAll']): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('ga1ColumnQuote')) {
    /**
     * An Offerta whose own `quote_user` team is $slots (position => user id),
     * with `quotes.operator_id` mirroring the OPERATOR slot exactly as
     * QuoteManagerWriter keeps it (spec 0087, INV-2).
     *
     * @param  array<int, int>  $slots
     */
    function ga1ColumnQuote(array $slots = [], ?ProductCategory $category = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        if ($category !== null) {
            OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
        }

        $quote = Quote::factory()->for($opportunity)->create([
            'operator_id' => $slots[ManagerPositions::OPERATOR] ?? null,
        ]);

        foreach ($slots as $position => $userId) {
            $quote->managers()->attach($userId, ['position' => $position]);
            $opportunity->managers()->attach($userId, ['position' => $position]);
        }

        return $quote;
    }
}

// ---------------------------------------------------------------------------
// Catalogo colonna
// ---------------------------------------------------------------------------

it('declares manager_ga1 as an editable users relation, unscoped by the Sede unlike GA2', function (): void {
    Sanctum::actingAs(ga1ColumnActor());

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns)->toHaveKey('manager_ga1');

    $ga1 = $columns['manager_ga1'];

    expect($ga1['editable'])->toBeTrue()
        ->and($ga1['label'])->toBe('requestManagement.columns.managerGa1')
        ->and($ga1['sortable'])->toBeFalse()
        ->and($ga1['filterable'])->toBeFalse()
        ->and($ga1['relation']['resource'])->toBe('users')
        // Only the Operatore slot is bound to the Sede operativa, exactly as
        // the form scopes it (operatorSlotParams).
        ->and($ga1['relation'])->not->toHaveKey('scope')
        ->and($columns['operator_ga2']['relation']['scope'])->toBe(['operational_site_id' => 'operational_site']);
});

// ---------------------------------------------------------------------------
// Rietichettatura per tab categoria (spec 0080 esteso alla posizione 1)
// ---------------------------------------------------------------------------

it('a category tab relabels BOTH G.A. columns from its own manager_labels', function (): void {
    $category = ProductCategory::factory()->create([
        'manager_labels' => ['2' => 'Operatore Tecnico', '1' => 'Back Office'],
    ]);
    Sanctum::actingAs(ga1ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('Operatore Tecnico')
        ->and($columns['manager_ga1']['label'])->toBe('Back Office');
});

it('a category defining only the level-1 label leaves operator_ga2 on its i18n key', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Back Office']]);
    Sanctum::actingAs(ga1ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['manager_ga1']['label'])->toBe('Back Office')
        ->and($columns['operator_ga2']['label'])->toBe('requestManagement.columns.operator');
});

it('a label configured on a position with no column of its own relabels nothing', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['4' => 'Quarto Livello']]);
    Sanctum::actingAs(ga1ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('requestManagement.columns.operator')
        ->and($columns['manager_ga1']['label'])->toBe('requestManagement.columns.managerGa1');
});

// ---------------------------------------------------------------------------
// Proiezione di riga
// ---------------------------------------------------------------------------

it('projects the GA1 slot occupant, and null when the slot is empty', function (): void {
    $actor = ga1ColumnActor();
    $ga1 = User::factory()->create();
    $operator = User::factory()->create();
    $withGa1 = ga1ColumnQuote([ManagerPositions::OPERATOR => $operator->id, ManagerPositions::GA1 => $ga1->id]);
    $withoutGa1 = ga1ColumnQuote([ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id');

    expect($rows[$withGa1->id]['manager_ga1'])->toMatchArray(['id' => $ga1->id, 'name' => $ga1->name])
        ->and($rows[$withGa1->id]['operator_ga2']['id'])->toBe($operator->id)
        ->and($rows[$withoutGa1->id]['manager_ga1'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Scrittura in-cella
// ---------------------------------------------------------------------------

it('an inline edit fills the GA1 slot without touching the Operatore', function (): void {
    $actor = ga1ColumnActor();
    $operator = User::factory()->create();
    $quote = ga1ColumnQuote([ManagerPositions::OPERATOR => $operator->id]);
    $ga1 = User::factory()->create();
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga1',
        'value' => $ga1->id,
    ])->assertOk()->json('data');

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA1)->first()?->id)->toBe($ga1->id)
        // GA1 has no denormalized column and moves no ownership: the scope
        // column stays exactly where it was.
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::OPERATOR)->first()?->id)->toBe($operator->id)
        ->and($row['manager_ga1']['id'])->toBe($ga1->id);
});

it('clearing the GA1 cell empties that slot alone', function (): void {
    $actor = ga1ColumnActor();
    $operator = User::factory()->create();
    $ga1 = User::factory()->create();
    $quote = ga1ColumnQuote([ManagerPositions::OPERATOR => $operator->id, ManagerPositions::GA1 => $ga1->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga1',
        'value' => null,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA1)->exists())->toBeFalse()
        ->and($quote->operator_id)->toBe($operator->id);
});

it('assigning the current Operatore to GA1 MOVES them instead of duplicating the row', function (): void {
    $actor = ga1ColumnActor();
    $operator = User::factory()->create();
    $quote = ga1ColumnQuote([ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga1',
        'value' => $operator->id,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->where('users.id', $operator->id)->count())->toBe(1)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA1)->first()?->id)->toBe($operator->id)
        // The denormalized column follows the pivot it mirrors (INV-2): the
        // OPERATOR slot is now empty.
        ->and($quote->operator_id)->toBeNull();
});

it('a role with manager_ga1_id non-editable is refused the write', function (): void {
    $actor = ga1ColumnActor();
    $role = Role::create(['name' => 'ga1-restricted-'.uniqid()]);
    $role->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'request-management',
        'field' => 'manager_ga1_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $restricted = User::factory()->create();
    $restricted->assignRole($role);
    $quote = ga1ColumnQuote();
    $ga1 = User::factory()->create();
    Sanctum::actingAs($restricted);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga1',
        'value' => $ga1->id,
    ])->assertForbidden();

    expect($quote->fresh()->managers()->count())->toBe(0);
});
