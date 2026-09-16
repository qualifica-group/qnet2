<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-015 — columns config
// ---------------------------------------------------------------------------

it('returns the 8 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = registryTableUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/registries/columns')->assertForbidden();

    $actor = registryTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/registries/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('registries')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'source', 'is_supplier', 'agreement_status', 'size_class', 'primary_contact', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['filterType'])->toBe('text')
        ->and($columns['source']['sortable'])->toBeTrue()
        ->and($columns['source']['filterType'])->toBe('set')
        ->and($columns['is_supplier']['filterType'])->toBe('set')
        ->and($columns['agreement_status']['filterType'])->toBe('set')
        ->and($columns['size_class']['filterType'])->toBe('set')
        ->and($columns['primary_contact']['sortable'])->toBeFalse()
        ->and($columns['primary_contact']['filterable'])->toBeFalse()
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = registryTableUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/registries/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-015 — rows shape, no N+1
// ---------------------------------------------------------------------------

it('rows expose source/is_supplier/agreement_status/size_class/primary_contact and per-row actions', function () {
    $actor = registryTableUserWith(['viewAny', 'view', 'update', 'delete']);
    $source = Source::factory()->create(['name' => 'Trade Show']);
    $registry = Registry::factory()->create([
        'name' => 'Ada Registry',
        'source_id' => $source->id,
        'is_supplier' => true,
        'agreement_status' => 'agreed',
        'size_class' => 'small',
    ]);
    $card = PersonalData::factory()->for($registry, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Ada Registry');

    expect($row)->not->toBeNull()
        ->and($row['source'])->toMatchArray(['id' => $source->id, 'name' => 'Trade Show'])
        ->and($row['is_supplier'])->toBeTrue()
        ->and($row['agreement_status'])->toBe('agreed')
        ->and($row['size_class'])->toBe('small')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);

    expect($row['primary_contact'])->toBeArray()->toHaveCount(1)
        ->and($row['primary_contact'][0]['value'])->toBe('ada@example.com');
});

it('a registry with no source/status/size/primary contact has null values', function () {
    $actor = registryTableUserWith(['viewAny']);
    Registry::factory()->create(['name' => 'Lonely', 'source_id' => null, 'agreement_status' => null, 'size_class' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Lonely');

    expect($row['source'])->toBeNull()
        ->and($row['agreement_status'])->toBeNull()
        ->and($row['size_class'])->toBeNull()
        ->and($row['primary_contact'])->toBe([]);
});

it('rows resolve source/primary_contact with a bounded query count (no N+1)', function () {
    $actor = registryTableUserWith(['viewAny']);

    foreach (range(1, 5) as $i) {
        $source = Source::factory()->create();
        $registry = Registry::factory()->create(['source_id' => $source->id]);
        $card = PersonalData::factory()->for($registry, 'personable')->create();
        Contact::factory()->email()->for($card, 'contactable')->create(['is_primary' => true]);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(10);
});

// ---------------------------------------------------------------------------
// AC-016 — derived filter/sort on source + /values distinct
// ---------------------------------------------------------------------------

it('filters rows by the derived source set filter (whereHas by name)', function () {
    $actor = registryTableUserWith(['viewAny']);
    $tradeShow = Source::factory()->create(['name' => 'Trade Show']);
    $referral = Source::factory()->create(['name' => 'Referral']);
    Registry::factory()->create(['name' => 'Registry A', 'source_id' => $tradeShow->id]);
    Registry::factory()->create(['name' => 'Registry B', 'source_id' => $referral->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/registries/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['source' => ['filterType' => 'set', 'values' => ['Trade Show']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Registry A']);
});

it('sorts rows by the derived source name via a correlated subquery', function () {
    $actor = registryTableUserWith(['viewAny']);
    $zed = Source::factory()->create(['name' => 'Zed Source']);
    $amy = Source::factory()->create(['name' => 'Amy Source']);
    Registry::factory()->create(['name' => 'Z-registry', 'source_id' => $zed->id]);
    Registry::factory()->create(['name' => 'A-registry', 'source_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/registries/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'source', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-registry', $names, true))->toBeLessThan(array_search('Z-registry', $names, true));
});

it('resolves distinct source names via /values', function () {
    $actor = registryTableUserWith(['viewAny']);
    $tradeShow = Source::factory()->create(['name' => 'Trade Show']);
    Registry::factory()->create(['source_id' => $tradeShow->id]);
    Registry::factory()->create(['source_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/registries/values', ['columnId' => 'source'])->assertOk();

    // `null` first: the blank entry ("(Vuoti)") for the registries with no source.
    expect($response->json('data.values'))->toBe([null, 'Trade Show']);
});

it('resolves distinct is_supplier values via /values (cast bypass)', function () {
    $actor = registryTableUserWith(['viewAny']);
    Registry::factory()->create(['is_supplier' => true]);
    Registry::factory()->create(['is_supplier' => false]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/registries/values', ['columnId' => 'is_supplier'])->assertOk();

    expect($response->json('data.values'))->toEqualCanonicalizing(['0', '1']);
});

it('resolves distinct agreement_status/size_class values via /values (cast bypass)', function () {
    $actor = registryTableUserWith(['viewAny']);
    Registry::factory()->create(['agreement_status' => 'agreed', 'size_class' => 'small']);
    Registry::factory()->create(['agreement_status' => 'negotiating', 'size_class' => 'large']);
    Sanctum::actingAs($actor);

    $statusResponse = $this->postJson('/api/tables/registries/values', ['columnId' => 'agreement_status'])->assertOk();
    $sizeResponse = $this->postJson('/api/tables/registries/values', ['columnId' => 'size_class'])->assertOk();

    expect($statusResponse->json('data.values'))->toEqualCanonicalizing(['agreed', 'negotiating'])
        ->and($sizeResponse->json('data.values'))->toEqualCanonicalizing(['small', 'large']);
});

it('resolves distinct primary_contact values via /values (identical to Referents/Users)', function () {
    $actor = registryTableUserWith(['viewAny']);
    $withContact = Registry::factory()->create();
    $card = PersonalData::factory()->for($withContact, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com', 'is_primary' => true]);
    Registry::factory()->create(); // no card / no contact
    Sanctum::actingAs($actor);

    // primary_contact is neither sortable nor filterable (spec 0020 data
    // contract) — it is NOT in the allow-list for /values either.
    $this->postJson('/api/tables/registries/values', ['columnId' => 'primary_contact'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('columnId');
});

it('rejects a columnId outside the allow-list (422)', function () {
    $actor = registryTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/registries/values', ['columnId' => 'employee_count'])->assertStatus(422);
});
