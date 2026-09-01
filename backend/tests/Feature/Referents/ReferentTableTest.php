<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-015 — columns config
// ---------------------------------------------------------------------------

it('returns the 7 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = referentUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/referents/columns')->assertForbidden();

    $actor = referentUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/referents/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('referents')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    // Spec 0090, D-3: `user` is APPENDED after `created_at` (not inserted
    // earlier), so already-saved column layouts keep the same order.
    expect($ids)->toBe(['id', 'name', 'referent_type', 'contact_scope', 'primary_contact', 'created_at', 'user']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['filterType'])->toBe('text')
        ->and($columns['referent_type']['sortable'])->toBeTrue()
        ->and($columns['referent_type']['filterType'])->toBe('set')
        ->and($columns['contact_scope']['filterType'])->toBe('set')
        ->and($columns['primary_contact']['type'])->toBe('tags')
        ->and($columns['primary_contact']['sortable'])->toBeTrue()
        ->and($columns['primary_contact']['filterable'])->toBeTrue()
        ->and($columns['primary_contact']['filterType'])->toBe('text')
        ->and($columns['created_at']['filterType'])->toBe('date')
        ->and($columns['user']['sortable'])->toBeTrue()
        ->and($columns['user']['filterable'])->toBeTrue()
        ->and($columns['user']['filterType'])->toBe('set');
});

it('hides action keys the user has no permission for', function () {
    $actor = referentUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/referents/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-015 — rows shape, no N+1
// ---------------------------------------------------------------------------

it('rows expose referent_type/contact_scope/primary_contact and per-row actions, no sensitive fields', function () {
    $actor = referentUserWith(['viewAny', 'view', 'update', 'delete']);
    $type = ReferentType::factory()->create(['name' => 'Commercial']);
    $target = Referent::factory()->create(['name' => 'Ada Referent', 'referent_type_id' => $type->id, 'contact_scope' => 'external']);
    $card = PersonalData::factory()->for($target, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Ada Referent');

    expect($row)->not->toBeNull()
        ->and($row['referent_type'])->toMatchArray(['id' => $type->id, 'name' => 'Commercial'])
        ->and($row['contact_scope'])->toBe('external')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete'])
        ->and($row)->not->toHaveKey('tax_code');

    // primary_contact is the array of ALL primary contacts (one per type),
    // IDENTICAL to the Users column payload {type, icon, label, value}.
    expect($row['primary_contact'])->toBeArray()->toHaveCount(1)
        ->and($row['primary_contact'][0])->toHaveKeys(['type', 'icon', 'label', 'value'])
        ->and($row['primary_contact'][0]['type'])->toBe('email')
        ->and($row['primary_contact'][0]['value'])->toBe('ada@example.com');
});

it('a referent with no type/primary contact has null referent_type/primary_contact', function () {
    $actor = referentUserWith(['viewAny']);
    Referent::factory()->create(['name' => 'Lonely', 'referent_type_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Lonely');

    expect($row['referent_type'])->toBeNull()
        ->and($row['primary_contact'])->toBe([]);
});

it('rows resolve referent_type/primary_contact with a bounded query count (no N+1)', function () {
    $actor = referentUserWith(['viewAny']);

    foreach (range(1, 5) as $i) {
        $type = ReferentType::factory()->create();
        $referent = Referent::factory()->create(['referent_type_id' => $type->id]);
        $card = PersonalData::factory()->for($referent, 'personable')->create();
        Contact::factory()->email()->for($card, 'contactable')->create(['is_primary' => true]);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/referents/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed, small number of queries regardless of row count, never one
    // query per row (base query + eager-loaded referentType/personalData/
    // contacts + count query).
    expect($queryCount)->toBeLessThan(10);
});

// ---------------------------------------------------------------------------
// AC-015 — derived filter/sort on referent_type
// ---------------------------------------------------------------------------

it('filters rows by the derived referent_type set filter (whereHas by name)', function () {
    $actor = referentUserWith(['viewAny']);
    $commercial = ReferentType::factory()->create(['name' => 'Commercial']);
    $technical = ReferentType::factory()->create(['name' => 'Technical']);
    Referent::factory()->create(['name' => 'Referent A', 'referent_type_id' => $commercial->id]);
    Referent::factory()->create(['name' => 'Referent B', 'referent_type_id' => $technical->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['referent_type' => ['filterType' => 'set', 'values' => ['Commercial']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Referent A']);
});

it('sorts rows by the derived referent_type name via a correlated subquery', function () {
    $actor = referentUserWith(['viewAny']);
    $zed = ReferentType::factory()->create(['name' => 'Zed Type']);
    $amy = ReferentType::factory()->create(['name' => 'Amy Type']);
    Referent::factory()->create(['name' => 'Z-referent', 'referent_type_id' => $zed->id]);
    Referent::factory()->create(['name' => 'A-referent', 'referent_type_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'referent_type', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-referent', $names, true))->toBeLessThan(array_search('Z-referent', $names, true));
});

// ---------------------------------------------------------------------------
// AC-016 — /values distinct values
// ---------------------------------------------------------------------------

it('resolves distinct referent_type names via /values', function () {
    $actor = referentUserWith(['viewAny']);
    $commercial = ReferentType::factory()->create(['name' => 'Commercial']);
    Referent::factory()->create(['referent_type_id' => $commercial->id]);
    Referent::factory()->create(['referent_type_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/values', ['columnId' => 'referent_type'])->assertOk();

    expect($response->json('data.values'))->toBe(['Commercial']);
});

it('resolves distinct contact_scope values via /values', function () {
    $actor = referentUserWith(['viewAny']);
    Referent::factory()->create(['contact_scope' => 'internal']);
    Referent::factory()->create(['contact_scope' => 'external']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/values', ['columnId' => 'contact_scope'])->assertOk();

    expect($response->json('data.values'))->toEqualCanonicalizing(['internal', 'external']);
});

it('resolves distinct primary_contact values via /values (identical to Users)', function () {
    $actor = referentUserWith(['viewAny']);
    $withContact = Referent::factory()->create();
    $card = PersonalData::factory()->for($withContact, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com', 'is_primary' => true]);
    Referent::factory()->create(); // no card / no contact
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/values', ['columnId' => 'primary_contact'])->assertOk();

    expect($response->json('data.values'))->toBe(['ada@example.com']);
});

// ---------------------------------------------------------------------------
// primary_contact — derived filter/sort, identical to the Users column
// ---------------------------------------------------------------------------

it('filters rows by the derived primary_contact text filter (whereHas LIKE)', function () {
    $actor = referentUserWith(['viewAny']);
    $match = Referent::factory()->create(['name' => 'Has Needle']);
    $matchCard = PersonalData::factory()->for($match, 'personable')->create();
    Contact::factory()->email()->for($matchCard, 'contactable')->create(['value' => 'needle@example.com', 'is_primary' => true]);
    $other = Referent::factory()->create(['name' => 'No Needle']);
    $otherCard = PersonalData::factory()->for($other, 'personable')->create();
    Contact::factory()->email()->for($otherCard, 'contactable')->create(['value' => 'other@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['primary_contact' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'needle@']],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Has Needle']);
});

it('sorts rows by the derived primary_contact value via a correlated subquery', function () {
    $actor = referentUserWith(['viewAny']);
    $zed = Referent::factory()->create(['name' => 'Z-referent']);
    $zedCard = PersonalData::factory()->for($zed, 'personable')->create();
    Contact::factory()->email()->for($zedCard, 'contactable')->create(['value' => 'zzz@example.com', 'is_primary' => true]);
    $amy = Referent::factory()->create(['name' => 'A-referent']);
    $amyCard = PersonalData::factory()->for($amy, 'personable')->create();
    Contact::factory()->email()->for($amyCard, 'contactable')->create(['value' => 'aaa@example.com', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'primary_contact', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-referent', $names, true))->toBeLessThan(array_search('Z-referent', $names, true));
});
