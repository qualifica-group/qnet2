<?php

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0373 D-10: the "Operatore APL" column exposes position 5 of the
 * `quote_user` team exactly as the Tutor column exposes position 1.
 */
uses(RefreshDatabase::class);

if (! function_exists('ga5ColumnActor')) {
    function ga5ColumnActor(string $module = 'request-management'): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(["{$module}.viewAny", "{$module}.update", "{$module}.viewAll"]);

        return $user;
    }
}

if (! function_exists('ga5ColumnQuote')) {
    /**
     * @param  array<int, int>  $slots  position => user id
     */
    function ga5ColumnQuote(array $slots = []): Quote
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

if (! function_exists('ga5ColumnRowIds')) {
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    function ga5ColumnRowIds(array $payload): array
    {
        return collect(test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 25,
            ...$payload,
        ])->assertOk()->json('items'))->pluck('id')->all();
    }
}

it('declares manager_ga5 like the Tutor column, in both modules', function (string $module): void {
    Sanctum::actingAs(ga5ColumnActor($module));

    $column = collect($this->getJson("/api/tables/{$module}/columns")->assertOk()->json('data.columns'))
        ->keyBy('id')['manager_ga5'];

    expect($column['label'])->toBe('requestManagement.columns.managerGa5')
        ->and($column['visible'])->toBeTrue()
        ->and($column['editable'])->toBeTrue()
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        ->and($column['relation']['resource'])->toBe('users')
        ->and($column['relation'])->not->toHaveKey('scope');
})->with(['request-management', 'enrollee-management']);

it('a category tab relabels the column from its level-5 label', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['5' => 'Referente APL']]);
    Sanctum::actingAs(ga5ColumnActor());

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['manager_ga5']['label'])->toBe('Referente APL')
        ->and($columns['manager_ga1']['label'])->toBe('requestManagement.columns.managerGa1');
});

it('projects the position 5 occupant, and null when the slot is empty', function (): void {
    $actor = ga5ColumnActor();
    $apl = User::factory()->create();
    $tutor = User::factory()->create();
    $withApl = ga5ColumnQuote([ManagerPositions::GA1 => $tutor->id, ManagerPositions::GA5 => $apl->id]);
    $withoutApl = ga5ColumnQuote([ManagerPositions::GA1 => $tutor->id]);
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id');

    expect($rows[$withApl->id]['manager_ga5'])->toMatchArray(['id' => $apl->id, 'name' => $apl->name])
        ->and($rows[$withoutApl->id]['manager_ga5'])->toBeNull();
});

it('an inline edit fills position 5 and leaves every other slot in place', function (): void {
    $actor = ga5ColumnActor();
    $operator = User::factory()->create();
    $tutor = User::factory()->create();
    $quote = ga5ColumnQuote([ManagerPositions::GA1 => $tutor->id, ManagerPositions::OPERATOR => $operator->id]);
    $apl = User::factory()->create();
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => $apl->id,
    ])->assertOk()->json('data');

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA5)->first()?->id)->toBe($apl->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA1)->first()?->id)->toBe($tutor->id)
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($row['manager_ga5']['id'])->toBe($apl->id);
});

it('an inline edit writes position 5 on the Iscritti grid too', function (): void {
    $actor = ga5ColumnActor('enrollee-management');
    $validated = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'validated')->value('id')
        ?? QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id;
    $quote = Quote::factory()->for(Opportunity::factory())->create(['quote_workflow_status_id' => $validated]);
    $apl = User::factory()->create();
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/enrollee-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => $apl->id,
    ])->assertOk()->json('data');

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA5)->first()?->id)->toBe($apl->id)
        ->and($row['manager_ga5']['id'])->toBe($apl->id);
});

it('clearing the cell empties position 5 alone', function (): void {
    $actor = ga5ColumnActor();
    $operator = User::factory()->create();
    $apl = User::factory()->create();
    $quote = ga5ColumnQuote([ManagerPositions::OPERATOR => $operator->id, ManagerPositions::GA5 => $apl->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => null,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', ManagerPositions::GA5)->exists())->toBeFalse()
        ->and($quote->operator_id)->toBe($operator->id);
});

it('a person already on the team is moved to position 5, not duplicated', function (): void {
    $actor = ga5ColumnActor();
    $tutor = User::factory()->create();
    $quote = ga5ColumnQuote([ManagerPositions::GA1 => $tutor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => $tutor->id,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->where('users.id', $tutor->id)->count())->toBe(1)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA5)->first()?->id)->toBe($tutor->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA1)->exists())->toBeFalse();
});

it('a role with manager_ga5_id non-editable is refused the write', function (): void {
    ga5ColumnActor();
    $role = Role::create(['name' => 'ga5-restricted-'.uniqid()]);
    $role->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'request-management',
        'field' => 'manager_ga5_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $restricted = User::factory()->create();
    $restricted->assignRole($role);
    $quote = ga5ColumnQuote();
    $apl = User::factory()->create();
    Sanctum::actingAs($restricted);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => $apl->id,
    ])->assertForbidden();

    expect($quote->fresh()->managers()->count())->toBe(0);
});

it('sorts by the name in position 5, ignoring the other slots', function (string $direction, array $expected): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $tutor = User::factory()->create(['name' => 'Aaron']);
    $zenoQuote = ga5ColumnQuote([ManagerPositions::GA1 => $tutor->id, ManagerPositions::GA5 => $zeno->id]);
    $annaQuote = ga5ColumnQuote([ManagerPositions::GA5 => $anna->id]);
    Sanctum::actingAs(ga5ColumnActor());

    $ids = ga5ColumnRowIds(['sortModel' => [['colId' => 'manager_ga5', 'sort' => $direction]]]);

    $byName = ['Anna' => $annaQuote->id, 'Zeno' => $zenoQuote->id];
    expect($ids)->toBe(array_map(static fn (string $name): int => $byName[$name], $expected));
})->with([
    'asc' => ['asc', ['Anna', 'Zeno']],
    'desc' => ['desc', ['Zeno', 'Anna']],
]);

it('filters by the name in position 5 only, and by the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $annaAsApl = ga5ColumnQuote([ManagerPositions::GA5 => $anna->id]);
    // Anna as Tutor must NOT match a filter on the APL column.
    ga5ColumnQuote([ManagerPositions::GA1 => $anna->id, ManagerPositions::GA5 => $zeno->id]);
    $noApl = ga5ColumnQuote([ManagerPositions::OPERATOR => $zeno->id]);
    Sanctum::actingAs(ga5ColumnActor());

    expect(ga5ColumnRowIds(['filterModel' => ['manager_ga5' => ['filterType' => 'set', 'values' => ['Anna']]]]))
        ->toBe([$annaAsApl->id])
        ->and(ga5ColumnRowIds(['filterModel' => ['manager_ga5' => ['filterType' => 'set', 'values' => [null]]]]))
        ->toBe([$noApl->id]);
});

it('lists the distinct names in position 5, with the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    ga5ColumnQuote([ManagerPositions::GA1 => $zeno->id, ManagerPositions::GA5 => $anna->id]);
    ga5ColumnQuote();
    Sanctum::actingAs(ga5ColumnActor());

    $this->postJson('/api/tables/request-management/values', ['columnId' => 'manager_ga5'])
        ->assertOk()->assertJsonPath('data.values', [null, 'Anna']);
});
