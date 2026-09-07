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
 * Direttiva utente 2026-09-07 — la Gestione Richieste espone una colonna GA3
 * accanto alla GA2 "Operatore": stessa editabilita' in-cella (una posizione
 * del pivot `quote_user`) e stessa rietichettatura per tab categoria, che ora
 * vale per ENTRAMBE le posizioni (spec 0080 esteso).
 */
uses(RefreshDatabase::class);

if (! function_exists('ga3ColumnActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function ga3ColumnActor(array $abilities = ['viewAny', 'update', 'viewAll']): User
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

if (! function_exists('ga3ColumnQuote')) {
    /**
     * An Offerta whose own `quote_user` team is $slots (position => user id),
     * with `quotes.operator_id` mirroring the OPERATOR slot exactly as
     * QuoteManagerWriter keeps it (spec 0087, INV-2).
     *
     * @param  array<int, int>  $slots
     */
    function ga3ColumnQuote(array $slots = [], ?ProductCategory $category = null): Quote
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

it('declares manager_ga3 as an editable users relation, unscoped by the Sede unlike GA2', function (): void {
    Sanctum::actingAs(ga3ColumnActor());

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns)->toHaveKey('manager_ga3');

    $ga3 = $columns['manager_ga3'];

    expect($ga3['editable'])->toBeTrue()
        ->and($ga3['label'])->toBe('requestManagement.columns.managerGa3')
        ->and($ga3['sortable'])->toBeFalse()
        ->and($ga3['filterable'])->toBeFalse()
        ->and($ga3['relation']['resource'])->toBe('users')
        // Only the Operatore slot is bound to the Sede operativa, exactly as
        // the form scopes it (operatorSlotParams).
        ->and($ga3['relation'])->not->toHaveKey('scope')
        ->and($columns['operator_ga2']['relation']['scope'])->toBe(['operational_site_id' => 'operational_site']);
});

// ---------------------------------------------------------------------------
// Rietichettatura per tab categoria (spec 0080 esteso alla posizione 3)
// ---------------------------------------------------------------------------

it('a category tab relabels BOTH G.A. columns from its own manager_labels', function (): void {
    $category = ProductCategory::factory()->create([
        'manager_labels' => ['2' => 'Operatore Tecnico', '3' => 'Back Office'],
    ]);
    Sanctum::actingAs(ga3ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('Operatore Tecnico')
        ->and($columns['manager_ga3']['label'])->toBe('Back Office');
});

it('a category defining only the level-3 label leaves operator_ga2 on its i18n key', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['3' => 'Back Office']]);
    Sanctum::actingAs(ga3ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['manager_ga3']['label'])->toBe('Back Office')
        ->and($columns['operator_ga2']['label'])->toBe('requestManagement.columns.operator');
});

it('a label configured on a position with no column of its own relabels nothing', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['4' => 'Quarto Livello']]);
    Sanctum::actingAs(ga3ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('requestManagement.columns.operator')
        ->and($columns['manager_ga3']['label'])->toBe('requestManagement.columns.managerGa3');
});

// ---------------------------------------------------------------------------
// Proiezione di riga
// ---------------------------------------------------------------------------

it('projects the GA3 slot occupant, and null when the slot is empty', function (): void {
    $actor = ga3ColumnActor();
    $ga3 = User::factory()->create();
    $operator = User::factory()->create();
    $withGa3 = ga3ColumnQuote([ManagerPositions::OPERATOR => $operator->id, ManagerPositions::GA3 => $ga3->id]);
    $withoutGa3 = ga3ColumnQuote([ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id');

    expect($rows[$withGa3->id]['manager_ga3'])->toMatchArray(['id' => $ga3->id, 'name' => $ga3->name])
        ->and($rows[$withGa3->id]['operator_ga2']['id'])->toBe($operator->id)
        ->and($rows[$withoutGa3->id]['manager_ga3'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Scrittura in-cella
// ---------------------------------------------------------------------------

it('an inline edit fills the GA3 slot without touching the Operatore', function (): void {
    $actor = ga3ColumnActor();
    $operator = User::factory()->create();
    $quote = ga3ColumnQuote([ManagerPositions::OPERATOR => $operator->id]);
    $ga3 = User::factory()->create();
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga3',
        'value' => $ga3->id,
    ])->assertOk()->json('data');

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA3)->first()?->id)->toBe($ga3->id)
        // GA3 has no denormalized column and moves no ownership: the scope
        // column stays exactly where it was.
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::OPERATOR)->first()?->id)->toBe($operator->id)
        ->and($row['manager_ga3']['id'])->toBe($ga3->id);
});

it('clearing the GA3 cell empties that slot alone', function (): void {
    $actor = ga3ColumnActor();
    $operator = User::factory()->create();
    $ga3 = User::factory()->create();
    $quote = ga3ColumnQuote([ManagerPositions::OPERATOR => $operator->id, ManagerPositions::GA3 => $ga3->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga3',
        'value' => null,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA3)->exists())->toBeFalse()
        ->and($quote->operator_id)->toBe($operator->id);
});

it('assigning the current Operatore to GA3 MOVES them instead of duplicating the row', function (): void {
    $actor = ga3ColumnActor();
    $operator = User::factory()->create();
    $quote = ga3ColumnQuote([ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga3',
        'value' => $operator->id,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->where('users.id', $operator->id)->count())->toBe(1)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA3)->first()?->id)->toBe($operator->id)
        // The denormalized column follows the pivot it mirrors (INV-2): the
        // OPERATOR slot is now empty.
        ->and($quote->operator_id)->toBeNull();
});

it('a role with manager_ga3_id non-editable is refused the write', function (): void {
    $actor = ga3ColumnActor();
    $role = Role::create(['name' => 'ga3-restricted-'.uniqid()]);
    $role->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'request-management',
        'field' => 'manager_ga3_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $restricted = User::factory()->create();
    $restricted->assignRole($role);
    $quote = ga3ColumnQuote();
    $ga3 = User::factory()->create();
    Sanctum::actingAs($restricted);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga3',
        'value' => $ga3->id,
    ])->assertForbidden();

    expect($quote->fresh()->managers()->count())->toBe(0);
});
