<?php

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\RewardType;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkOrder;
use App\Tables\Concerns\SearchesByIdNotLegacyId;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The legacy `old_id`, visible only to super-admin or to a role holding
 * `legacy-ids.view` (LegacyIdVisibility): the default hidden grid column
 * (InjectsDefaultIdColumn), the grid rows (TableService) and the record
 * Resources, plus its place in the Role form catalogue.
 *
 * @param  array<int, string>  $abilities  `<domain>.<ability>` permissions to grant
 */
function legacyIdActor(array $abilities, bool $seesLegacyIds = true): User
{
    $user = User::factory()->create();

    foreach ([...$abilities, ...($seesLegacyIds ? ['legacy-ids.view'] : [])] as $permission) {
        Permission::findOrCreate($permission);
        $user->givePermissionTo($permission);
    }

    return $user;
}

it('injects a hidden sortable and filterable old_id column right after id when the table has a legacy id', function () {
    Sanctum::actingAs(legacyIdActor(['tags.viewAny']));

    $columns = collect($this->getJson('/api/tables/tags/columns')->assertOk()->json('data.columns'));

    expect($columns->pluck('id')->take(2)->all())->toBe(['id', 'old_id']);

    expect($columns->firstWhere('id', 'old_id'))
        ->label->toBe('table.columns.old_id')
        ->type->toBe('number')
        ->visible->toBeFalse()
        ->sortable->toBeTrue()
        ->filterable->toBeTrue()
        ->filterType->toBe('number')
        ->order->toBe(2);
});

it('filters old_id with the number condition only, never a capped value checklist', function () {
    Sanctum::actingAs(legacyIdActor(['opportunities.viewAny', 'opportunities.viewAll']));

    $target = Opportunity::factory()->create(['old_id' => 173029]);
    Opportunity::factory()->create(['old_id' => 173030]);

    $column = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'old_id');

    expect($column['hasFilterValues'])->toBeFalse();

    $rows = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['old_id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 173029]],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'id'))->toBe([$target->id]);
});

it('places old_id after a table-declared id column', function () {
    Sanctum::actingAs(legacyIdActor(['users.viewAny']));

    $ids = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');
    $idPosition = $ids->search('id');

    expect($ids->filter(fn (string $id): bool => $id === 'old_id'))->toHaveCount(1)
        ->and($ids->get($idPosition + 1))->toBe('old_id');
});

it('does not inject old_id on a table without a legacy id', function () {
    Sanctum::actingAs(legacyIdActor(['reward-types.viewAny']));

    $ids = collect($this->getJson('/api/tables/reward-types/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($ids)->toContain('id')->not->toContain('old_id');
});

it('returns old_id in the rows and sorts by it', function () {
    Sanctum::actingAs(legacyIdActor(['tags.viewAny']));

    Tag::factory()->create(['old_id' => 20]);
    Tag::factory()->create(['old_id' => 30]);
    Tag::factory()->create(['old_id' => 10]);

    $rows = $this->postJson('/api/tables/tags/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'old_id', 'sort' => 'desc']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'old_id'))->toBe([30, 20, 10]);
});

it('filters the rows by old_id', function () {
    Sanctum::actingAs(legacyIdActor(['tags.viewAny']));

    Tag::factory()->create(['old_id' => 10]);
    Tag::factory()->create(['old_id' => 20]);
    Tag::factory()->create(['old_id' => 30]);

    $rows = $this->postJson('/api/tables/tags/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['old_id' => ['filterType' => 'number', 'type' => 'inRange', 'filter' => 15, 'filterTo' => 30]],
        'sortModel' => [['colId' => 'old_id', 'sort' => 'asc']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'old_id'))->toBe([20, 30]);
});

// REQUIREMENT CHANGED (user decision 2026-11-17): opportunities, quotes, work
// orders and contracts quick-search the exact id, never old_id.
it('quick-searches an opportunity by its exact id, never by old_id', function () {
    Sanctum::actingAs(legacyIdActor(['opportunities.viewAny', 'opportunities.viewAll']));

    $target = Opportunity::factory()->create(['id' => 173029, 'old_id' => 1, 'name' => 'Target']);
    Opportunity::factory()->create(['old_id' => 173029, 'name' => 'Legacy match']);

    $searchable = $this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.searchable');
    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => '173029',
    ])->assertOk();

    expect(array_column($response->json('items'), 'id'))->toBe([$target->id])
        ->and($searchable)->toContain('id')->not->toContain('old_id');
});

it('keeps old_id out of the quick search for an actor without legacy-ids.view', function () {
    Sanctum::actingAs(legacyIdActor(['opportunities.viewAny'], seesLegacyIds: false));

    Opportunity::factory()->create(['old_id' => 173029, 'name' => 'Unrelated']);

    $searchable = $this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.searchable');
    $rows = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25, 'search' => '173029'])
        ->assertOk()->json('items');

    expect($searchable)->not->toContain('old_id')
        ->and($rows)->toBeEmpty();
});

it('quick-searches the grid by exact id only: never a partial id, never old_id', function (string $domain, string $modelClass) {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($admin);

    $target = $modelClass::factory()->create(['id' => 987654]);
    $partial = $modelClass::factory()->create(['id' => 9876540]);
    $legacy = Schema::hasColumn((new $modelClass)->getTable(), 'old_id')
        ? $modelClass::factory()->create(['old_id' => 987654])
        : null;

    $search = fn (string $term): array => array_column($this->postJson("/api/tables/{$domain}/rows", [
        'startRow' => 0, 'endRow' => 25, 'search' => $term,
    ])->assertOk()->json('items'), 'id');

    expect($search('987654'))->toContain($target->id)->not->toContain($partial->id)
        ->and($search('98765'))->not->toContain($target->id);

    if ($legacy !== null) {
        expect($search('987654'))->not->toContain($legacy->id);
    }
})->with([
    'work orders' => ['work-orders', WorkOrder::class],
    'opportunities' => ['opportunities', Opportunity::class],
    'quotes' => ['quotes', Quote::class],
    'contracts' => ['contracts', Contract::class],
]);

it('sorts, filters and searches by old_id on every grid that exposes it (search: not where the grid searches by id)', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($admin);

    $domains = collect(array_keys(config('tables.definitions')))->filter(function (string $domain): bool {
        $modelClass = app(TableRegistry::class)->resolveRaw($domain)->modelClass();

        return Schema::hasColumn((new $modelClass)->getTable(), 'old_id');
    });

    expect($domains)->not->toBeEmpty();

    foreach ($domains as $domain) {
        $column = collect($this->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.columns'))
            ->firstWhere('id', 'old_id');

        expect($column)->not->toBeNull("{$domain} does not expose old_id")
            ->sortable->toBeTrue()->filterable->toBeTrue()->filterType->toBe('number')
            ->hasFilterValues->toBeFalse("{$domain} offers a capped value checklist on old_id");
        $searchesById = in_array(SearchesByIdNotLegacyId::class, class_uses_recursive(app(TableRegistry::class)->resolveRaw($domain)), true);
        expect(in_array('old_id', $this->getJson("/api/tables/{$domain}/columns")->json('data.searchable'), true))
            ->toBe(! $searchesById, "{$domain} quick search on old_id is wrong");

        $this->postJson("/api/tables/{$domain}/rows", [
            'startRow' => 0,
            'endRow' => 25,
            'filterModel' => ['old_id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 1]],
            'sortModel' => [['colId' => 'old_id', 'sort' => 'desc']],
            'search' => '1',
        ])->assertOk();
    }
});

it('leaves the row payload without old_id on a table without a legacy id', function () {
    Sanctum::actingAs(legacyIdActor(['reward-types.viewAny']));

    RewardType::factory()->create();

    $rows = $this->postJson('/api/tables/reward-types/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items');

    expect($rows)->not->toBeEmpty()
        ->and($rows[0])->not->toHaveKey('old_id');
});

it('hides the old_id column and row value from an actor without legacy-ids.view', function () {
    Sanctum::actingAs(legacyIdActor(['tags.viewAny'], seesLegacyIds: false));

    Tag::factory()->create(['old_id' => 42]);

    $ids = collect($this->getJson('/api/tables/tags/columns')->assertOk()->json('data.columns'))->pluck('id');
    $rows = $this->postJson('/api/tables/tags/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    expect($ids)->toContain('id')->not->toContain('old_id')
        ->and($rows[0])->not->toHaveKey('old_id');
});

it('shows old_id to a super-admin without the explicit permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($admin);

    $target = Tag::factory()->create(['old_id' => 7]);

    $ids = collect($this->getJson('/api/tables/tags/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($ids)->toContain('old_id');
    $this->getJson("/api/tags/{$target->id}")->assertOk()->assertJsonPath('data.old_id', 7);
});

it('exposes old_id on the record detail only to an actor who may see it', function () {
    $target = Tag::factory()->create(['old_id' => 123]);

    Sanctum::actingAs(legacyIdActor(['tags.view']));
    $this->getJson("/api/tags/{$target->id}")->assertOk()->assertJsonPath('data.old_id', 123);

    Sanctum::actingAs(legacyIdActor(['tags.view'], seesLegacyIds: false));
    expect($this->getJson("/api/tags/{$target->id}")->assertOk()->json('data'))->not->toHaveKey('old_id');
});

it('offers legacy-ids.view in the shared area of the Role form catalogue', function () {
    $this->artisan('permissions:sync');
    Sanctum::actingAs(legacyIdActor(['roles.viewAny'], seesLegacyIds: false));

    $shared = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'))
        ->firstWhere('key', 'shared');
    $module = collect($shared['resources'])->firstWhere('resource', 'legacy-ids');

    expect($module)->not->toBeNull()
        ->and(collect($module['permissions'])->pluck('name')->all())->toBe(['legacy-ids.view']);
});
