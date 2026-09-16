<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function businessFunctionUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — columns config
// ---------------------------------------------------------------------------

it('returns the 9 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = businessFunctionUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/business-functions/columns')->assertForbidden();

    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/business-functions/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('business-functions')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'is_business_unit', 'is_business_service', 'manager', 'parent', 'users', 'operational_sites', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        ->and($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['is_business_unit']['sortable'])->toBeTrue()
        ->and($columns['is_business_unit']['filterType'])->toBe('set')
        ->and($columns['is_business_service']['filterType'])->toBe('set')
        ->and($columns['manager']['sortable'])->toBeTrue()
        ->and($columns['manager']['filterType'])->toBe('set')
        ->and($columns['parent']['sortable'])->toBeTrue()
        ->and($columns['parent']['filterType'])->toBe('set')
        ->and($columns['users']['sortable'])->toBeFalse()
        ->and($columns['users']['type'])->toBe('tags')
        ->and($columns['operational_sites']['sortable'])->toBeFalse()
        ->and($columns['operational_sites']['type'])->toBe('tags')
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = businessFunctionUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/business-functions/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-004 — rows shape (manager/users avatar objects), actions, no N+1
// ---------------------------------------------------------------------------

it('rows expose manager/parent/users/operational_sites objects and per-row actions, no sensitive fields', function () {
    $actor = businessFunctionUserWith(['viewAny', 'view', 'update', 'delete']);
    $manager = User::factory()->create(['name' => 'Manager One']);
    $member = User::factory()->create(['name' => 'Member One']);
    $parent = BusinessFunction::factory()->create(['name' => 'Parent Function']);
    $site = OperationalSite::factory()->withAddress()->create();
    $target = BusinessFunction::factory()->create([
        'name' => 'Finance',
        'manager_id' => $manager->id,
        'parent_id' => $parent->id,
    ]);
    $target->users()->sync([$member->id]);
    $target->operationalSites()->sync([$site->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk();

    $row = collect($response->json('items'))->firstWhere('name', 'Finance');

    expect($row)->not->toBeNull()
        ->and($row['manager'])->toMatchArray(['id' => $manager->id, 'name' => 'Manager One'])
        ->and($row['manager'])->toHaveKey('avatar_url')
        ->and($row['parent'])->toMatchArray(['id' => $parent->id, 'name' => 'Parent Function'])
        ->and($row['users'])->toHaveCount(1)
        ->and($row['users'][0])->toMatchArray(['id' => $member->id, 'name' => 'Member One'])
        ->and($row['operational_sites'])->toHaveCount(1)
        ->and($row['operational_sites'][0]['id'])->toBe($site->id)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete'])
        ->and($row)->not->toHaveKey('password');
});

it('rows resolve manager/users with a bounded query count (no N+1)', function () {
    $actor = businessFunctionUserWith(['viewAny']);

    foreach (range(1, 5) as $i) {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $bf = BusinessFunction::factory()->create(['manager_id' => $manager->id]);
        $bf->users()->sync([$member->id]);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/business-functions/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed, small number of queries regardless of row count (base query +
    // count query + eager-loaded manager/manager avatar/users/users avatar/
    // parent/operationalSites/operationalSites.addresses/addresses.city),
    // never one query per row.
    expect($queryCount)->toBeLessThan(15);
});

it('a manager with no associated users/sites has empty users/operational_sites arrays and a null parent', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    BusinessFunction::factory()->create(['name' => 'Lonely', 'manager_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Lonely');

    expect($row['manager'])->toBeNull()
        ->and($row['parent'])->toBeNull()
        ->and($row['users'])->toBe([])
        ->and($row['operational_sites'])->toBe([]);
});

it('filters rows by the derived manager set filter (whereHas by name)', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $alice = User::factory()->create(['name' => 'Alice Manager']);
    $bob = User::factory()->create(['name' => 'Bob Manager']);
    BusinessFunction::factory()->create(['name' => 'Team Alice', 'manager_id' => $alice->id]);
    BusinessFunction::factory()->create(['name' => 'Team Bob', 'manager_id' => $bob->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['manager' => ['filterType' => 'set', 'values' => ['Alice Manager']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Team Alice']);
});

it('sorts rows by the derived manager name via a correlated subquery', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $zed = User::factory()->create(['name' => 'Zed']);
    $amy = User::factory()->create(['name' => 'Amy']);
    BusinessFunction::factory()->create(['name' => 'Z-func', 'manager_id' => $zed->id]);
    BusinessFunction::factory()->create(['name' => 'A-func', 'manager_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'manager', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-func', $names, true))->toBeLessThan(array_search('Z-func', $names, true));
});

it('filters rows by the derived parent set filter (whereHas by name)', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $root = BusinessFunction::factory()->create(['name' => 'Root Alpha']);
    $otherRoot = BusinessFunction::factory()->create(['name' => 'Root Beta']);
    BusinessFunction::factory()->childOf($root)->create(['name' => 'Child Alpha']);
    BusinessFunction::factory()->childOf($otherRoot)->create(['name' => 'Child Beta']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['parent' => ['filterType' => 'set', 'values' => ['Root Alpha']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Child Alpha']);
});

it('sorts rows by the derived parent name via a correlated subquery', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $zed = BusinessFunction::factory()->create(['name' => 'Zed Root']);
    $amy = BusinessFunction::factory()->create(['name' => 'Amy Root']);
    BusinessFunction::factory()->childOf($zed)->create(['name' => 'Z-child']);
    BusinessFunction::factory()->childOf($amy)->create(['name' => 'A-child']);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'parent', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-child', $names, true))->toBeLessThan(array_search('Z-child', $names, true));
});

it('filters rows by the derived operational_sites set filter (whereHas by primary address line1)', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $matchingSite = OperationalSite::factory()->withAddress()->create();
    $matchingSite->primaryAddress->update(['line1' => 'Via Roma 1']);
    $otherSite = OperationalSite::factory()->withAddress()->create();
    $otherSite->primaryAddress->update(['line1' => 'Via Milano 2']);
    $target = BusinessFunction::factory()->create(['name' => 'With Roma']);
    $target->operationalSites()->sync([$matchingSite->id]);
    $other = BusinessFunction::factory()->create(['name' => 'With Milano']);
    $other->operationalSites()->sync([$otherSite->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['operational_sites' => ['filterType' => 'set', 'values' => ['Via Roma 1']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['With Roma']);
});

// ---------------------------------------------------------------------------
// AC-005 — /values distinct values for manager and users
// ---------------------------------------------------------------------------

it('resolves distinct manager names via /values', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $alice = User::factory()->create(['name' => 'Alice Manager']);
    BusinessFunction::factory()->create(['manager_id' => $alice->id]);
    BusinessFunction::factory()->create(['manager_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'manager'])->assertOk();

    // `null` first: the blank entry ("(Vuoti)"), for the rows with none.
    expect($response->json('data.values'))->toBe([null, 'Alice Manager']);
});

it('resolves distinct associated user names via /values', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $carol = User::factory()->create(['name' => 'Carol Member']);
    $bf = BusinessFunction::factory()->create();
    $bf->users()->sync([$carol->id]);
    BusinessFunction::factory()->create(); // no members
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'users'])->assertOk();

    expect($response->json('data.values'))->toBe([null, 'Carol Member']);
});

it('resolves distinct parent names via /values', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $root = BusinessFunction::factory()->create(['name' => 'Root Alpha']);
    BusinessFunction::factory()->childOf($root)->create();
    BusinessFunction::factory()->create(); // no parent
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'parent'])->assertOk();

    expect($response->json('data.values'))->toBe([null, 'Root Alpha']);
});

it('resolves distinct operational site addresses (line1) via /values', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $site = OperationalSite::factory()->withAddress()->create();
    $site->primaryAddress->update(['line1' => 'Via Torino 5']);
    $bf = BusinessFunction::factory()->create();
    $bf->operationalSites()->sync([$site->id]);
    BusinessFunction::factory()->create(); // no sites
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'operational_sites'])->assertOk();

    expect($response->json('data.values'))->toBe([null, 'Via Torino 5']);
});

it('/values search narrows the distinct manager names', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $alice = User::factory()->create(['name' => 'Alice Manager']);
    $bob = User::factory()->create(['name' => 'Bob Manager']);
    BusinessFunction::factory()->create(['manager_id' => $alice->id]);
    BusinessFunction::factory()->create(['manager_id' => $bob->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', [
        'columnId' => 'manager',
        'search' => 'alice',
    ])->assertOk();

    expect($response->json('data.values'))->toBe(['Alice Manager']);
});

it('422 on the values endpoint when columnId is not filterable', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/business-functions/values', ['columnId' => 'id'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});
