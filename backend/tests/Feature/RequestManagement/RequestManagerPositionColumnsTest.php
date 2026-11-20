<?php

use App\Authorization\RequestManagementAuthorization;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0382: one Gestore Account column per `quote_user` position, named and
 * shown per tab, replacing the fixed Tutor/Operatore/Operatore APL columns.
 * Position 5 stands in for any non-Operatore position (it superseded the
 * spec 0373 D-10 "Operatore APL" test file: REQUIREMENT CHANGED). In a
 * category tab only the named positions get a column, the others go into
 * "Altri gestori account" (D-11).
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

if (! function_exists('positionColumnsCategoryQuote')) {
    /**
     * A request filed under $category, with the given team.
     *
     * @param  array<int, int>  $slots  position => user id
     */
    function positionColumnsCategoryQuote(ProductCategory $category, array $slots): Quote
    {
        $quote = positionColumnsQuote($slots);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $quote->opportunity_id,
            'product_category_id' => $category->id,
        ]);

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

it('AC-002: a category tab shows only its named positions, and "Altri gestori account"', function (): void {
    positionColumnsQuote([6 => User::factory()->create()->id]);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore', '4' => 'Back office']]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_id={$category->id}");

    expect($columns['manager_ga1'])->toMatchArray(['label' => 'Tutor', 'visible' => true])
        ->and($columns['operator_ga2'])->toMatchArray(['label' => 'Operatore', 'visible' => true])
        ->and($columns['manager_ga4'])->toMatchArray(['label' => 'Back office', 'visible' => true])
        ->and($columns)->not->toHaveKeys(['manager_ga3', 'manager_ga5', 'manager_ga6'])
        ->and($columns['managers'])->toMatchArray(['label' => 'requestManagement.columns.otherManagers', 'visible' => true]);
});

it('AC-002: a label beyond the highest occupied position still gets its column', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['5' => 'Operatore APL']]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_id={$category->id}");

    expect($columns['manager_ga5'])->toMatchArray(['label' => 'Operatore APL', 'visible' => true])
        ->and($columns)->not->toHaveKey('manager_ga1');
});

it('AC-002: a root tab uses the labels of its root', function (): void {
    $root = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor']]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_branch_id={$root->id}");

    expect($columns['manager_ga1']['label'])->toBe('Tutor')
        ->and($columns)->not->toHaveKey('operator_ga2')
        ->and($columns['managers']['label'])->toBe('requestManagement.columns.otherManagers');
});

it('AC-002: a category with no label behaves like "Tutte"', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => null]);
    Sanctum::actingAs(positionColumnsActor());

    $columns = positionColumnsConfig("?product_category_id={$category->id}");

    expect($columns['manager_ga1'])->toMatchArray(['label' => 'Account manager 1', 'visible' => true])
        ->and($columns['operator_ga2'])->toMatchArray(['label' => 'Account manager 2', 'visible' => true])
        ->and($columns['managers']['label'])->toBe('requestManagement.columns.managers');
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

// Task 426900 (user decision 2026-11-10): whoever is newly put in a position
// is notified in that position, like the Offerta card (spec 0186 D-3); a member
// merely moved, and the actor assigning themselves, are not.
it('a position cell notifies the newcomer in that position, mail included', function (): void {
    Notification::fake();
    $quote = positionColumnsQuote([ManagerPositions::OPERATOR => User::factory()->create()->id]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs(positionColumnsActor());

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'manager_ga3',
        'value' => $newcomer->id,
    ])->assertOk();

    Notification::assertSentTo($newcomer, function (RecordAssignmentNotification $notification) use ($newcomer): bool {
        return $notification->via($newcomer) === ['database', 'mail']
            && str_contains((string) $notification->toArray($newcomer)['message'], ' 3 ');
    });
    Notification::assertCount(1);
});

it('a position cell notifies nobody for a member moved within the team, nor for the actor themselves', function (): void {
    Notification::fake();
    $tutor = User::factory()->create();
    $actor = positionColumnsActor();
    $quote = positionColumnsQuote([ManagerPositions::GA1 => $tutor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", ['column' => 'manager_ga5', 'value' => $tutor->id])->assertOk();
    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", ['column' => 'manager_ga3', 'value' => $actor->id])->assertOk();

    Notification::assertNothingSent();
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

it('AC-006: hiding a position in "Tutte" survives the reload', function (): void {
    Sanctum::actingAs(positionColumnsActor());

    $this->postJson('/api/tables/request-management/preferences', [
        'columns' => [['id' => 'manager_ga1', 'visible' => false]],
    ])->assertOk();

    expect(positionColumnsConfig()['manager_ga1']['visible'])->toBeFalse();
});

it('AC-008: in a category tab "Altri gestori account" holds only the unnamed positions', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore']]);
    $tutor = User::factory()->create(['name' => 'Tutor Person']);
    $partner = User::factory()->create(['name' => 'Partner Person']);
    $quote = positionColumnsCategoryQuote($category, [1 => $tutor->id, 3 => $partner->id]);
    Sanctum::actingAs(positionColumnsActor());

    $row = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id])
        ->assertOk()->json('items'))->keyBy('id')[$quote->id];
    $allRow = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('id')[$quote->id];

    expect(array_column($row['managers'], 'id'))->toBe([$partner->id])
        ->and($row['manager_ga1']['id'])->toBe($tutor->id)
        ->and(array_column($allRow['managers'], 'id'))->toBe([$tutor->id, $partner->id]);
});

it('AC-008: the filter and the distinct values of "Altri gestori account" ignore the named positions', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor']]);
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    positionColumnsCategoryQuote($category, [1 => $anna->id]);
    $annaAsOther = positionColumnsCategoryQuote($category, [3 => $anna->id, 1 => $zeno->id]);
    Sanctum::actingAs(positionColumnsActor());

    $ids = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'productCategoryId' => $category->id,
        'filterModel' => ['managers' => ['filterType' => 'set', 'values' => ['Anna']]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$annaAsOther->id]);

    $this->postJson('/api/tables/request-management/values', ['columnId' => 'managers', 'productCategoryId' => $category->id])
        ->assertOk()->assertJsonPath('data.values', [null, 'Anna']);
});

it('AC-009: the "Altri gestori account" cell writes only the unnamed positions', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore', '3' => 'Partner']]);
    $tutor = User::factory()->create();
    $other = User::factory()->create();
    $newcomer = User::factory()->create();
    $quote = positionColumnsCategoryQuote($category, [1 => $tutor->id, 5 => $other->id]);
    Sanctum::actingAs(positionColumnsActor());

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'managers',
        'value' => [$newcomer->id],
        'product_category_id' => $category->id,
    ])->assertOk()->json('data');

    $positions = $quote->fresh()->managers()->get()->mapWithKeys(fn (User $user): array => [$user->pivot->position => $user->id])->all();

    // The Tutor stays, the empty named positions 2-3 are skipped, the other leaves.
    expect($positions)->toBe([1 => $tutor->id, 4 => $newcomer->id])
        ->and(array_column($row['managers'], 'id'))->toBe([$newcomer->id]);
});

it('AC-009: without a tab the team cell still edits the whole team', function (): void {
    $tutor = User::factory()->create();
    $newcomer = User::factory()->create();
    $quote = positionColumnsQuote([1 => $tutor->id]);
    Sanctum::actingAs(positionColumnsActor());

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'managers',
        'value' => [$newcomer->id],
    ])->assertOk();

    expect($quote->fresh()->managers()->pluck('users.id')->all())->toBe([$newcomer->id]);
});
