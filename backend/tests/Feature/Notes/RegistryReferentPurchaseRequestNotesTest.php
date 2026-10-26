<?php

use App\Models\Note;
use App\Models\PurchaseRequest;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use App\Notes\NoteEntityRegistry;
use App\Services\PurchaseRequests\PurchaseRequestNotable;
use App\Services\Referents\ReferentNotable;
use App\Services\Registries\RegistryNotable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Notes thread on anagrafiche, referenti and RDA (spec 0312)
|--------------------------------------------------------------------------
|
| Each module registers itself as a host of the agnostic notes component; no
| endpoint is added. Read access is the module's `view` permission, plus the
| visibility scope for the RDA; the mentionable set is that rule read from the
| other end. The RDA actors hold neither `viewAll` nor a link to the request
| unless the test is about it, so a 403 may mean "not visible".
*/

const NOTES_0312_MODULES = [
    'registries' => ['registries', 'registry', Registry::class, RegistryNotable::class],
    'referents' => ['referents', 'referent', Referent::class, ReferentNotable::class],
    'purchase-requests' => ['purchase-requests', 'purchase_request', PurchaseRequest::class, PurchaseRequestNotable::class],
];

if (! function_exists('notes0312Actor')) {
    /**
     * @param  array<int, string>  $abilities  `{module}.{ability}` names
     */
    function notes0312Actor(array $abilities, bool $canCreateNotes = true): User
    {
        foreach (['registries', 'referents', 'purchase-requests'] as $module) {
            foreach (['viewAny', 'view', 'viewAll', 'delete', 'viewActivity', 'viewDocuments'] as $ability) {
                Permission::findOrCreate("{$module}.{$ability}");
            }
        }

        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();
        $user->givePermissionTo([...$abilities, ...($canCreateNotes ? ['notes.create'] : [])]);

        return $user;
    }

    /**
     * A record of the module visible to $reader: the RDA names them requester.
     *
     * @param  class-string<Model>  $modelClass
     */
    function notes0312Record(string $modelClass, ?User $reader = null): Model
    {
        return $modelClass === PurchaseRequest::class && $reader !== null
            ? PurchaseRequest::factory()->create(['requester_id' => $reader->id, 'created_by' => $reader->id])
            : $modelClass::factory()->create();
    }
}

it('AC-001 registers the module slug on its descriptor', function (string $slug, string $alias, string $modelClass, string $notableClass) {
    expect(config("notes.notable_types.{$slug}"))->toBe($notableClass)
        ->and(app(NoteEntityRegistry::class)->registeredTypes())->toContain($slug)
        ->and(app($notableClass)->modelClass())->toBe($modelClass);
})->with(NOTES_0312_MODULES);

it('AC-002 the view permission reads the thread, without it 403 and nothing leaks', function (string $slug, string $alias, string $modelClass) {
    $reader = notes0312Actor(["{$slug}.view"]);
    $record = notes0312Record($modelClass, $reader);
    Note::factory()->create(['notable_type' => $alias, 'notable_id' => $record->getKey(), 'body' => '<p>Riservata</p>']);

    Sanctum::actingAs($reader);
    $this->getJson("/api/notes?entity_type={$slug}&entity_id={$record->getKey()}")
        ->assertOk()
        ->assertJsonPath('data.0.body', '<p>Riservata</p>');

    Sanctum::actingAs(notes0312Actor([]));
    $response = $this->getJson("/api/notes?entity_type={$slug}&entity_id={$record->getKey()}")->assertForbidden();

    expect($response->getContent())->not->toContain('Riservata');
})->with(NOTES_0312_MODULES);

it('AC-002 an RDA outside the actor visibility is 403 even with purchase-requests.view, viewAll reads it', function () {
    $record = PurchaseRequest::factory()->create();

    Sanctum::actingAs(notes0312Actor(['purchase-requests.view']));
    $this->getJson("/api/notes?entity_type=purchase-requests&entity_id={$record->id}")->assertForbidden();

    Sanctum::actingAs(notes0312Actor(['purchase-requests.view', 'purchase-requests.viewAll']));
    $this->getJson("/api/notes?entity_type=purchase-requests&entity_id={$record->id}")->assertOk();
});

it('AC-003 notes.create writes a note under the module morph, without it 403', function (string $slug, string $alias, string $modelClass) {
    $writer = notes0312Actor(["{$slug}.view"]);
    $record = notes0312Record($modelClass, $writer);
    Sanctum::actingAs($writer);

    $this->postJson('/api/notes', ['entity_type' => $slug, 'entity_id' => $record->getKey(), 'body' => 'Primo commento'])
        ->assertCreated();

    $this->assertDatabaseHas('notes', ['notable_type' => $alias, 'notable_id' => $record->getKey(), 'user_id' => $writer->id]);

    $reader = notes0312Actor(["{$slug}.view"], canCreateNotes: false);
    $readable = notes0312Record($modelClass, $reader);
    Sanctum::actingAs($reader);

    $this->postJson('/api/notes', ['entity_type' => $slug, 'entity_id' => $readable->getKey(), 'body' => 'No'])
        ->assertForbidden();

    expect(Note::query()->count())->toBe(1);
})->with(NOTES_0312_MODULES);

it('AC-004 the mentionable set is the active view holders plus super-admins', function (string $slug, string $alias, string $modelClass) {
    $reader = notes0312Actor(["{$slug}.view"]);
    $other = notes0312Actor(["{$slug}.view"]);
    $inactive = notes0312Actor(["{$slug}.view"]);
    $inactive->update(['is_active' => false]);
    notes0312Actor([]);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));

    $record = $modelClass::factory()->create();
    Sanctum::actingAs($superAdmin);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type={$slug}&entity_id={$record->getKey()}")
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$reader->id, $other->id, $superAdmin->id])->sort()->values()->all());
})->with(array_slice(NOTES_0312_MODULES, 0, 2));

it('AC-004 the RDA mentionable set excludes view holders who cannot see the request', function () {
    $requester = notes0312Actor(['purchase-requests.view']);
    $manager = notes0312Actor(['purchase-requests.view']);
    $viewAll = notes0312Actor(['purchase-requests.view', 'purchase-requests.viewAll']);
    notes0312Actor(['purchase-requests.view']);
    $record = PurchaseRequest::factory()->create([
        'requester_id' => $requester->id,
        'function_manager_id' => $manager->id,
        'created_by' => $requester->id,
    ]);
    Sanctum::actingAs($requester);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=purchase-requests&entity_id={$record->id}")
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$requester->id, $manager->id, $viewAll->id])->sort()->values()->all());
});

it('AC-005 deep link to the detail for a reader, null otherwise, and the record label', function (string $slug, string $alias, string $modelClass, string $notableClass) {
    $reader = notes0312Actor(["{$slug}.view"]);
    $record = notes0312Record($modelClass, $reader);
    $notable = app($notableClass);
    $expectedLabel = $record instanceof PurchaseRequest ? "RDA {$record->id} — {$record->subject}" : $record->name;

    expect($notable->deepLinkPath($record, $reader, null))->toBe("/{$slug}/{$record->getKey()}")
        ->and($notable->deepLinkPath($record, notes0312Actor([]), null))->toBeNull()
        ->and($notable->label($record))->toBe($expectedLabel);
})->with(NOTES_0312_MODULES);

it('AC-006 the grid row carries the notes action and its notes_count badge', function (string $slug, string $alias, string $modelClass) {
    $reader = notes0312Actor(["{$slug}.view", "{$slug}.viewAny"]);
    $record = notes0312Record($modelClass, $reader);
    $root = Note::factory()->create(['notable_type' => $alias, 'notable_id' => $record->getKey()]);
    Note::factory()->create(['notable_type' => $alias, 'notable_id' => $record->getKey(), 'parent_id' => $root->id]);
    Sanctum::actingAs($reader);

    $row = collect($this->postJson("/api/tables/{$slug}/rows", ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('id', $record->getKey());

    expect($row['actions'])->toContain('notes')
        ->and($row['notes_count'])->toBe(2);

    $actionKeys = collect($this->getJson("/api/tables/{$slug}/columns")->assertOk()->json('data.actions'))->pluck('key');
    expect($actionKeys)->toContain('notes');
})->with(NOTES_0312_MODULES);
