<?php

use App\Authorization\RequestManagementAuthorization;
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
 * Spec 0382: one Gestore Account column per `quote_user` position, named and
 * shown per tab, replacing the fixed Tutor/Operatore/Operatore APL columns.
 * Position 5 stands in for any non-Operatore position (it superseded the
 * spec 0373 D-10 "Operatore APL" test file: REQUIREMENT CHANGED).
 */
uses(RefreshDatabase::class);

if (! function_exists('positionColumnsActor')) {
    function positionColumnsActor(string $module = 'request-management'): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(["{$module}.viewAny", "{$module}.update", "{$module}.viewAll"]);

        return $user;
    }
}

if (! function_exists('positionColumnsQuote')) {
    /**
     * @param  array<int, int>  $slots  position => user id
     */
    function positionColumnsQuote(array $slots = []): Quote
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

if (! function_exists('positionColumnsConfig')) {
    /**
     * The config columns keyed by id.
     *
     * @return array<string, array<string, mixed>>
     */
    function positionColumnsConfig(string $query = '', string $module = 'request-management'): array
    {
        return collect(test()->getJson("/api/tables/{$module}/columns{$query}")->assertOk()->json('data.columns'))
            ->keyBy('id')->all();
    }
}

if (! function_exists('positionColumnsRowIds')) {
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    function positionColumnsRowIds(array $payload): array
    {
        return collect(test()->postJson('/api/tables/request-management/rows', [
            'startRow' => 0,
            'endRow' => 25,
            ...$payload,
        ])->assertOk()->json('items'))->pluck('id')->all();
    }
}

it('AC-001: "Tutte" shows "Gestore account 1..N" up to the highest occupied position, all visible', function (string $module): void {
    positionColumnsQuote([4 => User::factory()->create()->id]);
    Sanctum::actingAs(positionColumnsActor($module));

    $columns = positionColumnsConfig('', $module);

    foreach ([1 => 'manager_ga1', 2 => 'operator_ga2', 3 => 'manager_ga3', 4 => 'manager_ga4'] as $position => $id) {
        expect($columns[$id]['label'])->toBe("Account manager {$position}")
            ->and($columns[$id]['visible'])->toBeTrue()
            ->and($columns[$id]['sortable'])->toBeTrue()
            ->and($columns[$id]['filterType'])->toBe('set')
            ->and($columns[$id]['editable'])->toBeTrue()
            ->and($columns[$id]['relation']['resource'])->toBe('users');
    }

    expect($columns)->not->toHaveKey('manager_ga5')
        ->and($columns['manager_ga1']['relation'])->not->toHaveKey('scope');
})->with(['request-management', 'enrollee-management']);

it('AC-001: with no team at all the columns still reach the Operatore position', function (): void {
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig();

    expect($columns)->toHaveKeys(['manager_ga1', 'operator_ga2'])
        ->and($columns)->not->toHaveKey('manager_ga3');
});

it('AC-001: the position columns sit together after "Fonte", in position order, before "Gestori account"', function (): void {
    positionColumnsQuote([3 => User::factory()->create()->id]);
    Sanctum::actingAs(positionColumnsActor());

    $visible = collect(positionColumnsConfig())->filter(fn (array $c): bool => $c['visible'])->sortBy('order')->keys()->values()->all();
    $from = array_search('source', $visible, true);

    expect(array_slice($visible, $from, 5))->toBe(['source', 'manager_ga1', 'operator_ga2', 'manager_ga3', 'managers']);
});

it('AC-002: a category tab names its labelled positions and hides the others', function (): void {
    positionColumnsQuote([6 => User::factory()->create()->id]);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore', '4' => 'Back office']]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_id={$category->id}");

    expect($columns['manager_ga1'])->toMatchArray(['label' => 'Tutor', 'visible' => true])
        ->and($columns['operator_ga2'])->toMatchArray(['label' => 'Operatore', 'visible' => true])
        ->and($columns['manager_ga3'])->toMatchArray(['label' => 'Account manager 3', 'visible' => false])
        ->and($columns['manager_ga4'])->toMatchArray(['label' => 'Back office', 'visible' => true])
        ->and($columns['manager_ga6'])->toMatchArray(['label' => 'Account manager 6', 'visible' => false])
        ->and($columns)->not->toHaveKey('manager_ga7');
});

it('AC-002: a label beyond the highest occupied position still gets its column', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['5' => 'Operatore APL']]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_id={$category->id}");

    expect($columns['manager_ga5'])->toMatchArray(['label' => 'Operatore APL', 'visible' => true])
        ->and($columns['manager_ga1']['visible'])->toBeFalse();
});

it('AC-002: a category with no label behaves like "Tutte"', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => null]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_id={$category->id}");

    expect($columns['manager_ga1'])->toMatchArray(['label' => 'Account manager 1', 'visible' => true])
        ->and($columns['operator_ga2'])->toMatchArray(['label' => 'Account manager 2', 'visible' => true]);
});

it('AC-002: the Italian header reads "Gestore account N"', function (): void {
    Sanctum::actingAs(positionColumnsActor());

    $columns = collect($this->withHeader('Accept-Language', 'it')->getJson('/api/tables/request-management/columns')
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('Gestore account 2');
});

it('AC-003: projects the occupant of every position, and null when the slot is empty', function (): void {
    $apl = User::factory()->create();
    $tutor = User::factory()->create();
    $withApl = positionColumnsQuote([ManagerPositions::GA1 => $tutor->id, 5 => $apl->id]);
    $withoutApl = positionColumnsQuote([ManagerPositions::GA1 => $tutor->id]);
    Sanctum::actingAs(positionColumnsActor());

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id');

    expect($rows[$withApl->id]['manager_ga5'])->toMatchArray(['id' => $apl->id, 'name' => $apl->name])
        ->and($rows[$withApl->id]['manager_ga1'])->toMatchArray(['id' => $tutor->id])
        ->and($rows[$withoutApl->id]['manager_ga5'])->toBeNull()
        ->and($rows[$withoutApl->id])->toHaveKey('manager_ga12');
});

it('AC-003: sorts by the name in one position, ignoring the other slots', function (string $direction, array $expected): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $tutor = User::factory()->create(['name' => 'Aaron']);
    $zenoQuote = positionColumnsQuote([ManagerPositions::GA1 => $tutor->id, 5 => $zeno->id]);
    $annaQuote = positionColumnsQuote([5 => $anna->id]);
    Sanctum::actingAs(positionColumnsActor());

    $ids = positionColumnsRowIds(['sortModel' => [['colId' => 'manager_ga5', 'sort' => $direction]]]);

    $byName = ['Anna' => $annaQuote->id, 'Zeno' => $zenoQuote->id];
    expect($ids)->toBe(array_map(static fn (string $name): int => $byName[$name], $expected));
})->with([
    'asc' => ['asc', ['Anna', 'Zeno']],
    'desc' => ['desc', ['Zeno', 'Anna']],
]);

it('AC-003: filters by the name in one position only, and by the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $annaThere = positionColumnsQuote([5 => $anna->id]);
    // Anna in position 1 must NOT match a filter on position 5.
    positionColumnsQuote([ManagerPositions::GA1 => $anna->id, 5 => $zeno->id]);
    $empty = positionColumnsQuote([ManagerPositions::OPERATOR => $zeno->id]);
    Sanctum::actingAs(positionColumnsActor());

    expect(positionColumnsRowIds(['filterModel' => ['manager_ga5' => ['filterType' => 'set', 'values' => ['Anna']]]]))
        ->toBe([$annaThere->id])
        ->and(positionColumnsRowIds(['filterModel' => ['manager_ga5' => ['filterType' => 'set', 'values' => [null]]]]))
        ->toBe([$empty->id]);
});

it('AC-003: lists the distinct names in one position, with the blank entry', function (): void {
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    positionColumnsQuote([ManagerPositions::GA1 => $zeno->id, 5 => $anna->id]);
    positionColumnsQuote();
    Sanctum::actingAs(positionColumnsActor());

    $this->postJson('/api/tables/request-management/values', ['columnId' => 'manager_ga5'])
        ->assertOk()->assertJsonPath('data.values', [null, 'Anna']);
});

it('AC-004: an inline edit fills one position and leaves every other slot in place', function (): void {
    $operator = User::factory()->create();
    $tutor = User::factory()->create();
    $quote = positionColumnsQuote([ManagerPositions::GA1 => $tutor->id, ManagerPositions::OPERATOR => $operator->id]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs(positionColumnsActor());

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => $newcomer->id,
    ])->assertOk()->json('data');

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', 5)->first()?->id)->toBe($newcomer->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA1)->first()?->id)->toBe($tutor->id)
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($row['manager_ga5']['id'])->toBe($newcomer->id);
});

it('AC-004: an inline edit writes a position on the Iscritti grid too', function (): void {
    $actor = positionColumnsActor('enrollee-management');
    $validated = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'validated')->value('id')
        ?? QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id;
    $quote = Quote::factory()->for(Opportunity::factory())->create(['quote_workflow_status_id' => $validated]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/enrollee-management/rows/{$quote->id}", [
        'column' => 'manager_ga3',
        'value' => $newcomer->id,
    ])->assertOk()->json('data');

    expect($quote->managers()->wherePivot('position', 3)->first()?->id)->toBe($newcomer->id)
        ->and($row['manager_ga3']['id'])->toBe($newcomer->id);
});

it('AC-004: clearing the cell empties that position alone', function (): void {
    $operator = User::factory()->create();
    $other = User::factory()->create();
    $quote = positionColumnsQuote([ManagerPositions::OPERATOR => $operator->id, 5 => $other->id]);
    Sanctum::actingAs(positionColumnsActor());

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => null,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->wherePivot('position', 5)->exists())->toBeFalse()
        ->and($quote->operator_id)->toBe($operator->id);
});

it('AC-004: a person already on the team is moved, not duplicated', function (): void {
    $tutor = User::factory()->create();
    $quote = positionColumnsQuote([ManagerPositions::GA1 => $tutor->id]);
    Sanctum::actingAs(positionColumnsActor());

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga5',
        'value' => $tutor->id,
    ])->assertOk();

    $quote->refresh();

    expect($quote->managers()->where('users.id', $tutor->id)->count())->toBe(1)
        ->and($quote->managers()->wherePivot('position', 5)->first()?->id)->toBe($tutor->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::GA1)->exists())->toBeFalse();
});

it('AC-004: the position-2 cell still writes the Operatore', function (): void {
    $quote = positionColumnsQuote();
    $operator = User::factory()->create();
    Sanctum::actingAs(positionColumnsActor());

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $operator->id,
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('AC-005: with the team key non-editable every position cell is read-only and refused', function (): void {
    positionColumnsActor();
    $role = Role::create(['name' => 'team-restricted-'.uniqid()]);
    $role->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'request-management',
        'field' => 'manager_slots',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $restricted = User::factory()->create();
    $restricted->assignRole($role);
    $quote = positionColumnsQuote();
    Sanctum::actingAs($restricted);

    $columns = positionColumnsConfig();

    expect($columns['manager_ga1']['editable'])->toBeFalse()
        ->and($columns['operator_ga2']['editable'])->toBeFalse();

    foreach (['manager_ga1', 'operator_ga2'] as $columnId) {
        $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
            'column' => $columnId,
            'value' => User::factory()->create()->id,
        ])->assertForbidden();
    }

    expect($quote->fresh()->managers()->count())->toBe(0);
});

it('AC-005: the single-position keys are no longer in the role matrix', function (): void {
    $keys = array_column(app(RequestManagementAuthorization::class)->fields(), 'key');

    expect($keys)->toContain('manager_slots')
        ->and($keys)->not->toContain('manager_ga1_id')
        ->and($keys)->not->toContain('manager_ga5_id');
});

it('AC-006: showing a hidden position in a category tab survives the reload', function (): void {
    positionColumnsQuote([3 => User::factory()->create()->id]);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor']]);
    Sanctum::actingAs(positionColumnsActor());

    $this->postJson('/api/tables/request-management/preferences', [
        'product_category_id' => $category->id,
        'columns' => [['id' => 'manager_ga3', 'visible' => true]],
    ])->assertOk();

    expect(positionColumnsConfig("?product_category_id={$category->id}")['manager_ga3']['visible'])->toBeTrue()
        ->and(positionColumnsConfig("?product_category_id={$category->id}")['operator_ga2']['visible'])->toBeFalse();
});

it('AC-006: hiding a position in "Tutte" survives the reload', function (): void {
    Sanctum::actingAs(positionColumnsActor());

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'manager_ga1', 'visible' => false]],
    ])->assertOk();

    expect(positionColumnsConfig()['manager_ga1']['visible'])->toBeFalse();
});
